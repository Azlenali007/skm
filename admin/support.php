<?php
/**
 * Sikkim Gaming Platform - Admin Support Desk
 * Reply to Player Tickets & Close Inquiries
 */
require_once __DIR__ . '/includes/admin_header.php';
$pdo = getDB();

$ticketId = isset($_GET['ticket']) ? (int)$_GET['ticket'] : 0;

// Handle Admin Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_reply'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $tid = (int)$_POST['ticket_id'];
        $reply = trim($_POST['reply_message'] ?? '');
        $newStatus = trim($_POST['ticket_status'] ?? 'in_progress');

        if (!empty($reply) && $tid > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO `support_messages` (`ticket_id`, `sender_type`, `user_id`, `message`)
                VALUES (:tid, 'admin', :uid, :msg)
            ");
            $stmt->execute([
                ':tid' => $tid,
                ':uid' => $adminUser['id'],
                ':msg' => $reply
            ]);

            $pdo->prepare("UPDATE `support_tickets` SET `status` = :st, `updated_at` = NOW() WHERE `id` = :id")->execute([
                ':st' => $newStatus,
                ':id' => $tid
            ]);

            // Notify user
            $t = $pdo->query("SELECT user_id, ticket_number FROM `support_tickets` WHERE `id` = $tid")->fetch();
            if ($t) {
                $pdo->prepare("
                    INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`)
                    VALUES (:uid, 'Support Desk Update', :msg, 'system')
                ")->execute([
                    ':uid' => $t['user_id'],
                    ':msg' => 'Support agent has replied to your ticket #' . $t['ticket_number'] . '.'
                ]);
            }

            setFlash('success', "Response sent to player.");
            header("Location: /admin/support.php?ticket=" . $tid);
            exit;
        }
    }
}

// Fetch Tickets
$tickets = $pdo->query("
    SELECT t.*, u.username, u.phone,
           (SELECT COUNT(*) FROM `support_messages` WHERE `ticket_id` = t.id) as msg_count
    FROM `support_tickets` t
    JOIN `users` u ON t.user_id = u.id
    ORDER BY (t.status = 'open') DESC, t.id DESC
")->fetchAll();

$activeTicket = null;
$messages = [];
if ($ticketId > 0) {
    $stmt = $pdo->prepare("
        SELECT t.*, u.username, u.phone, u.email
        FROM `support_tickets` t
        JOIN `users` u ON t.user_id = u.id
        WHERE t.id = :id
    ");
    $stmt->execute([':id' => $ticketId]);
    $activeTicket = $stmt->fetch();

    if ($activeTicket) {
        $mStmt = $pdo->prepare("SELECT * FROM `support_messages` WHERE `ticket_id` = :tid ORDER BY `id` ASC");
        $mStmt->execute([':tid' => $ticketId]);
        $messages = $mStmt->fetchAll();
    }
}

$pageTitle = "Support Desk Administration";
?>

<div class="w-full mb-6">
    <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
        <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
        Customer Support Inquiries
    </h1>
    <p class="text-xs text-slate-500 mt-1">Review player tickets, send official replies, and track resolution status</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Tickets List -->
    <div class="card-premium p-5 self-start">
        <h3 class="font-black text-slate-900 text-sm mb-3">All Tickets (<?= count($tickets) ?>)</h3>
        <?php if (!empty($tickets)): ?>
            <div class="divide-y divide-slate-100 text-xs">
                <?php foreach ($tickets as $t): ?>
                    <a href="/admin/support.php?ticket=<?= $t['id'] ?>" class="block py-3 hover:bg-slate-50 transition px-2 rounded-lg <?= $activeTicket && $activeTicket['id'] == $t['id'] ? 'bg-blue-50/70 border border-blue-200' : '' ?>">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-mono text-[10px] text-slate-400 font-bold"><?= e($t['ticket_number']) ?></span>
                            <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded <?= $t['status'] === 'open' ? 'bg-rose-100 text-rose-800' : ($t['status'] === 'in_progress' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') ?>">
                                <?= e($t['status']) ?>
                            </span>
                        </div>
                        <div class="font-bold text-slate-800 truncate"><?= e($t['subject']) ?></div>
                        <div class="flex items-center justify-between text-[10px] text-slate-400 mt-1">
                            <span><?= e($t['username']) ?></span>
                            <span><?= date('d M, h:i A', strtotime($t['created_at'])) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-xs text-slate-400 py-4 text-center">No support tickets received.</p>
        <?php endif; ?>
    </div>

    <!-- Active Ticket View -->
    <div class="lg:col-span-2">
        <?php if ($activeTicket): ?>
            <div class="card-premium p-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 mb-4 border-b border-slate-100">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-bold text-blue-600"><?= e($activeTicket['ticket_number']) ?></span>
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-slate-100 text-slate-700"><?= e($activeTicket['category']) ?></span>
                        </div>
                        <h2 class="text-lg font-black text-slate-900 mt-1"><?= e($activeTicket['subject']) ?></h2>
                        <div class="text-xs text-slate-500 mt-0.5">
                            Player: <strong class="text-slate-800"><?= e($activeTicket['username']) ?></strong> • Mobile: <?= e($activeTicket['phone']) ?>
                        </div>
                    </div>
                    <span class="text-[10px] uppercase font-black px-2.5 py-1 rounded-full self-start <?= $activeTicket['status'] === 'open' ? 'bg-rose-100 text-rose-800' : ($activeTicket['status'] === 'in_progress' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') ?>">
                        <?= e($activeTicket['status']) ?>
                    </span>
                </div>

                <!-- Messages Thread -->
                <div class="space-y-4 mb-6">
                    <?php foreach ($messages as $m):
                        $isAdmin = $m['sender_type'] === 'admin';
                    ?>
                        <div class="p-4 rounded-2xl <?= $isAdmin ? 'bg-blue-50/80 border border-blue-100' : 'bg-slate-50 border border-slate-200' ?>">
                            <div class="flex items-center justify-between mb-1.5 text-xs">
                                <span class="font-bold <?= $isAdmin ? 'text-blue-700' : 'text-slate-800' ?>">
                                    <?= $isAdmin ? 'Admin Response' : 'Player (' . e($activeTicket['username']) . ')' ?>
                                </span>
                                <span class="text-[10px] font-mono text-slate-400"><?= date('d M Y, h:i A', strtotime($m['created_at'])) ?></span>
                            </div>
                            <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-wrap"><?= e($m['message']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Admin Response Form -->
                <form method="POST" action="/admin/support.php?ticket=<?= $activeTicket['id'] ?>" class="pt-4 border-t border-slate-100 space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="admin_reply" value="1">
                    <input type="hidden" name="ticket_id" value="<?= $activeTicket['id'] ?>">

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Official Response</label>
                        <textarea name="reply_message" rows="3" required placeholder="Type support reply to player..." class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-bold text-slate-600">Update Status:</label>
                            <select name="ticket_status" class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
                                <option value="in_progress" <?= $activeTicket['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="resolved" <?= $activeTicket['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                <option value="closed" <?= $activeTicket['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                        </div>

                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow transition">
                            Send Reply to Player
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="card-premium p-12 text-center text-slate-400 text-xs">
                <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <h3 class="text-sm font-bold text-slate-700 mb-1">Select a Support Ticket</h3>
                <p>Click on any inquiry from the left to view messages and respond.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
