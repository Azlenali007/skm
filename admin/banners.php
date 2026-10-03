<?php
/**
 * Sikkim Gaming Platform - Admin Banners Management
 * Real MySQL CRUD
 */
require_once __DIR__ . '/includes/admin_header.php';
$pdo = getDB();

$editId = (int)($_GET['edit'] ?? 0);

// Create / Edit Banner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_banner'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $id = (int)($_POST['banner_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image = trim($_POST['image'] ?? '/assets/images/banner-pubg.png');
        $link = trim($_POST['link'] ?? '#');
        $order = (int)($_POST['display_order'] ?? 0);
        $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

        if (empty($title)) {
            setFlash('error', 'Banner title is required.');
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE `banners` SET `title` = :t, `description` = :d, `image` = :i, `link` = :l, `display_order` = :o, `status` = :s WHERE `id` = :id");
                $stmt->execute([':t' => $title, ':d' => $description, ':i' => $image, ':l' => $link, ':o' => $order, ':s' => $status, ':id' => $id]);
                setFlash('success', "Banner updated.");
            } else {
                $stmt = $pdo->prepare("INSERT INTO `banners` (`title`, `description`, `image`, `link`, `display_order`, `status`) VALUES (:t, :d, :i, :l, :o, :s)");
                $stmt->execute([':t' => $title, ':d' => $description, ':i' => $image, ':l' => $link, ':o' => $order, ':s' => $status]);
                setFlash('success', "Banner created.");
            }
            header("Location: /admin/banners.php");
            exit;
        }
    }
}

// Delete Banner
if (isset($_POST['delete_banner'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $delId = (int)$_POST['delete_id'];
        $pdo->prepare("DELETE FROM `banners` WHERE `id` = :id")->execute([':id' => $delId]);
        setFlash('success', "Banner deleted.");
        header("Location: /admin/banners.php");
        exit;
    }
}

$editBanner = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM `banners` WHERE `id` = :id");
    $stmt->execute([':id' => $editId]);
    $editBanner = $stmt->fetch();
}

$banners = $pdo->query("SELECT * FROM `banners` ORDER BY `display_order` ASC, `id` DESC")->fetchAll();

$pageTitle = "Banner Slider Management";
?>

<div class="w-full mb-6">
    <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
        <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
        Promotional Banners Management
    </h1>
    <p class="text-xs text-slate-500 mt-1">Configure landing page and dashboard hero sliders</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Form -->
    <div class="card-premium p-5 self-start">
        <h3 class="font-black text-slate-900 text-sm mb-3">
            <?= $editBanner ? 'Edit Banner' : 'Create Banner' ?>
        </h3>

        <form method="POST" action="/admin/banners.php" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="save_banner" value="1">
            <input type="hidden" name="banner_id" value="<?= $editBanner['id'] ?? 0 ?>">

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Title <span class="text-rose-500">*</span></label>
                <input type="text" name="title" value="<?= e($editBanner['title'] ?? '') ?>" required placeholder="e.g. Win Big Rewards" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Description</label>
                <textarea name="description" rows="2" placeholder="Sub-copy or offer details" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500"><?= e($editBanner['description'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Image URL</label>
                <input type="text" name="image" value="<?= e($editBanner['image'] ?? '/assets/images/banner-pubg.png') ?>" placeholder="/assets/images/banner.png" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Click Destination Link</label>
                <input type="text" name="link" value="<?= e($editBanner['link'] ?? '/games.php') ?>" placeholder="/games.php" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Display Order</label>
                    <input type="number" name="display_order" value="<?= (int)($editBanner['display_order'] ?? 0) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
                        <option value="active" <?= ($editBanner && $editBanner['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($editBanner && $editBanner['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <?php if ($editBanner): ?>
                    <a href="/admin/banners.php" class="px-3 py-2 text-xs font-bold text-slate-500 hover:underline">Cancel</a>
                <?php endif; ?>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow">
                    <?= $editBanner ? 'Update Banner' : 'Publish Banner' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Banners Table -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 font-bold text-xs text-slate-600">
            Current Banners (<?= count($banners) ?>)
        </div>
        <?php if (!empty($banners)): ?>
            <div class="overflow-x-auto no-scrollbar">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 uppercase font-bold bg-slate-50">
                            <th class="py-3 px-4">Banner Title</th>
                            <th class="py-3 px-4 text-center">Order</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($banners as $b): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900"><?= e($b['title']) ?></div>
                                    <div class="text-[10px] text-slate-400 truncate max-w-xs"><?= e($b['description']) ?></div>
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-bold">
                                    <?= (int)$b['display_order'] ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="text-[10px] uppercase font-black px-2 py-0.5 rounded-full <?= $b['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                        <?= e($b['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="/admin/banners.php?edit=<?= $b['id'] ?>" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 hover:bg-slate-200 text-slate-700">
                                            Edit
                                        </a>
                                        <form method="POST" action="/admin/banners.php" onsubmit="return confirm('Delete this banner?');" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                            <input type="hidden" name="delete_id" value="<?= $b['id'] ?>">
                                            <button type="submit" name="delete_banner" value="1" class="px-2.5 py-1 rounded-lg text-[11px] font-bold text-rose-600 hover:bg-rose-50">
                                                Delete
                                            </button>
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
                No banners active. Create one using the form on the left.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
