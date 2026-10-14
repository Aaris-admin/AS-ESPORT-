<?php
/**
 * AS ESPORT Admin - promo codes (Super Admin only).
 *
 * Create, enable/disable and delete promo codes, and inspect who claimed them.
 */

require_once __DIR__ . '/../config.php';
$admin = require_super_admin($pdo);
$pageTitle = 'Promo Codes';
$activeNav = 'promo';

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $amount = max(1, (float) ($_POST['amount'] ?? 0));
        $maxUses = max(1, (int) ($_POST['max_uses'] ?? 1));
        $expires = trim($_POST['expires_at'] ?? '');

        if (!preg_match('/^[A-Z0-9_\-]{3,50}$/', $code)) {
            flash('error', 'Code must be 3-50 characters (A-Z, 0-9, underscore, hyphen).');
        } else {
            $exists = $pdo->prepare('SELECT id FROM promo_codes WHERE code = ?');
            $exists->execute([$code]);
            if ($exists->fetch()) {
                flash('error', 'That promo code already exists.');
            } else {
                $expiresAt = $expires !== '' ? date('Y-m-d H:i:s', strtotime($expires)) : null;
                $stmt = $pdo->prepare('INSERT INTO promo_codes (code, amount, max_uses, expires_at, created_by) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$code, $amount, $maxUses, $expiresAt, $admin['id']]);
                flash('success', 'Promo code created.');
            }
        }
        redirect('promo_codes.php');
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE promo_codes SET status = 1 - status WHERE id = ?')->execute([$id]);
        flash('success', 'Promo code status updated.');
        redirect('promo_codes.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM promo_codes WHERE id = ?')->execute([$id]);
        flash('success', 'Promo code deleted.');
        redirect('promo_codes.php');
    }
}

$codes = $pdo->query(
    'SELECT pc.*,
            (SELECT COUNT(*) FROM promo_claims c WHERE c.promo_id = pc.id) AS claims
     FROM promo_codes pc ORDER BY pc.id DESC LIMIT 200'
)->fetchAll();

// Recent claims, newest first.
$claims = $pdo->query(
    'SELECT c.*, pc.code, u.name AS user_name, u.username
     FROM promo_claims c
     JOIN promo_codes pc ON pc.id = c.promo_id
     JOIN users u ON u.id = c.user_id
     ORDER BY c.id DESC LIMIT 40'
)->fetchAll();

require __DIR__ . '/partials/header.php';
?>

<div class="grid gap-5 lg:grid-cols-3">
    <section class="lg:col-span-1">
        <div class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
            <h2 class="mb-4 font-semibold text-white"><i class="fa-solid fa-ticket text-[#00f2fe]"></i> Create Promo Code</h2>
            <form method="post" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Code</label>
                    <input name="code" required class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm uppercase tracking-widest outline-none focus:border-[#00f2fe]" placeholder="WELCOME50">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-400">Amount ₹</label>
                        <input name="amount" type="number" min="1" step="1" required class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" placeholder="50">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-400">Max Uses</label>
                        <input name="max_uses" type="number" min="1" value="1" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Expires (IST, optional)</label>
                    <input name="expires_at" type="datetime-local" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                </div>
                <p class="rounded-lg border border-white/10 bg-black/30 px-3 py-2 text-xs text-slate-400">
                    Every code is single-use per user and per device, regardless of Max Uses.
                </p>
                <button class="w-full rounded-xl accent-gradient py-3 font-bold text-[#0d0f17]">Create Code</button>
            </form>
        </div>
    </section>

    <section class="space-y-5 lg:col-span-2">
        <div class="overflow-hidden rounded-2xl border border-white/10 bg-[#13151f]">
            <div class="border-b border-white/10 px-4 py-3"><h3 class="font-semibold text-white"><i class="fa-solid fa-list text-[#00f2fe]"></i> Promo Codes</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-black/40 text-left text-xs uppercase text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Usage</th>
                            <th class="px-4 py-3">Expires</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php if (!$codes): ?>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No promo codes yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($codes as $c): ?>
                            <tr class="hover:bg-white/5">
                                <td class="px-4 py-3 font-mono font-bold text-[#00f2fe]"><?= e($c['code']) ?></td>
                                <td class="px-4 py-3 text-emerald-300"><?= money($c['amount']) ?></td>
                                <td class="px-4 py-3 text-slate-300"><?= (int) $c['used_count'] ?>/<?= (int) $c['max_uses'] ?></td>
                                <td class="px-4 py-3 text-xs text-slate-400"><?= $c['expires_at'] ? e(format_match_time($c['expires_at'])) : 'Never' ?></td>
                                <td class="px-4 py-3"><?= status_badge((int) $c['status'] === 1 ? 'room_released' : 'cancelled') ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="post" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                            <button class="rounded-lg bg-white/5 px-3 py-1.5 text-xs text-slate-300 hover:text-[#00f2fe]" title="Enable/Disable"><i class="fa-solid fa-power-off"></i></button>
                                        </form>
                                        <form method="post" data-confirm="Delete this promo code and its claim history?" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                            <button class="rounded-lg bg-rose-500/10 px-3 py-1.5 text-xs text-rose-300 hover:bg-rose-500/20" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-white/10 bg-[#13151f]">
            <div class="border-b border-white/10 px-4 py-3"><h3 class="font-semibold text-white"><i class="fa-solid fa-clock-rotate-left text-[#00f2fe]"></i> Recent Claims</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-black/40 text-left text-xs uppercase text-slate-400">
                        <tr><th class="px-4 py-3">User</th><th class="px-4 py-3">Code</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Device</th><th class="px-4 py-3">Claimed</th></tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php if (!$claims): ?>
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No claims yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($claims as $cl): ?>
                            <tr class="hover:bg-white/5">
                                <td class="px-4 py-3"><p class="text-white"><?= e($cl['user_name']) ?></p><p class="text-xs text-slate-500">@<?= e($cl['username']) ?></p></td>
                                <td class="px-4 py-3 font-mono text-[#00f2fe]"><?= e($cl['code']) ?></td>
                                <td class="px-4 py-3 text-emerald-300"><?= money($cl['amount']) ?></td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-500"><?= e(substr($cl['device_id'], 0, 10)) ?>…</td>
                                <td class="px-4 py-3 text-xs text-slate-400"><?= e(format_match_time($cl['claimed_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
