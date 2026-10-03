<?php
/**
 * Sikkim Gaming Platform - User Account & Security
 * Real MySQL Password Updates & Profile Information
 * STRICT NORMAL FLOW: NO sticky, NO fixed.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin();
$pdo = getDB();

$error = '';
$success = '';

// Process Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass)) {
            $error = "Please fill in all password fields.";
        } elseif (strlen($newPass) < 6) {
            $error = "New password must be at least 6 characters long.";
        } elseif ($newPass !== $confirmPass) {
            $error = "New passwords do not match.";
        } else {
            // Verify current password from database
            $stmt = $pdo->prepare("SELECT password_hash FROM `users` WHERE `id` = :id");
            $stmt->execute([':id' => $user['id']]);
            $currentHash = $stmt->fetchColumn();

            if (!password_verify($currentPass, $currentHash)) {
                $error = "Current password is incorrect.";
            } else {
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $up = $pdo->prepare("UPDATE `users` SET `password_hash` = :hash WHERE `id` = :id");
                $up->execute([':hash' => $newHash, ':id' => $user['id']]);
                $success = "Password updated successfully.";
            }
        }
    }
}

$pageTitle = "My Account Profile";
require_once __DIR__ . '/includes/header.php';
?>

<div class="w-full mb-6">
    <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
        <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
        Player Account & Settings
    </h1>
    <p class="text-xs text-slate-500 mt-1">Manage credentials, review account credentials, and sign out</p>
</div>

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

<!-- Profile Overview Card -->
<div class="w-full card-premium p-6 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-700 text-white font-black text-2xl flex items-center justify-center shadow-md">
                <?= strtoupper(substr($user['username'], 0, 1)) ?>
            </div>
            <div>
                <h2 class="text-lg font-black text-slate-900"><?= e($user['username']) ?></h2>
                <p class="text-xs text-slate-500 font-mono mt-0.5">Mobile: +91 <?= e($user['phone']) ?></p>
                <div class="flex items-center gap-2 mt-2">
                    <span class="text-[10px] font-extrabold uppercase bg-blue-100 text-blue-800 px-2 py-0.5 rounded">Player Member</span>
                    <span class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Active</span>
                </div>
            </div>
        </div>

        <div class="sm:text-right">
            <span class="text-[10px] uppercase font-bold text-slate-400">Current Balance</span>
            <div class="text-2xl font-black text-blue-700"><?= formatMoney((float)$user['balance']) ?></div>
            <a href="/wallet.php" class="inline-block mt-2 px-3 py-1 bg-blue-600 text-white text-xs font-bold rounded-lg shadow-sm">Manage Wallet</a>
        </div>
    </div>

    <!-- Account Details Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-6 text-xs">
        <div>
            <span class="text-slate-400 block mb-1">User ID:</span>
            <span class="font-mono font-bold text-slate-800">SKM-<?= str_pad((string)$user['id'], 6, '0', STR_PAD_LEFT) ?></span>
        </div>
        <div>
            <span class="text-slate-400 block mb-1">Email:</span>
            <span class="font-bold text-slate-800"><?= !empty($user['email']) ? e($user['email']) : 'Not linked' ?></span>
        </div>
        <div>
            <span class="text-slate-400 block mb-1">Registration Date:</span>
            <span class="font-bold text-slate-800"><?= date('d F Y', strtotime($user['created_at'])) ?></span>
        </div>
    </div>
</div>

<!-- Change Password Card -->
<div class="w-full card-premium p-6 mb-8">
    <h3 class="font-black text-slate-900 text-base mb-4 flex items-center gap-2">
        <span class="w-1.5 h-5 bg-blue-600 rounded-full inline-block"></span>
        Security & Password Change
    </h3>

    <form method="POST" action="/account.php" class="space-y-4 max-w-lg">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        <input type="hidden" name="change_password" value="1">

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Current Password</label>
            <input type="password" name="current_password" required placeholder="Enter your current password" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">New Password</label>
            <input type="password" name="new_password" required placeholder="Minimum 6 characters" minlength="6" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Confirm New Password</label>
            <input type="password" name="confirm_password" required placeholder="Repeat new password" minlength="6" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow transition">
            Update Password
        </button>
    </form>
</div>

<!-- Logout Action -->
<div class="w-full card-premium p-5 mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div>
        <h4 class="font-bold text-sm text-slate-800">Sign Out of Sikkim Session</h4>
        <p class="text-xs text-slate-500">End your current session safely on this browser.</p>
    </div>
    <a href="/logout.php" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-sm transition text-center shrink-0">
        Log Out
    </a>
</div>

<!-- User Bottom Navigation -->
<?php require_once __DIR__ . '/includes/user_nav.php'; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
