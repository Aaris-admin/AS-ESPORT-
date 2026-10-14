<?php
/**
 * AS ESPORT - Login and registration (single entry point).
 *
 * Registration is folded into this page because the spec only defines
 * login.php. Both flows post to this same file and set $_SESSION['user_id'].
 */

require_once __DIR__ . '/config.php';

if (current_user($pdo)) {
    redirect('index.php');
}

$mode = 'login';
$errors = [];

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? 'login';

    if ($action === 'register') {
        $mode = 'register';
        $name = trim($_POST['name'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = preg_replace('/\D+/', '', $_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($name === '' || mb_strlen($name) < 2) {
            $errors[] = 'Enter your full name.';
        }
        if (!preg_match('/^[a-z0-9_]{3,20}$/', $username)) {
            $errors[] = 'Username must be 3-20 characters (letters, numbers, underscore).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$errors) {
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $check->execute([$username, $email]);
            if ($check->fetch()) {
                $errors[] = 'That username or email is already registered.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO users (name, username, email, phone, password, device_id) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $name, $username, $email, $phone,
                    password_hash($password, PASSWORD_DEFAULT),
                    device_fingerprint(),
                ]);
                $_SESSION['user_id'] = (int) $pdo->lastInsertId();
                session_regenerate_id(true);
                flash('success', 'Welcome to ' . SITE_NAME . ', ' . $name . '!');
                redirect('index.php');
            }
        }
    } else {
        $identity = trim($_POST['identity'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($identity === '' || $password === '') {
            $errors[] = 'Enter your username/email and password.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([strtolower($identity), strtolower($identity)]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Invalid credentials. Please try again.';
            } elseif ((int) $user['status'] !== 1) {
                $errors[] = 'Your account has been disabled. Contact support.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                // Refresh the device binding on every successful login.
                $pdo->prepare('UPDATE users SET device_id = ? WHERE id = ?')
                    ->execute([device_fingerprint(), $user['id']]);
                flash('success', 'Logged in successfully.');
                redirect('index.php');
            }
        }
    }
}

$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login &middot; <?= e(SITE_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Russo+One&display=swap" rel="stylesheet">
<style>
    body { background: radial-gradient(circle at 20% 0%, #1a1f33 0%, #0d0f17 45%); }
    .accent-gradient { background: linear-gradient(135deg, #00f2fe 0%, #7c3aed 100%); }
</style>
</head>
<body class="min-h-screen text-slate-200 font-sans flex items-center justify-center p-4">
<div class="w-full max-w-md">
    <div class="mb-6 text-center">
        <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl accent-gradient text-2xl text-[#0d0f17]">
            <i class="fa-solid fa-gamepad"></i>
        </span>
        <h1 class="font-display text-2xl tracking-wide text-white"><?= e(SITE_NAME) ?></h1>
        <p class="text-sm text-slate-400">Compete. Win. Withdraw.</p>
    </div>

    <div class="rounded-2xl border border-white/10 bg-[#13151f] p-6 shadow-2xl">
        <div class="mb-5 grid grid-cols-2 gap-1 rounded-xl bg-black/30 p-1 text-sm font-semibold">
            <button type="button" data-mode="login" class="tab-btn rounded-lg py-2">Login</button>
            <button type="button" data-mode="register" class="tab-btn rounded-lg py-2">Register</button>
        </div>

        <?php if ($errors): ?>
            <div class="mb-4 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
                <?php foreach ($errors as $error): ?>
                    <div><?= e($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Login -->
        <form method="post" id="loginForm" class="<?= $mode === 'register' ? 'hidden' : '' ?> space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Username or Email</label>
                <div class="relative">
                    <i class="fa-solid fa-user absolute left-3 top-1/2 -translate-y-1/2 text-slate-500"></i>
                    <input name="identity" required class="w-full rounded-xl border border-white/10 bg-black/30 py-3 pl-10 pr-3 text-sm outline-none focus:border-[#00f2fe]" placeholder="you@example.com">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Password</label>
                <div class="relative">
                    <i class="fa-solid fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-slate-500"></i>
                    <input id="loginPassword" name="password" type="password" required class="w-full rounded-xl border border-white/10 bg-black/30 py-3 pl-10 pr-11 text-sm outline-none focus:border-[#00f2fe]" placeholder="••••••••">
                    <button type="button" data-toggle-password="loginPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-[#00f2fe]"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>
            <button class="w-full rounded-xl accent-gradient py-3 font-bold text-[#0d0f17] hover:brightness-110">Login</button>
        </form>

        <!-- Register -->
        <form method="post" id="registerForm" class="<?= $mode === 'register' ? '' : 'hidden' ?> space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="register">
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Full Name</label>
                <input name="name" value="<?= e($_POST['name'] ?? '') ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-3 text-sm outline-none focus:border-[#00f2fe]" placeholder="Aarav Sharma">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Username</label>
                    <input name="username" value="<?= e($_POST['username'] ?? '') ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-3 text-sm outline-none focus:border-[#00f2fe]" placeholder="aarav_99">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Phone</label>
                    <input name="phone" value="<?= e($_POST['phone'] ?? '') ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-3 text-sm outline-none focus:border-[#00f2fe]" placeholder="9876543210">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Email</label>
                <input name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-3 text-sm outline-none focus:border-[#00f2fe]" placeholder="you@example.com">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Password</label>
                <div class="relative">
                    <input id="regPassword" name="password" type="password" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-3 pr-11 text-sm outline-none focus:border-[#00f2fe]" placeholder="Min 6 characters">
                    <button type="button" data-toggle-password="regPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-[#00f2fe]"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Confirm Password</label>
                <div class="relative">
                    <input id="regConfirm" name="confirm_password" type="password" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-3 pr-11 text-sm outline-none focus:border-[#00f2fe]" placeholder="Repeat password">
                    <button type="button" data-toggle-password="regConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-[#00f2fe]"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>
            <button class="w-full rounded-xl accent-gradient py-3 font-bold text-[#0d0f17] hover:brightness-110">Create Account</button>
        </form>
    </div>

    <p class="mt-5 text-center text-xs text-slate-500">
        Need help? <a href="<?= e(SUPPORT_WHATSAPP) ?>" class="text-[#00f2fe]">WhatsApp Support</a>
    </p>
</div>

<script>
const tabs = document.querySelectorAll('.tab-btn');
const loginForm = document.getElementById('loginForm');
const registerForm = document.getElementById('registerForm');
function paint(mode) {
    tabs.forEach((t) => {
        const on = t.dataset.mode === mode;
        t.className = 'tab-btn rounded-lg py-2 ' + (on ? 'accent-gradient text-[#0d0f17]' : 'text-slate-400');
    });
    loginForm.classList.toggle('hidden', mode !== 'login');
    registerForm.classList.toggle('hidden', mode !== 'register');
}
tabs.forEach((t) => t.addEventListener('click', () => paint(t.dataset.mode)));
paint(<?= json_encode($mode) ?>);

/* Password eye toggles (this page does not load bottom.php). */
document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.togglePassword);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.innerHTML = show ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
    });
});
</script>
</body>
</html>
