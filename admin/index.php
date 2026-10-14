<?php
/**
 * AS ESPORT Admin - dashboard.
 *
 * Headline metrics, pending approval queues and a recent activity feed.
 */

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$pageActions = '<a href="tournament.php" class="rounded-lg accent-gradient px-3 py-2 text-sm font-bold text-[#0d0f17]"><i class="fa-solid fa-plus"></i> New Tournament</a>';

require_once __DIR__ . '/partials/header.php';

$totals = [
    'users'      => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'tournaments'=> (int) $pdo->query('SELECT COUNT(*) FROM tournaments')->fetchColumn(),
    'live'       => (int) $pdo->query("SELECT COUNT(*) FROM tournaments WHERE status = 'upcoming'")->fetchColumn(),
    'entries'    => (int) $pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn(),
    'deposits'   => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM deposits WHERE status = 'approved'")->fetchColumn(),
    'withdrawals'=> (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status = 'approved'")->fetchColumn(),
    'wallet'     => (float) $pdo->query('SELECT COALESCE(SUM(balance),0) FROM users')->fetchColumn(),
    'pending_dep'=> (int) $pdo->query("SELECT COUNT(*) FROM deposits WHERE status = 'pending'")->fetchColumn(),
    'pending_wd' => (int) $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'")->fetchColumn(),
];

$upcoming = $pdo->query(
    "SELECT t.*, (SELECT COUNT(*) FROM participants p WHERE p.tournament_id = t.id) AS entries
     FROM tournaments t WHERE t.status = 'upcoming'
     ORDER BY t.match_time ASC LIMIT 6"
)->fetchAll();

$recentUsers = $pdo->query('SELECT id, name, username, balance, created_at FROM users ORDER BY id DESC LIMIT 6')->fetchAll();
?>

<div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <?php
    $cards = [
        ['Registered Users', $totals['users'], 'fa-users', 'text-[#00f2fe]'],
        ['Total Tournaments', $totals['tournaments'], 'fa-trophy', 'text-amber-300'],
        ['Live (Upcoming)', $totals['live'], 'fa-tower-broadcast', 'text-emerald-300'],
        ['Total Entries', $totals['entries'], 'fa-ticket', 'text-violet-300'],
        ['Approved Deposits', money($totals['deposits']), 'fa-arrow-down', 'text-emerald-300'],
        ['Approved Withdrawals', money($totals['withdrawals']), 'fa-arrow-up', 'text-rose-300'],
        ['Wallet Liability', money($totals['wallet']), 'fa-wallet', 'text-[#00f2fe]'],
        ['Pending Approvals', $totals['pending_dep'] + $totals['pending_wd'], 'fa-hourglass-half', 'text-amber-300'],
    ];
    foreach ($cards as [$label, $value, $icon, $color]): ?>
        <div class="card fade-in rounded-2xl p-4">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-wide text-slate-500"><?= e($label) ?></p>
                <i class="fa-solid <?= e($icon) ?> <?= $color ?>"></i>
            </div>
            <p class="mt-2 font-display text-2xl text-white"><?= e((string) $value) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($totals['pending_dep'] || $totals['pending_wd']): ?>
    <div class="mb-5 flex flex-wrap gap-3">
        <?php if ($totals['pending_dep']): ?>
            <a href="deposits.php" class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-300">
                <i class="fa-solid fa-circle-exclamation"></i> <?= $totals['pending_dep'] ?> deposit request(s) awaiting review
            </a>
        <?php endif; ?>
        <?php if ($totals['pending_wd']): ?>
            <a href="withdrawals.php" class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-300">
                <i class="fa-solid fa-circle-exclamation"></i> <?= $totals['pending_wd'] ?> withdrawal request(s) awaiting review
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="grid gap-5 lg:grid-cols-2">
    <section class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
        <h2 class="mb-3 font-semibold text-white"><i class="fa-solid fa-clock text-[#00f2fe]"></i> Upcoming Matches</h2>
        <?php if (!$upcoming): ?>
            <p class="text-sm text-slate-500">No upcoming matches.</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($upcoming as $t): ?>
                    <a href="manage_tournament.php?id=<?= (int) $t['id'] ?>" class="flex items-center justify-between rounded-xl bg-black/30 px-3 py-2 text-sm hover:bg-white/5">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-white"><?= e($t['title']) ?></p>
                            <p class="text-xs text-slate-500"><?= e(format_match_time($t['match_time'])) ?> IST &middot; <?= (int) $t['entries'] ?>/<?= (int) $t['total_slots'] ?> slots</p>
                        </div>
                        <i class="fa-solid fa-chevron-right text-slate-600"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
        <h2 class="mb-3 font-semibold text-white"><i class="fa-solid fa-user-plus text-[#00f2fe]"></i> Newest Users</h2>
        <?php if (!$recentUsers): ?>
            <p class="text-sm text-slate-500">No users yet.</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($recentUsers as $u): ?>
                    <a href="user.php?id=<?= (int) $u['id'] ?>" class="flex items-center justify-between rounded-xl bg-black/30 px-3 py-2 text-sm hover:bg-white/5">
                        <div>
                            <p class="font-semibold text-white"><?= e($u['name']) ?></p>
                            <p class="text-xs text-slate-500">@<?= e($u['username']) ?> &middot; <?= e(format_match_time($u['created_at'])) ?></p>
                        </div>
                        <span class="font-semibold text-[#00f2fe]"><?= money($u['balance']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
