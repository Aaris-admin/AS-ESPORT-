<?php
/**
 * AS ESPORT Admin - tournaments list and create/edit form.
 *
 * match_time is entered as a local datetime string and stored verbatim as a
 * DATETIME. Because the whole app treats that column as IST wall-clock, the
 * value the admin types is exactly what players see.
 */

require_once __DIR__ . '/../config.php';
$admin = require_admin($pdo);
$pageTitle = 'Tournaments';
$activeNav = 'tournament';

$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $data = [
            'title'              => trim($_POST['title'] ?? ''),
            'game'               => trim($_POST['game'] ?? 'BGMI'),
            'map'                => trim($_POST['map'] ?? 'Erangle'),
            'mode'               => trim($_POST['mode'] ?? 'Squad'),
            'match_type'         => trim($_POST['match_type'] ?? 'Battle Royale'),
            'entry_fee'          => max(0, (float) ($_POST['entry_fee'] ?? 0)),
            'prize_pool'         => max(0, (float) ($_POST['prize_pool'] ?? 0)),
            'per_kill'           => max(0, (float) ($_POST['per_kill'] ?? 0)),
            'total_slots'        => max(1, (int) ($_POST['total_slots'] ?? 100)),
            'max_slots_per_user' => min(4, max(1, (int) ($_POST['max_slots_per_user'] ?? 4))),
            'match_time'         => trim($_POST['match_time'] ?? ''),
            'rules'              => trim($_POST['rules'] ?? ''),
            'status'             => in_array($_POST['status'] ?? '', ['upcoming', 'completed', 'cancelled'], true) ? $_POST['status'] : 'upcoming',
        ];

        if ($data['title'] === '') {
            flash('error', 'Tournament title is required.');
            redirect('tournament.php' . ($id ? '?edit=' . $id : ''));
        }
        if ($data['match_time'] === '' || strtotime($data['match_time']) === false) {
            flash('error', 'Enter a valid match date and time (IST).');
            redirect('tournament.php' . ($id ? '?edit=' . $id : ''));
        }
        // Normalise to the canonical DATETIME shape.
        $data['match_time'] = date('Y-m-d H:i:s', strtotime($data['match_time']));

        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE tournaments SET title=?, game=?, map=?, mode=?, match_type=?, entry_fee=?, prize_pool=?,
                 per_kill=?, total_slots=?, max_slots_per_user=?, match_time=?, rules=?, status=? WHERE id=?'
            );
            $stmt->execute([
                $data['title'], $data['game'], $data['map'], $data['mode'], $data['match_type'],
                $data['entry_fee'], $data['prize_pool'], $data['per_kill'], $data['total_slots'],
                $data['max_slots_per_user'], $data['match_time'], $data['rules'], $data['status'], $id,
            ]);
            flash('success', 'Tournament updated.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO tournaments (title, game, map, mode, match_type, entry_fee, prize_pool, per_kill,
                 total_slots, max_slots_per_user, match_time, rules, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['title'], $data['game'], $data['map'], $data['mode'], $data['match_type'],
                $data['entry_fee'], $data['prize_pool'], $data['per_kill'], $data['total_slots'],
                $data['max_slots_per_user'], $data['match_time'], $data['rules'], $data['status'],
            ]);
            flash('success', 'Tournament created.');
        }
        redirect('tournament.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM tournaments WHERE id = ?')->execute([$id]);
        flash('success', 'Tournament deleted.');
        redirect('tournament.php');
    }
}

if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM tournaments WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch();
}

$list = $pdo->query(
    "SELECT t.*, (SELECT COUNT(*) FROM participants p WHERE p.tournament_id = t.id) AS entries
     FROM tournaments t ORDER BY t.match_time DESC LIMIT 100"
)->fetchAll();

$pageActions = $editing
    ? '<a href="tournament.php" class="rounded-lg border border-white/10 px-3 py-2 text-sm text-slate-300 hover:text-white">Cancel Edit</a>'
    : '';

require __DIR__ . '/partials/header.php';
?>

<div class="grid gap-5 xl:grid-cols-3">
    <!-- Form -->
    <section class="xl:col-span-1">
        <div class="rounded-2xl border border-white/10 bg-[#13151f] p-5">
            <h2 class="mb-4 font-semibold text-white"><i class="fa-solid <?= $editing ? 'fa-pen' : 'fa-plus' ?> text-[#00f2fe]"></i> <?= $editing ? 'Edit Tournament' : 'Create Tournament' ?></h2>
            <form method="post" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editing ? (int) $editing['id'] : 0 ?>">

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Title</label>
                    <input name="title" required value="<?= e($editing['title'] ?? '') ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" placeholder="Sunday Squad Showdown">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-400">Game</label>
                        <input name="game" value="<?= e($editing['game'] ?? 'BGMI') ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-400">Map</label>
                        <input name="map" value="<?= e($editing['map'] ?? 'Erangle') ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-400">Mode</label>
                        <select name="mode" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                            <?php foreach (['Solo', 'Duo', 'Squad'] as $m): ?>
                                <option <?= ($editing['mode'] ?? 'Squad') === $m ? 'selected' : '' ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-400">Type</label>
                        <input name="match_type" value="<?= e($editing['match_type'] ?? 'Battle Royale') ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-400">Entry ₹</label>
                        <input name="entry_fee" type="number" step="1" min="0" value="<?= e((string) ($editing['entry_fee'] ?? 0)) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-400">Prize ₹</label>
                        <input name="prize_pool" type="number" step="1" min="0" value="<?= e((string) ($editing['prize_pool'] ?? 0)) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-400">Per Kill ₹</label>
                        <input name="per_kill" type="number" step="1" min="0" value="<?= e((string) ($editing['per_kill'] ?? 0)) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-400">Total Slots</label>
                        <input name="total_slots" type="number" min="1" value="<?= e((string) ($editing['total_slots'] ?? 100)) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-400">Max / User</label>
                        <input name="max_slots_per_user" type="number" min="1" max="4" value="<?= e((string) ($editing['max_slots_per_user'] ?? 4)) ?>" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Match Time (IST)</label>
                    <input name="match_time" type="datetime-local" required
                           value="<?= e($editing ? date('Y-m-d\TH:i', strtotime($editing['match_time'])) : '') ?>"
                           class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                    <p class="mt-1 text-[11px] text-slate-500">Stored and displayed as Asia/Kolkata (UTC+5:30).</p>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Status</label>
                    <select name="status" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]">
                        <?php foreach (['upcoming' => 'Upcoming', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($editing['status'] ?? 'upcoming') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-400">Rules</label>
                    <textarea name="rules" rows="4" class="w-full rounded-xl border border-white/10 bg-black/30 px-3 py-2.5 text-sm outline-none focus:border-[#00f2fe]" placeholder="One rule per line"><?= e($editing['rules'] ?? '') ?></textarea>
                </div>

                <button class="w-full rounded-xl accent-gradient py-3 font-bold text-[#0d0f17]"><?= $editing ? 'Update Tournament' : 'Create Tournament' ?></button>
            </form>
        </div>
    </section>

    <!-- List -->
    <section class="xl:col-span-2">
        <div class="overflow-hidden rounded-2xl border border-white/10 bg-[#13151f]">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-black/40 text-left text-xs uppercase text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Tournament</th>
                            <th class="px-4 py-3">Time (IST)</th>
                            <th class="px-4 py-3">Entry</th>
                            <th class="px-4 py-3">Slots</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php if (!$list): ?>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No tournaments yet. Create one on the left.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($list as $t): ?>
                            <tr class="hover:bg-white/5">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-white"><?= e($t['title']) ?></p>
                                    <p class="text-xs text-slate-500"><?= e($t['map']) ?> &middot; <?= e($t['mode']) ?></p>
                                </td>
                                <td class="px-4 py-3 text-slate-400"><?= e(format_match_time($t['match_time'])) ?></td>
                                <td class="px-4 py-3 text-[#00f2fe]"><?= (float) $t['entry_fee'] > 0 ? money($t['entry_fee']) : 'FREE' ?></td>
                                <td class="px-4 py-3 text-slate-300"><?= (int) $t['entries'] ?>/<?= (int) $t['total_slots'] ?></td>
                                <td class="px-4 py-3"><?= status_badge(tournament_status($t)) ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="manage_tournament.php?id=<?= (int) $t['id'] ?>" class="rounded-lg bg-white/5 px-2.5 py-1.5 text-xs text-slate-300 hover:text-[#00f2fe]" title="Manage"><i class="fa-solid fa-sliders"></i></a>
                                        <a href="tournament.php?edit=<?= (int) $t['id'] ?>" class="rounded-lg bg-white/5 px-2.5 py-1.5 text-xs text-slate-300 hover:text-[#00f2fe]" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                        <form method="post" data-confirm="Delete this tournament and all its entries?" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                            <button class="rounded-lg bg-rose-500/10 px-2.5 py-1.5 text-xs text-rose-300 hover:bg-rose-500/20" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </form>
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
