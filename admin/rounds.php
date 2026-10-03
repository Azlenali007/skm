<?php
/**
 * Sikkim Gaming Platform - Admin Game Rounds & Audit
 * Provably Fair Sequential Rounds Inspector & Cron Trigger
 */
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/round_engine.php';

$pdo = getDB();

// Manual Trigger Round Advance
if (isset($_POST['advance_round'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $slug = trim($_POST['game_slug'] ?? 'win-go-1m');
        $active = $pdo->prepare("SELECT id FROM `game_rounds` WHERE `game_slug` = :slug AND `status` = 'active' LIMIT 1");
        $active->execute([':slug' => $slug]);
        $r = $active->fetch();
        if ($r) {
            finalizeRoundAndCreateNext($pdo, (int)$r['id'], $slug);
            setFlash('success', "Active round successfully advanced and next sequential round generated!");
        } else {
            processAndGetActiveRound($pdo, $slug);
            setFlash('info', "New active round initialized.");
        }
        header("Location: /admin/rounds.php");
        exit;
    }
}

// Fetch Active Round
$activeRound = $pdo->query("SELECT * FROM `game_rounds` WHERE `status` = 'active' ORDER BY `round_number` DESC LIMIT 1")->fetch();

// Fetch Completed Rounds History
$rounds = $pdo->query("
    SELECT r.*,
           (SELECT COUNT(*) FROM `game_bets` WHERE `round_id` = r.id) as bets_count
    FROM `game_rounds` r
    ORDER BY r.round_number DESC
    LIMIT 50
")->fetchAll();

$pageTitle = "Rounds & Fairness Audit";
?>

<div class="w-full mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
            <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
            Sequential Rounds & Fairness Audit
        </h1>
        <p class="text-xs text-slate-500 mt-1">Authoritative sequential database rounds, payout metrics, and RNG outcomes</p>
    </div>

    <!-- Manual Advance Trigger -->
    <form method="POST" action="/admin/rounds.php">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="game_slug" value="win-go-1m">
        <button type="submit" name="advance_round" value="1" class="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-700 text-white rounded-xl text-xs font-bold shadow transition active:scale-95 flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Trigger & Finalize Active Round
        </button>
    </form>
</div>

<!-- Active Round Status Card -->
<?php if ($activeRound): ?>
    <div class="w-full card-premium p-5 mb-6 bg-slate-900 text-white">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="text-[10px] uppercase font-black tracking-widest text-emerald-400 bg-emerald-950 px-2 py-0.5 rounded border border-emerald-800">
                    ● Active Live Period
                </span>
                <div class="text-3xl font-black font-mono text-white mt-2">
                    #<?= $activeRound['round_number'] ?>
                </div>
                <div class="text-xs text-slate-400 mt-1 font-mono">
                    Starts: <?= $activeRound['start_time'] ?> &bull; Ends: <?= $activeRound['end_time'] ?>
                </div>
            </div>
            <div class="text-right">
                <span class="text-xs text-slate-400">Total Bets In Period:</span>
                <div class="text-2xl font-black text-amber-400 font-mono"><?= formatMoney((float)$activeRound['total_bets_amount']) ?></div>
                <span class="text-[11px] text-slate-400">RNG outcome is unexposed until expiration</span>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Rounds History Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <div class="p-4 bg-slate-50 border-b border-slate-200 font-bold text-xs text-slate-600 flex justify-between items-center">
        <span>Rounds Ledger (Latest 50 Sequential Periods)</span>
    </div>
    <?php if (!empty($rounds)): ?>
        <div class="overflow-x-auto no-scrollbar">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 uppercase font-bold bg-slate-50">
                        <th class="py-3 px-4">Period</th>
                        <th class="py-3 px-4">Game</th>
                        <th class="py-3 px-4 text-center">Result Number</th>
                        <th class="py-3 px-4 text-center">Color / Size</th>
                        <th class="py-3 px-4 text-center">Bets Count</th>
                        <th class="py-3 px-4 text-right">Staked</th>
                        <th class="py-3 px-4 text-right">Payout</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Completed Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rounds as $r): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                #<?= $r['round_number'] ?>
                            </td>
                            <td class="py-3 px-4 font-semibold text-slate-700">
                                <?= e($r['game_slug']) ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <?php if ($r['status'] === 'completed'): ?>
                                    <span class="inline-block w-6 h-6 rounded-full text-white font-black text-xs leading-6 <?= $r['result_number'] % 2 === 0 ? 'bg-rose-500' : 'bg-emerald-500' ?>">
                                        <?= $r['result_number'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 font-mono">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <?php if ($r['status'] === 'completed'): ?>
                                    <span class="font-bold capitalize text-slate-800"><?= e($r['result_color']) ?></span>
                                    <span class="text-[10px] uppercase font-bold text-slate-400">(<?= e($r['result_size']) ?>)</span>
                                <?php else: ?>
                                    <span class="text-amber-600 font-semibold">Running</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-center font-bold text-slate-700">
                                <?= (int)$r['bets_count'] ?>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-slate-800">
                                <?= formatMoney((float)$r['total_bets_amount']) ?>
                            </td>
                            <td class="py-3 px-4 text-right font-black <?= $r['total_payout_amount'] > 0 ? 'text-emerald-600' : 'text-slate-500' ?>">
                                <?= formatMoney((float)$r['total_payout_amount']) ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="text-[10px] uppercase font-black px-2 py-0.5 rounded-full <?= $r['status'] === 'completed' ? 'bg-slate-100 text-slate-700' : 'bg-emerald-100 text-emerald-800' ?>">
                                    <?= e($r['status']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-[10px] text-slate-400">
                                <?= $r['completed_at'] ? date('d M, h:i:s A', strtotime($r['completed_at'])) : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="p-8 text-center text-xs text-slate-400">
            No rounds found in database.
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
