<?php
/**
 * Sikkim Gaming Platform - Admin Game Management
 * Card-Based Interface for Games Catalog
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
                setFlash('success', "New game '{$name}' added to catalog.");
            }
            header("Location: /admin/games.php");
            exit;
        }
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $delId = (int)$_POST['delete_id'];
        $delStmt = $pdo->prepare("DELETE FROM `games` WHERE `id` = :id");
        $delStmt->execute([':id' => $delId]);
        setFlash('success', 'Game record removed successfully.');
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

// Fetch All Games for Cards
$games = $pdo->query("
    SELECT g.*, c.name as category_name
    FROM `games` g
    LEFT JOIN `categories` c ON g.category_id = c.id
    ORDER BY g.display_order ASC, g.id DESC
")->fetchAll();

$pageTitle = "Games Management";
?>

<div class="w-full mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
            <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
            Games
        </h1>
        <p class="text-xs text-slate-500 mt-1">Manage all real-time prediction and skill games in MySQL</p>
    </div>

    <div>
        <?php if ($action === 'create' || $action === 'edit'): ?>
            <a href="/admin/games.php" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-300 transition">
                &larr; Back to Games
            </a>
        <?php else: ?>
            <a href="/admin/games.php?action=create" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-sm inline-flex items-center gap-1.5">
                <span>+</span> Add Game
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action === 'create' || $action === 'edit'): ?>
    <!-- Game Form (Strict Normal Flow) -->
    <div class="card-premium p-6 max-w-2xl mx-auto mb-8 bg-white rounded-2xl border border-slate-200 shadow-sm">
        <h2 class="font-black text-slate-900 text-base mb-4">
            <?= $action === 'edit' ? 'Edit Game: ' . e($editGame['name']) : 'Add New Game' ?>
        </h2>

        <form method="POST" action="/admin/games.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="save_game" value="1">
            <input type="hidden" name="game_id" value="<?= $editGame['id'] ?? 0 ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Game Name</label>
                    <input type="text" name="name" value="<?= e($editGame['name'] ?? '') ?>" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-blue-600">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Slug (URL identifier)</label>
                    <input type="text" name="slug" value="<?= e($editGame['slug'] ?? '') ?>" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold font-mono focus:outline-none focus:ring-2 focus:ring-blue-600">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Category</label>
                    <select name="category_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-blue-600">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($editGame && (int)$editGame['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Image Asset Path</label>
                    <input type="text" name="image" value="<?= e($editGame['image'] ?? '/assets/images/game-wingo.png') ?>" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-blue-600">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Description</label>
                <textarea name="description" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-blue-600"><?= e($editGame['description'] ?? '') ?></textarea>
            </div>

            <div class="grid grid-cols-3 gap-4 items-center pt-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Display Priority</label>
                    <input type="number" name="display_order" value="<?= (int)($editGame['display_order'] ?? 1) ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
                    <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold">
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

<!-- Clean Card-Based Games Grid (Matches exact requested wireframe) -->
<div class="w-full mb-8">
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
        <?php foreach ($games as $g): ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between hover:shadow-md transition">
                <div>
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <h3 class="font-black text-slate-900 text-base leading-snug">
                            <?= e($g['name']) ?>
                        </h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider <?= $g['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?>">
                            <span class="w-1.5 h-1.5 rounded-full mr-1 <?= $g['status'] === 'active' ? 'bg-emerald-500' : 'bg-rose-500' ?>"></span>
                            <?= e($g['status']) ?>
                        </span>
                    </div>

                    <p class="text-xs text-slate-500 line-clamp-2 mb-3">
                        <?= e($g['description'] ?: 'Official Sikkim prediction round with live timer and authoritative database outcomes.') ?>
                    </p>

                    <div class="flex items-center gap-2 text-[11px] text-slate-400 mb-4">
                        <span class="bg-slate-100 px-2 py-0.5 rounded-md font-medium text-slate-600"><?= e($g['category_name'] ?? 'Game') ?></span>
                        <span class="font-mono text-[10px]">slug: <?= e($g['slug']) ?></span>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex flex-col gap-2">
                    <!-- Manage Game Primary Button -->
                    <?php if ($g['slug'] === 'colour-game'): ?>
                        <a href="/admin/colour-game.php" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs text-center shadow-xs transition flex items-center justify-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                            Manage Game
                        </a>
                    <?php elseif ($g['slug'] === 'crash-game' || $g['slug'] === 'aviator-blast'): ?>
                        <a href="/admin/crash-game.php" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs text-center shadow-xs transition flex items-center justify-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                            Manage Game
                        </a>
                    <?php else: ?>
                        <a href="/admin/rounds.php" class="w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs text-center transition flex items-center justify-center gap-1.5">
                            Manage Game
                        </a>
                    <?php endif; ?>

                    <div class="flex items-center justify-between text-xs pt-1">
                        <a href="/admin/games.php?action=edit&id=<?= $g['id'] ?>" class="text-blue-600 hover:underline font-bold text-[11px]">
                            Edit Details
                        </a>
                        <form method="POST" action="/admin/games.php" onsubmit="return confirm('Delete this game record?');" class="inline">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="delete_id" value="<?= $g['id'] ?>">
                            <button type="submit" class="text-rose-500 hover:underline font-bold text-[11px]">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
