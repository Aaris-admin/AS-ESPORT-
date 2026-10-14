<?php
/**
 * AS ESPORT - Profile.
 *
 * Account summary, editable profile details, password change and the official
 * support links. All writes are CSRF-protected and validated server-side.
 */

require_once __DIR__ . '/config.php';

$user = require_user($pdo);
$pageTitle = 'Profile';
$activeNav = 'profile';

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = preg_replace('/\D+/', '', $_POST['phone'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (mb_strlen($name) < 2) {
            flash('error', 'Enter your full name.');
        } elseif ($phone !== '' && strlen($phone) < 10) {
            flash('error', 'Enter a valid phone number.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid email address.');
        } else {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $check->execute([$email, $user['id']]);
            if ($check->fetch()) {
                flash('error', 'That email is already in use.');
            } else {
                $pdo->prepare('UPDATE users SET name = ?, phone = ?, email = ? WHERE id = ?')
                    ->execute([$name, $phone, $email, $user['id']]);
                flash('success', 'Profile updated.');
            }
        }
        redirect('profile.php');
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            flash('error', 'Your current password is incorrect.');
        } elseif (strlen($new) < 6) {
            flash('error', 'New password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            flash('error', 'New passwords do not match.');
        } else {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash('success', 'Password changed successfully.');
        }
        redirect('profile.php');
    }

    if ($action === 'logout') {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
        flash('success', 'Logged out.');
        redirect('login.php');
    }
}

// Career stats.
$stmt = $pdo->prepare('SELECT COUNT(*) AS matches, COALESCE(SUM(entry_fee),0) AS spent FROM participants WHERE user_id = ?');
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND type = 'prize'");
$stmt->execute([$user['id']]);
$winnings = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM participants WHERE user_id = ? AND is_winner = 1');
$stmt->execute([$user['id']]);
$wins = (int) $stmt->fetchColumn();

require __DIR__ . '/common/header.php';
?>

<section class="mb-5 rounded-2xl border border-white/10 bg-[#13151f] p-5">
    <div class="flex items-center gap-4">
        <span class="flex h-16 w-16 items-center justify-center rounded-2xl accent-gradient text-2xl text-[#0d0f17]"><i class="fa-solid fa-user"></i></span>
        <div class="min-w-0">
            <h1 class="truncate font-display text-xl text-white"><?= e($user['name']) ?></h1>
            <p class="text-sm text-slate-400">@<?= e($user['username']) ?> &middot; <?= e($user['email']) ?></p>
            <p class="text-xs text-slate-500">Member since <?= e(format_match_time($user['created_at'])) ?> IST</p>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-3 gap-3 text-center">
        <div class="rounded-xl bg-black/30 py-3">
            <p class="text-xs uppercase text-slate-500">Matches</p>
            <p class="font-bold text-white"><?= (int) $stats['matches'] ?></p>
        </div>
        <div class="rounded-xl bg-black/30 py-3">
            <p class="text-xs uppercase text-slate-500">Wins</p>
            <p class="font-bold text-emerald-300"><?= $wins ?></p>
        </div>
        <div class="rounded-xl bg-black/30 py-3">
            <p class="text-xs uppercase text-slate-500">Winnings</p>
            <p class="font-bold text-amber-300"><?= money($winnings) ?></p>
        </div>
    </div>

    <div class="mt-3 flex items-center justify-between rounded-xl border border-[#00f2fe]/25 bg-[#00f2fe]/10 px-4 py-3">
        <span class="text-sm text-slate-300">Wallet Balance</span>
        <span class="font-display text-xl text-[#00f2fe]" data-balance-value><?= money($user['balance']) ?></span>
    </div>
</section>

<div class="grid gap-5 lg:grid-cols-2">
    <!-- Profile details -->
    <section class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
        <h2 class="mb-3 font-semibold text-white"><i class="fa-solid fa-id-card text-[#00f2fe]"></i> Account Details</h2>
        <form method="post" class="space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-400">Full Name</label>
                <input name="name" value="<?= e($user['name']) ?>" required class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Phone</label>
                    <input name="phone" value="<?= e($user['phone']) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Username</label>
                    <input value="<?= e($user['username']) ?>" disabled class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-slate-500">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-400">Email</label>
                <input name="email" type="email" value="<?= e($user['email']) ?>" required class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
            </div>
            <button class="w-full rounded-xl accent-gradient py-3 font-bold text-[#0d0f17]">Save Changes</button>
        </form>
    </section>

    <div class="space-y-5">
        <!-- Password -->
        <section class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
            <h2 class="mb-3 font-semibold text-white"><i class="fa-solid fa-lock text-[#00f2fe]"></i> Change Password</h2>
            <form method="post" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <?php foreach ([['current_password', 'Current Password'], ['new_password', 'New Password'], ['confirm_password', 'Confirm New Password']] as $idx => [$field, $label]): ?>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-400"><?= e($label) ?></label>
                        <div class="relative">
                            <input id="pw_<?= e($field) ?>" name="<?= e($field) ?>" type="password" required class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 pr-11 text-sm outline-none focus:border-[#00f2fe]">
                            <button type="button" data-toggle-password="pw_<?= e($field) ?>" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-[#00f2fe]"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <button class="w-full rounded-xl border border-white/15 py-3 font-bold text-slate-200 hover:border-[#00f2fe]">Update Password</button>
            </form>
        </section>

        <!-- Support -->
        <section class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
            <h2 class="mb-3 font-semibold text-white"><i class="fa-solid fa-headset text-[#00f2fe]"></i> Support &amp; Socials</h2>
            <div class="grid gap-2">
                <a href="<?= e(setting($pdo, 'whatsapp', SUPPORT_WHATSAPP)) ?>" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl bg-black/30 px-4 py-3 text-sm hover:bg-white/5">
                    <i class="fa-brands fa-whatsapp text-lg text-emerald-400"></i><span class="text-slate-300">WhatsApp: +91 83938 33973</span>
                </a>
                <a href="mailto:<?= e(setting($pdo, 'email', SUPPORT_EMAIL)) ?>" class="flex items-center gap-3 rounded-xl bg-black/30 px-4 py-3 text-sm hover:bg-white/5">
                    <i class="fa-solid fa-envelope text-lg text-[#00f2fe]"></i><span class="text-slate-300"><?= e(setting($pdo, 'email', SUPPORT_EMAIL)) ?></span>
                </a>
                <a href="<?= e(setting($pdo, 'instagram', SUPPORT_INSTAGRAM)) ?>" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl bg-black/30 px-4 py-3 text-sm hover:bg-white/5">
                    <i class="fa-brands fa-instagram text-lg text-pink-400"></i><span class="text-slate-300">Instagram</span>
                </a>
                <a href="<?= e(setting($pdo, 'youtube', SUPPORT_YOUTUBE)) ?>" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl bg-black/30 px-4 py-3 text-sm hover:bg-white/5">
                    <i class="fa-brands fa-youtube text-lg text-red-500"></i><span class="text-slate-300">YouTube</span>
                </a>
            </div>
        </section>

        <!-- Logout -->
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="logout">
            <button class="w-full rounded-xl border border-rose-500/40 bg-rose-500/10 py-3 font-bold text-rose-300 hover:bg-rose-500/20"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/common/bottom.php'; ?>
