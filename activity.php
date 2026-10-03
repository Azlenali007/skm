<?php
/**
 * Sikkim Gaming Platform - User Activity & Game History
 * Real MySQL Bet Records (Strict Normal Document Flow: NO sticky, NO fixed)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin();
$pdo = getDB();

$filter = trim($_GET['status'] ?? 'all');

// Fetch user bets with round details
$sql = "
    SELECT b.*, r.round_number, r.result_number, r.result_color, r.result_size, r.completed_at, r.game_slug
    FROM `game_bets` b
    JOIN `game_rounds` r ON b.round_id = r.id
    WHERE b.user_id = :uid
";
$params = [':uid' => $user['id']];

if (in_array($filter, ['won', 'lost', 'pending'], true)) {
    $sql .= " AND b.status = :status";
    $params[':status'] = $filter;
}

$sql .= " ORDER BY b.id DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bets = $stmt->fetchAll();

// Calculate Summary Metrics
$summaryStmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_bets_count,
        COALESCE(SUM(amount), 0.00) as total_bet_amount,
        COALESCE(SUM(win_amount), 0.00) as total_win_amount,
        SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as wins_count
    FROM `game_bets`
    WHERE `user_id` = :uid
");
$summaryStmt->execute([':uid' => $user['id']]);
$metrics = $summaryStmt->fetch();

$pageTitle = "My Game Activity";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="w-full mb-6">
    <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
        <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
        Player Activity & Betting Log
    </h1>
    <p class="text-xs text-slate-500 mt-1">Certified records of all rounds played and payouts awarded</p>
</div>

<!-- Metrics Cards (Strict Normal Flow) -->
<div class="w-full grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    <div class="card-premium p-4">
        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Total Rounds Played</span>
        <div class="text-xl sm:text-2xl font-black text-slate-900 mt-1"><?= (int)$metrics['total_bets_count'] ?></div>
        <span class="text-[10px] text-slate-400">Total predictions</span>
    </div>

    <div class="card-premium p-4">
        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Winning Rounds</span>
        <div class="text-xl sm:text-2xl font-black text-emerald-600 mt-1"><?= (int)$metrics['wins_count'] ?></div>
        <span class="text-[10px] text-emerald-600 font-semibold"><?= $metrics['total_bets_count'] > 0 ? round(($metrics['wins_count'] / $metrics['total_bets_count']) * 100, 1) . '% win rate' : '0%' ?></span>
    </div>

    <div class="card-premium p-4">
        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Total Staked</span>
        <div class="text-xl sm:text-2xl font-black text-slate-800 mt-1"><?= formatMoney((float)$metrics['total_bet_amount']) ?></div>
        <span class="text-[10px] text-slate-400">Cumulative stake</span>
    </div>

    <div class="card-premium p-4 bg-gradient-to-br from-emerald-600 to-teal-700 text-white">
        <span class="text-[10px] uppercase font-bold text-emerald-100 tracking-wider">Total Winnings</span>
        <div class="text-xl sm:text-2xl font-black mt-1"><?= formatMoney((float)$metrics['total_win_amount']) ?></div>
        <span class="text-[10px] text-emerald-100/80">Paid out to wallet</span>
    </div>
</div>

<!-- Bet History Table -->
<div class="w-full card-premium p-5 sm:p-6 mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <div>
            <h3 class="font-black text-slate-900 text-base sm:text-lg flex items-center gap-2">
                <span class="w-1.5 h-5 bg-blue-600 rounded-full inline-block"></span>
                Betting Records
            </h3>
            <p class="text-xs text-slate-500">Real-time settlement status from MySQL database</p>
        </div>

        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-1">
            <a href="/activity.php?status=all" class="px-3 py-1 rounded-lg text-xs font-bold transition <?= $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600' ?>">All</a>
            <a href="/activity.php?status=won" class="px-3 py-1 rounded-lg text-xs font-bold transition <?= $filter === 'won' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600' ?>">Won</a>
            <a href="/activity.php?status=lost" class="px-3 py-1 rounded-lg text-xs font-bold transition <?= $filter === 'lost' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600' ?>">Lost</a>
            <a href="/activity.php?status=pending" class="px-3 py-1 rounded-lg text-xs font-bold transition <?= $filter === 'pending' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600' ?>">Pending</a>
        </div>
    </div>

    <?php if (!empty($bets)): ?>
        <div class="overflow-x-auto no-scrollbar">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 uppercase font-bold">
                        <th class="py-2.5 px-3">Period</th>
                        <th class="py-2.5 px-3">Selected Choice</th>
                        <th class="py-2.5 px-3 text-right">Stake</th>
                        <th class="py-2.5 px-3 text-center">Outcome</th>
                        <th class="py-2.5 px-3 text-center">Status</th>
                        <th class="py-2.5 px-3 text-right">Payout</th>
                        <th class="py-2.5 px-3 text-right">Date/Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($bets as $b): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3 px-3 font-mono font-bold text-slate-800">
                                <?= e($b['round_number']) ?>
                            </td>
                            <td class="py-3 px-3">
                                <span class="uppercase font-black text-xs text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-100">
                                    <?= e($b['bet_choice']) ?>
                                </span>
                                <span class="text-[10px] text-slate-400 ml-1"><?= number_format((float)$b['multiplier'], 1) ?>x</span>
                            </td>
                            <td class="py-3 px-3 text-right font-black text-slate-800">
                                <?= formatMoney((float)$b['amount']) ?>
                            </td>
                            <td class="py-3 px-3 text-center font-mono">
                                <?php if ($b['status'] === 'pending'): ?>
                                    <span class="text-amber-500 font-bold">Running...</span>
                                <?php else: ?>
                                    <span class="font-black text-slate-900"><?= $b['result_number'] ?></span>
                                    <span class="text-[10px] capitalize text-slate-500">(<?= e($b['result_color']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <span class="text-[10px] uppercase font-black px-2 py-0.5 rounded-full <?= $b['status'] === 'won' ? 'bg-emerald-100 text-emerald-800' : ($b['status'] === 'lost' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') ?>">
                                    <?= e($b['status']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-3 text-right font-black <?= $b['status'] === 'won' ? 'text-emerald-600' : 'text-slate-400' ?>">
                                <?= $b['status'] === 'won' ? '+' . formatMoney((float)$b['win_amount']) : '₹0.00' ?>
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-[10px] text-slate-400">
                                <?= date('d M, h:i A', strtotime($b['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="py-12 text-center text-slate-400 text-xs">
            <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            No bets found in your history matching this filter.
            <div class="mt-3">
                <a href="/game-play.php?slug=win-go-1m" class="px-4 py-2 bg-blue-600 text-white rounded-xl font-bold text-xs">Place First Bet</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- User Bottom Nav -->
<?php require_once __DIR__ . '/includes/user_nav.php'; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
