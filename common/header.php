<?php
/**
 * AS ESPORT - Shared front-end layout: <head>, theme tokens, navigation.
 *
 * Pages define $pageTitle before including this file and $activeNav to light
 * up the correct tab. Bottom.php closes the document and injects $pageScripts.
 */

require_once __DIR__ . '/../config.php';

$user = current_user($pdo);
$pageTitle = $pageTitle ?? 'Home';
$activeNav = $activeNav ?? '';
$pageScripts = $pageScripts ?? '';
$siteName = setting($pdo, 'site_name', SITE_NAME);

/** Return the active-tab class string for the primary navigation. */
function nav_class(string $key, string $active): string
{
    return $key === $active
        ? 'text-[#00f2fe] bg-[#00f2fe]/10'
        : 'text-slate-400 hover:text-white hover:bg-white/5';
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrftoken()) ?>">
<meta name="theme-color" content="#0d0f17">
<title><?= e($pageTitle) ?> &middot; <?= e($siteName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<script>
tailwind.config = {
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                base:   '#0d0f17',
                card:   '#13151f',
                accent: '#00f2fe',
                accent2:'#7c3aed',
            },
            fontFamily: {
                display: ['"Russo One"', 'system-ui', 'sans-serif'],
                sans: ['Inter', 'system-ui', 'sans-serif'],
            },
            boxShadow: {
                glow: '0 0 24px rgba(0, 242, 254, 0.25)',
            },
        },
    },
};
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Russo+One&display=swap" rel="stylesheet">
<style>
    body { background-color: #0d0f17; }
    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: #0d0f17; }
    ::-webkit-scrollbar-thumb { background: #232735; border-radius: 8px; }
    ::-webkit-scrollbar-thumb:hover { background: #00f2fe; }
    .accent-gradient { background: linear-gradient(135deg, #00f2fe 0%, #7c3aed 100%); }
    .accent-text { background: linear-gradient(135deg, #00f2fe 0%, #7c3aed 100%); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .card { background: #13151f; border: 1px solid rgba(255,255,255,0.08); }
    .card:hover { border-color: rgba(0,242,254,0.35); }
    .fade-in { animation: fadeIn .35s ease both; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
    /* Spinner shown while AJAX requests are in flight. */
    .btn-spinner { display: none; }
    .is-loading .btn-label { display: none; }
    .is-loading .btn-spinner { display: inline-block; }
</style>
</head>
<body class="min-h-screen bg-base text-slate-200 font-sans antialiased">

<!-- Top bar -->
<header class="sticky top-0 z-40 border-b border-white/10 bg-[#0d0f17]/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3">
        <a href="index.php" class="flex items-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded-lg accent-gradient text-[#0d0f17]">
                <i class="fa-solid fa-gamepad"></i>
            </span>
            <span class="font-display text-lg tracking-wide text-white"><?= e($siteName) ?></span>
        </a>

        <div class="flex items-center gap-2 sm:gap-3">
            <div id="walletBalance" class="hidden items-center gap-2 rounded-full border border-[#00f2fe]/30 bg-[#00f2fe]/10 px-3 py-1.5 text-sm font-semibold text-[#00f2fe] sm:flex">
                <i class="fa-solid fa-wallet"></i>
                <span data-balance-value><?= $user ? money($user['balance']) : money(0) ?></span>
            </div>
            <?php if ($user): ?>
                <a href="wallet.php" class="rounded-lg bg-[#00f2fe] px-3 py-1.5 text-sm font-semibold text-[#0d0f17] hover:brightness-110">
                    <i class="fa-solid fa-plus"></i> <span class="hidden sm:inline">Add</span>
                </a>
                <a href="profile.php" class="flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-white/5 text-slate-300 hover:text-white" title="<?= e($user['username']) ?>">
                    <i class="fa-solid fa-user"></i>
                </a>
            <?php else: ?>
                <a href="login.php" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm font-semibold text-slate-300 hover:text-white">Login</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="mx-auto max-w-7xl px-4 pb-28 pt-5">

<?php foreach (flash() as $item): ?>
    <div class="mb-4 fade-in rounded-xl border px-4 py-3 text-sm <?= $item['type'] === 'success' ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-rose-500/30 bg-rose-500/10 text-rose-300' ?>">
        <?= e($item['message']) ?>
    </div>
<?php endforeach; ?>
