<?php
/**
 * AJAX - Return the signed-in user's live wallet balance.
 * Keeps the header widget in sync without a page reload.
 */

require_once __DIR__ . '/../config.php';

if (!is_post()) {
    json_response(['success' => false, 'message' => 'POST required.'], 405);
}

$user = current_user($pdo);
if (!$user) {
    json_response(['success' => false, 'message' => 'Not authenticated.'], 401);
}

$balance = (float) $user['balance'];
json_response([
    'success'   => true,
    'balance'   => $balance,
    'formatted' => money($balance),
]);
