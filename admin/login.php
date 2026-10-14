<?php
/**
 * AS ESPORT Admin - login.
 *
 * Authenticates against the admins table. Both Super and Sub Admins use this
 * page; the role stored on the row drives what they can reach afterwards.
 */

require_once __DIR__ . '/../config.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('index.php');
}

$errors = [];

if (is_post()) {
    verify_csrf();
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = 'Enter your username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password'])) {
            $errors[] = 'Invalid admin credentials.';
        } elseif ((int) $admin['status'] !== 1) {
            $errors[] = 'This admin account has been revoked.';
        } else {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_name'] = $admin['name'];
            $pdo->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?')->execute([$admin['id']]);
            redirect('index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login &middot; <?= e(SITE_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Russo+One&display=swap" rel="stylesheet">
<style>
    body { background: radial-gradient(circle at 50% 0%, #1b2440 0%, #0d0f17 55%); }
    .accent-gradient { background: linear-gradient(135deg, #00f2fe 0%, #7c3aed 100%); }
</style>
</head>
<body class="flex min-h-screen items-center justify-center p-4 font-sans text-slate-200">
<div class="w-full max-w-sm">
    <div class="mb-6 text-center">
        <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl accent-gradient text-2xl text-[#0d0f17]"><i class="fa-solid fa-shield-halved"></i></span>
        <h1 class="font-display text-2xl tracking-wide text-white">Admin Panel</h1>
        <p class="text-sm text-slate-400"><?= e(SITE_NAME) ?> control centre</p>
    </div>

    <div class="rounded-2xl border border-white/10 bg-[#13151f] p-6 shadow-2xl">
        <?php if ($errors): ?>
            <div class="mb-4 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
                <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Username</label>
                <div class="relative">
                    <i class="fa-solid fa-user absolute left-3 top-1/2 -translate-y-1/2 text-slate-500"></i>
                    <input name="username" required class="w-full rounded-xl border border-white/10 bg-black/30 py-3 pl-10 pr-3 text-sm outline-none focus:border-[#00f2fe]" placeholder="superadmin">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-400">Password</label>
                <div class="relative">
                    <i class="fa-solid fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-slate-500"></i>
                    <input id="adminPassword" name="password" type="password" required class="w-full rounded-xl border border-white/10 bg-black/30 py-3 pl-10 pr-11 text-sm outline-none focus:border-[#00f2fe]" placeholder="••••••••">
                    <button type="button" data-toggle-password="adminPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-[#00f2fe]"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>
            <button class="w-full rounded-xl accent-gradient py-3 font-bold text-[#0d0f17] hover:brightness-110">Login</button>
        </form>
    </div>
</div>

<script>
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
