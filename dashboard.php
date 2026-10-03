<?php
/**
 * Sikkim Gaming Platform - Authenticated User Dashboard
 * Strictly Real MySQL persistence, Normal Document Flow (NO sticky, NO fixed)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/icons.php';

$user = requireLogin();
$pdo = getDB();

// 1. Fetch active banners
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
    SELECT g.*, c.name as category_name
    FROM `games` g
    LEFT JOIN `categories` c ON g.category_id = c.id
    WHERE g.status = 'active'
    ORDER BY g.is_recommended DESC, g.display_order ASC, g.id ASC
    LIMIT 8
");
$gamesStmt->execute();
$games = $gamesStmt->fetchAll();

// 5. Fetch user recent 5 transactions
$txStmt = $pdo->prepare("
    SELECT * FROM `transactions`
    WHERE `user_id` = :uid
    ORDER BY `id` DESC
    LIMIT 5
");
$txStmt->execute([':uid' => $user['id']]);
$recentTransactions = $txStmt->fetchAll();

// 6. Fetch user unread notifications count
$notifStmt = $pdo->prepare("
    SELECT COUNT(*) FROM `notifications`
    WHERE (`user_id` = :uid OR `user_id` IS NULL) AND `is_read` = 0
");
$notifStmt->execute([':uid' => $user['id']]);
$unreadNotifs = (int)$notifStmt->fetchColumn();

$pageTitle = "Player Dashboard";
require_once __DIR__ . '/includes/header.php';
?>

<!-- User Welcome & Wallet Overview Card (Strict Normal Document Flow) -->
<section class="w-full mb-6">
    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-blue-900 rounded-2xl p-5 sm:p-6 text-white shadow-md relative overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <!-- User Info -->
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-sm border border-white/30 flex items-center justify-center text-white font-black text-lg shadow-inner">
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-black tracking-tight"><?= e($user['username']) ?></h2>
                        <span class="text-[10px] font-extrabold uppercase bg-amber-400 text-slate-900 px-2 py-0.5 rounded-full shadow-sm">VIP 1</span>
                    </div>
                    <p class="text-xs text-blue-200 mt-0.5 font-mono">ID: SKM-<?= str_pad((string)$user['id'], 6, '0', STR_PAD_LEFT) ?> • <?= e($user['phone']) ?></p>
                </div>
            </div>

            <!-- Balance Quick Details -->
            <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-xl p-3 sm:text-right flex sm:flex-col justify-between items-center sm:items-end">
                <span class="text-[11px] font-bold text-blue-200 uppercase tracking-wider">Available Balance</span>
                <span class="text-2xl sm:text-3xl font-black text-white"><?= formatMoney((float)$user['balance']) ?></span>
            </div>
        </div>

        <!-- Wallet Shortcuts Grid -->
        <div class="grid grid-cols-4 gap-2 sm:gap-4 mt-6 pt-5 border-t border-white/15 text-center">
            <a href="/wallet.php?action=deposit" class="flex flex-col items-center justify-center py-2 px-1 rounded-xl bg-white/15 hover:bg-white/25 transition">
                <div class="w-8 h-8 rounded-lg bg-emerald-500 text-white flex items-center justify-center mb-1 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                </div>
                <span class="text-[11px] font-bold">Deposit</span>
            </a>

            <a href="/wallet.php?action=withdraw" class="flex flex-col items-center justify-center py-2 px-1 rounded-xl bg-white/15 hover:bg-white/25 transition">
                <div class="w-8 h-8 rounded-lg bg-amber-500 text-white flex items-center justify-center mb-1 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <span class="text-[11px] font-bold">Withdraw</span>
            </a>

            <a href="/activity.php" class="flex flex-col items-center justify-center py-2 px-1 rounded-xl bg-white/15 hover:bg-white/25 transition">
                <div class="w-8 h-8 rounded-lg bg-blue-500 text-white flex items-center justify-center mb-1 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <span class="text-[11px] font-bold">Bets History</span>
            </a>

            <a href="/notifications.php" class="flex flex-col items-center justify-center py-2 px-1 rounded-xl bg-white/15 hover:bg-white/25 transition relative">
                <?php if ($unreadNotifs > 0): ?>
                    <span class="absolute top-1 right-2 w-4 h-4 bg-rose-500 text-white rounded-full text-[9px] font-black flex items-center justify-center"><?= $unreadNotifs ?></span>
                <?php endif; ?>
                <div class="w-8 h-8 rounded-lg bg-purple-500 text-white flex items-center justify-center mb-1 shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </div>
                <span class="text-[11px] font-bold">Notices</span>
            </a>
        </div>
    </div>
</section>

<!-- Banner Slider (Normal Document Flow) -->
<section class="w-full mb-6">
    <div id="banner-container" class="relative overflow-hidden rounded-2xl shadow-sm border border-slate-200/80 bg-gradient-to-r from-blue-900 via-indigo-900 to-blue-950 text-white min-h-[140px] sm:min-h-[180px] flex items-center">
        <?php if (!empty($banners)): ?>
            <?php foreach ($banners as $idx => $banner): ?>
                <div class="banner-slide w-full p-4 sm:p-6 <?= $idx === 0 ? 'block' : 'hidden' ?>">
                    <div class="max-w-md">
                        <span class="inline-block bg-blue-500/30 text-blue-200 text-[10px] uppercase font-extrabold px-2 py-0.5 rounded-full mb-1">
                            Promotion
                        </span>
                        <h3 class="text-base sm:text-xl font-black text-white leading-tight mb-1">
                            <?= e($banner['title']) ?>
                        </h3>
                        <?php if (!empty($banner['description'])): ?>
                            <p class="text-blue-100 text-xs line-clamp-1 mb-3">
                                <?= e($banner['description']) ?>
                            </p>
                        <?php endif; ?>
                        <a href="<?= e($banner['link']) ?>" class="inline-block px-4 py-1.5 bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs rounded-lg shadow transition">
                            Check Details &rarr;
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Real Announcement Bar -->
<section class="w-full mb-6">
    <div class="bg-white border border-blue-100 rounded-full px-4 py-2 shadow-sm flex items-center gap-3">
        <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 8a3 3 0 010 6M11 13h4a2 2 0 002-2V9a2 2 0 00-2-2h-4l-5-4v18l5-4z"/></svg>
        </div>
        <div class="flex-1 overflow-hidden">
            <p class="text-xs font-semibold text-slate-700 truncate">
                <?= !empty($announcements) ? e($announcements[0]['content']) : 'Welcome to Sikkim Official Gaming Hub!' ?>
            </p>
        </div>
        <a href="/notifications.php" class="text-xs font-bold text-blue-600 hover:underline shrink-0">More</a>
    </div>
</section>

<!-- Circular Categories Bar -->
<section class="w-full mb-8">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-extrabold text-slate-900 text-base flex items-center gap-2">
            <span class="w-1.5 h-5 bg-blue-600 rounded-full inline-block"></span>
            Categories
        </h3>
        <a href="/games.php" class="text-xs font-bold text-blue-600 hover:text-blue-700">View All</a>
    </div>

    <?php if (!empty($categories)): ?>
        <div class="grid grid-cols-4 sm:grid-cols-8 gap-3 text-center">
            <?php foreach ($categories as $cat): ?>
                <a href="/games.php?category=<?= urlencode($cat['slug']) ?>" class="group flex flex-col items-center">
                    <div class="w-14 h-14 rounded-full category-circle flex items-center justify-center mb-1.5 shadow-sm group-hover:border-blue-500 transition">
                        <?= getCategoryIconSvg($cat['slug']) ?>
                    </div>
                    <span class="text-[11px] font-bold text-slate-700 group-hover:text-blue-600 transition truncate w-full">
                        <?= e($cat['name']) ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Games Lobby Grid -->
<section class="w-full mb-8">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="font-black text-slate-900 text-lg flex items-center gap-2">
                <span class="w-1.5 h-6 bg-blue-600 rounded-full inline-block"></span>
                Hot & Recommended Games
            </h3>
            <p class="text-xs text-slate-500">Pick a game to start playing certified rounds</p>
        </div>
        <a href="/games.php" class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1.5 rounded-xl border border-blue-100">
            Lobby &rarr;
        </a>
    </div>

    <?php if (!empty($games)): ?>
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($games as $game): ?>
                <div class="card-premium overflow-hidden group hover:border-blue-400 transition flex flex-col">
                    <div class="h-32 bg-gradient-to-br from-blue-700 via-indigo-700 to-blue-900 p-4 flex flex-col justify-between text-white relative">
                        <div class="flex justify-between items-start">
                            <span class="bg-black/30 backdrop-blur-sm text-[10px] font-extrabold uppercase px-2 py-0.5 rounded text-blue-200">
                                <?= e($game['category_name'] ?? 'Game') ?>
                            </span>
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400" title="Active"></span>
                        </div>
                        <div>
                            <h4 class="font-black text-base leading-tight text-white group-hover:text-blue-200 transition">
                                <?= e($game['name']) ?>
                            </h4>
                            <p class="text-[11px] text-blue-100/80 truncate mt-0.5">
                                Instant Round Engine
                            </p>
                        </div>
                    </div>
                    <div class="p-3 bg-white flex items-center justify-between gap-2 border-t border-slate-100 mt-auto">
                        <span class="text-[11px] text-slate-500">Fast 1-Min</span>
                        <a href="/game-play.php?slug=<?= urlencode($game['slug']) ?>" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold shadow-sm transition">
                            Play
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="p-8 bg-white rounded-2xl border border-slate-200 text-center text-sm text-slate-500">
            No games currently configured.
        </div>
    <?php endif; ?>
</section>

<!-- Recent Real Account Transactions -->
<section class="w-full mb-8">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-extrabold text-slate-900 text-base flex items-center gap-2">
            <span class="w-1.5 h-5 bg-blue-600 rounded-full inline-block"></span>
            Recent Wallet Activity
        </h3>
        <a href="/wallet.php" class="text-xs font-bold text-blue-600 hover:underline">Full Statement</a>
    </div>

    <div class="card-premium overflow-hidden">
        <?php if (!empty($recentTransactions)): ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($recentTransactions as $tx): ?>
                    <div class="p-3.5 flex items-center justify-between gap-3 text-xs">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs <?= $tx['type'] === 'deposit' || $tx['type'] === 'win' || $tx['type'] === 'bonus' ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-rose-50 text-rose-600 border border-rose-100' ?>">
                                <?= $tx['type'] === 'deposit' ? 'DEP' : ($tx['type'] === 'win' ? 'WIN' : ($tx['type'] === 'withdrawal' ? 'WTH' : 'BET')) ?>
                            </div>
                            <div>
                                <div class="font-bold text-slate-800 capitalize"><?= e($tx['type']) ?> (<?= e($tx['notes'] ?? $tx['payment_method'] ?? 'General') ?>)</div>
                                <div class="text-[10px] text-slate-400"><?= date('d M Y, h:i A', strtotime($tx['created_at'])) ?></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-black text-sm <?= $tx['type'] === 'deposit' || $tx['type'] === 'win' || $tx['type'] === 'bonus' ? 'text-emerald-600' : 'text-slate-800' ?>">
                                <?= ($tx['type'] === 'deposit' || $tx['type'] === 'win' || $tx['type'] === 'bonus') ? '+' : '-' ?><?= formatMoney((float)$tx['amount']) ?>
                            </div>
                            <span class="text-[10px] font-semibold text-slate-400 capitalize"><?= e($tx['status']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="p-6 text-center text-xs text-slate-500">
                No transaction records found yet. Make a deposit or claim bonuses to start playing!
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Support & Quick Assistance -->
<section class="w-full mb-6">
    <div class="card-premium p-4 flex flex-col sm:flex-row items-center justify-between gap-3 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-100">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <div>
                <h4 class="font-bold text-sm text-slate-800">Need Assistance or Deposit Support?</h4>
                <p class="text-xs text-slate-500">Our round-the-clock help desk is ready to resolve inquiries.</p>
            </div>
        </div>
        <a href="/support.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-sm transition shrink-0">
            Open Support Ticket
        </a>
    </div>
</section>

<!-- Mobile Navigation Bar (Strict Normal Document Flow) -->
<?php require_once __DIR__ . '/includes/user_nav.php'; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
