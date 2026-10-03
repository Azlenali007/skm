<?php
/**
 * Sikkim Gaming Platform - Admin Categories Management
 * Real MySQL CRUD
 */
require_once __DIR__ . '/includes/admin_header.php';
$pdo = getDB();

$editId = (int)($_GET['edit'] ?? 0);

// Create / Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $id = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $icon = trim($_POST['icon'] ?? 'casino-chips');
        $order = (int)($_POST['display_order'] ?? 0);
        $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

        if (empty($name) || empty($slug)) {
            setFlash('error', 'Name and Slug are required.');
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE `categories` SET `name` = :n, `slug` = :s, `icon` = :i, `display_order` = :o, `status` = :st WHERE `id` = :id");
                $stmt->execute([':n' => $name, ':s' => $slug, ':i' => $icon, ':o' => $order, ':st' => $status, ':id' => $id]);
                setFlash('success', "Category '{$name}' updated.");
            } else {
                $stmt = $pdo->prepare("INSERT INTO `categories` (`name`, `slug`, `icon`, `display_order`, `status`) VALUES (:n, :s, :i, :o, :st)");
                $stmt->execute([':n' => $name, ':s' => $slug, ':i' => $icon, ':o' => $order, ':st' => $status]);
                setFlash('success', "Category '{$name}' created.");
            }
            header("Location: /admin/categories.php");
            exit;
        }
    }
}

// Delete Category
if (isset($_POST['delete_category'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $delId = (int)$_POST['delete_id'];
        $pdo->prepare("DELETE FROM `categories` WHERE `id` = :id")->execute([':id' => $delId]);
        setFlash('success', "Category deleted.");
        header("Location: /admin/categories.php");
        exit;
    }
}

$editCat = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM `categories` WHERE `id` = :id");
    $stmt->execute([':id' => $editId]);
    $editCat = $stmt->fetch();
}

$categories = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM `games` WHERE `category_id` = c.id) as game_count
    FROM `categories` c
    ORDER BY c.display_order ASC, c.id ASC
")->fetchAll();

$pageTitle = "Category Management";
?>

<div class="w-full mb-6">
    <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
        <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
        Game Categories Management
    </h1>
    <p class="text-xs text-slate-500 mt-1">Configure lobby horizontal tabs and circular icons</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Category Form -->
    <div class="card-premium p-5 self-start">
        <h3 class="font-black text-slate-900 text-sm mb-3">
            <?= $editCat ? 'Edit Category' : 'Create Category' ?>
        </h3>

        <form method="POST" action="/admin/categories.php" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="save_category" value="1">
            <input type="hidden" name="category_id" value="<?= $editCat['id'] ?? 0 ?>">

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Name</label>
                <input type="text" name="name" value="<?= e($editCat['name'] ?? '') ?>" required placeholder="e.g. Hot Slots" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Slug</label>
                <input type="text" name="slug" value="<?= e($editCat['slug'] ?? '') ?>" required placeholder="e.g. hot-slots" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-semibold focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Icon Code</label>
                <select name="icon" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500">
                    <option value="hot-slots" <?= ($editCat && $editCat['icon'] === 'hot-slots') ? 'selected' : '' ?>>Hot Slots (Reels)</option>
                    <option value="lottery" <?= ($editCat && $editCat['icon'] === 'lottery') ? 'selected' : '' ?>>Lottery (Crown Ball)</option>
                    <option value="original" <?= ($editCat && $editCat['icon'] === 'original') ? 'selected' : '' ?>>Original (Rocket Flame)</option>
                    <option value="slots" <?= ($editCat && $editCat['icon'] === 'slots') ? 'selected' : '' ?>>Slots (Reels)</option>
                    <option value="fishing" <?= ($editCat && $editCat['icon'] === 'fishing') ? 'selected' : '' ?>>Fishing (Fish)</option>
                    <option value="sports" <?= ($editCat && $editCat['icon'] === 'sports') ? 'selected' : '' ?>>Sports (Trophy)</option>
                    <option value="casino" <?= ($editCat && $editCat['icon'] === 'casino') ? 'selected' : '' ?>>Casino (Roulette)</option>
                    <option value="rummy" <?= ($editCat && $editCat['icon'] === 'rummy') ? 'selected' : '' ?>>Rummy (Card Suits)</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Order</label>
                    <input type="number" name="display_order" value="<?= (int)($editCat['display_order'] ?? 0) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
                        <option value="active" <?= ($editCat && $editCat['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($editCat && $editCat['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <?php if ($editCat): ?>
                    <a href="/admin/categories.php" class="px-3 py-2 text-xs font-bold text-slate-500 hover:underline">Cancel</a>
                <?php endif; ?>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow">
                    <?= $editCat ? 'Update' : 'Create' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Categories Table -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 font-bold text-xs text-slate-600">
            Configured Categories (<?= count($categories) ?>)
        </div>
        <?php if (!empty($categories)): ?>
            <div class="overflow-x-auto no-scrollbar">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 uppercase font-bold bg-slate-50">
                            <th class="py-3 px-4">Category</th>
                            <th class="py-3 px-4 text-center">Slug</th>
                            <th class="py-3 px-4 text-center">Games</th>
                            <th class="py-3 px-4 text-center">Order</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($categories as $cat): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="py-3 px-4 font-bold text-slate-900 flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                    <span><?= e($cat['name']) ?></span>
                                </td>
                                <td class="py-3 px-4 text-center font-mono text-[11px] text-slate-500">
                                    <?= e($cat['slug']) ?>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-700">
                                    <?= (int)$cat['game_count'] ?>
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-bold">
                                    <?= (int)$cat['display_order'] ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="text-[10px] uppercase font-black px-2 py-0.5 rounded-full <?= $cat['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                        <?= e($cat['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="/admin/categories.php?edit=<?= $cat['id'] ?>" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 hover:bg-slate-200 text-slate-700">
                                            Edit
                                        </a>
                                        <form method="POST" action="/admin/categories.php" onsubmit="return confirm('Delete category? Games under it may lose their category.');" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                            <input type="hidden" name="delete_id" value="<?= $cat['id'] ?>">
                                            <button type="submit" name="delete_category" value="1" class="px-2.5 py-1 rounded-lg text-[11px] font-bold text-rose-600 hover:bg-rose-50">
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
                No categories found. Create your first category using the form on the left.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
