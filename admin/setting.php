<?php
/**
 * AS ESPORT Admin - site settings (Super Admin only).
 *
 * Payment details (UPI + QR), wallet limits and the official support links.
 * The QR image is stored under assets/uploads/screenshots with a hashed name.
 */

require_once __DIR__ . '/../config.php';
$admin = require_super_admin($pdo);
$pageTitle = 'Settings';
$activeNav = 'setting';

$keys = [
    'site_name', 'upi_id', 'upi_name', 'qr_image',
    'min_deposit', 'min_withdrawal', 'deposit_note',
    'whatsapp', 'email', 'instagram', 'youtube', 'whatsapp_channel', 'telegram',
];

if (is_post()) {
    verify_csrf();

    $values = [];
    foreach ($keys as $key) {
        if ($key === 'qr_image') {
            continue;
        }
        $values[$key] = trim($_POST[$key] ?? '');
    }

    if ($values['site_name'] === '') {
        $values['site_name'] = SITE_NAME;
    }
    $values['min_deposit'] = (string) max(1, (float) $values['min_deposit']);
    $values['min_withdrawal'] = (string) max(1, (float) $values['min_withdrawal']);

    // Optional QR upload.
    $qr = setting($pdo, 'qr_image', '');
    $qrError = '';
    if (!empty($_FILES['qr_image_file']['name'])) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['qr_image_file']['tmp_name']);
        if (isset($allowed[$mime]) && $_FILES['qr_image_file']['size'] <= 2 * 1024 * 1024) {
            $name = 'qr_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
            if (move_uploaded_file($_FILES['qr_image_file']['tmp_name'], __DIR__ . '/../assets/uploads/screenshots/' . $name)) {
                $qr = 'assets/uploads/screenshots/' . $name;
            } else {
                $qrError = 'Could not save the QR image. Try again.';
            }
        } else {
            $qrError = 'QR image must be a JPG, PNG or WEBP under 2 MB.';
        }
    }
    $values['qr_image'] = $qr;

    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($values as $key => $value) {
        $stmt->execute([$key, $value]);
    }

    flash($qrError !== '' ? 'error' : 'success', $qrError !== '' ? $qrError : 'Settings saved.');
    redirect('setting.php');
}

$current = [];
foreach ($keys as $key) {
    $current[$key] = setting($pdo, $key, '');
}

$dbNow = $pdo->query('SELECT NOW()')->fetchColumn();

require __DIR__ . '/partials/header.php';
?>

<div class="mb-5 rounded-2xl border border-[#00f2fe]/25 bg-[#00f2fe]/5 p-4 text-sm">
    <p class="font-semibold text-white"><i class="fa-solid fa-clock text-[#00f2fe]"></i> Timezone Sync</p>
    <p class="mt-1 text-slate-300">
        Application timezone: <span class="font-mono text-[#00f2fe]"><?= e(APP_TIMEZONE) ?></span> (UTC+5:30) &middot;
        MySQL session: <span class="font-mono text-[#00f2fe]"><?= e(DB_TIMEZONE_OFFSET) ?></span>
    </p>
    <p class="mt-1 text-slate-400">
        PHP now: <?= e(date('d M Y, h:i A')) ?> IST &middot; MySQL NOW(): <?= e(format_match_time($dbNow)) ?> IST
    </p>
</div>

<form method="post" enctype="multipart/form-data" class="space-y-5">
    <?= csrf_field() ?>

    <div class="grid gap-5 lg:grid-cols-2">
        <!-- Branding -->
        <section class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
            <h2 class="mb-4 font-semibold text-white"><i class="fa-solid fa-palette text-[#00f2fe]"></i> Branding</h2>
            <label class="mb-1 block text-xs font-semibold text-slate-400">Site Name</label>
            <input name="site_name" value="<?= e($current['site_name']) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
        </section>

        <!-- Payments -->
        <section class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
            <h2 class="mb-4 font-semibold text-white"><i class="fa-solid fa-indian-rupee-sign text-[#00f2fe]"></i> Payments</h2>
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Admin UPI ID</label>
                    <input name="upi_id" value="<?= e($current['upi_id']) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" placeholder="asesport@upi">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">UPI Payee Name</label>
                    <input name="upi_name" value="<?= e($current['upi_name']) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-400">Min Deposit ₹</label>
                        <input name="min_deposit" type="number" min="1" value="<?= e($current['min_deposit']) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-400">Min Withdrawal ₹</label>
                        <input name="min_withdrawal" type="number" min="1" value="<?= e($current['min_withdrawal']) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Deposit Review Note</label>
                    <input name="deposit_note" value="<?= e($current['deposit_note']) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                </div>
            </div>
        </section>

        <!-- QR -->
        <section class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
            <h2 class="mb-4 font-semibold text-white"><i class="fa-solid fa-qrcode text-[#00f2fe]"></i> Payment QR</h2>
            <?php if ($current['qr_image']): ?>
                <img src="../<?= e($current['qr_image']) ?>" alt="Current QR" class="mb-3 h-40 w-40 rounded-xl bg-white p-1">
            <?php else: ?>
                <p class="mb-3 text-sm text-slate-500">No QR uploaded yet.</p>
            <?php endif; ?>
            <label class="mb-1 block text-xs font-semibold text-slate-400">Upload QR Image</label>
            <input name="qr_image_file" type="file" accept="image/*" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-[#00f2fe]/15 file:px-3 file:py-1 file:text-[#00f2fe]">
        </section>

        <!-- Support -->
        <section class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
            <h2 class="mb-4 font-semibold text-white"><i class="fa-solid fa-headset text-[#00f2fe]"></i> Support Links</h2>
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">WhatsApp Support URL</label>
                    <input name="whatsapp" value="<?= e($current['whatsapp']) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Support Email</label>
                    <input name="email" value="<?= e($current['email']) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Instagram URL</label>
                    <input name="instagram" value="<?= e($current['instagram']) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">YouTube URL</label>
                    <input name="youtube" value="<?= e($current['youtube']) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">WhatsApp Channel URL</label>
                    <input name="whatsapp_channel" value="<?= e($current['whatsapp_channel']) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Telegram URL</label>
                    <input name="telegram" value="<?= e($current['telegram']) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                </div>
            </div>
        </section>
    </div>

    <button class="w-full rounded-xl accent-gradient py-3 font-bold text-[#0d0f17] sm:w-auto sm:px-8"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button>
</form>

<?php require __DIR__ . '/partials/footer.php'; ?>
