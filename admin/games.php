<?php
/**
 * Sikkim Gaming Platform - Admin Game Management
 * Real MySQL CRUD for Games
 */
require_once __DIR__ . '/includes/admin_header.php';
$pdo = getDB();

$action = trim($_GET['action'] ?? 'list');
$editId = (int)($_GET['id'] ?? 0);

// Handle Create / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_game'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $id = (int)($_POST['game_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 1);
        $image = trim($_POST['image'] ?? '/assets/images/game-wingo.png');
        $desc = trim($_POST['description'] ?? '');
        $order = (int)($_POST['display_order'] ?? 0);
        $isRec = isset($_POST['is_recommended']) ? 1 : 0;
        $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

        if (empty($name) || empty($slug)) {
            setFlash('error', 'Game name and slug are required.');
        } else {
            if ($id > 0) {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE `games`
                    SET `category_id` = :cid, `name` = :name, `slug` = :slug, `image` = :img,
                        `description` = :desc, `display_order` = :order, `is_recommended` = :rec, `status` = :st
                    WHERE `id` = :id
                ");
                $stmt->execute([
                    ':cid' => $categoryId,
                    ':name' => $name,
                    ':slug' => $slug,
                    ':img' => $image,
                    ':desc' => $desc,
                    ':order' => $order,
                    ':rec' => $isRec,
                    ':st' => $status,
                    ':id' => $id
                ]);
                setFlash('success', "Game '{$name}' updated successfully.");
            } else {
                // Insert
                $stmt = $pdo->prepare("
                    INSERT INTO `games` (`category_id`, `name`, `slug`, `image`, `description`, `display_order`, `is_recommended`, `status`)
                    VALUES (:cid, :name, :slug, :img, :desc, :order, :rec, :st)
                ");
                $stmt->execute([
                    ':cid' => $categoryId,
                    ':name' => $name,
                    ':slug' => $slug,
                    ':img' => $image,
                    ':desc' => $desc,
                    ':order' => $order,
                    ':rec' => $isRec,
                    ':st' => $status
                ]);
                setFlash('success', "New game '{$name}' created successfully.");
            }
            header("Location: /admin/games.php");
            exit;
        }
    }
}

// Handle Delete
if (isset($_POST['delete_game'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $delId = (int)$_POST['delete_id'];
        $pdo->prepare("DELETE FROM `games` WHERE `id` = :id")->execute([':id' => $delId]);
        setFlash('success', "Game deleted from system.");
        header("Location: /admin/games.php");
        exit;
    }
}

// Fetch Categories for Dropdown
$categories = $pdo->query("SELECT id, name FROM `categories` ORDER BY `name` ASC")->fetchAll();

// If Editing, Fetch Existing Record
$editGame = null;
if ($action === 'edit' && $editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM `games` WHERE `id` = :id");
    $stmt->execute([':id' => $editId]);
    $editGame = $stmt->fetch();
}

// Fetch All Games for List
$games = $pdo->query("
    SELECT g.*, c.name as category_name
    FROM `games` g
    LEFT JOIN `categories` c ON g.category_id = c.id
    ORDER BY g.display_order ASC, g.id DESC
")->fetchAll();

$pageTitle = "Game Management";
?>

<div class="w-full mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
            <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
            Game Catalog Management
        </h1>
        <p class="text-xs text-slate-500 mt-1">Configure live games, category mappings, and display priority</p>
    </div>

    <div>
        <?php if ($action === 'create' || $action === 'edit'): ?>
            <a href="/admin/games.php" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-300 transition">
                &larr; Back to Catalog
            </a>
        <?php else: ?>
            <a href="/admin/games.php?action=create" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                + Add New Game
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'create' || $action === 'edit'): ?>
    <!-- Game Form (Strict Normal Flow) -->
    <div class="card-premium p-6 max-w-2xl mx-auto mb-8">
        <h2 class="font-black text-slate-900 text-base mb-4">
            <?= $action === 'edit' ? 'Edit Game: ' . e($editGame['name']) : 'Register New Game' ?>
        </h2>

        <form method="POST" action="/admin/games.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="save_game" value="1">
            <input type="hidden" name="game_id" value="<?= $editGame['id'] ?? 0 ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Game Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="<?= e($editGame['name'] ?? '') ?>" required placeholder="e.g. Win Go 1Min" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">URL Slug <span class="text-rose-500">*</span></label>
                    <input type="text" name="slug" value="<?= e($editGame['slug'] ?? '') ?>" required placeholder="e.g. win-go-1m" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-semibold focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Category <span class="text-rose-500">*</span></label>
                    <select name="category_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($editGame && $editGame['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Image / Asset URL</label>
                    <input type="text" name="image" value="<?= e($editGame['image'] ?? '/assets/images/game-wingo.png') ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Short Description</label>
                <textarea name="description" rows="2" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-blue-500"><?= e($editGame['description'] ?? '') ?></textarea>
            </div>

            <div class="grid grid-cols-3 gap-4 items-center pt-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Display Order</label>
                    <input type="number" name="display_order" value="<?= (int)($editGame['display_order'] ?? 0) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
                        <option value="active" <?= ($editGame && $editGame['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($editGame && $editGame['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="flex items-center gap-2 pt-4">
                    <input type="checkbox" id="is_rec" name="is_recommended" value="1" <?= ($editGame && $editGame['is_recommended']) ? 'checked' : '' ?> class="w-4 h-4 text-blue-600 rounded">
                    <label for="is_rec" class="text-xs font-bold text-slate-700">Recommend on Home</label>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="/admin/games.php" class="px-4 py-2 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50">Cancel</a>
                <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow transition">
                    Save Game Record
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Games Table -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <div class="p-4 bg-slate-50 border-b border-slate-200 font-bold text-xs text-slate-600 flex justify-between items-center">
        <span>Installed Games (<?= count($games) ?>)</span>
    </div>
    <?php if (!empty($games)): ?>
        <div class="overflow-x-auto no-scrollbar">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-400 uppercase font-bold bg-slate-50">
                        <th class="py-3 px-4">Game</th>
                        <th class="py-3 px-4">Category</th>
                        <th class="py-3 px-4 text-center">Order</th>
                        <th class="py-3 px-4 text-center">Featured</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($games as $g): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="py-3 px-4">
                                <div class="font-black text-slate-900"><?= e($g['name']) ?></div>
                                <span class="font-mono text-[10px] text-slate-400">/game-play.php?slug=<?= e($g['slug']) ?></span>
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-700">
                                <?= e($g['category_name'] ?? 'Uncategorized') ?>
                            </td>
                            <td class="py-3 px-4 text-center font-mono font-bold">
                                <?= (int)$g['display_order'] ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <?php if ($g['is_recommended']): ?>
                                    <span class="bg-amber-100 text-amber-800 text-[9px] font-black px-1.5 py-0.5 rounded">Featured</span>
                                <?php else: ?>
                                    <span class="text-slate-300 text-[10px]">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="text-[10px] uppercase font-black px-2 py-0.5 rounded-full <?= $g['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                                    <?= e($g['status']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="/admin/games.php?action=edit&id=<?= $g['id'] ?>" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 hover:bg-slate-200 text-slate-700">
                                        Edit
                                    </a>
                                    <form method="POST" action="/admin/games.php" onsubmit="return confirm('Delete this game record?');" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                        <input type="hidden" name="delete_id" value="<?= $g['id'] ?>">
                                        <button type="submit" name="delete_game" value="1" class="px-2.5 py-1 rounded-lg text-[11px] font-bold text-rose-600 hover:bg-rose-50">
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
            No games installed yet. Use the Add Game button to register games.
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
