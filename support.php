<?php
/**
 * Sikkim Gaming Platform - Real 24/7 Support Desk
 * Backed by MySQL support_tickets and support_messages
 * STRICT NORMAL FLOW: NO sticky, NO fixed.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin();
$pdo = getDB();

$ticketId = isset($_GET['ticket']) ? (int)$_GET['ticket'] : 0;
$error = '';
$success = '';

// Create Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed.";
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $message = trim($_POST['message'] ?? '');

        if (empty($subject) || empty($message)) {
            $error = "Please provide both a ticket subject and your inquiry message.";
        } else {
            try {
                $pdo->beginTransaction();

                $ticketNumber = 'TKT-' . date('Ymd') . '-' . rand(1000, 9999);

                $stmtT = $pdo->prepare("
                    INSERT INTO `support_tickets` (`user_id`, `ticket_number`, `subject`, `category`, `status`)
                    VALUES (:uid, :num, :sub, :cat, 'open')
                ");
                $stmtT->execute([
                    ':uid' => $user['id'],
                    ':num' => $ticketNumber,
                    ':sub' => $subject,
                    ':cat' => $category
                ]);
                $newTicketId = (int)$pdo->lastInsertId();

                $stmtM = $pdo->prepare("
                    INSERT INTO `support_messages` (`ticket_id`, `sender_type`, `user_id`, `message`)
                    VALUES (:tid, 'user', :uid, :msg)
                ");
                $stmtM->execute([
                    ':tid' => $newTicketId,
                    ':uid' => $user['id'],
                    ':msg' => $message
                ]);

                $pdo->commit();
                setFlash('success', 'Ticket #' . $ticketNumber . ' created! Support team will respond shortly.');
                header("Location: /support.php?ticket=" . $newTicketId);
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Failed to create support ticket: " . $e->getMessage();
            }
        }
    }
}

// Reply to Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed.";
    } else {
        $replyMsg = trim($_POST['reply_message'] ?? '');
        $tid = (int)($_POST['ticket_id'] ?? 0);

        if (empty($replyMsg) || !$tid) {
            $error = "Reply message cannot be empty.";
        } else {
            // Check ticket ownership
            $check = $pdo->prepare("SELECT id FROM `support_tickets` WHERE `id` = :id AND `user_id` = :uid");
            $check->execute([':id' => $tid, ':uid' => $user['id']]);
            if (!$check->fetch()) {
                $error = "Unauthorized ticket access.";
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO `support_messages` (`ticket_id`, `sender_type`, `user_id`, `message`)
                    VALUES (:tid, 'user', :uid, :msg)
                ");
                $ins->execute([
                    ':tid' => $tid,
                    ':uid' => $user['id'],
                    ':msg' => $replyMsg
                ]);

                // Update ticket updated_at
                $pdo->prepare("UPDATE `support_tickets` SET `updated_at` = NOW() WHERE `id` = :id")->execute([':id' => $tid]);

                setFlash('success', 'Your reply has been sent.');
                header("Location: /support.php?ticket=" . $tid);
                exit;
            }
        }
    }
}

// Fetch active ticket details if selected
$activeTicket = null;
$messages = [];
if ($ticketId > 0) {
    $tStmt = $pdo->prepare("SELECT * FROM `support_tickets` WHERE `id` = :id AND `user_id` = :uid LIMIT 1");
    $tStmt->execute([':id' => $ticketId, ':uid' => $user['id']]);
    $activeTicket = $tStmt->fetch();

    if ($activeTicket) {
        $mStmt = $pdo->prepare("SELECT * FROM `support_messages` WHERE `ticket_id` = :tid ORDER BY `id` ASC");
        $mStmt->execute([':tid' => $ticketId]);
        $messages = $mStmt->fetchAll();
    }
}

// Fetch all user tickets
$allTicketsStmt = $pdo->prepare("SELECT * FROM `support_tickets` WHERE `user_id` = :uid ORDER BY `id` DESC");
$allTicketsStmt->execute([':uid' => $user['id']]);
$userTickets = $allTicketsStmt->fetchAll();

$pageTitle = "Customer Support Desk";
require_once __DIR__ . '/includes/header.php';
?>

<div class="w-full mb-6">
    <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
        <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
        24/7 Official Support Desk
    </h1>
    <p class="text-xs text-slate-500 mt-1">Submit inquiries regarding deposits, withdrawals, or account assistance</p>
</div>

<?php if (!empty($error)): ?>
    <div class="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Left Column: Tickets List & Create Button -->
    <div class="space-y-4">
        <!-- New Ticket Form Trigger -->
        <div class="card-premium p-5">
            <h3 class="font-black text-slate-900 text-sm mb-3">Open New Support Ticket</h3>
            <form method="POST" action="/support.php" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="create_ticket" value="1">

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Category</label>
                    <select name="category" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="Deposit">Deposit / Payment Issue</option>
                        <option value="Withdrawal">Withdrawal / Payout Query</option>
                        <option value="Game">Game Rules / Rounds</option>
                        <option value="Account">Account Security</option>
                        <option value="General">General Inquiries</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Subject</label>
                    <input type="text" name="subject" required placeholder="Brief description of issue" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Message</label>
                    <textarea name="message" rows="3" required placeholder="Describe your question or provide transaction details..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow transition">
                    Submit Ticket
                </button>
            </form>
        </div>

        <!-- My Tickets List -->
        <div class="card-premium p-5">
            <h3 class="font-black text-slate-900 text-sm mb-3">My Support Inquiries</h3>
            <?php if (!empty($userTickets)): ?>
                <div class="divide-y divide-slate-100 text-xs">
                    <?php foreach ($userTickets as $t): ?>
                        <a href="/support.php?ticket=<?= $t['id'] ?>" class="block py-3 hover:bg-slate-50 transition px-2 rounded-lg <?= $activeTicket && $activeTicket['id'] == $t['id'] ? 'bg-blue-50/70 border border-blue-200' : '' ?>">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-mono text-[10px] text-slate-400 font-bold"><?= e($t['ticket_number']) ?></span>
                                <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded <?= $t['status'] === 'open' ? 'bg-emerald-100 text-emerald-800' : ($t['status'] === 'in_progress' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') ?>">
                                    <?= e($t['status']) ?>
                                </span>
                            </div>
                            <div class="font-bold text-slate-800 truncate"><?= e($t['subject']) ?></div>
                            <span class="text-[10px] text-slate-400"><?= date('d M Y', strtotime($t['created_at'])) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-xs text-slate-400 py-3 text-center">No support tickets created yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Ticket Conversation View -->
    <div class="lg:col-span-2">
        <?php if ($activeTicket): ?>
            <div class="card-premium p-6">
                <!-- Ticket Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 mb-4 border-b border-slate-100">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-bold text-blue-600"><?= e($activeTicket['ticket_number']) ?></span>
                            <span class="text-[10px] uppercase font-extrabold px-2 py-0.5 rounded bg-blue-100 text-blue-800"><?= e($activeTicket['category']) ?></span>
                        </div>
                        <h2 class="text-lg font-black text-slate-900 mt-1"><?= e($activeTicket['subject']) ?></h2>
                    </div>
                    <span class="text-[10px] uppercase font-bold px-2 py-1 rounded-full self-start <?= $activeTicket['status'] === 'open' ? 'bg-emerald-100 text-emerald-800' : ($activeTicket['status'] === 'in_progress' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') ?>">
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
                                    <?= $isAdmin ? 'Official Support Staff' : 'You (Player)' ?>
                                </span>
                                <span class="text-[10px] font-mono text-slate-400"><?= date('d M Y, h:i A', strtotime($m['created_at'])) ?></span>
                            </div>
                            <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-wrap"><?= e($m['message']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Reply Box -->
                <form method="POST" action="/support.php?ticket=<?= $activeTicket['id'] ?>" class="pt-4 border-t border-slate-100">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="reply_ticket" value="1">
                    <input type="hidden" name="ticket_id" value="<?= $activeTicket['id'] ?>">

                    <label class="block text-xs font-bold text-slate-700 uppercase mb-2">Reply to this Ticket</label>
                    <textarea name="reply_message" rows="3" required placeholder="Type your response here..." class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 mb-3"></textarea>

                    <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow transition">
                        Send Message
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="card-premium p-12 text-center text-slate-400 text-xs">
                <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <h3 class="text-sm font-bold text-slate-700 mb-1">Select or Create a Support Ticket</h3>
                <p>Choose an existing ticket from the left or fill out the form to contact our support team.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- User Bottom Nav -->
<?php require_once __DIR__ . '/includes/user_nav.php'; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
