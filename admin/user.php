<?php
/**
 * AS ESPORT Admin - users.
 *
 * Searchable user list, per-user drill-down and manual wallet credit/debit.
 * Every manual adjustment writes a ledger row so balances always reconcile.
 */

require_once __DIR__ . '/../config.php';
$admin = require_admin($pdo);
$pageTitle = 'Users';
$activeNav = 'user';

$viewId = (int) ($_GET['id'] ?? 0);
$search = trim($_GET['q'] ?? '');

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'adjust') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $direction = $_POST['direction'] ?? 'credit';
        $note = trim($_POST['note'] ?? 'Manual adjustment');

        if ($amount <= 0) {
            flash('error', 'Enter an amount greater than zero.');
            redirect('user.php?id=' . $userId);
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            $balance = $stmt->fetchColumn();

            if ($balance === false) {
                $pdo->rollBack();
                flash('error', 'User not found.');
                redirect('user.php');
            }
            $balance = (float) $balance;

            if ($direction === 'debit' && $balance < $amount) {
                $pdo->rollBack();
                flash('error', 'Debit exceeds the user balance.');
                redirect('user.php?id=' . $userId);
            }

            $delta = $direction === 'credit' ? $amount : -$amount;
            $newBalance = $balance + $delta;
            $pdo->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$newBalance, $userId]);
            $pdo->prepare('INSERT INTO transactions (user_id, type, amount, balance_after, reference, note) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$userId, $direction === 'credit' ? 'admin_credit' : 'admin_debit', $delta, $newBalance, 'ADMIN', $note]);

            $pdo->commit();
            flash('success', 'Wallet ' . $direction . 'ed by ' . money($amount) . '.');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('wallet adjust failed: ' . $e->getMessage());
            flash('error', 'Could not adjust the wallet.');
        }
        redirect('user.php?id=' . $userId);
    }

    if ($action === 'toggle_status') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $pdo->prepare('UPDATE users SET status = 1 - status WHERE id = ?')->execute([$userId]);
        flash('success', 'User status updated.');
        redirect('user.php?id=' . $userId);
    }
}

$detail = null;
$detailTx = [];
$detailMatches = [];

if ($viewId) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$viewId]);
    $detail = $stmt->fetch();

    if ($detail) {
        $stmt = $pdo->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 30');
        $stmt->execute([$viewId]);
        $detailTx = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT t.title, t.match_time, p.slot_no, p.in_game_uid, p.is_winner
             FROM participants p JOIN tournaments t ON t.id = p.tournament_id
             WHERE p.user_id = ? ORDER BY t.match_time DESC LIMIT 20'
        );
        $stmt->execute([$viewId]);
        $detailMatches = $stmt->fetchAll();
    }
}

$sql = 'SELECT u.*,
        (SELECT COUNT(*) FROM participants p WHERE p.user_id = u.id) AS matches
        FROM users u';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY u.id DESC LIMIT 100';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require __DIR__ . '/partials/header.php';
?>

<?php if ($detail): ?>
    <!-- User detail -->
    <section class="mb-5 rounded-2xl border border-white/10 bg-[#13151f] p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-display text-xl text-white"><?= e($detail['name']) ?></h2>
                <p class="text-sm text-slate-400">@<?= e($detail['username']) ?> &middot; <?= e($detail['email']) ?><?= $detail['phone'] ? ' &middot; ' . e($detail['phone']) : '' ?></p>
                <p class="text-xs text-slate-500">Joined <?= e(format_match_time($detail['created_at'])) ?> IST</p>
            </div>
            <div class="text-right">
                <p class="text-xs uppercase text-slate-500">Balance</p>
                <p class="font-display text-2xl text-[#00f2fe]"><?= money($detail['balance']) ?></p>
                <?= status_badge((int) $detail['status'] === 1 ? 'room_released' : 'cancelled') ?>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <a href="user.php" class="rounded-lg border border-white/10 px-3 py-2 text-sm text-slate-300 hover:text-white"><i class="fa-solid fa-arrow-left"></i> All Users</a>
            <form method="post" class="flex flex-wrap items-center gap-2" data-confirm="Apply this wallet adjustment?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="adjust">
                <input type="hidden" name="user_id" value="<?= (int) $detail['id'] ?>">
                <select name="direction" class="rounded-lg border border-white/10 bg-black/30 px-3 py-2 text-sm outline-none focus:border-[#00f2fe]">
                    <option value="credit">Credit (+)</option>
                    <option value="debit">Debit (-)</option>
                </select>
                <input name="amount" type="number" min="1" step="1" placeholder="Amount ₹" class="w-32 rounded-lg border border-white/10 bg-black/30 px-3 py-2 text-sm outline-none focus:border-[#00f2fe]">
                <input name="note" placeholder="Reason" class="w-40 rounded-lg border border-white/10 bg-black/30 px-3 py-2 text-sm outline-none focus:border-[#00f2fe]">
                <button class="rounded-lg accent-gradient px-4 py-2 text-sm font-bold text-[#0d0f17]">Apply</button>
            </form>
            <form method="post" data-confirm="<?= (int) $detail['status'] === 1 ? 'Disable this user?' : 'Enable this user?' ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="user_id" value="<?= (int) $detail['id'] ?>">
                <button class="rounded-lg border border-white/15 px-3 py-2 text-sm text-slate-300 hover:text-white">
                    <i class="fa-solid <?= (int) $detail['status'] === 1 ? 'fa-user-slash' : 'fa-user-check' ?>"></i> <?= (int) $detail['status'] === 1 ? 'Disable' : 'Enable' ?>
                </button>
            </form>
        </div>
    </section>

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="overflow-hidden rounded-2xl border border-white/10 bg-[#13151f]">
            <div class="border-b border-white/10 px-4 py-3"><h3 class="font-semibold text-white"><i class="fa-solid fa-receipt text-[#00f2fe]"></i> Recent Transactions</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-black/40 text-left text-xs uppercase text-slate-400">
                        <tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Note</th><th class="px-4 py-3 text-right">Amount</th></tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php if (!$detailTx): ?>
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No transactions.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($detailTx as $tx): ?>
                            <tr>
                                <td class="px-4 py-2 text-xs text-slate-400"><?= e(format_match_time($tx['created_at'])) ?></td>
                                <td class="px-4 py-2 capitalize text-slate-300"><?= e(str_replace('_', ' ', $tx['type'])) ?></td>
                                <td class="px-4 py-2 text-slate-400"><?= e($tx['note']) ?></td>
                                <td class="px-4 py-2 text-right font-semibold <?= (float) $tx['amount'] >= 0 ? 'text-emerald-300' : 'text-rose-300' ?>"><?= money($tx['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-white/10 bg-[#13151f]">
            <div class="border-b border-white/10 px-4 py-3"><h3 class="font-semibold text-white"><i class="fa-solid fa-trophy text-[#00f2fe]"></i> Tournament Entries</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-black/40 text-left text-xs uppercase text-slate-400">
                        <tr><th class="px-4 py-3">Tournament</th><th class="px-4 py-3">Slot</th><th class="px-4 py-3">UID</th><th class="px-4 py-3">Result</th></tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php if (!$detailMatches): ?>
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No entries.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($detailMatches as $m): ?>
                            <tr>
                                <td class="px-4 py-2"><p class="text-white"><?= e($m['title']) ?></p><p class="text-xs text-slate-500"><?= e(format_match_time($m['match_time'])) ?></p></td>
                                <td class="px-4 py-2 text-slate-300">#<?= (int) $m['slot_no'] ?></td>
                                <td class="px-4 py-2 font-mono text-xs text-slate-300"><?= e($m['in_game_uid']) ?></td>
                                <td class="px-4 py-2"><?= $m['is_winner'] ? '<span class="text-amber-300 font-semibold"><i class="fa-solid fa-medal"></i> Won</span>' : '<span class="text-slate-500">-</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
<?php else: ?>
    <!-- User list -->
    <form method="get" class="mb-4 flex gap-2">
        <input name="q" value="<?= e($search) ?>" placeholder="Search by name, username or email" class="flex-1 rounded-xl border border-white/10 bg-black/30 px-4 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
        <button class="rounded-xl accent-gradient px-5 py-2.5 font-bold text-[#0d0f17]"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-white/10 bg-[#13151f]">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-black/40 text-left text-xs uppercase text-slate-400">
                    <tr>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Contact</th>
                        <th class="px-4 py-3">Balance</th>
                        <th class="px-4 py-3">Matches</th>
                        <th class="px-4 py-3">Joined</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (!$users): ?>
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No users found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-white/5">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-white"><?= e($u['name']) ?></p>
                                <p class="text-xs text-slate-500">@<?= e($u['username']) ?></p>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400"><?= e($u['email']) ?><?= $u['phone'] ? '<br>' . e($u['phone']) : '' ?></td>
                            <td class="px-4 py-3 font-semibold text-[#00f2fe]"><?= money($u['balance']) ?></td>
                            <td class="px-4 py-3 text-slate-300"><?= (int) $u['matches'] ?></td>
                            <td class="px-4 py-3 text-xs text-slate-400"><?= e(format_match_time($u['created_at'])) ?></td>
                            <td class="px-4 py-3"><?= status_badge((int) $u['status'] === 1 ? 'room_released' : 'cancelled') ?></td>
                            <td class="px-4 py-3 text-right">
                                <a href="user.php?id=<?= (int) $u['id'] ?>" class="rounded-lg bg-white/5 px-3 py-1.5 text-xs text-slate-300 hover:text-[#00f2fe]"><i class="fa-solid fa-eye"></i> View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
