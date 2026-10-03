<?php
/**
 * Sikkim Gaming Platform - Admin Announcements Management
 * Real MySQL CRUD
 */
require_once __DIR__ . '/includes/admin_header.php';
$pdo = getDB();

$editId = (int)($_GET['edit'] ?? 0);

// Create / Edit Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_announcement'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $id = (int)($_POST['ann_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $priority = (int)($_POST['priority'] ?? 0);
        $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

        if (empty($content)) {
            setFlash('error', 'Announcement text is required.');
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE `announcements` SET `content` = :c, `priority` = :p, `status` = :s WHERE `id` = :id");
                $stmt->execute([':c' => $content, ':p' => $priority, ':s' => $status, ':id' => $id]);
                setFlash('success', "Announcement updated.");
            } else {
                $stmt = $pdo->prepare("INSERT INTO `announcements` (`content`, `priority`, `status`) VALUES (:c, :p, :s)");
                $stmt->execute([':c' => $content, ':p' => $priority, ':s' => $status]);
                setFlash('success', "Announcement published.");
            }
            header("Location: /admin/announcements.php");
            exit;
        }
    }
}

// Delete Announcement
if (isset($_POST['delete_announcement'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $delId = (int)$_POST['delete_id'];
        $pdo->prepare("DELETE FROM `announcements` WHERE `id` = :id")->execute([':id' => $delId]);
        setFlash('success', "Announcement deleted.");
        header("Location: /admin/announcements.php");
        exit;
    }
}

$editAnn = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM `announcements` WHERE `id` = :id");
    $stmt->execute([':id' => $editId]);
    $editAnn = $stmt->fetch();
}

$announcements = $pdo->query("SELECT * FROM `announcements` ORDER BY `priority` DESC, `id` DESC")->fetchAll();

$pageTitle = "Announcements Management";
?>

<div class="w-full mb-6">
    <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
        <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
        Announcement Bar Management
    </h1>
    <p class="text-xs text-slate-500 mt-1">Broadcast official news to player dashboards and landing page</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Form -->
    <div class="card-premium p-5 self-start">
        <h3 class="font-black text-slate-900 text-sm mb-3">
            <?= $editAnn ? 'Edit Announcement' : 'Post Announcement' ?>
        </h3>

        <form method="POST" action="/admin/announcements.php" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="save_announcement" value="1">
            <input type="hidden" name="ann_id" value="<?= $editAnn['id'] ?? 0 ?>">

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Announcement Message <span class="text-rose-500">*</span></label>
                <textarea name="content" rows="3" required placeholder="Type announcement ticker text here..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500"><?= e($editAnn['content'] ?? '') ?></textarea>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Priority Weight</label>
                    <input type="number" name="priority" value="<?= (int)($editAnn['priority'] ?? 0) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
                        <option value="active" <?= ($editAnn && $editAnn['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($editAnn && $editAnn['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <?php if ($editAnn): ?>
                    <a href="/admin/announcements.php" class="px-3 py-2 text-xs font-bold text-slate-500 hover:underline">Cancel</a>
                <?php endif; ?>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow">
                    <?= $editAnn ? 'Update Text' : 'Publish Text' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 font-bold text-xs text-slate-600">
            Active Announcements (<?= count($announcements) ?>)
        </div>
        <?php if (!empty($announcements)): ?>
            <div class="divide-y divide-slate-100 text-xs">
                <?php foreach ($announcements as $a): ?>
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50 transition">
                        <div class="flex-1">
                            <p class="font-semibold text-slate-800 leading-relaxed"><?= e($a['content']) ?></p>
                            <div class="flex items-center gap-3 mt-1.5 text-[10px] text-slate-400 font-mono">
                                <span>Priority: <strong><?= (int)$a['priority'] ?></strong></span>
                                <span>Published: <?= date('d M Y', strtotime($a['created_at'])) ?></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-[9px] uppercase font-black px-2 py-0.5 rounded-full <?= $a['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                <?= e($a['status']) ?>
                            </span>
                            <a href="/admin/announcements.php?edit=<?= $a['id'] ?>" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 text-slate-700 hover:bg-slate-200">
                                Edit
                            </a>
                            <form method="POST" action="/admin/announcements.php" onsubmit="return confirm('Delete announcement?');" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="delete_id" value="<?= $a['id'] ?>">
                                <button type="submit" name="delete_announcement" value="1" class="px-2.5 py-1 rounded-lg text-[11px] font-bold text-rose-600 hover:bg-rose-50">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="p-8 text-center text-xs text-slate-400">
                No announcement messages in system.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
