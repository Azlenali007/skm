<?php
/**
 * Sikkim Gaming Platform - Public Landing Page
 * Displays real MySQL-driven banners, announcements, categories, and recommended games.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/icons.php';

// If logged-in user opens /, redirect them to /dashboard
if (isLoggedIn()) {
    header("Location: /dashboard.php");
    exit;
}

$pdo = getDB();

// 1. Fetch active banners ordered by display_order
$bannersStmt = $pdo->prepare("SELECT * FROM `banners` WHERE `status` = 'active' ORDER BY `display_order` ASC, `id` DESC");
$bannersStmt->execute();
$banners = $bannersStmt->fetchAll();

// 2. Fetch active announcements
$announcementsStmt = $pdo->prepare("SELECT * FROM `announcements` WHERE `status` = 'active' ORDER BY `priority` DESC, `id` DESC LIMIT 5");
$announcementsStmt->execute();
$announcements = $announcementsStmt->fetchAll();

// 3. Fetch active categories
$categoriesStmt = $pdo->prepare("SELECT * FROM `categories` WHERE `status` = 'active' ORDER BY `display_order` ASC, `id` ASC");
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll();

// 4. Fetch recommended games
$gamesStmt = $pdo->prepare("
    SELECT g.*, c.name as category_name, c.slug as category_slug
    FROM `games` g
    LEFT JOIN `categories` c ON g.category_id = c.id
    WHERE g.status = 'active' AND g.is_recommended = 1
    ORDER BY g.display_order ASC, g.id ASC
");
$gamesStmt->execute();
$recommendedGames = $gamesStmt->fetchAll();

$pageTitle = "Official Sikkim Gaming Platform - Play & Win";
require_once __DIR__ . '/includes/header.php';
?>

<!-- 1. Real Database-Driven Banner Slider (Strictly Normal Document Flow) -->
<section class="w-full mb-6">
    <div id="banner-container" class="relative overflow-hidden rounded-2xl shadow-sm border border-slate-200/80 bg-gradient-to-r from-blue-900 via-indigo-900 to-blue-950 text-white min-h-[160px] sm:min-h-[220px] md:min-h-[260px] flex items-center">
        <?php if (!empty($banners)): ?>
            <?php foreach ($banners as $idx => $banner): ?>
                <div class="banner-slide w-full p-5 sm:p-8 <?= $idx === 0 ? 'block' : 'hidden' ?>">
                    <div class="max-w-xl">
                        <span class="inline-block bg-blue-500/30 text-blue-200 text-[10px] sm:text-xs uppercase font-extrabold px-2.5 py-1 rounded-full mb-2 tracking-wider border border-blue-400/30">
                            Featured Promotion
                        </span>
                        <h2 class="text-xl sm:text-2xl md:text-3xl font-black tracking-tight text-white mb-2 leading-tight">
                            <?= e($banner['title']) ?>
                        </h2>
                        <?php if (!empty($banner['description'])): ?>
                            <p class="text-blue-100/90 text-xs sm:text-sm mb-4 leading-relaxed line-clamp-2">
                                <?= e($banner['description']) ?>
                            </p>
                        <?php endif; ?>
                        <div class="flex items-center gap-3">
                            <a href="<?= e($banner['link']) ?>" class="px-5 py-2 bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-950 font-black text-xs sm:text-sm rounded-xl shadow-md transition transform active:scale-95">
                                Play Now
                            </a>
                            <a href="/register.php" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white font-bold text-xs sm:text-sm rounded-xl transition border border-white/20">
                                Join Free
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Proper Empty State if No Banners -->
            <div class="w-full p-8 text-center">
                <p class="text-sm font-semibold text-blue-200">Welcome to Sikkim Platform</p>
                <h2 class="text-2xl font-black text-white mt-1">Certified Provably Fair Online Games</h2>
            </div>
        <?php endif; ?>
    </div>

    <!-- Banner Dots Indicator -->
    <?php if (count($banners) > 1): ?>
        <div class="flex justify-center items-center gap-2 mt-3">
            <?php foreach ($banners as $idx => $b): ?>
                <button type="button" class="banner-dot h-2 rounded-full transition-all duration-300 <?= $idx === 0 ? 'bg-blue-600 w-6' : 'bg-slate-300 w-2' ?>" aria-label="Slide <?= $idx + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- 2. Real Database-Driven Announcement Bar -->
<section class="w-full mb-6">
    <div class="bg-white border border-blue-100 rounded-full px-4 py-2 shadow-sm flex items-center gap-3">
        <!-- Megaphone Speaker Icon -->
        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 8a3 3 0 010 6M11 13h4a2 2 0 002-2V9a2 2 0 00-2-2h-4l-5-4v18l5-4z"/>
            </svg>
        </div>

        <!-- Announcement Content -->
        <div class="flex-1 overflow-hidden">
            <?php if (!empty($announcements)): ?>
                <div class="text-xs sm:text-sm font-medium text-slate-700 truncate">
                    <?= e($announcements[0]['content']) ?>
                </div>
            <?php else: ?>
                <div class="text-xs text-slate-500">Welcome to SIKKIM game platform, we will serve you wholeheartedly!</div>
            <?php endif; ?>
        </div>

        <!-- Right Quick Badge -->
        <div class="shrink-0 flex items-center gap-1.5 bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-xs font-bold border border-blue-200">
            <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
            <span>Live 24/7</span>
        </div>
    </div>
</section>

<!-- 3. Horizontal Category Section (Circular Icons - Strict Normal Flow) -->
<section class="w-full mb-8">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-extrabold text-slate-900 text-base sm:text-lg flex items-center gap-2">
            <span class="w-1.5 h-5 bg-blue-600 rounded-full inline-block"></span>
            Game Categories
        </h3>
        <a href="/games.php" class="text-xs font-bold text-blue-600 hover:text-blue-700 transition">View All &rarr;</a>
    </div>

    <?php if (!empty($categories)): ?>
        <div class="grid grid-cols-4 sm:grid-cols-8 gap-3 sm:gap-4 text-center">
            <?php foreach ($categories as $cat): ?>
                <a href="/games.php?category=<?= urlencode($cat['slug']) ?>" class="group flex flex-col items-center">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full category-circle flex items-center justify-center mb-1.5 shadow-sm group-hover:border-blue-500 transition">
                        <?= getCategoryIconSvg($cat['slug']) ?>
                    </div>
                    <span class="text-[11px] sm:text-xs font-bold text-slate-700 group-hover:text-blue-600 transition truncate w-full">
                        <?= e($cat['name']) ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="p-6 bg-white rounded-2xl border border-slate-200 text-center text-xs text-slate-500">
            No categories available at the moment.
        </div>
    <?php endif; ?>
</section>

<!-- 4. Recommended Games Section -->
<section class="w-full mb-8">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="font-black text-slate-900 text-lg sm:text-xl flex items-center gap-2">
                <span class="w-1.5 h-6 bg-blue-600 rounded-full inline-block"></span>
                Recommended Games
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">The most popular electronic games among players</p>
        </div>
        <a href="/games.php" class="text-xs font-bold text-blue-600 hover:text-blue-700 bg-blue-50 px-3 py-1.5 rounded-xl border border-blue-100 transition">
            All Games
        </a>
    </div>

    <?php if (!empty($recommendedGames)): ?>
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($recommendedGames as $game): ?>
                <div class="card-premium overflow-hidden group hover:border-blue-400 transition flex flex-col">
                    <!-- Game Card Banner / Image Area -->
                    <div class="h-32 sm:h-36 bg-gradient-to-br from-blue-700 via-indigo-700 to-blue-900 p-4 flex flex-col justify-between text-white relative">
                        <div class="flex justify-between items-start">
                            <span class="bg-black/30 backdrop-blur-sm text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md text-blue-200 border border-white/10">
                                <?= e($game['category_name'] ?? 'Popular') ?>
                            </span>
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-sm" title="Active"></span>
                        </div>
                        <div>
                            <h4 class="font-black text-base sm:text-lg leading-tight text-white group-hover:text-blue-200 transition">
                                <?= e($game['name']) ?>
                            </h4>
                            <p class="text-[11px] text-blue-100/80 truncate mt-0.5">
                                Certified 1-Minute Round
                            </p>
                        </div>
                    </div>

                    <!-- Card Body & Action -->
                    <div class="p-3 bg-white flex items-center justify-between gap-2 border-t border-slate-100 mt-auto">
                        <div class="text-[11px] text-slate-500">
                            Min Bet: <span class="font-bold text-slate-800">₹10</span>
                        </div>
                        <a href="/login.php?redirect=game&slug=<?= urlencode($game['slug']) ?>" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold shadow-sm transition">
                            Play &rarr;
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="p-8 bg-white rounded-2xl border border-slate-200 text-center text-sm text-slate-500">
            No recommended games configured currently. Games can be added in the Admin Panel.
        </div>
    <?php endif; ?>
</section>

<!-- 5. Platform Advantages / Features -->
<section class="w-full grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
    <div class="card-premium p-5 flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 border border-blue-100">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <div>
            <h4 class="font-bold text-slate-900 text-sm">Instant Withdrawals</h4>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Automated transaction processing with UPI and direct bank IMPS support.</p>
        </div>
    </div>
    <div class="card-premium p-5 flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        </div>
        <div>
            <h4 class="font-bold text-slate-900 text-sm">Provably Fair System</h4>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Cryptographic hash and transparent sequential rounds. No client manipulation.</p>
        </div>
    </div>
    <div class="card-premium p-5 flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0 border border-purple-100">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        </div>
        <div>
            <h4 class="font-bold text-slate-900 text-sm">24/7 Dedicated Support</h4>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Round-the-clock ticket assistance and priority resolution desk.</p>
        </div>
    </div>
</section>

<!-- 6. Call to Action -->
<section class="w-full card-premium p-6 sm:p-8 bg-gradient-to-br from-blue-600 to-indigo-700 text-white text-center mb-6">
    <h3 class="text-xl sm:text-2xl font-black mb-2">Ready to Start Playing?</h3>
    <p class="text-xs sm:text-sm text-blue-100 max-w-md mx-auto mb-5 leading-relaxed">
        Register in less than 30 seconds and experience India's premier Sikkim gaming platform.
    </p>
    <div class="flex items-center justify-center gap-3">
        <a href="/register.php" class="px-6 py-2.5 bg-white text-blue-700 font-bold rounded-xl shadow hover:bg-blue-50 transition text-sm">
            Create Free Account
        </a>
        <a href="/login.php" class="px-6 py-2.5 bg-blue-800/60 hover:bg-blue-800 text-white font-bold rounded-xl border border-blue-400/40 transition text-sm">
            Sign In
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
