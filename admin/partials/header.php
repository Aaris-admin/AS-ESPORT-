<?php
/**
 * AS ESPORT Admin - shared layout header.
 *
 * Pages set $pageTitle, $activeNav and optionally $pageActions, then include
 * this file. admin/partials/footer.php closes the document.
 */

require_once __DIR__ . '/../../config.php';

$admin = require_admin($pdo);
$isSuper = $admin['role'] === 'super';
$pageTitle = $pageTitle ?? 'Dashboard';
$activeNav = $activeNav ?? '';
$pageActions = $pageActions ?? '';
$pageScripts = $pageScripts ?? '';

$pendingDeposits = (int) $pdo->query("SELECT COUNT(*) FROM deposits WHERE status = 'pending'")->fetchColumn();
$pendingWithdrawals = (int) $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'")->fetchColumn();

/** Sidebar link classes, greying out the current page. */
function admin_nav(string $key, string $active): string
{
    return $key === $active
        ? 'flex items-center gap-3 rounded-xl bg-[#00f2fe]/10 px-3 py-2.5 text-sm font-semibold text-[#00f2fe]'
        : 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-400 hover:bg-white/5 hover:text-white';
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrftoken()) ?>">
<title><?= e($pageTitle) ?> &middot; <?= e(SITE_NAME) ?> Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Russo+One&display=swap" rel="stylesheet">
<script>
tailwind.config = { darkMode: 'class', theme: { extend: {
    colors: { base: '#0d0f17', card: '#13151f', accent: '#00f2fe' },
    fontFamily: { display: ['"Russo One"', 'sans-serif'], sans: ['Inter', 'sans-serif'] },
}}};
</script>
<style>
    body { background-color: #0d0f17; }
    .accent-gradient { background: linear-gradient(135deg, #00f2fe 0%, #7c3aed 100%); }
    .fade-in { animation: fadeIn .3s ease both; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-thumb { background: #232735; border-radius: 8px; }
    .btn-spinner { display: none; }
    .is-loading .btn-label { display: none; }
    .is-loading .btn-spinner { display: inline-block; }
</style>
</head>
<body class="min-h-screen bg-base font-sans text-slate-200 antialiased">

<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside id="adminSidebar" class="fixed inset-y-0 left-0 z-50 w-64 -translate-x-full border-r border-white/10 bg-[#13151f] p-4 transition-transform lg:static lg:translate-x-0">
        <div class="mb-6 flex items-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg accent-gradient text-[#0d0f17]"><i class="fa-solid fa-shield-halved"></i></span>
            <div>
                <p class="font-display text-sm text-white"><?= e(SITE_NAME) ?></p>
                <p class="text-[10px] uppercase tracking-wide text-slate-500"><?= $isSuper ? 'Super Admin' : 'Sub Admin' ?></p>
            </div>
        </div>

        <nav class="space-y-1">
            <a href="index.php" class="<?= admin_nav('dashboard', $activeNav) ?>"><i class="fa-solid fa-gauge-high w-5"></i> Dashboard</a>
            <a href="tournament.php" class="<?= admin_nav('tournament', $activeNav) ?>"><i class="fa-solid fa-trophy w-5"></i> Tournaments</a>
            <a href="manage_tournament.php" class="<?= admin_nav('manage', $activeNav) ?>"><i class="fa-solid fa-sliders w-5"></i> Manage Matches</a>
            <a href="deposits.php" class="<?= admin_nav('deposits', $activeNav) ?>">
                <i class="fa-solid fa-arrow-down w-5"></i> Deposits
                <?php if ($pendingDeposits): ?><span class="ml-auto rounded-full bg-amber-500/20 px-2 py-0.5 text-xs text-amber-300"><?= $pendingDeposits ?></span><?php endif; ?>
            </a>
            <a href="withdrawals.php" class="<?= admin_nav('withdrawals', $activeNav) ?>">
                <i class="fa-solid fa-arrow-up w-5"></i> Withdrawals
                <?php if ($pendingWithdrawals): ?><span class="ml-auto rounded-full bg-amber-500/20 px-2 py-0.5 text-xs text-amber-300"><?= $pendingWithdrawals ?></span><?php endif; ?>
            </a>
            <a href="user.php" class="<?= admin_nav('user', $activeNav) ?>"><i class="fa-solid fa-users w-5"></i> Users</a>
            <?php if ($isSuper): ?>
                <a href="promo_codes.php" class="<?= admin_nav('promo', $activeNav) ?>"><i class="fa-solid fa-ticket w-5"></i> Promo Codes</a>
                <a href="sub_admins.php" class="<?= admin_nav('subadmins', $activeNav) ?>"><i class="fa-solid fa-user-shield w-5"></i> Sub Admins</a>
                <a href="setting.php" class="<?= admin_nav('setting', $activeNav) ?>"><i class="fa-solid fa-gear w-5"></i> Settings</a>
            <?php endif; ?>
        </nav>

        <div class="mt-6 rounded-xl border border-white/10 bg-black/30 p-3">
            <p class="text-xs text-slate-400">Signed in as</p>
            <p class="truncate text-sm font-semibold text-white"><?= e($admin['name']) ?></p>
            <p class="text-xs text-slate-500"><?= e($admin['username']) ?></p>
            <a href="logout.php" class="mt-3 block rounded-lg border border-rose-500/40 bg-rose-500/10 py-2 text-center text-xs font-semibold text-rose-300 hover:bg-rose-500/20"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </aside>

    <!-- Overlay for mobile sidebar -->
    <div id="adminOverlay" class="fixed inset-0 z-40 hidden bg-black/60 lg:hidden"></div>

    <!-- Main -->
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-white/10 bg-[#0d0f17]/95 px-4 py-3 backdrop-blur">
            <div class="flex items-center gap-3">
                <button id="sidebarToggle" class="rounded-lg border border-white/10 p-2 text-slate-300 lg:hidden"><i class="fa-solid fa-bars"></i></button>
                <h1 class="font-display text-lg text-white"><?= e($pageTitle) ?></h1>
            </div>
            <div class="flex items-center gap-2"><?= $pageActions ?></div>
        </header>

        <main class="flex-1 p-4">
            <?php foreach (flash() as $item): ?>
                <div class="mb-4 fade-in rounded-xl border px-4 py-3 text-sm <?= $item['type'] === 'success' ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-rose-500/30 bg-rose-500/10 text-rose-300' ?>">
                    <?= e($item['message']) ?>
                </div>
            <?php endforeach; ?>
