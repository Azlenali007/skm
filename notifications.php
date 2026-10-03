<?php
/**
 * Sikkim Gaming Platform - Notifications
 * Real database-backed alerts & updates (Strict Normal Document Flow)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin();
$pdo = getDB();

// Mark all as read if requested
if (isset($_POST['mark_all_read'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $up = $pdo->prepare("UPDATE `notifications` SET `is_read` = 1 WHERE `user_id` = :uid OR `user_id` IS NULL");
        $up->execute([':uid' => $user['id']]);
        setFlash('success', 'All notifications marked as read.');
        header("Location: /notifications.php");
        exit;
    }
}

// Fetch user notifications
$stmt = $pdo->prepare("
    SELECT * FROM `notifications`
    WHERE `user_id` = :uid OR `user_id` IS NULL
    ORDER BY `id` DESC
    LIMIT 30
");
$stmt->execute([':uid' => $user['id']]);
$notifications = $stmt->fetchAll();

$pageTitle = "Notifications & Announcements";
require_once __DIR__ . '/includes/header.php';
?>

<div class="w-full mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
            <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
            Notifications & Messages
        </h1>
        <p class="text-xs text-slate-500 mt-1">Official system notices, deposit confirmations, and round winnings</p>
    </div>

    <?php if (!empty($notifications)): ?>
        <form method="POST" action="/notifications.php">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <button type="submit" name="mark_all_read" value="1" class="px-3.5 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition">
                Mark All as Read
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="w-full card-premium overflow-hidden mb-8">
    <?php if (!empty($notifications)): ?>
        <div class="divide-y divide-slate-100">
            <?php foreach ($notifications as $n):
                $isWin = $n['type'] === 'win';
                $isPayment = $n['type'] === 'payment';
            ?>
                <div class="p-4 sm:p-5 flex items-start gap-3 sm:gap-4 <?= $n['is_read'] ? 'bg-white' : 'bg-blue-50/40' ?> transition">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 <?= $isWin ? 'bg-amber-100 text-amber-700' : ($isPayment ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700') ?>">
                        <?php if ($isWin): ?>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <?php elseif ($isPayment): ?>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        <?php else: ?>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <h3 class="font-bold text-sm text-slate-900"><?= e($n['title']) ?></h3>
                            <span class="text-[10px] font-mono text-slate-400 shrink-0"><?= date('d M Y, h:i A', strtotime($n['created_at'])) ?></span>
                        </div>
                        <p class="text-xs text-slate-600 leading-relaxed"><?= e($n['message']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="p-12 text-center text-slate-400 text-xs">
            <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            No notifications found at this time.
        </div>
    <?php endif; ?>
</div>

<!-- User Bottom Navigation -->
<?php require_once __DIR__ . '/includes/user_nav.php'; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
