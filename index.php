<?php
/**
 * AS ESPORT - Home / tournament listing.
 *
 * Guests can browse. Joining requires a session and runs entirely over AJAX
 * through ajax/join_tournament.php, so the page never reloads.
 */

require_once __DIR__ . '/config.php';

$user = current_user($pdo);
$pageTitle = 'Tournaments';
$activeNav = 'home';

$filter = $_GET['filter'] ?? 'all';
$allowedFilters = ['all', 'upcoming', 'room', 'completed'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$sql = 'SELECT t.*,
               (SELECT COUNT(*) FROM participants p WHERE p.tournament_id = t.id AND p.user_id = :uid) AS my_slots
        FROM tournaments t
        WHERE 1 = 1';
$params = [':uid' => $user['id'] ?? 0];

if ($filter === 'upcoming') {
    $sql .= " AND t.status = 'upcoming'";
} elseif ($filter === 'room') {
    $sql .= " AND t.status = 'upcoming' AND t.room_id <> ''";
} elseif ($filter === 'completed') {
    $sql .= " AND t.status = 'completed'";
}
$sql .= ' ORDER BY (t.status = \'upcoming\') DESC, t.match_time ASC, t.id DESC LIMIT 60';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tournaments = $stmt->fetchAll();

require __DIR__ . '/common/header.php';
?>

<!-- Hero -->
<section class="mb-6 overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-[#141a2e] via-[#13151f] to-[#0d0f17] p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="mb-1 text-xs font-semibold uppercase tracking-[0.2em] text-[#00f2fe]">Daily BGMI Scrims</p>
            <h1 class="font-display text-3xl text-white sm:text-4xl">Play. Dominate. <span class="accent-text">Get Paid.</span></h1>
            <p class="mt-2 max-w-lg text-sm text-slate-400">Join daily tournaments, win cash prizes directly to your wallet, and withdraw to UPI within minutes.</p>
        </div>
        <?php if ($user): ?>
            <div class="rounded-xl border border-[#00f2fe]/25 bg-[#00f2fe]/10 p-4 text-center">
                <p class="text-xs uppercase tracking-wide text-slate-400">Wallet Balance</p>
                <p class="font-display text-2xl text-[#00f2fe]" data-balance-value><?= money($user['balance']) ?></p>
                <a href="wallet.php" class="mt-2 inline-block rounded-lg bg-[#00f2fe] px-4 py-1.5 text-xs font-bold text-[#0d0f17]">Add Funds</a>
            </div>
        <?php else: ?>
            <a href="login.php" class="rounded-xl accent-gradient px-6 py-3 text-center font-bold text-[#0d0f17]">Login to Play</a>
        <?php endif; ?>
    </div>
</section>

<!-- Filters -->
<div class="mb-5 flex flex-wrap gap-2">
    <?php
    $tabs = ['all' => 'All Matches', 'upcoming' => 'Upcoming', 'room' => 'Room Ready', 'completed' => 'Completed'];
    foreach ($tabs as $key => $label):
        $on = $filter === $key;
    ?>
        <a href="index.php?filter=<?= e($key) ?>" class="rounded-full border px-4 py-1.5 text-sm font-semibold <?= $on ? 'border-[#00f2fe] bg-[#00f2fe]/15 text-[#00f2fe]' : 'border-white/10 text-slate-400 hover:text-white' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<!-- Tournament grid -->
<?php if (!$tournaments): ?>
    <div class="rounded-2xl border border-white/10 bg-[#13151f] p-10 text-center">
        <i class="fa-solid fa-ghost mb-3 text-4xl text-slate-600"></i>
        <p class="text-slate-400">No tournaments match this filter right now. Check back soon.</p>
    </div>
<?php else: ?>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($tournaments as $t): ?>
            <?php
                $status = tournament_status($t);
                $remaining = max(0, (int) $t['total_slots'] - (int) $t['filled_slots']);
                $percent = $t['total_slots'] > 0 ? min(100, round($t['filled_slots'] / $t['total_slots'] * 100)) : 0;
                $closed = in_array($status, ['completed', 'cancelled'], true) || $remaining <= 0;
            ?>
            <article class="card fade-in flex flex-col rounded-2xl p-4 transition">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <h2 class="font-semibold leading-tight text-white"><?= e($t['title']) ?></h2>
                        <p class="mt-1 text-xs text-slate-400">
                            <i class="fa-solid fa-map-location-dot"></i> <?= e($t['map']) ?>
                            &middot; <i class="fa-solid fa-users"></i> <?= e($t['mode']) ?>
                        </p>
                    </div>
                    <?= status_badge($status) ?>
                </div>

                <div class="mb-3 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-lg bg-black/30 py-2">
                        <p class="text-[10px] uppercase tracking-wide text-slate-500">Prize</p>
                        <p class="text-sm font-bold text-emerald-300"><?= money($t['prize_pool']) ?></p>
                    </div>
                    <div class="rounded-lg bg-black/30 py-2">
                        <p class="text-[10px] uppercase tracking-wide text-slate-500">Per Kill</p>
                        <p class="text-sm font-bold text-amber-300"><?= money($t['per_kill']) ?></p>
                    </div>
                    <div class="rounded-lg bg-black/30 py-2">
                        <p class="text-[10px] uppercase tracking-wide text-slate-500">Entry</p>
                        <p class="text-sm font-bold text-[#00f2fe]"><?= (float) $t['entry_fee'] > 0 ? money($t['entry_fee']) : 'FREE' ?></p>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="mb-1 flex items-center justify-between text-xs text-slate-400">
                        <span><i class="fa-solid fa-clock text-[#00f2fe]"></i> <?= e(format_match_time($t['match_time'])) ?> IST</span>
                        <span><?= (int) $t['filled_slots'] ?>/<?= (int) $t['total_slots'] ?></span>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-black/40">
                        <div class="h-full rounded-full accent-gradient" style="width: <?= $percent ?>%"></div>
                    </div>
                </div>

                <?php if ($status === 'room_released'): ?>
                    <div class="mb-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-300">
                        <i class="fa-solid fa-door-open"></i> Room ID: <span class="font-mono font-bold"><?= e($t['room_id']) ?></span>
                        &middot; Pass: <span class="font-mono font-bold"><?= e($t['room_password']) ?></span>
                    </div>
                <?php endif; ?>

                <div class="mt-auto flex items-center gap-2">
                    <a href="my_tournaments.php?id=<?= (int) $t['id'] ?>" class="rounded-lg border border-white/10 px-3 py-2 text-xs font-semibold text-slate-300 hover:text-white">Details</a>
                    <?php if ($closed): ?>
                        <span class="flex-1 rounded-lg bg-white/5 py-2 text-center text-xs font-semibold text-slate-500">
                            <?= $status === 'completed' ? 'Completed' : ($status === 'cancelled' ? 'Cancelled' : 'Slots Full') ?>
                        </span>
                    <?php else: ?>
                        <button type="button"
                                class="join-trigger flex-1 rounded-lg accent-gradient py-2 text-xs font-bold text-[#0d0f17] hover:brightness-110"
                                data-id="<?= (int) $t['id'] ?>"
                                data-title="<?= e($t['title']) ?>"
                                data-fee="<?= (float) $t['entry_fee'] ?>"
                                data-max="<?= (int) $t['max_slots_per_user'] ?>"
                                data-my-slots="<?= (int) $t['my_slots'] ?>"
                                data-remaining="<?= $remaining ?>">
                            <?= $user ? 'Join Now' : 'Login to Join' ?>
                        </button>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Join modal -->
<div id="joinModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#13151f] p-5 fade-in">
        <div class="mb-4 flex items-start justify-between">
            <div>
                <h3 class="font-display text-lg text-white">Join Tournament</h3>
                <p id="joinTitle" class="text-xs text-slate-400"></p>
            </div>
            <button type="button" data-close-join class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">Number of Slots (max <span id="joinMaxSlots">4</span>)</label>
        <div id="slotChoices" class="mb-4 grid grid-cols-4 gap-2"></div>

        <div id="uidFields" class="mb-4 space-y-3"></div>

        <div class="mb-4 space-y-1 rounded-xl bg-black/30 p-3 text-sm">
            <div class="flex justify-between text-slate-400"><span>Entry fee / slot</span><span id="joinFeePer">₹0.00</span></div>
            <div class="flex justify-between text-slate-400"><span>Slots selected</span><span id="joinSlotsCount">1</span></div>
            <div class="flex justify-between border-t border-white/10 pt-1 font-bold text-white"><span>Total payable</span><span id="joinTotal" class="text-[#00f2fe]">₹0.00</span></div>
        </div>

        <button id="joinSubmit" class="w-full rounded-xl accent-gradient py-3 font-bold text-[#0d0f17] hover:brightness-110">
            <span class="btn-label">Confirm &amp; Pay</span>
            <span class="btn-spinner"><i class="fa-solid fa-circle-notch fa-spin"></i> Processing…</span>
        </button>
    </div>
</div>

<?php
$pageScripts = <<<'HTML'
<script>
(() => {
    const modal = document.getElementById('joinModal');
    const slotChoices = document.getElementById('slotChoices');
    const uidFields = document.getElementById('uidFields');
    const maxLabel = document.getElementById('joinMaxSlots');
    const titleEl = document.getElementById('joinTitle');
    const feePerEl = document.getElementById('joinFeePer');
    const countEl = document.getElementById('joinSlotsCount');
    const totalEl = document.getElementById('joinTotal');
    const submitBtn = document.getElementById('joinSubmit');
    const loggedIn = __LOGGED_IN__;

    let current = { id: 0, fee: 0, max: 4, slots: 1 };

    function openModal(data) {
        current = { id: +data.id, fee: +data.fee, max: Math.max(1, +data.max), slots: 1 };
        titleEl.textContent = data.title;
        maxLabel.textContent = current.max;
        feePerEl.textContent = '₹' + current.fee.toFixed(2);
        renderSlots();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function renderSlots() {
        slotChoices.innerHTML = '';
        for (let i = 1; i <= current.max; i++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = i;
            btn.className = 'rounded-lg border py-2 text-sm font-bold ' +
                (i === current.slots ? 'border-[#00f2fe] bg-[#00f2fe]/15 text-[#00f2fe]' : 'border-white/10 text-slate-400');
            btn.addEventListener('click', () => { current.slots = i; renderSlots(); renderUid(); updateTotals(); });
            slotChoices.appendChild(btn);
        }
        renderUid();
        updateTotals();
    }

    /* Rebuild the in-game UID inputs so the count always matches slot choice. */
    function renderUid() {
        const existing = Array.from(uidFields.querySelectorAll('input')).map((i) => i.value);
        uidFields.innerHTML = '';
        for (let i = 0; i < current.slots; i++) {
            const wrap = document.createElement('div');
            wrap.innerHTML =
                '<label class="mb-1 block text-xs font-semibold text-slate-400">Slot ' + (i + 1) + ' - In-Game UID</label>' +
                '<input data-uid="' + i + '" value="' + (existing[i] || '') + '" inputmode="numeric" maxlength="20" ' +
                'class="uid-input w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" ' +
                'placeholder="e.g. 5123456789">';
            uidFields.appendChild(wrap);
        }
    }

    function updateTotals() {
        countEl.textContent = current.slots;
        totalEl.textContent = '₹' + (current.fee * current.slots).toFixed(2);
    }

    document.querySelectorAll('.join-trigger').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!loggedIn) { window.location.href = 'login.php'; return; }
            openModal(btn.dataset);
        });
    });
    document.querySelectorAll('[data-close-join]').forEach((b) => b.addEventListener('click', closeModal));
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    submitBtn.addEventListener('click', async () => {
        const uids = Array.from(uidFields.querySelectorAll('.uid-input')).map((i) => i.value.trim());
        if (uids.some((u) => u === '')) { AS.toast('Please enter every in-game UID.', 'error'); return; }

        const restore = AS.loading(submitBtn);
        const payload = await AS.post('ajax/join_tournament.php', {
            tournament_id: current.id,
            slots: current.slots,
            uids: JSON.stringify(uids),
        });
        restore();

        if (payload.success) {
            AS.toast(payload.message, 'success');
            closeModal();
            AS.refreshBalance();
            setTimeout(() => window.location.reload(), 1200);
        } else {
            AS.toast(payload.message || 'Could not join.', 'error');
        }
    });
})();
</script>
HTML;
$pageScripts = str_replace('__LOGGED_IN__', $user ? 'true' : 'false', $pageScripts);
require __DIR__ . '/common/bottom.php';
