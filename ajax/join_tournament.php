<?php
/**
 * AJAX - Join a tournament with 1-4 slots.
 *
 * Everything happens inside a single transaction with row locks so two
 * concurrent requests can never oversell the last slot or double-spend a
 * balance. No reload and no logout: responses are always JSON.
 */

require_once __DIR__ . '/../config.php';

if (!is_post()) {
    json_response(['success' => false, 'message' => 'POST required.'], 405);
}
verify_csrf();

$user = current_user($pdo);
if (!$user) {
    json_response(['success' => false, 'message' => 'Please log in to join.'], 401);
}

$tournamentId = (int) ($_POST['tournament_id'] ?? 0);
$slots = (int) ($_POST['slots'] ?? 1);
$uids = json_decode($_POST['uids'] ?? '[]', true);

if ($tournamentId <= 0) {
    json_response(['success' => false, 'message' => 'Invalid tournament.']);
}
if ($slots < 1 || $slots > 4) {
    json_response(['success' => false, 'message' => 'You can join with 1 to 4 slots.']);
}
if (!is_array($uids) || count($uids) !== $slots) {
    json_response(['success' => false, 'message' => 'Provide an in-game UID for every slot.']);
}

$uids = array_map(function ($uid) {
    return substr(trim((string) $uid), 0, 64);
}, $uids);
foreach ($uids as $uid) {
    if ($uid === '' || !preg_match('/^[A-Za-z0-9_\-]{4,64}$/', $uid)) {
        json_response(['success' => false, 'message' => 'In-game UIDs must be 4-64 characters.']);
    }
}
if (count(array_unique($uids)) !== count($uids)) {
    json_response(['success' => false, 'message' => 'Duplicate UIDs are not allowed in one entry.']);
}

try {
    $pdo->beginTransaction();

    // Lock the tournament row for the duration of the transaction.
    $stmt = $pdo->prepare('SELECT * FROM tournaments WHERE id = ? FOR UPDATE');
    $stmt->execute([$tournamentId]);
    $tournament = $stmt->fetch();

    if (!$tournament) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Tournament not found.']);
    }
    if ($tournament['status'] !== 'upcoming') {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'This tournament is no longer open for joining.']);
    }

    $remaining = (int) $tournament['total_slots'] - (int) $tournament['filled_slots'];
    if ($remaining < $slots) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => "Only {$remaining} slot(s) left. Reduce your selection."]);
    }

    // Enforce the per-user slot cap for this tournament.
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM participants WHERE tournament_id = ? AND user_id = ? FOR UPDATE');
    $stmt->execute([$tournamentId, $user['id']]);
    $mine = (int) $stmt->fetchColumn();
    $maxPerUser = (int) $tournament['max_slots_per_user'];
    if ($mine + $slots > $maxPerUser) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => "You already hold {$mine} slot(s). Max {$maxPerUser} per user."]);
    }

    $entryFee = (float) $tournament['entry_fee'];
    $total = $entryFee * $slots;

    // Re-read the balance under lock so we never spend stale funds.
    $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
    $stmt->execute([$user['id']]);
    $balance = (float) $stmt->fetchColumn();

    if ($balance < $total) {
        $pdo->rollBack();
        json_response([
            'success' => false,
            'message' => 'Insufficient balance. You need ' . money($total) . ', wallet has ' . money($balance) . '.',
        ]);
    }

    // Next free slot number inside this tournament.
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(slot_no), 0) FROM participants WHERE tournament_id = ? FOR UPDATE');
    $stmt->execute([$tournamentId]);
    $nextSlot = (int) $stmt->fetchColumn() + 1;

    $insert = $pdo->prepare('INSERT INTO participants (tournament_id, user_id, slot_no, in_game_uid, entry_fee) VALUES (?, ?, ?, ?, ?)');
    for ($i = 0; $i < $slots; $i++) {
        $insert->execute([$tournamentId, $user['id'], $nextSlot + $i, $uids[$i], $entryFee]);
    }

    $newBalance = $balance - $total;
    $pdo->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$newBalance, $user['id']]);
    $pdo->prepare('UPDATE tournaments SET filled_slots = filled_slots + ? WHERE id = ?')->execute([$slots, $tournamentId]);

    $pdo->prepare('INSERT INTO transactions (user_id, type, amount, balance_after, reference, note) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([
            $user['id'],
            'entry_fee',
            -$total,
            $newBalance,
            'T' . $tournamentId,
            'Entry fee for ' . $tournament['title'] . " ({$slots} slot" . ($slots > 1 ? 's' : '') . ')',
        ]);

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('join_tournament failed: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Could not join right now. Please try again.']);
}

json_response([
    'success'   => true,
    'message'   => "Joined {$tournament['title']} with {$slots} slot(s).",
    'balance'   => $newBalance,
    'formatted' => money($newBalance),
]);
