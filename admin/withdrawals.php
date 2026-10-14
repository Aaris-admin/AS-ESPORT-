<?php
/**
 * AS ESPORT Admin - withdrawal requests.
 *
 * The user's balance is held when the request is created (checked here too).
 * On approval the funds are deducted and a ledger row is written; on rejection
 * nothing moves because the balance was never touched.
 */

require_once __DIR__ . '/../config.php';
$admin = require_admin($pdo);
$pageTitle = 'Withdrawals';
$activeNav = 'withdrawals';

if (is_post()) {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    $note = trim($_POST['admin_note'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM withdrawals WHERE id = ?');
    $stmt->execute([$id]);
    $withdrawal = $stmt->fetch();

    if (!$withdrawal) {
        flash('error', 'Withdrawal not found.');
        redirect('withdrawals.php');
    }
    if ($withdrawal['status'] !== 'pending') {
        flash('error', 'This withdrawal has already been processed.');
        redirect('withdrawals.php');
    }

    try {
        $pdo->beginTransaction();

        if ($decision === 'approve') {
            $amount = (float) $withdrawal['amount'];

            // Lock the balance row and make sure the funds still exist.
            $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$withdrawal['user_id']]);
            $balance = (float) $stmt->fetchColumn();

            if ($balance < $amount) {
                $pdo->rollBack();
                flash('error', 'User balance is now too low to approve this withdrawal.');
                redirect('withdrawals.php');
            }

            $newBalance = $balance - $amount;
            $pdo->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$newBalance, $withdrawal['user_id']]);
            $pdo->prepare('INSERT INTO transactions (user_id, type, amount, balance_after, reference, note) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([
                    $withdrawal['user_id'], 'withdrawal', -$amount, $newBalance,
                    'W' . $id, 'Withdrawal to ' . $withdrawal['upi_id'],
                ]);
            $status = 'approved';
        } else {
            $status = 'rejected';
        }

        $pdo->prepare('UPDATE withdrawals SET status = ?, admin_note = ?, processed_by = ?, processed_at = NOW() WHERE id = ?')
            ->execute([$status, $note, $admin['id'], $id]);

        $pdo->commit();
        flash('success', 'Withdrawal ' . $status . '.');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('withdrawal processing failed: ' . $e->getMessage());
        flash('error', 'Could not process the withdrawal.');
    }
    redirect('withdrawals.php');
}

$status = $_GET['status'] ?? 'pending';
if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
    $status = 'pending';
}

$sql = 'SELECT w.*, u.name AS user_name, u.username, u.balance, a.username AS admin_name
        FROM withdrawals w
        JOIN users u ON u.id = w.user_id
        LEFT JOIN admins a ON a.id = w.processed_by';
$params = [];
if ($status !== 'all') {
    $sql .= ' WHERE w.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY w.id DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$withdrawals = $stmt->fetchAll();

$counts = [];
foreach ($pdo->query("SELECT status, COUNT(*) c FROM withdrawals GROUP BY status") as $row) {
    $counts[$row['status']] = (int) $row['c'];
}

require __DIR__ . '/partials/header.php';
?>

<div class="mb-4 flex flex-wrap gap-2">
    <?php foreach (['pending', 'approved', 'rejected', 'all'] as $tab): ?>
        <a href="withdrawals.php?status=<?= $tab ?>" class="rounded-full border px-4 py-1.5 text-sm font-semibold <?= $status === $tab ? 'border-[#00f2fe] bg-[#00f2fe]/15 text-[#00f2fe]' : 'border-white/10 text-slate-400 hover:text-white' ?>">
            <?= ucfirst($tab) ?><?= isset($counts[$tab]) ? ' (' . $counts[$tab] . ')' : '' ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="overflow-hidden rounded-2xl border border-white/10 bg-[#13151f]">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-black/40 text-left text-xs uppercase text-slate-400">
                <tr>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">UPI ID</th>
                    <th class="px-4 py-3">Balance</th>
                    <th class="px-4 py-3">Requested</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php if (!$withdrawals): ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No withdrawals in this view.</td></tr>
                <?php endif; ?>
                <?php foreach ($withdrawals as $w): ?>
                    <tr class="hover:bg-white/5">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-white"><?= e($w['user_name']) ?></p>
                            <p class="text-xs text-slate-500">@<?= e($w['username']) ?></p>
                        </td>
                        <td class="px-4 py-3 font-bold text-rose-300"><?= money($w['amount']) ?></td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-300"><?= e($w['upi_id']) ?></td>
                        <td class="px-4 py-3 text-slate-400"><?= money($w['balance']) ?></td>
                        <td class="px-4 py-3 text-xs text-slate-400"><?= e(format_match_time($w['created_at'])) ?></td>
                        <td class="px-4 py-3"><?= status_badge($w['status'] === 'approved' ? 'room_released' : ($w['status'] === 'rejected' ? 'cancelled' : 'upcoming')) ?></td>
                        <td class="px-4 py-3">
                            <?php if ($w['status'] === 'pending'): ?>
                                <form method="post" class="flex items-center justify-end gap-2" data-confirm="Confirm this withdrawal decision?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                                    <input name="admin_note" class="w-28 rounded-lg border border-white/10 bg-black/30 px-2 py-1.5 text-xs outline-none focus:border-[#00f2fe]" placeholder="Note">
                                    <button name="decision" value="approve" class="rounded-lg bg-emerald-500/15 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/25"><i class="fa-solid fa-check"></i></button>
                                    <button name="decision" value="reject" class="rounded-lg bg-rose-500/15 px-3 py-1.5 text-xs font-semibold text-rose-300 hover:bg-rose-500/25"><i class="fa-solid fa-xmark"></i></button>
                                </form>
                            <?php else: ?>
                                <p class="text-right text-xs text-slate-500">
                                    by <?= e($w['admin_name'] ?? 'system') ?><br><?= e(format_match_time($w['processed_at'])) ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
