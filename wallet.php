<?php
/**
 * AS ESPORT - Wallet.
 *
 * Deposit requests (UPI + UTR), withdrawal requests to UPI, and the full
 * transaction ledger. Deposits and withdrawals are queued as 'pending' for an
 * admin to approve; money only moves when an admin acts.
 */

require_once __DIR__ . '/config.php';

$user = require_user($pdo);
$pageTitle = 'Wallet';
$activeNav = 'wallet';

$minDeposit = (float) setting($pdo, 'min_deposit', 50);
$minWithdrawal = (float) setting($pdo, 'min_withdrawal', 100);
$upiId = setting($pdo, 'upi_id', 'asesport@upi');
$upiName = setting($pdo, 'upi_name', 'AS ESPORT');
$qrImage = setting($pdo, 'qr_image', '');
$depositNote = setting($pdo, 'deposit_note', 'Payments are reviewed within 5-10 minutes.');
$depositSubmitted = false;

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'deposit') {
        $amount = (float) ($_POST['amount'] ?? 0);
        $txnId = trim($_POST['txn_id'] ?? '');
        $utr = trim($_POST['utr'] ?? '');
        $depositError = '';

        if ($amount < $minDeposit) {
            $depositError = 'Minimum deposit is ' . money($minDeposit) . '.';
        } elseif ($txnId === '' && $utr === '') {
            $depositError = 'Enter the Txn ID or UTR number from your payment app.';
        } else {
            $screenshot = '';
            if (!empty($_FILES['screenshot']['name'])) {
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES['screenshot']['tmp_name']);
                if (!isset($allowed[$mime])) {
                    $depositError = 'Screenshot must be a JPG, PNG or WEBP image.';
                } elseif ($_FILES['screenshot']['size'] > 3 * 1024 * 1024) {
                    $depositError = 'Screenshot must be under 3 MB.';
                } else {
                    $name = 'dep_' . $user['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
                    $dest = __DIR__ . '/assets/uploads/screenshots/' . $name;
                    if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $dest)) {
                        $screenshot = $name;
                    } else {
                        $depositError = 'Could not save the screenshot. Try again.';
                    }
                }
            }

            if ($depositError === '') {
                $stmt = $pdo->prepare('INSERT INTO deposits (user_id, amount, txn_id, utr, method, screenshot) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$user['id'], $amount, $txnId, $utr, 'upi', $screenshot]);
                $depositSubmitted = true;
            }
        }

        if ($depositError !== '') {
            flash('error', $depositError);
            redirect('wallet.php');
        }
    } elseif ($action === 'withdraw') {
        $amount = (float) ($_POST['amount'] ?? 0);
        $upi = trim($_POST['upi_id'] ?? '');

        if ($amount < $minWithdrawal) {
            flash('error', 'Minimum withdrawal is ' . money($minWithdrawal) . '.');
        } elseif (!preg_match('/^[\w.\-]{2,}@[\w.\-]{2,}$/', $upi)) {
            flash('error', 'Enter a valid UPI ID (e.g. name@bank).');
        } elseif ($amount > (float) $user['balance']) {
            flash('error', 'Withdrawal exceeds your available balance.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO withdrawals (user_id, amount, upi_id) VALUES (?, ?, ?)');
            $stmt->execute([$user['id'], $amount, $upi]);
            flash('success', 'Withdrawal request of ' . money($amount) . ' submitted for review.');
        }
        redirect('wallet.php');
    }
}

// Ledger + pending requests.
$stmt = $pdo->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 40');
$stmt->execute([$user['id']]);
$ledger = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM deposits WHERE user_id = ? ORDER BY id DESC LIMIT 10");
$stmt->execute([$user['id']]);
$deposits = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY id DESC LIMIT 10");
$stmt->execute([$user['id']]);
$withdrawals = $stmt->fetchAll();

require __DIR__ . '/common/header.php';
?>

<section class="mb-5 overflow-hidden rounded-2xl border border-[#00f2fe]/25 bg-gradient-to-br from-[#102030] to-[#13151f] p-6">
    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#00f2fe]">Available Balance</p>
    <p class="font-display text-4xl text-white" data-balance-value><?= money($user['balance']) ?></p>
    <div class="mt-4 flex gap-3">
        <button type="button" data-open-modal="depositModal" class="flex-1 rounded-xl accent-gradient py-3 font-bold text-[#0d0f17]"><i class="fa-solid fa-arrow-down"></i> Deposit</button>
        <button type="button" data-open-modal="withdrawModal" class="flex-1 rounded-xl border border-white/15 py-3 font-bold text-slate-200 hover:border-[#00f2fe]"><i class="fa-solid fa-arrow-up"></i> Withdraw</button>
    </div>
</section>

<div class="grid gap-5 lg:grid-cols-2">
    <section class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
        <h2 class="mb-3 font-semibold text-white"><i class="fa-solid fa-arrow-down text-emerald-300"></i> Deposit Requests</h2>
        <?php if (!$deposits): ?>
            <p class="text-sm text-slate-500">No deposit requests yet.</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($deposits as $d): ?>
                    <div class="flex items-center justify-between rounded-xl bg-black/30 px-3 py-2 text-sm">
                        <div>
                            <p class="font-semibold text-white"><?= money($d['amount']) ?></p>
                            <p class="text-xs text-slate-500"><?= e(format_match_time($d['created_at'])) ?><?= $d['utr'] ? ' · UTR ' . e($d['utr']) : '' ?></p>
                        </div>
                        <?= status_badge($d['status'] === 'approved' ? 'room_released' : ($d['status'] === 'rejected' ? 'cancelled' : 'upcoming')) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
        <h2 class="mb-3 font-semibold text-white"><i class="fa-solid fa-arrow-up text-amber-300"></i> Withdrawal Requests</h2>
        <?php if (!$withdrawals): ?>
            <p class="text-sm text-slate-500">No withdrawal requests yet.</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($withdrawals as $w): ?>
                    <div class="flex items-center justify-between rounded-xl bg-black/30 px-3 py-2 text-sm">
                        <div>
                            <p class="font-semibold text-white"><?= money($w['amount']) ?></p>
                            <p class="text-xs text-slate-500"><?= e($w['upi_id']) ?> · <?= e(format_match_time($w['created_at'])) ?></p>
                        </div>
                        <?= status_badge($w['status'] === 'approved' ? 'room_released' : ($w['status'] === 'rejected' ? 'cancelled' : 'upcoming')) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="mt-5 rounded-2xl border border-white/10 bg-[#13151f] p-5">
    <h2 class="mb-3 font-semibold text-white"><i class="fa-solid fa-receipt text-[#00f2fe]"></i> Transaction History</h2>
    <?php if (!$ledger): ?>
        <p class="text-sm text-slate-500">No transactions yet.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500">
                    <tr><th class="py-2">Date (IST)</th><th class="py-2">Type</th><th class="py-2">Note</th><th class="py-2 text-right">Amount</th><th class="py-2 text-right">Balance</th></tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php foreach ($ledger as $tx): ?>
                        <tr>
                            <td class="py-2 text-slate-400"><?= e(format_match_time($tx['created_at'])) ?></td>
                            <td class="py-2 capitalize text-slate-300"><?= e(str_replace('_', ' ', $tx['type'])) ?></td>
                            <td class="py-2 text-slate-400"><?= e($tx['note']) ?></td>
                            <td class="py-2 text-right font-semibold <?= (float) $tx['amount'] >= 0 ? 'text-emerald-300' : 'text-rose-300' ?>">
                                <?= (float) $tx['amount'] >= 0 ? '+' : '' ?><?= money($tx['amount']) ?>
                            </td>
                            <td class="py-2 text-right text-slate-400"><?= money($tx['balance_after']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- Deposit submitted popup -->
<?php if ($depositSubmitted): ?>
<div id="depositDone" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
    <div class="w-full max-w-sm rounded-2xl border border-[#00f2fe]/30 bg-[#13151f] p-6 text-center fade-in">
        <span class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500/15 text-2xl text-emerald-300"><i class="fa-solid fa-clock"></i></span>
        <h3 class="font-display text-lg text-white">Deposit Under Review</h3>
        <p class="mt-2 text-sm text-slate-300">Your deposit request has been submitted. Payments are verified within <strong class="text-[#00f2fe]">5-10 minutes</strong>. Your wallet balance will update automatically once approved.</p>
        <button type="button" onclick="document.getElementById('depositDone').remove()" class="mt-5 w-full rounded-xl accent-gradient py-3 font-bold text-[#0d0f17]">Got it</button>
    </div>
</div>
<?php endif; ?>

<!-- Deposit modal -->
<div id="depositModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#13151f] p-5 fade-in">
        <div class="mb-4 flex items-start justify-between">
            <h3 class="font-display text-lg text-white">Add Funds</h3>
            <button type="button" data-close-modal class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <div class="mb-4 rounded-xl border border-white/10 bg-black/30 p-4 text-center">
            <?php if ($qrImage): ?>
                <img src="<?= e($qrImage) ?>" alt="UPI QR" class="mx-auto mb-3 h-44 w-44 rounded-lg bg-white p-1">
            <?php else: ?>
                <div class="mx-auto mb-3 flex h-44 w-44 items-center justify-center rounded-lg border border-dashed border-white/20 text-slate-500">
                    <div class="text-center text-xs"><i class="fa-solid fa-qrcode mb-1 text-2xl"></i><br>QR not configured</div>
                </div>
            <?php endif; ?>
            <p class="text-xs uppercase tracking-wide text-slate-500">Pay to UPI</p>
            <p class="font-mono text-lg font-bold text-[#00f2fe]"><?= e($upiId) ?></p>
            <p class="text-xs text-slate-400"><?= e($upiName) ?></p>
            <button type="button" data-copy="<?= e($upiId) ?>" class="mt-2 rounded-lg border border-white/15 px-3 py-1 text-xs text-slate-300 hover:text-white"><i class="fa-solid fa-copy"></i> Copy UPI ID</button>
        </div>

        <p class="mb-3 rounded-lg border border-amber-500/25 bg-amber-500/10 px-3 py-2 text-xs text-amber-300">
            <i class="fa-solid fa-circle-info"></i> <?= e($depositNote) ?>
        </p>

        <form method="post" enctype="multipart/form-data" class="space-y-3" id="depositForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="deposit">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-400">Amount (min <?= money($minDeposit) ?>)</label>
                <input name="amount" type="number" min="<?= (int) $minDeposit ?>" step="1" required class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" placeholder="100">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Txn ID</label>
                    <input name="txn_id" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" placeholder="Optional">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">UTR Number</label>
                    <input name="utr" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" placeholder="Optional">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-400">Payment Screenshot (optional)</label>
                <input name="screenshot" type="file" accept="image/*" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-[#00f2fe]/15 file:px-3 file:py-1 file:text-[#00f2fe]">
            </div>
            <button class="w-full rounded-xl accent-gradient py-3 font-bold text-[#0d0f17]">Submit Deposit Request</button>
        </form>
    </div>
</div>

<!-- Withdraw modal -->
<div id="withdrawModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#13151f] p-5 fade-in">
        <div class="mb-4 flex items-start justify-between">
            <h3 class="font-display text-lg text-white">Withdraw Winnings</h3>
            <button type="button" data-close-modal class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <form method="post" class="space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="withdraw">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-400">Amount (min <?= money($minWithdrawal) ?>)</label>
                <input name="amount" type="number" min="<?= (int) $minWithdrawal ?>" step="1" required class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" placeholder="500">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-400">Your UPI ID</label>
                <input name="upi_id" required class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" placeholder="yourname@bank">
            </div>
            <p class="rounded-lg border border-white/10 bg-black/30 px-3 py-2 text-xs text-slate-400">
                Withdrawals are processed to your UPI ID within 24 hours of approval.
            </p>
            <button class="w-full rounded-xl accent-gradient py-3 font-bold text-[#0d0f17]">Request Withdrawal</button>
        </form>
    </div>
</div>

<?php
$pageScripts = <<<'HTML'
<script>
/* Modal open/close */
document.querySelectorAll('[data-open-modal]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const el = document.getElementById(btn.dataset.openModal);
        el.classList.remove('hidden'); el.classList.add('flex');
    });
});
document.querySelectorAll('[data-close-modal]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const el = btn.closest('.fixed');
        el.classList.add('hidden'); el.classList.remove('flex');
    });
});
document.querySelectorAll('.fixed[id$="Modal"]').forEach((m) => {
    m.addEventListener('click', (e) => { if (e.target === m) { m.classList.add('hidden'); m.classList.remove('flex'); } });
});

/* Copy UPI ID */
document.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(btn.dataset.copy); AS.toast('UPI ID copied.', 'success'); }
        catch (e) { AS.toast('Copy failed. Long-press to select.', 'error'); }
    });
});

/* Deposit submission: confirmation popup about the 5-10 minute review. */
const depositForm = document.getElementById('depositForm');
if (depositForm) {
    depositForm.addEventListener('submit', (e) => {
        if (!depositForm.reportValidity()) { e.preventDefault(); return; }
        e.preventDefault();
        const amount = depositForm.querySelector('[name="amount"]').value;
        AS.toast('Submitting your deposit request…', 'info');
        setTimeout(() => {
            depositForm.submit();
        }, 400);
    });
}
</script>
HTML;
require __DIR__ . '/common/bottom.php';
