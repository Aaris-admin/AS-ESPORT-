<?php
/**
 * AS ESPORT - Promo codes.
 *
 * Users redeem a code here. The claim runs over AJAX (no reload) and is
 * enforced single-use per user and per device server-side. The page also
 * carries the official WhatsApp channel and Telegram links.
 */

require_once __DIR__ . '/config.php';

$user = require_user($pdo);
$pageTitle = 'Promo Codes';
$activeNav = 'promo';

// Codes this user has already claimed, so we can grey them out.
$stmt = $pdo->prepare('SELECT promo_id FROM promo_claims WHERE user_id = ?');
$stmt->execute([$user['id']]);
$claimed = array_map('intval', array_column($stmt->fetchAll(), 'promo_id'));

// Publicly visible active codes (no secrets here, just marketing offers).
$stmt = $pdo->query(
    "SELECT * FROM promo_codes
     WHERE status = 1 AND (expires_at IS NULL OR expires_at > NOW()) AND used_count < max_uses
     ORDER BY amount DESC LIMIT 20"
);
$codes = $stmt->fetchAll();

$whatsappChannel = setting($pdo, 'whatsapp_channel', PROMO_WHATSAPP_CHANNEL);
$telegram = setting($pdo, 'telegram', PROMO_TELEGRAM);

require __DIR__ . '/common/header.php';
?>

<section class="mb-5 overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-[#241640] via-[#13151f] to-[#0d0f17] p-6">
    <p class="mb-1 text-xs font-semibold uppercase tracking-[0.2em] text-[#00f2fe]">Rewards</p>
    <h1 class="font-display text-3xl text-white">Promo Codes</h1>
    <p class="mt-2 max-w-lg text-sm text-slate-400">Redeem a code to instantly top up your wallet. Each code is single-use per user and per device.</p>
</section>

<!-- Redeem -->
<section class="mb-6 rounded-2xl border border-white/10 bg-[#13151f] p-5">
    <h2 class="mb-3 font-semibold text-white"><i class="fa-solid fa-ticket text-[#00f2fe]"></i> Redeem a Code</h2>
    <div class="flex flex-col gap-3 sm:flex-row">
        <input id="promoInput" class="flex-1 rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm uppercase tracking-widest outline-none focus:border-[#00f2fe]" placeholder="ENTER CODE">
        <button id="promoBtn" class="rounded-xl accent-gradient px-6 py-3 font-bold text-[#0d0f17]">
            <span class="btn-label">Claim</span>
            <span class="btn-spinner"><i class="fa-solid fa-circle-notch fa-spin"></i> Checking…</span>
        </button>
    </div>
</section>

<!-- Available codes -->
<section class="mb-6">
    <h2 class="mb-3 font-semibold text-white"><i class="fa-solid fa-gift text-amber-300"></i> Live Offers</h2>
    <?php if (!$codes): ?>
        <div class="rounded-2xl border border-white/10 bg-[#13151f] p-8 text-center text-slate-400">
            No live promo codes right now. Follow our channels below for new drops.
        </div>
    <?php else: ?>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($codes as $code): ?>
                <?php $used = in_array((int) $code['id'], $claimed, true); ?>
                <div class="card fade-in rounded-2xl p-4">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-lg font-bold tracking-widest text-[#00f2fe]"><?= e($code['code']) ?></span>
                        <span class="rounded-full bg-emerald-500/15 px-3 py-1 text-sm font-bold text-emerald-300"><?= money($code['amount']) ?></span>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        <?= (int) $code['max_uses'] - (int) $code['used_count'] ?> use(s) left
                        <?= $code['expires_at'] ? ' · expires ' . e(format_match_time($code['expires_at'])) . ' IST' : '' ?>
                    </p>
                    <button type="button"
                            data-code="<?= e($code['code']) ?>"
                            class="use-code mt-3 w-full rounded-lg py-2 text-xs font-bold <?= $used ? 'cursor-not-allowed bg-white/5 text-slate-500' : 'accent-gradient text-[#0d0f17]' ?>"
                            <?= $used ? 'disabled' : '' ?>>
                        <?= $used ? 'Already Claimed' : 'Use Code' ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Official channels -->
<section class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
    <h2 class="mb-1 font-semibold text-white">Get Codes First</h2>
    <p class="mb-4 text-sm text-slate-400">New codes drop on our official channels before anywhere else.</p>
    <div class="grid gap-3 sm:grid-cols-2">
        <a href="<?= e($whatsappChannel) ?>" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 hover:bg-emerald-500/20">
            <i class="fa-brands fa-whatsapp text-2xl text-emerald-400"></i>
            <div><p class="font-semibold text-white">WhatsApp Channel</p><p class="text-xs text-slate-400">Join for instant drops</p></div>
        </a>
        <a href="<?= e($telegram) ?>" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl border border-sky-500/30 bg-sky-500/10 p-4 hover:bg-sky-500/20">
            <i class="fa-brands fa-telegram text-2xl text-sky-400"></i>
            <div><p class="font-semibold text-white">Telegram</p><p class="text-xs text-slate-400">Official announcements</p></div>
        </a>
    </div>
</section>

<?php
$pageScripts = <<<'HTML'
<script>
const promoInput = document.getElementById('promoInput');
const promoBtn = document.getElementById('promoBtn');

async function claim(code) {
    code = (code || '').trim().toUpperCase();
    if (!code) { AS.toast('Enter a promo code.', 'error'); return; }
    const restore = AS.loading(promoBtn);
    const payload = await AS.post('ajax/claim_promo.php', { code });
    restore();
    if (payload.success) {
        AS.toast(payload.message, 'success');
        AS.refreshBalance();
        setTimeout(() => window.location.reload(), 1400);
    } else {
        AS.toast(payload.message || 'Could not claim the code.', 'error');
    }
}

promoBtn.addEventListener('click', () => claim(promoInput.value));
promoInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') claim(promoInput.value); });
document.querySelectorAll('.use-code:not([disabled])').forEach((btn) => {
    btn.addEventListener('click', () => claim(btn.dataset.code));
});
</script>
HTML;
require __DIR__ . '/common/bottom.php';
