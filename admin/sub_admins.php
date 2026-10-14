<?php
/**
 * AS ESPORT Admin - sub-admin management (Super Admin only).
 *
 * Create sub-admins and revoke access instantly. Revocation flips status to 0;
 * require_admin() then destroys the revoked session on their next request, so
 * access is gone immediately without needing to find their session row.
 */

require_once __DIR__ . '/../config.php';
$admin = require_super_admin($pdo);
$pageTitle = 'Sub Admins';
$activeNav = 'subadmins';

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (mb_strlen($name) < 2) {
            flash('error', 'Enter the sub-admin name.');
        } elseif (!preg_match('/^[a-z0-9_]{3,20}$/', $username)) {
            flash('error', 'Username must be 3-20 characters (letters, numbers, underscore).');
        } elseif (strlen($password) < 6) {
            flash('error', 'Password must be at least 6 characters.');
        } else {
            $check = $pdo->prepare('SELECT id FROM admins WHERE username = ?');
            $check->execute([$username]);
            if ($check->fetch()) {
                flash('error', 'That admin username already exists.');
            } else {
                $stmt = $pdo->prepare("INSERT INTO admins (name, username, email, password, role, status, created_by) VALUES (?, ?, ?, ?, 'sub', 1, ?)");
                $stmt->execute([$name, $username, $email, password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
                flash('success', 'Sub-admin created.');
            }
        }
        redirect('sub_admins.php');
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $admin['id']) {
            flash('error', 'You cannot revoke your own account.');
        } else {
            // Never allow the last active Super Admin to be locked out.
            $target = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
            $target->execute([$id]);
            $row = $target->fetch();
            if ($row && $row['role'] === 'super') {
                flash('error', 'Super Admin accounts cannot be toggled here.');
            } else {
                $pdo->prepare('UPDATE admins SET status = 1 - status WHERE id = ?')->execute([$id]);
                flash('success', 'Sub-admin access updated. Revoked sessions end immediately.');
            }
        }
        redirect('sub_admins.php');
    }

    if ($action === 'reset_password') {
        $id = (int) ($_POST['id'] ?? 0);
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 6) {
            flash('error', 'Password must be at least 6 characters.');
        } else {
            $pdo->prepare('UPDATE admins SET password = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            flash('success', 'Password reset.');
        }
        redirect('sub_admins.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $target = $pdo->prepare('SELECT role FROM admins WHERE id = ?');
        $target->execute([$id]);
        $row = $target->fetch();
        if (!$row) {
            flash('error', 'Sub-admin not found.');
        } elseif ($row['role'] === 'super') {
            flash('error', 'Super Admin accounts cannot be deleted.');
        } else {
            $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
            flash('success', 'Sub-admin removed.');
        }
        redirect('sub_admins.php');
    }
}

$admins = $pdo->query('SELECT * FROM admins ORDER BY role ASC, id ASC')->fetchAll();

require __DIR__ . '/partials/header.php';
?>

<div class="grid gap-5 lg:grid-cols-3">
    <section class="lg:col-span-1">
        <div class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
            <h2 class="mb-4 font-semibold text-white"><i class="fa-solid fa-user-shield text-[#00f2fe]"></i> Create Sub-Admin</h2>
            <form method="post" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Name</label>
                    <input name="name" required class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" placeholder="Rohan Verma">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Username</label>
                    <input name="username" required class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" placeholder="rohan_admin">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Email (optional)</label>
                    <input name="email" type="email" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" placeholder="rohan@example.com">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Password</label>
                    <div class="relative">
                        <input id="subPass" name="password" type="password" required class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 pr-11 text-sm outline-none focus:border-[#00f2fe]" placeholder="Min 6 characters">
                        <button type="button" data-toggle-password="subPass" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-[#00f2fe]"><i class="fa-solid fa-eye"></i></button>
                    </div>
                </div>
                <div class="rounded-lg border border-white/10 bg-black/30 px-3 py-2 text-xs text-slate-400">
                    Sub-admins can update Room ID/Password and declare winners. They cannot access Settings, Promo Codes or Sub-Admin management.
                </div>
                <button class="w-full rounded-xl accent-gradient py-3 font-bold text-[#0d0f17]">Create Sub-Admin</button>
            </form>
        </div>
    </section>

    <section class="lg:col-span-2">
        <div class="overflow-hidden rounded-2xl border border-white/10 bg-[#13151f]">
            <div class="border-b border-white/10 px-4 py-3"><h3 class="font-semibold text-white"><i class="fa-solid fa-users-gear text-[#00f2fe]"></i> Admin Accounts</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-black/40 text-left text-xs uppercase text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Last Login</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php foreach ($admins as $a): ?>
                            <tr class="hover:bg-white/5">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-white"><?= e($a['name']) ?></p>
                                    <p class="text-xs text-slate-500">@<?= e($a['username']) ?><?= $a['email'] ? ' · ' . e($a['email']) : '' ?></p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $a['role'] === 'super' ? 'border-[#00f2fe]/30 bg-[#00f2fe]/10 text-[#00f2fe]' : 'border-white/15 bg-white/5 text-slate-300' ?>">
                                        <?= $a['role'] === 'super' ? 'Super Admin' : 'Sub Admin' ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-400"><?= $a['last_login'] ? e(format_match_time($a['last_login'])) : 'Never' ?></td>
                                <td class="px-4 py-3"><?= status_badge((int) $a['status'] === 1 ? 'room_released' : 'cancelled') ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="post" class="flex items-center gap-1">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                            <input name="password" type="text" placeholder="New password" class="w-32 rounded-lg border border-white/10 bg-black/30 px-2 py-1.5 text-xs outline-none focus:border-[#00f2fe]">
                                            <button class="rounded-lg bg-white/5 px-2.5 py-1.5 text-xs text-slate-300 hover:text-[#00f2fe]" title="Reset password"><i class="fa-solid fa-key"></i></button>
                                        </form>
                                        <?php if ($a['role'] !== 'super'): ?>
                                            <form method="post" class="inline" data-confirm="<?= (int) $a['status'] === 1 ? 'Revoke access for this sub-admin?' : 'Restore access for this sub-admin?' ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                                <button class="rounded-lg px-2.5 py-1.5 text-xs <?= (int) $a['status'] === 1 ? 'bg-amber-500/15 text-amber-300 hover:bg-amber-500/25' : 'bg-emerald-500/15 text-emerald-300 hover:bg-emerald-500/25' ?>" title="<?= (int) $a['status'] === 1 ? 'Revoke' : 'Restore' ?>">
                                                    <i class="fa-solid <?= (int) $a['status'] === 1 ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="post" class="inline" data-confirm="Permanently delete this sub-admin?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                                <button class="rounded-lg bg-rose-500/10 px-2.5 py-1.5 text-xs text-rose-300 hover:bg-rose-500/20" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
