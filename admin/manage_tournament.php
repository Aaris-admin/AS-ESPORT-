<?php
/**
 * AS ESPORT Admin - manage a single tournament.
 *
 * Sub Admins are allowed here: they can publish Room ID / Password and declare
 * winners. Prize credits run inside a transaction so a wallet is never changed
 * without a matching ledger row.
 */

require_once __DIR__ . '/../config.php';
$admin = require_admin($pdo);
$pageTitle = 'Manage Match';
$activeNav = 'manage';

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM tournaments WHERE id = ?');
$stmt->execute([$id]);
$tournament = $stmt->fetch();

if (!$tournament) {
    flash('error', 'Tournament not found.');
    redirect('tournament.php');
}

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'room') {
        $roomId = trim($_POST['room_id'] ?? '');
        $roomPass = trim($_POST['room_password'] ?? '');
        $pdo->prepare('UPDATE tournaments SET room_id = ?, room_password = ? WHERE id = ?')
            ->execute([$roomId, $roomPass, $id]);
        flash('success', 'Room credentials published to all participants.');
        redirect('manage_tournament.php?id=' . $id);
    }

    if ($action === 'winner') {
        $participantId = (int) ($_POST['participant_id'] ?? 0);
        $prize = max(0, (float) ($_POST['prize'] ?? 0));

        $stmt = $pdo->prepare('SELECT * FROM participants WHERE id = ? AND tournament_id = ?');
        $stmt->execute([$participantId, $id]);
        $participant = $stmt->fetch();

        if (!$participant) {
            flash('error', 'Participant not found in this tournament.');
            redirect('manage_tournament.php?id=' . $id);
        }

        try {
            $pdo->beginTransaction();

            $pdo->prepare('UPDATE participants SET is_winner = 1 WHERE id = ?')->execute([$participantId]);
            $pdo->prepare("UPDATE tournaments SET winner_user_id = ?, status = 'completed' WHERE id = ?")
                ->execute([$participant['user_id'], $id]);

            if ($prize > 0) {
                $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')
                    ->execute([$prize, $participant['user_id']]);
                $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
                $stmt->execute([$participant['user_id']]);
                $newBalance = (float) $stmt->fetchColumn();

                $pdo->prepare('INSERT INTO transactions (user_id, type, amount, balance_after, reference, note) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([
                        $participant['user_id'], 'prize', $prize, $newBalance,
                        'T' . $id, 'Prize for ' . $tournament['title'] . ' (UID ' . $participant['in_game_uid'] . ')',
                    ]);
            }

            $pdo->commit();
            flash('success', 'Winner declared' . ($prize > 0 ? ' and ' . money($prize) . ' credited.' : '.'));
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('declare winner failed: ' . $e->getMessage());
            flash('error', 'Could not record the winner. Please try again.');
        }
        redirect('manage_tournament.php?id=' . $id);
    }

    if ($action === 'reopen') {
        $pdo->prepare("UPDATE tournaments SET status = 'upcoming', winner_user_id = NULL WHERE id = ?")->execute([$id]);
        flash('success', 'Tournament reopened as upcoming.');
        redirect('manage_tournament.php?id=' . $id);
    }

    if ($action === 'note') {
        $note = trim($_POST['result_note'] ?? '');
        $pdo->prepare('UPDATE tournaments SET result_note = ? WHERE id = ?')->execute([$note, $id]);
        flash('success', 'Result note saved.');
        redirect('manage_tournament.php?id=' . $id);
    }
}

$stmt = $pdo->prepare(
    'SELECT p.*, u.name AS user_name, u.username, u.email
     FROM participants p JOIN users u ON u.id = p.user_id
     WHERE p.tournament_id = ? ORDER BY p.slot_no ASC'
);
$stmt->execute([$id]);
$participants = $stmt->fetchAll();

$pageActions = '<a href="tournament.php" class="rounded-lg border border-white/10 px-3 py-2 text-sm text-slate-300 hover:text-white"><i class="fa-solid fa-arrow-left"></i> Back</a>';

require __DIR__ . '/partials/header.php';
?>

<!-- Summary -->
<section class="mb-5 rounded-2xl border border-white/10 bg-[#13151f] p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="font-display text-xl text-white"><?= e($tournament['title']) ?></h2>
            <p class="text-sm text-slate-400">
                <i class="fa-solid fa-clock text-[#00f2fe]"></i> <?= e(format_match_time($tournament['match_time'])) ?> IST
                &middot; <?= e($tournament['map']) ?> &middot; <?= e($tournament['mode']) ?>
            </p>
        </div>
        <div class="flex items-center gap-2"><?= status_badge(tournament_status($tournament)) ?></div>
    </div>
    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl bg-black/30 p-3 text-center"><p class="text-xs uppercase text-slate-500">Entry</p><p class="font-bold text-[#00f2fe]"><?= money($tournament['entry_fee']) ?></p></div>
        <div class="rounded-xl bg-black/30 p-3 text-center"><p class="text-xs uppercase text-slate-500">Prize Pool</p><p class="font-bold text-emerald-300"><?= money($tournament['prize_pool']) ?></p></div>
        <div class="rounded-xl bg-black/30 p-3 text-center"><p class="text-xs uppercase text-slate-500">Filled</p><p class="font-bold text-white"><?= count($participants) ?>/<?= (int) $tournament['total_slots'] ?></p></div>
        <div class="rounded-xl bg-black/30 p-3 text-center"><p class="text-xs uppercase text-slate-500">Max / User</p><p class="font-bold text-white"><?= (int) $tournament['max_slots_per_user'] ?></p></div>
    </div>
</section>

<div class="grid gap-5 lg:grid-cols-3">
    <!-- Room + result controls -->
    <div class="space-y-5 lg:col-span-1">
        <section class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
            <h3 class="mb-3 font-semibold text-white"><i class="fa-solid fa-door-open text-emerald-300"></i> Room Credentials</h3>
            <form method="post" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="room">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Room ID</label>
                    <input name="room_id" value="<?= e($tournament['room_id']) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" placeholder="12345678">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Room Password</label>
                    <input name="room_password" value="<?= e($tournament['room_password']) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" placeholder="ase123">
                </div>
                <button class="w-full rounded-xl accent-gradient py-3 font-bold text-[#0d0f17]">Publish Room</button>
            </form>
        </section>

        <section class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
            <h3 class="mb-3 font-semibold text-white"><i class="fa-solid fa-note-sticky text-[#00f2fe]"></i> Result Note</h3>
            <form method="post" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="note">
                <textarea name="result_note" rows="3" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" placeholder="e.g. Prize credited to winner's wallet."><?= e($tournament['result_note']) ?></textarea>
                <button class="w-full rounded-xl border border-white/15 py-2.5 font-semibold text-slate-200 hover:border-[#00f2fe]">Save Note</button>
            </form>
        </section>

        <?php if ($tournament['status'] === 'completed'): ?>
            <form method="post" data-confirm="Reopen this tournament as upcoming?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reopen">
                <button class="w-full rounded-xl border border-amber-500/40 bg-amber-500/10 py-3 font-semibold text-amber-300 hover:bg-amber-500/20"><i class="fa-solid fa-rotate-left"></i> Reopen Tournament</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Participants -->
    <section class="lg:col-span-2">
        <div class="overflow-hidden rounded-2xl border border-white/10 bg-[#13151f]">
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                <h3 class="font-semibold text-white"><i class="fa-solid fa-users text-[#00f2fe]"></i> Participants (<?= count($participants) ?>)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-black/40 text-left text-xs uppercase text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Slot</th>
                            <th class="px-4 py-3">Player</th>
                            <th class="px-4 py-3">In-Game UID</th>
                            <th class="px-4 py-3">Entry</th>
                            <th class="px-4 py-3 text-right">Declare Winner</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php if (!$participants): ?>
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No entries yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($participants as $p): ?>
                            <tr class="hover:bg-white/5">
                                <td class="px-4 py-3 text-slate-300">#<?= (int) $p['slot_no'] ?></td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-white"><?= e($p['user_name']) ?> <?= $p['is_winner'] ? '<i class="fa-solid fa-medal text-amber-300"></i>' : '' ?></p>
                                    <p class="text-xs text-slate-500">@<?= e($p['username']) ?></p>
                                </td>
                                <td class="px-4 py-3 font-mono text-slate-200"><?= e($p['in_game_uid']) ?></td>
                                <td class="px-4 py-3 text-slate-400"><?= money($p['entry_fee']) ?></td>
                                <td class="px-4 py-3">
                                    <form method="post" class="flex items-center justify-end gap-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="winner">
                                        <input type="hidden" name="participant_id" value="<?= (int) $p['id'] ?>">
                                        <input name="prize" type="number" min="0" step="1" value="<?= (int) $tournament['prize_pool'] ?>" class="w-24 rounded-lg border border-white/10 bg-black/30 px-2 py-1.5 text-xs outline-none focus:border-[#00f2fe]" placeholder="Prize ₹">
                                        <button class="rounded-lg bg-emerald-500/15 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/25"><i class="fa-solid fa-crown"></i> Win</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
