<?php
/**
 * AS ESPORT Admin - deposit requests.
 *
 * Approving a deposit credits the user's wallet and writes a ledger row in one
 * transaction. A deposit can only be approved once; the status guard prevents
 * double-crediting on a double click or replay.
 */

require_once __DIR__ . '/../config.php';
$admin = require_admin($pdo);
$pageTitle = 'Deposits';
$activeNav = 'deposits';

if (is_post()) {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    $note = trim($_POST['admin_note'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM deposits WHERE id = ?');
    $stmt->execute([$id]);
    $deposit = $stmt->fetch();

    if (!$deposit) {
        flash('error', 'Deposit not found.');
        redirect('deposits.php');
    }
    if ($deposit['status'] !== 'pending') {
        flash('error', 'This deposit has already been processed.');
        redirect('deposits.php');
    }

    try {
        $pdo->beginTransaction();

        if ($decision === 'approve') {
            $amount = (float) $deposit['amount'];
            $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([$amount, $deposit['user_id']]);

            $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
            $stmt->execute([$deposit['user_id']]);
            $newBalance = (float) $stmt->fetchColumn();

            $pdo->prepare('INSERT INTO transactions (user_id, type, amount, balance_after, reference, note) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([
                    $deposit['user_id'], 'deposit', $amount, $newBalance,
                    'D' . $id, 'Deposit approved' . ($deposit['utr'] ? ' (UTR ' . $deposit['utr'] . ')' : ''),
                ]);
            $status = 'approved';
        } else {
            $status = 'rejected';
        }

        $pdo->prepare('UPDATE deposits SET status = ?, admin_note = ?, processed_by = ?, processed_at = NOW() WHERE id = ?')
            ->execute([$status, $note, $admin['id'], $id]);

        $pdo->commit();
        flash('success', 'Deposit ' . $status . '.');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('deposit processing failed: ' . $e->getMessage());
        flash('error', 'Could not process the deposit.');
    }
    redirect('deposits.php');
}

$status = $_GET['status'] ?? 'pending';
if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
    $status = 'pending';
}

$sql = 'SELECT d.*, u.name AS user_name, u.username, u.email, a.username AS admin_name
        FROM deposits d
        JOIN users u ON u.id = d.user_id
        LEFT JOIN admins a ON a.id = d.processed_by';
$params = [];
if ($status !== 'all') {
    $sql .= ' WHERE d.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY d.id DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$deposits = $stmt->fetchAll();

$counts = [];
foreach ($pdo->query("SELECT status, COUNT(*) c FROM deposits GROUP BY status") as $row) {
    $counts[$row['status']] = (int) $row['c'];
}

require __DIR__ . '/partials/header.php';
?>

<div class="mb-4 flex flex-wrap gap-2">
    <?php foreach (['pending', 'approved', 'rejected', 'all'] as $tab): ?>
        <a href="deposits.php?status=<?= $tab ?>" class="rounded-full border px-4 py-1.5 text-sm font-semibold <?= $status === $tab ? 'border-[#00f2fe] bg-[#00f2fe]/15 text-[#00f2fe]' : 'border-white/10 text-slate-400 hover:text-white' ?>">
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
                    <th class="px-4 py-3">Txn / UTR</th>
                    <th class="px-4 py-3">Proof</th>
                    <th class="px-4 py-3">Requested</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php if (!$deposits): ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No deposits in this view.</td></tr>
                <?php endif; ?>
                <?php foreach ($deposits as $d): ?>
                    <tr class="hover:bg-white/5">
                        <td class="px-4 py-3">
                            <p class="font-semibold text-white"><?= e($d['user_name']) ?></p>
                            <p class="text-xs text-slate-500">@<?= e($d['username']) ?></p>
                        </td>
                        <td class="px-4 py-3 font-bold text-emerald-300"><?= money($d['amount']) ?></td>
                        <td class="px-4 py-3 text-xs text-slate-400">
                            <?= $d['txn_id'] ? 'Txn: ' . e($d['txn_id']) . '<br>' : '' ?>
                            <?= $d['utr'] ? 'UTR: ' . e($d['utr']) : '' ?>
                            <?= (!$d['txn_id'] && !$d['utr']) ? '-' : '' ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($d['screenshot']): ?>
                                <a href="../assets/uploads/screenshots/<?= e($d['screenshot']) ?>" target="_blank" class="text-[#00f2fe]"><i class="fa-solid fa-image"></i> View</a>
                            <?php else: ?>
                                <span class="text-slate-600">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-400"><?= e(format_match_time($d['created_at'])) ?></td>
                        <td class="px-4 py-3"><?= status_badge($d['status'] === 'approved' ? 'room_released' : ($d['status'] === 'rejected' ? 'cancelled' : 'upcoming')) ?></td>
                        <td class="px-4 py-3">
                            <?php if ($d['status'] === 'pending'): ?>
                                <form method="post" class="flex items-center justify-end gap-2" data-confirm="Confirm this deposit decision?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                                    <input name="admin_note" class="w-28 rounded-lg border border-white/10 bg-black/30 px-2 py-1.5 text-xs outline-none focus:border-[#00f2fe]" placeholder="Note">
                                    <button name="decision" value="approve" class="rounded-lg bg-emerald-500/15 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/25"><i class="fa-solid fa-check"></i></button>
                                    <button name="decision" value="reject" class="rounded-lg bg-rose-500/15 px-3 py-1.5 text-xs font-semibold text-rose-300 hover:bg-rose-500/25"><i class="fa-solid fa-xmark"></i></button>
                                </form>
                            <?php else: ?>
                                <p class="text-right text-xs text-slate-500">
                                    by <?= e($d['admin_name'] ?? 'system') ?><br><?= e(format_match_time($d['processed_at'])) ?>
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
