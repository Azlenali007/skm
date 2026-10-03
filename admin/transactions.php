<?php
/**
 * Sikkim Gaming Platform - Admin Financial Ledger
 * Real MySQL Transactions Log & Review
 */
require_once __DIR__ . '/includes/admin_header.php';
$pdo = getDB();

$filter = trim($_GET['type'] ?? 'all');
$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT t.*, u.username, u.phone
    FROM `transactions` t
    JOIN `users` u ON t.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($filter !== 'all') {
    $sql .= " AND t.type = :type";
    $params[':type'] = $filter;
}

if (!empty($search)) {
    $sql .= " AND (u.username LIKE :q OR u.phone LIKE :q OR t.reference_id LIKE :q OR t.notes LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}

$sql .= " ORDER BY t.id DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$pageTitle = "Financial Ledger";
?>

<div class="w-full mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
            <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
            Financial Ledger & Transactions
        </h1>
        <p class="text-xs text-slate-500 mt-1">Audit all player deposits, withdrawals, bonus credits, and game rounds</p>
    </div>

    <!-- Search & Filter -->
    <form method="GET" action="/admin/transactions.php" class="flex items-center gap-2 max-w-sm w-full">
        <?php if ($filter !== 'all'): ?>
            <input type="hidden" name="type" value="<?= e($filter) ?>">
        <?php endif; ?>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search user, ref, phone..." class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold shrink-0">Search</button>
    </form>
</div>

<!-- Type Filter Tabs -->
<div class="flex items-center gap-2 mb-6 overflow-x-auto no-scrollbar py-1">
    <a href="/admin/transactions.php?type=all" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-700' ?>">All Types</a>
    <a href="/admin/transactions.php?type=deposit" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $filter === 'deposit' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-700' ?>">Deposits</a>
    <a href="/admin/transactions.php?type=withdrawal" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $filter === 'withdrawal' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-700' ?>">Withdrawals</a>
    <a href="/admin/transactions.php?type=win" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $filter === 'win' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-700' ?>">Wins</a>
    <a href="/admin/transactions.php?type=bet" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $filter === 'bet' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-700' ?>">Bets</a>
    <a href="/admin/transactions.php?type=bonus" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $filter === 'bonus' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-700' ?>">Bonuses</a>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <?php if (!empty($transactions)): ?>
        <div class="overflow-x-auto no-scrollbar">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 uppercase font-bold bg-slate-50">
                        <th class="py-3 px-4">Tx ID / Type</th>
                        <th class="py-3 px-4">Player</th>
                        <th class="py-3 px-4 text-right">Amount</th>
                        <th class="py-3 px-4 text-right">Before &rarr; After</th>
                        <th class="py-3 px-4">Reference / Details</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($transactions as $t):
                        $isAdd = in_array($t['type'], ['deposit', 'win', 'bonus'], true);
                    ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3 px-4">
                                <span class="font-black uppercase text-[10px] px-2 py-0.5 rounded <?= $isAdd ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                    <?= e($t['type']) ?>
                                </span>
                                <div class="font-mono text-[10px] text-slate-400 mt-1">#<?= $t['id'] ?></div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900"><?= e($t['username']) ?></div>
                                <span class="font-mono text-[10px] text-slate-400">+91 <?= e($t['phone']) ?></span>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-sm <?= $isAdd ? 'text-emerald-600' : 'text-slate-800' ?>">
                                <?= $isAdd ? '+' : '-' ?><?= formatMoney((float)$t['amount']) ?>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-[11px] text-slate-500">
                                <?= formatMoney((float)$t['balance_before']) ?> &rarr; <strong class="text-slate-800"><?= formatMoney((float)$t['balance_after']) ?></strong>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-mono font-bold text-slate-700 text-[11px]"><?= e($t['reference_id'] ?? '-') ?></div>
                                <span class="text-[10px] text-slate-400"><?= e($t['notes'] ?? $t['payment_method'] ?? '') ?></span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="text-[10px] uppercase font-black px-2 py-0.5 rounded-full <?= $t['status'] === 'completed' || $t['status'] === 'approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
                                    <?= e($t['status']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-[10px] text-slate-400">
                                <?= date('d M Y, h:i A', strtotime($t['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="p-8 text-center text-xs text-slate-400">
            No transaction records match the specified filters.
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
