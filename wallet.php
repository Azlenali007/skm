<?php
/**
 * Sikkim Gaming Platform - Wallet & Payments
 * Real MySQL Transactions, Atomic Balances & Ledger Records
 * STRICT NORMAL FLOW: NO sticky, NO fixed.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin();
$pdo = getDB();

$action = trim($_GET['action'] ?? 'deposit');
$filter = trim($_GET['filter'] ?? 'all');
$error = '';
$success = '';

// Process Deposit Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_deposit'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token validation failed. Please try again.";
    } else {
        $amount = (float)($_POST['amount'] ?? 0);
        $method = trim($_POST['payment_method'] ?? 'UPI');
        $utr = trim($_POST['utr_reference'] ?? '');

        if ($amount < 100) {
            $error = "Minimum deposit amount is ₹100.";
        } elseif ($amount > 100000) {
            $error = "Maximum single deposit limit is ₹1,00,000.";
        } elseif (empty($utr)) {
            $error = "Please provide the 12-digit UPI / UTR Transaction Reference Number.";
        } else {
            // Process deposit in MySQL transaction
            try {
                $pdo->beginTransaction();

                // Check duplicate UTR
                $checkUtr = $pdo->prepare("SELECT id FROM `transactions` WHERE `reference_id` = :utr LIMIT 1");
                $checkUtr->execute([':utr' => $utr]);
                if ($checkUtr->fetch()) {
                    $pdo->rollBack();
                    $error = "This UTR reference has already been submitted.";
                } else {
                    // Lock wallet
                    $wStmt = $pdo->prepare("SELECT balance FROM `wallets` WHERE `user_id` = :uid FOR UPDATE");
                    $wStmt->execute([':uid' => $user['id']]);
                    $currentBal = (float)$wStmt->fetchColumn();

                    $newBal = $currentBal + $amount;

                    // Update wallet balance and total deposited
                    $upW = $pdo->prepare("
                        UPDATE `wallets`
                        SET `balance` = :newbal, `total_deposited` = `total_deposited` + :amt
                        WHERE `user_id` = :uid
                    ");
                    $upW->execute([':newbal' => $newBal, ':amt' => $amount, ':uid' => $user['id']]);

                    // Add Ledger Transaction
                    $inTx = $pdo->prepare("
                        INSERT INTO `transactions` (`user_id`, `type`, `amount`, `balance_before`, `balance_after`, `reference_id`, `status`, `payment_method`, `notes`)
                        VALUES (:uid, 'deposit', :amt, :before, :after, :ref, 'completed', :method, 'Direct UPI Automated Instant Credit')
                    ");
                    $inTx->execute([
                        ':uid' => $user['id'],
                        ':amt' => $amount,
                        ':before' => $currentBal,
                        ':after' => $newBal,
                        ':ref' => $utr,
                        ':method' => $method
                    ]);

                    // Add Notification
                    $inNotif = $pdo->prepare("
                        INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`)
                        VALUES (:uid, 'Deposit Successful', :msg, 'payment')
                    ");
                    $inNotif->execute([
                        ':uid' => $user['id'],
                        ':msg' => 'Your deposit of ₹' . number_format($amount, 2) . ' via ' . $method . ' (Ref: ' . $utr . ') has been credited to your wallet.'
                    ]);

                    $pdo->commit();
                    $success = "Deposit of ₹" . number_format($amount, 2) . " processed successfully! Balance updated.";
                    // Refresh user data
                    $user = getCurrentUser();
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Deposit processing failed: " . $e->getMessage();
            }
        }
    }
}

// Process Withdrawal Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_withdraw'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token validation failed. Please try again.";
    } else {
        $amount = (float)($_POST['withdraw_amount'] ?? 0);
        $payoutMethod = trim($_POST['payout_method'] ?? 'UPI');
        $payoutAccount = trim($_POST['payout_account'] ?? '');

        if ($amount < 200) {
            $error = "Minimum withdrawal amount is ₹200.";
        } elseif ($amount > 50000) {
            $error = "Maximum withdrawal per transaction is ₹50,000.";
        } elseif (empty($payoutAccount)) {
            $error = "Please specify your UPI ID or Bank Account Details.";
        } else {
            // Process withdrawal atomically in MySQL
            try {
                $pdo->beginTransaction();

                // Lock wallet
                $wStmt = $pdo->prepare("SELECT balance FROM `wallets` WHERE `user_id` = :uid FOR UPDATE");
                $wStmt->execute([':uid' => $user['id']]);
                $currentBal = (float)$wStmt->fetchColumn();

                if ($currentBal < $amount) {
                    $pdo->rollBack();
                    $error = "Insufficient wallet balance. You only have ₹" . number_format($currentBal, 2) . " available.";
                } else {
                    $newBal = $currentBal - $amount;
                    $ref = 'WTH-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

                    // Update wallet balance and total withdrawn
                    $upW = $pdo->prepare("
                        UPDATE `wallets`
                        SET `balance` = :newbal, `total_withdrawn` = `total_withdrawn` + :amt
                        WHERE `user_id` = :uid
                    ");
                    $upW->execute([':newbal' => $newBal, ':amt' => $amount, ':uid' => $user['id']]);

                    // Add Ledger Transaction (Status: approved / completed)
                    $inTx = $pdo->prepare("
                        INSERT INTO `transactions` (`user_id`, `type`, `amount`, `balance_before`, `balance_after`, `reference_id`, `status`, `payment_method`, `notes`)
                        VALUES (:uid, 'withdrawal', :amt, :before, :after, :ref, 'completed', :method, :notes)
                    ");
                    $inTx->execute([
                        ':uid' => $user['id'],
                        ':amt' => $amount,
                        ':before' => $currentBal,
                        ':after' => $newBal,
                        ':ref' => $ref,
                        ':method' => $payoutMethod,
                        ':notes' => 'Payout sent to ' . $payoutAccount
                    ]);

                    // Add Notification
                    $inNotif = $pdo->prepare("
                        INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`)
                        VALUES (:uid, 'Withdrawal Processed', :msg, 'payment')
                    ");
                    $inNotif->execute([
                        ':uid' => $user['id'],
                        ':msg' => 'Your withdrawal of ₹' . number_format($amount, 2) . ' to ' . $payoutAccount . ' has been approved and disbursed. Ref: ' . $ref
                    ]);

                    $pdo->commit();
                    $success = "Withdrawal of ₹" . number_format($amount, 2) . " processed successfully! Funds sent to your account.";
                    $user = getCurrentUser();
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Withdrawal processing error: " . $e->getMessage();
            }
        }
    }
}

// Fetch User Transaction Statement with Filter
$txSql = "SELECT * FROM `transactions` WHERE `user_id` = :uid";
$txParams = [':uid' => $user['id']];

if ($filter !== 'all') {
    $txSql .= " AND `type` = :ftype";
    $txParams[':ftype'] = $filter;
}
$txSql .= " ORDER BY `id` DESC LIMIT 20";

$stmtTx = $pdo->prepare($txSql);
$stmtTx->execute($txParams);
$statement = $stmtTx->fetchAll();

$pageTitle = "My Wallet & Transactions";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header / Breadcrumb (Strict Normal Flow) -->
<div class="w-full mb-6">
    <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
        <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
        Player Wallet & Payouts
    </h1>
    <p class="text-xs text-slate-500 mt-1">Real-time certified balance and ledger statements</p>
</div>

<!-- Feedback Messages -->
<?php if (!empty($error)): ?>
    <div class="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span><?= e($error) ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="mb-5 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span><?= e($success) ?></span>
    </div>
<?php endif; ?>

<!-- Balance Stats Cards Grid (Strict Normal Flow) -->
<div class="w-full grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <div class="card-premium p-4 bg-gradient-to-br from-blue-700 to-indigo-800 text-white">
        <span class="text-[10px] uppercase font-bold text-blue-200 tracking-wider">Playable Balance</span>
        <div class="text-xl sm:text-2xl font-black mt-1"><?= formatMoney((float)$user['balance']) ?></div>
        <span class="text-[10px] text-blue-200/80">Available for bets & withdrawal</span>
    </div>

    <div class="card-premium p-4">
        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Signup Bonus</span>
        <div class="text-xl sm:text-2xl font-black text-amber-500 mt-1"><?= formatMoney((float)$user['bonus_balance']) ?></div>
        <span class="text-[10px] text-slate-400">Bonus rewarded</span>
    </div>

    <div class="card-premium p-4">
        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Total Deposited</span>
        <div class="text-xl sm:text-2xl font-black text-emerald-600 mt-1"><?= formatMoney((float)$user['total_deposited']) ?></div>
        <span class="text-[10px] text-slate-400">Lifetime deposits</span>
    </div>

    <div class="card-premium p-4">
        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Total Withdrawn</span>
        <div class="text-xl sm:text-2xl font-black text-slate-800 mt-1"><?= formatMoney((float)$user['total_withdrawn']) ?></div>
        <span class="text-[10px] text-slate-400">Lifetime payouts</span>
    </div>
</div>

<!-- Action Tabs: Deposit vs Withdraw (Strict Normal Flow) -->
<div class="w-full card-premium p-5 sm:p-6 mb-8">
    <div class="flex items-center gap-3 border-b border-slate-200 pb-4 mb-6">
        <a href="/wallet.php?action=deposit" class="px-5 py-2.5 rounded-xl font-black text-xs sm:text-sm transition <?= $action === 'deposit' ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
            Deposit Funds
        </a>
        <a href="/wallet.php?action=withdraw" class="px-5 py-2.5 rounded-xl font-black text-xs sm:text-sm transition <?= $action === 'withdraw' ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
            Withdraw Cash
        </a>
    </div>

    <?php if ($action === 'deposit'): ?>
        <!-- Deposit Form -->
        <form method="POST" action="/wallet.php?action=deposit" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="do_deposit" value="1">

            <!-- Official UPI Gateway Display -->
            <div class="p-4 bg-blue-50/70 border border-blue-200 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-white border border-blue-200 flex items-center justify-center font-black text-blue-600 text-lg shadow-sm">
                        UPI
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600">Official Merchant UPI ID</span>
                        <div class="text-sm font-mono font-black text-slate-900 flex items-center gap-2">
                            <span>sikkim.payments@axisbank</span>
                            <button type="button" onclick="copyToClipboard('sikkim.payments@axisbank', this)" class="text-[10px] bg-white border border-blue-300 text-blue-700 px-2 py-0.5 rounded font-bold hover:bg-blue-50">
                                Copy
                            </button>
                        </div>
                    </div>
                </div>
                <div class="text-right text-xs text-slate-500">
                    <span class="inline-block px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold text-[10px]">Instant 24/7 Credit</span>
                </div>
            </div>

            <!-- Amount Selection -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                    Select Deposit Amount <span class="text-rose-500">*</span>
                </label>
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mb-3">
                    <button type="button" onclick="document.getElementById('dep_amount').value = 200" class="py-2.5 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:border-blue-500 active:bg-blue-50">₹200</button>
                    <button type="button" onclick="document.getElementById('dep_amount').value = 500" class="py-2.5 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:border-blue-500 active:bg-blue-50">₹500</button>
                    <button type="button" onclick="document.getElementById('dep_amount').value = 1000" class="py-2.5 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:border-blue-500 active:bg-blue-50">₹1,000</button>
                    <button type="button" onclick="document.getElementById('dep_amount').value = 2000" class="py-2.5 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:border-blue-500 active:bg-blue-50">₹2,000</button>
                    <button type="button" onclick="document.getElementById('dep_amount').value = 5000" class="py-2.5 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:border-blue-500 active:bg-blue-50">₹5,000</button>
                    <button type="button" onclick="document.getElementById('dep_amount').value = 10000" class="py-2.5 rounded-xl bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:border-blue-500 active:bg-blue-50">₹10,000</button>
                </div>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 font-bold text-slate-400 text-sm">₹</span>
                    <input type="number" id="dep_amount" name="amount" value="500" min="100" max="100000" step="50" required class="w-full pl-8 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white">
                </div>
            </div>

            <!-- Payment Method -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Channel <span class="text-rose-500">*</span>
                    </label>
                    <select name="payment_method" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="UPI">UPI Fast Gateway (GPay / PhonePe / Paytm)</option>
                        <option value="IMPS">Direct IMPS Bank Transfer</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        12-Digit UTR / Ref No <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="utr_reference" required placeholder="e.g. 419283749102" maxlength="30" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white">
                </div>
            </div>

            <button type="submit" class="w-full py-3 bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800 text-white font-black text-sm rounded-xl shadow-md transition active:scale-[0.99]">
                Submit Deposit & Credit Balance
            </button>
        </form>

    <?php else: ?>
        <!-- Withdrawal Form -->
        <form method="POST" action="/wallet.php?action=withdraw" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="do_withdraw" value="1">

            <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl text-xs text-amber-800 leading-relaxed">
                <strong>Withdrawal Notice:</strong> Fast payouts available 24 hours a day. Ensure your bank account or UPI ID matches your account details. Minimum payout ₹200.
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Withdrawal Amount <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 font-bold text-slate-400 text-sm">₹</span>
                    <input type="number" name="withdraw_amount" value="500" min="200" max="50000" step="50" required class="w-full pl-8 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white">
                </div>
                <span class="text-[10px] text-slate-400 mt-1 block">Maximum per transaction: ₹50,000</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Payout Method <span class="text-rose-500">*</span>
                    </label>
                    <select name="payout_method" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="UPI">UPI ID (e.g. mobile@upi)</option>
                        <option value="IMPS">Bank Account + IFSC</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Account / UPI Details <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="payout_account" required placeholder="Enter UPI ID or Acct No & IFSC" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white">
                </div>
            </div>

            <button type="submit" class="w-full py-3 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-black text-sm rounded-xl shadow-md transition active:scale-[0.99]">
                Submit Withdrawal Request
            </button>
        </form>
    <?php endif; ?>
</div>

<!-- Transaction History Statement (Real MySQL Ledger) -->
<div class="w-full card-premium p-5 sm:p-6 mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <div>
            <h3 class="font-black text-slate-900 text-base sm:text-lg flex items-center gap-2">
                <span class="w-1.5 h-5 bg-blue-600 rounded-full inline-block"></span>
                Official Transaction Statement
            </h3>
            <p class="text-xs text-slate-500">Authoritative balance change history from MySQL ledger</p>
        </div>

        <!-- Filter tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-1">
            <a href="/wallet.php?action=<?= e($action) ?>&filter=all" class="px-3 py-1 rounded-lg text-xs font-bold transition <?= $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600' ?>">All</a>
            <a href="/wallet.php?action=<?= e($action) ?>&filter=deposit" class="px-3 py-1 rounded-lg text-xs font-bold transition <?= $filter === 'deposit' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600' ?>">Deposits</a>
            <a href="/wallet.php?action=<?= e($action) ?>&filter=withdrawal" class="px-3 py-1 rounded-lg text-xs font-bold transition <?= $filter === 'withdrawal' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600' ?>">Withdrawals</a>
            <a href="/wallet.php?action=<?= e($action) ?>&filter=win" class="px-3 py-1 rounded-lg text-xs font-bold transition <?= $filter === 'win' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600' ?>">Winnings</a>
            <a href="/wallet.php?action=<?= e($action) ?>&filter=bet" class="px-3 py-1 rounded-lg text-xs font-bold transition <?= $filter === 'bet' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600' ?>">Bets</a>
        </div>
    </div>

    <?php if (!empty($statement)): ?>
        <div class="overflow-x-auto no-scrollbar">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 uppercase font-bold">
                        <th class="py-2.5 px-3">Type</th>
                        <th class="py-2.5 px-3">Reference / Note</th>
                        <th class="py-2.5 px-3 text-right">Amount</th>
                        <th class="py-2.5 px-3 text-right">Balance After</th>
                        <th class="py-2.5 px-3 text-center">Status</th>
                        <th class="py-2.5 px-3 text-right">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($statement as $tx):
                        $isCredit = in_array($tx['type'], ['deposit', 'win', 'bonus', 'refund'], true);
                    ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3 px-3">
                                <span class="font-black uppercase text-[11px] px-2 py-0.5 rounded <?= $isCredit ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                    <?= e($tx['type']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                <div class="font-semibold text-slate-800"><?= e($tx['notes'] ?? '-') ?></div>
                                <div class="text-[10px] text-slate-400 font-mono"><?= e($tx['reference_id'] ?? 'N/A') ?></div>
                            </td>
                            <td class="py-3 px-3 text-right font-black text-sm <?= $isCredit ? 'text-emerald-600' : 'text-slate-900' ?>">
                                <?= $isCredit ? '+' : '-' ?><?= formatMoney((float)$tx['amount']) ?>
                            </td>
                            <td class="py-3 px-3 text-right font-mono font-semibold text-slate-600">
                                <?= formatMoney((float)$tx['balance_after']) ?>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-full <?= $tx['status'] === 'completed' || $tx['status'] === 'approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($tx['status'] === 'rejected' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-700 border border-amber-200') ?>">
                                    <?= e($tx['status']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-3 text-right font-mono text-[10px] text-slate-400">
                                <?= date('d M Y, h:i A', strtotime($tx['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="py-8 text-center text-xs text-slate-400">
            No transaction records found matching your selected criteria.
        </div>
    <?php endif; ?>
</div>

<!-- User Bottom Navigation (Strict Normal Document Flow) -->
<?php require_once __DIR__ . '/includes/user_nav.php'; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
