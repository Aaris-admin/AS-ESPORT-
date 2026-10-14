<?php
/**
 * AS ESPORT - My Tournaments.
 *
 * Lists every tournament the user has entered, with their slots, room
 * credentials once released, and final results. Passing ?id= shows one match
 * in detail with the full slot breakdown.
 */

require_once __DIR__ . '/config.php';

$user = require_user($pdo);
$pageTitle = 'My Tournaments';
$activeNav = 'my';

$detail = null;
$detailSlots = [];

if (!empty($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM tournaments WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_GET['id']]);
    $detail = $stmt->fetch();

    if ($detail) {
        $stmt = $pdo->prepare('SELECT * FROM participants WHERE tournament_id = ? AND user_id = ? ORDER BY slot_no');
        $stmt->execute([$detail['id'], $user['id']]);
        $detailSlots = $stmt->fetchAll();
    }
}

$stmt = $pdo->prepare(
    'SELECT t.*, COUNT(p.id) AS my_slots, SUM(p.is_winner) AS won
     FROM tournaments t
     JOIN participants p ON p.tournament_id = t.id AND p.user_id = ?
     GROUP BY t.id
     ORDER BY t.match_time DESC'
);
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

require __DIR__ . '/common/header.php';
?>

<div class="mb-5 flex items-center justify-between">
    <h1 class="font-display text-2xl text-white">My Tournaments</h1>
    <a href="index.php" class="rounded-lg border border-white/10 px-3 py-1.5 text-sm text-slate-300 hover:text-white"><i class="fa-solid fa-plus"></i> Find Match</a>
</div>

<?php if ($detail): ?>
    <!-- Detail view -->
    <section class="mb-6 rounded-2xl border border-white/10 bg-[#13151f] p-5">
        <div class="mb-3 flex items-start justify-between gap-3">
            <div>
                <h2 class="font-display text-xl text-white"><?= e($detail['title']) ?></h2>
                <p class="text-sm text-slate-400"><i class="fa-solid fa-clock text-[#00f2fe]"></i> <?= e(format_match_time($detail['match_time'])) ?> IST</p>
            </div>
            <?= status_badge(tournament_status($detail)) ?>
        </div>

        <?php if (!$detailSlots): ?>
            <p class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-300">You are not registered in this tournament.</p>
        <?php else: ?>
            <div class="mb-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-black/30 p-3 text-center">
                    <p class="text-xs uppercase text-slate-500">Entry Paid</p>
                    <p class="font-bold text-white"><?= money(array_sum(array_column($detailSlots, 'entry_fee'))) ?></p>
                </div>
                <div class="rounded-xl bg-black/30 p-3 text-center">
                    <p class="text-xs uppercase text-slate-500">Slots</p>
                    <p class="font-bold text-white"><?= count($detailSlots) ?></p>
                </div>
                <div class="rounded-xl bg-black/30 p-3 text-center">
                    <p class="text-xs uppercase text-slate-500">Result</p>
                    <p class="font-bold <?= array_sum(array_column($detailSlots, 'is_winner')) ? 'text-emerald-300' : 'text-slate-300' ?>">
                        <?= array_sum(array_column($detailSlots, 'is_winner')) ? 'Winner' : 'Participated' ?>
                    </p>
                </div>
            </div>

            <?php if ($detail['room_id'] !== ''): ?>
                <div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-emerald-300">Room Credentials</p>
                    <p class="text-sm text-slate-200">Room ID: <span class="font-mono font-bold text-white"><?= e($detail['room_id']) ?></span></p>
                    <p class="text-sm text-slate-200">Password: <span class="font-mono font-bold text-white"><?= e($detail['room_password']) ?></span></p>
                </div>
            <?php endif; ?>

            <?php if ($detail['result_note'] !== ''): ?>
                <p class="mb-4 rounded-xl border border-white/10 bg-black/30 p-3 text-sm text-slate-300"><i class="fa-solid fa-circle-info text-[#00f2fe]"></i> <?= e($detail['result_note']) ?></p>
            <?php endif; ?>

            <div class="overflow-hidden rounded-xl border border-white/10">
                <table class="w-full text-sm">
                    <thead class="bg-black/40 text-left text-xs uppercase text-slate-400">
                        <tr><th class="px-3 py-2">Slot</th><th class="px-3 py-2">In-Game UID</th><th class="px-3 py-2">Entry</th><th class="px-3 py-2">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php foreach ($detailSlots as $slot): ?>
                            <tr>
                                <td class="px-3 py-2 text-slate-300">#<?= (int) $slot['slot_no'] ?></td>
                                <td class="px-3 py-2 font-mono text-slate-200"><?= e($slot['in_game_uid']) ?></td>
                                <td class="px-3 py-2 text-slate-400"><?= money($slot['entry_fee']) ?></td>
                                <td class="px-3 py-2">
                                    <?= $slot['is_winner'] ? '<span class="text-emerald-300 font-semibold">Winner</span>' : '<span class="text-slate-400">Played</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<!-- History -->
<?php if (!$rows): ?>
    <div class="rounded-2xl border border-white/10 bg-[#13151f] p-10 text-center">
        <i class="fa-solid fa-trophy mb-3 text-4xl text-slate-600"></i>
        <p class="text-slate-400">You have not joined any tournaments yet.</p>
        <a href="index.php" class="mt-4 inline-block rounded-lg accent-gradient px-5 py-2 font-semibold text-[#0d0f17]">Browse Tournaments</a>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($rows as $row): ?>
            <a href="my_tournaments.php?id=<?= (int) $row['id'] ?>" class="card fade-in flex items-center justify-between gap-3 rounded-2xl p-4">
                <div class="min-w-0">
                    <p class="truncate font-semibold text-white"><?= e($row['title']) ?></p>
                    <p class="text-xs text-slate-400">
                        <i class="fa-solid fa-clock text-[#00f2fe]"></i> <?= e(format_match_time($row['match_time'])) ?> IST
                        &middot; <?= (int) $row['my_slots'] ?> slot(s)
                    </p>
                    <?php if ($row['room_id'] !== ''): ?>
                        <p class="mt-1 text-xs text-emerald-300"><i class="fa-solid fa-door-open"></i> Room: <span class="font-mono"><?= e($row['room_id']) ?></span></p>
                    <?php endif; ?>
                </div>
                <div class="shrink-0 text-right">
                    <?= status_badge(tournament_status($row)) ?>
                    <?php if ((int) $row['won'] > 0): ?>
                        <p class="mt-1 text-xs font-semibold text-amber-300"><i class="fa-solid fa-medal"></i> Won</p>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/common/bottom.php'; ?>
