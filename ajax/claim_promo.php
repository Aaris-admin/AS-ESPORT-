<?php
/**
 * AJAX - Claim a promo code.
 *
 * Enforced as single-use per user AND per device. The device binding is what
 * stops someone creating fresh accounts to farm the same code on one phone.
 */

require_once __DIR__ . '/../config.php';

if (!is_post()) {
    json_response(['success' => false, 'message' => 'POST required.'], 405);
}
verify_csrf();

$user = current_user($pdo);
if (!$user) {
    json_response(['success' => false, 'message' => 'Please log in to claim a code.'], 401);
}

$code = strtoupper(trim($_POST['code'] ?? ''));
if ($code === '' || !preg_match('/^[A-Z0-9_\-]{3,50}$/', $code)) {
    json_response(['success' => false, 'message' => 'Enter a valid promo code.']);
}

$device = device_fingerprint();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM promo_codes WHERE code = ? FOR UPDATE');
    $stmt->execute([$code]);
    $promo = $stmt->fetch();

    if (!$promo || (int) $promo['status'] !== 1) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Invalid or inactive promo code.']);
    }
    if ($promo['expires_at'] && strtotime($promo['expires_at']) < time()) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'This promo code has expired.']);
    }
    if ((int) $promo['used_count'] >= (int) $promo['max_uses']) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'This promo code has reached its usage limit.']);
    }

    // Already claimed by this user?
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM promo_claims WHERE promo_id = ? AND user_id = ?');
    $stmt->execute([$promo['id'], $user['id']]);
    if ((int) $stmt->fetchColumn() > 0) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'You have already used this promo code.']);
    }

    // Already claimed on this device (possibly by another account)?
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM promo_claims WHERE promo_id = ? AND device_id = ?');
    $stmt->execute([$promo['id'], $device]);
    if ((int) $stmt->fetchColumn() > 0) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'This promo code was already used on this device.']);
    }

    $amount = (float) $promo['amount'];

    $pdo->prepare('INSERT INTO promo_claims (promo_id, user_id, device_id, amount) VALUES (?, ?, ?, ?)')
        ->execute([$promo['id'], $user['id'], $device, $amount]);
    $pdo->prepare('UPDATE promo_codes SET used_count = used_count + 1 WHERE id = ?')->execute([$promo['id']]);
    $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([$amount, $user['id']]);

    $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $newBalance = (float) $stmt->fetchColumn();

    $pdo->prepare('INSERT INTO transactions (user_id, type, amount, balance_after, reference, note) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$user['id'], 'promo', $amount, $newBalance, $code, 'Promo code ' . $code]);

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('claim_promo failed: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Could not claim the code. Please try again.']);
}

json_response([
    'success'   => true,
    'message'   => 'Promo applied! ' . money($amount) . ' added to your wallet.',
    'amount'    => $amount,
    'balance'   => $newBalance,
    'formatted' => money($newBalance),
]);
