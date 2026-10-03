<?php
/**
 * Sikkim Gaming Platform - Admin Control Center
 * Real MySQL Statistics & System Overview
 * STRICT NORMAL FLOW: NO sticky, NO fixed!
 */
require_once __DIR__ . '/includes/admin_header.php';
$pdo = getDB();

// 1. Total Registered Users
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'user'")->fetchColumn();
$activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'user' AND `status` = 'active'")->fetchColumn();

// 2. Games & Categories
$totalGames = (int)$pdo->query("SELECT COUNT(*) FROM `games`")->fetchColumn();
$activeGames = (int)$pdo->query("SELECT COUNT(*) FROM `games` WHERE `status` = 'active'")->fetchColumn();
$totalCategories = (int)$pdo->query("SELECT COUNT(*) FROM `categories`")->fetchColumn();

// 3. Rounds Metrics
$totalRounds = (int)$pdo->query("SELECT COUNT(*) FROM `game_rounds`")->fetchColumn();
$activeRound = $pdo->query("SELECT round_number, start_time, end_time FROM `game_rounds` WHERE `status` = 'active' ORDER BY `round_number` DESC LIMIT 1")->fetch();

// 4. Financials from Wallets & Transactions
$totalDepositVolume = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0.00) FROM `transactions` WHERE `type` = 'deposit' AND `status` = 'completed'")->fetchColumn();
$totalWithdrawVolume = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0.00) FROM `transactions` WHERE `type` = 'withdrawal' AND `status` = 'completed'")->fetchColumn();
$totalBetsVolume = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0.00) FROM `transactions` WHERE `type` = 'bet'")->fetchColumn();
$totalWinsVolume = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0.00) FROM `transactions` WHERE `type` = 'win'")->fetchColumn();

// 5. Support Tickets
$openTickets = (int)$pdo->query("SELECT COUNT(*) FROM `support_tickets` WHERE `status` = 'open'")->fetchColumn();

// 6. Recent Registered Users (5)
$recentUsers = $pdo->query("
    SELECT u.id, u.username, u.phone, u.status, u.created_at, COALESCE(w.balance, 0.00) as balance
    FROM `users` u
    LEFT JOIN `wallets` w ON u.id = w.user_id
    WHERE u.role = 'user'
    ORDER BY u.id DESC
    LIMIT 5
")->fetchAll();

// 7. Recent Transactions (5)
$recentTransactions = $pdo->query("
    SELECT t.*, u.username
    FROM `transactions` t
    JOIN `users` u ON t.user_id = u.id
    ORDER BY t.id DESC
    LIMIT 6
")->fetchAll();

$pageTitle = "System Overview";
?>

<!-- Admin Greeting & System Quick Actions -->
<div class="w-full mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
            <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
            Management Overview
        </h1>
        <p class="text-xs text-slate-500 mt-1">Real-time statistics sourced directly from the active MySQL database</p>
    </div>

    <div class="flex items-center gap-2">
        <a href="/cron/round_processor.php" target="_blank" class="px-3.5 py-1.5 bg-white border border-slate-200 text-slate-700 hover:border-blue-400 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
            <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Trigger Round Cron
        </a>
        <a href="/admin/games.php?action=create" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
            + New Game
        </a>
    </div>
</div>

<!-- Key Stat Metric Cards Grid -->
<div class="w-full grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-[10px] font-bold uppercase tracking-wider">Registered Players</span>
            <span class="p-1 rounded bg-blue-50 text-blue-600">● Live</span>
        </div>
        <div class="text-2xl font-black text-slate-900"><?= $totalUsers ?></div>
        <div class="text-[11px] text-slate-400 mt-1"><?= $activeUsers ?> active accounts</div>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-[10px] font-bold uppercase tracking-wider">Deposit Volume</span>
            <span class="p-1 rounded bg-emerald-50 text-emerald-600 font-bold text-[10px]">Real</span>
        </div>
        <div class="text-2xl font-black text-emerald-600"><?= formatMoney($totalDepositVolume) ?></div>
        <div class="text-[11px] text-slate-400 mt-1"><?= formatMoney($totalWithdrawVolume) ?> withdrawn</div>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-[10px] font-bold uppercase tracking-wider">Total Staked Bets</span>
            <span class="p-1 rounded bg-purple-50 text-purple-600 font-bold text-[10px]">Turnover</span>
        </div>
        <div class="text-2xl font-black text-purple-700"><?= formatMoney($totalBetsVolume) ?></div>
        <div class="text-[11px] text-slate-400 mt-1"><?= formatMoney($totalWinsVolume) ?> payout</div>
    </div>

    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between text-slate-400 mb-2">
            <span class="text-[10px] font-bold uppercase tracking-wider">Active Round Period</span>
            <span class="p-1 rounded bg-amber-50 text-amber-600 font-bold text-[10px]">RNG</span>
        </div>
        <div class="text-2xl font-black font-mono text-slate-900">
            <?= $activeRound ? '#' . $activeRound['round_number'] : 'None' ?>
        </div>
        <div class="text-[11px] text-slate-400 mt-1"><?= $totalRounds ?> sequential rounds</div>
    </div>
</div>

<!-- Secondary Stats Grid -->
<div class="w-full grid grid-cols-2 md:grid-cols-4 gap-4 mb-8 text-xs font-semibold">
    <div class="bg-white p-4 rounded-xl border border-slate-200 flex items-center justify-between">
        <span class="text-slate-500">Configured Games:</span>
        <span class="font-bold text-slate-900"><?= $totalGames ?> (<?= $activeGames ?> active)</span>
    </div>
    <div class="bg-white p-4 rounded-xl border border-slate-200 flex items-center justify-between">
        <span class="text-slate-500">Categories:</span>
        <span class="font-bold text-slate-900"><?= $totalCategories ?> configured</span>
    </div>
    <div class="bg-white p-4 rounded-xl border border-slate-200 flex items-center justify-between">
        <span class="text-slate-500">Open Tickets:</span>
        <span class="font-bold <?= $openTickets > 0 ? 'text-rose-600' : 'text-slate-900' ?>"><?= $openTickets ?> pending</span>
    </div>
    <div class="bg-white p-4 rounded-xl border border-slate-200 flex items-center justify-between">
        <span class="text-slate-500">Server Platform:</span>
        <span class="font-bold text-blue-600">PHP 8.2 + MariaDB</span>
    </div>
</div>

<!-- Tables Grid: Recent Users & Recent Transactions -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Recent Users -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-black text-slate-900 text-sm">Recent Registered Players</h3>
            <a href="/admin/users.php" class="text-xs text-blue-600 font-bold hover:underline">All Users &rarr;</a>
        </div>
        <?php if (!empty($recentUsers)): ?>
            <div class="divide-y divide-slate-100 text-xs">
                <?php foreach ($recentUsers as $u): ?>
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <div class="font-bold text-slate-900"><?= e($u['username']) ?></div>
                            <span class="text-[10px] text-slate-400 font-mono">+91 <?= e($u['phone']) ?></span>
                        </div>
                        <div class="text-right">
                            <div class="font-black text-slate-800"><?= formatMoney((float)$u['balance']) ?></div>
                            <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded <?= $u['status'] === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' ?>"><?= e($u['status']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-xs text-slate-400 py-4 text-center">No player records in database.</p>
        <?php endif; ?>
    </div>

    <!-- Recent Ledger Transactions -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-black text-slate-900 text-sm">Recent Financial Transactions</h3>
            <a href="/admin/transactions.php" class="text-xs text-blue-600 font-bold hover:underline">All Ledger &rarr;</a>
        </div>
        <?php if (!empty($recentTransactions)): ?>
            <div class="divide-y divide-slate-100 text-xs">
                <?php foreach ($recentTransactions as $tx):
                    $isAdd = in_array($tx['type'], ['deposit', 'win', 'bonus'], true);
                ?>
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <div class="font-bold text-slate-800">
                                <span class="capitalize"><?= e($tx['type']) ?></span> • <span class="text-blue-600"><?= e($tx['username']) ?></span>
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono"><?= e($tx['reference_id'] ?? $tx['notes']) ?></div>
                        </div>
                        <div class="text-right">
                            <div class="font-black <?= $isAdd ? 'text-emerald-600' : 'text-slate-800' ?>">
                                <?= $isAdd ? '+' : '-' ?><?= formatMoney((float)$tx['amount']) ?>
                            </div>
                            <span class="text-[9px] text-slate-400 font-mono"><?= date('d M, h:i A', strtotime($tx['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-xs text-slate-400 py-4 text-center">No transactions recorded yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
