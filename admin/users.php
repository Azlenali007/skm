<?php
/**
 * Sikkim Gaming Platform - Admin User Management
 * Real MySQL Player CRUD & Balance Adjustments
 */
require_once __DIR__ . '/includes/admin_header.php';
$pdo = getDB();

$search = trim($_GET['q'] ?? '');

// Handle Status Toggle
if (isset($_POST['toggle_status'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $uid = (int)$_POST['user_id'];
        $newStatus = $_POST['new_status'] === 'active' ? 'active' : 'suspended';
        $up = $pdo->prepare("UPDATE `users` SET `status` = :st WHERE `id` = :id AND `role` != 'admin'");
        $up->execute([':st' => $newStatus, ':id' => $uid]);
        setFlash('success', "Player status updated to {$newStatus}.");
        header("Location: /admin/users.php");
        exit;
    }
}

// Handle Manual Balance Adjustment
if (isset($_POST['adjust_balance'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $uid = (int)$_POST['user_id'];
        $adjAmount = (float)$_POST['adjust_amount'];
        $adjType = $_POST['adj_type'] === 'credit' ? 'credit' : 'debit';
        $reason = trim($_POST['reason'] ?? 'Admin manual adjustment');

        if ($adjAmount > 0) {
            try {
                $pdo->beginTransaction();
                $wStmt = $pdo->prepare("SELECT balance FROM `wallets` WHERE `user_id` = :uid FOR UPDATE");
                $wStmt->execute([':uid' => $uid]);
                $currentBal = (float)$wStmt->fetchColumn();

                $newBal = $adjType === 'credit' ? ($currentBal + $adjAmount) : max(0, $currentBal - $adjAmount);
                $finalAdj = $adjType === 'credit' ? $adjAmount : ($currentBal - $newBal);

                $pdo->prepare("UPDATE `wallets` SET `balance` = :nb WHERE `user_id` = :uid")->execute([':nb' => $newBal, ':uid' => $uid]);

                $pdo->prepare("
                    INSERT INTO `transactions` (`user_id`, `type`, `amount`, `balance_before`, `balance_after`, `reference_id`, `status`, `notes`)
                    VALUES (:uid, :type, :amt, :before, :after, :ref, 'completed', :notes)
                ")->execute([
                    ':uid' => $uid,
                    ':type' => $adjType === 'credit' ? 'bonus' : 'withdrawal',
                    ':amt' => $finalAdj,
                    ':before' => $currentBal,
                    ':after' => $newBal,
                    ':ref' => 'MANUAL-' . time(),
                    ':notes' => $reason
                ]);

                $pdo->commit();
                setFlash('success', "User balance adjusted successfully. New balance: ₹" . number_format($newBal, 2));
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                setFlash('error', "Adjustment error: " . $e->getMessage());
            }
        }
        header("Location: /admin/users.php");
        exit;
    }
}

// Query Users
$sql = "
    SELECT u.*, COALESCE(w.balance, 0.00) as balance, COALESCE(w.total_deposited, 0.00) as total_deposited,
           (SELECT COUNT(*) FROM `game_bets` WHERE `user_id` = u.id) as total_bets
    FROM `users` u
    LEFT JOIN `wallets` w ON u.id = w.user_id
    WHERE u.role = 'user'
";
$params = [];
if (!empty($search)) {
    $sql .= " AND (u.username LIKE :q OR u.phone LIKE :q OR u.email LIKE :q)";
    $params[':q'] = '%' . $search . '%';
}
$sql .= " ORDER BY u.id DESC LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = "Player Management";
?>

<div class="w-full mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
            <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
            Registered Players Management
        </h1>
        <p class="text-xs text-slate-500 mt-1">Review accounts, adjust wallet credits, or suspend fraudulent profiles</p>
    </div>

    <form method="GET" action="/admin/users.php" class="relative max-w-xs w-full">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by phone, username..." class="w-full pl-9 pr-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-blue-500">
        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    </form>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <?php if (!empty($users)): ?>
        <div class="overflow-x-auto no-scrollbar">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 uppercase font-bold bg-slate-50">
                        <th class="py-3 px-4">User</th>
                        <th class="py-3 px-4">Contact</th>
                        <th class="py-3 px-4 text-right">Balance</th>
                        <th class="py-3 px-4 text-right">Total Deposited</th>
                        <th class="py-3 px-4 text-center">Bets Placed</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3 px-4">
                                <div class="font-black text-slate-900"><?= e($u['username']) ?></div>
                                <span class="font-mono text-[10px] text-slate-400">ID: SKM-<?= str_pad((string)$u['id'], 6, '0', STR_PAD_LEFT) ?></span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-mono font-bold text-slate-700">+91 <?= e($u['phone']) ?></div>
                                <span class="text-[10px] text-slate-400"><?= e($u['email'] ?? 'No email') ?></span>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-blue-600 text-sm">
                                <?= formatMoney((float)$u['balance']) ?>
                            </td>
                            <td class="py-3 px-4 text-right font-semibold text-slate-600">
                                <?= formatMoney((float)$u['total_deposited']) ?>
                            </td>
                            <td class="py-3 px-4 text-center font-bold text-slate-700">
                                <?= (int)$u['total_bets'] ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="text-[10px] uppercase font-black px-2 py-0.5 rounded-full <?= $u['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                    <?= e($u['status']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Toggle Status Button -->
                                    <form method="POST" action="/admin/users.php" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $u['status'] === 'active' ? 'suspended' : 'active' ?>">
                                        <button type="submit" name="toggle_status" value="1" class="px-2.5 py-1 rounded-lg text-[11px] font-bold border transition <?= $u['status'] === 'active' ? 'border-rose-200 text-rose-600 hover:bg-rose-50' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50' ?>">
                                            <?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                                        </button>
                                    </form>

                                    <!-- Quick Balance Modal/Trigger -->
                                    <button type="button" onclick="document.getElementById('adj-box-<?= $u['id'] ?>').classList.toggle('hidden')" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100">
                                        Adjust ₹
                                    </button>
                                </div>

                                <!-- Inline Balance Adjust Form (Normal Flow) -->
                                <div id="adj-box-<?= $u['id'] ?>" class="hidden mt-2 p-3 bg-slate-50 border border-slate-200 rounded-xl text-left">
                                    <form method="POST" action="/admin/users.php" class="space-y-2">
                                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="adjust_balance" value="1">

                                        <div class="flex items-center gap-2">
                                            <select name="adj_type" class="px-2 py-1 bg-white border border-slate-200 rounded text-xs font-bold">
                                                <option value="credit">+ Credit</option>
                                                <option value="debit">- Debit</option>
                                            </select>
                                            <input type="number" name="adjust_amount" placeholder="Amount (₹)" min="1" step="10" required class="w-24 px-2 py-1 bg-white border border-slate-200 rounded text-xs font-bold">
                                            <input type="text" name="reason" placeholder="Reason note" required class="flex-1 px-2 py-1 bg-white border border-slate-200 rounded text-xs">
                                            <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded text-xs font-bold hover:bg-blue-700">Submit</button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="p-8 text-center text-xs text-slate-400">
            No registered players match your search filter.
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
