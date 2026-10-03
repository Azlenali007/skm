<?php
/**
 * Sikkim Gaming Platform - Games Lobby
 * Filter by Category, Search, Responsive Cards (Strict Normal Flow)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/icons.php';

$pdo = getDB();
$categorySlug = trim($_GET['category'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');

// Fetch Categories
$categories = $pdo->query("SELECT * FROM `categories` WHERE `status` = 'active' ORDER BY `display_order` ASC")->fetchAll();

// Build Game Query
$sql = "
    SELECT g.*, c.name as category_name, c.slug as category_slug
    FROM `games` g
    LEFT JOIN `categories` c ON g.category_id = c.id
    WHERE g.status = 'active'
";
$params = [];

if (!empty($categorySlug)) {
    $sql .= " AND c.slug = :cslug";
    $params[':cslug'] = $categorySlug;
}

if (!empty($searchQuery)) {
    $sql .= " AND (g.name LIKE :search OR g.description LIKE :search)";
    $params[':search'] = '%' . $searchQuery . '%';
}

$sql .= " ORDER BY g.is_recommended DESC, g.display_order ASC, g.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$games = $stmt->fetchAll();

$pageTitle = "Game Lobby";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Lobby Header -->
<div class="w-full mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
                <span class="w-2 h-6 bg-blue-600 rounded-full inline-block"></span>
                Official Sikkim Game Lobby
            </h1>
            <p class="text-xs text-slate-500 mt-1">Select from our certified collection of real-time multiplayer games</p>
        </div>

        <!-- Search Box -->
        <form method="GET" action="/games.php" class="relative max-w-xs w-full">
            <?php if (!empty($categorySlug)): ?>
                <input type="hidden" name="category" value="<?= e($categorySlug) ?>">
            <?php endif; ?>
            <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="Search games..." class="w-full pl-9 pr-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </form>
    </div>
</div>

<!-- Category Filter Pills (Normal Flow) -->
<div class="w-full mb-6 overflow-x-auto no-scrollbar flex items-center gap-2 py-1">
    <a href="/games.php<?= !empty($searchQuery) ? '?q=' . urlencode($searchQuery) : '' ?>" class="px-4 py-2 rounded-xl text-xs font-bold transition shrink-0 <?= empty($categorySlug) ? 'bg-blue-600 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-700 hover:border-blue-400' ?>">
        All Categories
    </a>
    <?php foreach ($categories as $cat): ?>
        <a href="/games.php?category=<?= urlencode($cat['slug']) ?><?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>" class="px-4 py-2 rounded-xl text-xs font-bold transition shrink-0 flex items-center gap-1.5 <?= $categorySlug === $cat['slug'] ? 'bg-blue-600 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-700 hover:border-blue-400' ?>">
            <span><?= e($cat['name']) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Games Grid -->
<div class="w-full mb-8">
    <?php if (!empty($games)): ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            <?php foreach ($games as $game): ?>
                <div class="card-premium overflow-hidden group hover:border-blue-400 transition flex flex-col">
                    <div class="h-32 sm:h-36 bg-gradient-to-br from-blue-700 via-indigo-700 to-blue-900 p-4 flex flex-col justify-between text-white relative">
                        <div class="flex justify-between items-start">
                            <span class="bg-black/30 backdrop-blur-sm text-[10px] font-extrabold uppercase px-2 py-0.5 rounded text-blue-200">
                                <?= e($game['category_name'] ?? 'Popular') ?>
                            </span>
                            <?php if ($game['is_recommended']): ?>
                                <span class="bg-amber-400 text-slate-900 text-[9px] font-black uppercase px-1.5 py-0.5 rounded shadow">HOT</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="font-black text-base sm:text-lg leading-tight text-white group-hover:text-blue-200 transition">
                                <?= e($game['name']) ?>
                            </h3>
                            <p class="text-[11px] text-blue-100/80 truncate mt-0.5">
                                <?= e($game['description'] ?? 'Multiplayer live round') ?>
                            </p>
                        </div>
                    </div>
                    <div class="p-3 bg-white flex items-center justify-between gap-2 border-t border-slate-100 mt-auto">
                        <div class="text-[11px] text-slate-500">
                            Min: <strong class="text-slate-800">₹10</strong>
                        </div>
                        <?php 
                        $playUrl = '/game-play.php?slug=' . urlencode($game['slug']);
                        if ($game['slug'] === 'colour-game') $playUrl = '/colour-game.php';
                        if ($game['slug'] === 'crash-game' || $game['slug'] === 'aviator-blast') $playUrl = '/crash-game.php';
                        ?>
                        <a href="<?= $playUrl ?>" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold shadow-sm transition">
                            Play Now
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card-premium p-12 text-center text-slate-500">
            <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <h3 class="text-base font-bold text-slate-800 mb-1">No Games Found</h3>
            <p class="text-xs text-slate-400 mb-4">No active games match your selected filters.</p>
            <a href="/games.php" class="inline-block px-4 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl">Clear Filters</a>
        </div>
    <?php endif; ?>
</div>

<!-- Normal Flow User Navigation -->
<?php if (isLoggedIn()): ?>
    <?php require_once __DIR__ . '/includes/user_nav.php'; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
