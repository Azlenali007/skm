<?php
/**
 * Sikkim Gaming Platform - Admin Panel Header
 * STRICT NORMAL FLOW: Absolutely NO sticky, NO fixed!
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$adminUser = requireAdmin();
$currentAdminUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - Sikkim Admin' : 'Sikkim Administration' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-slate-100 text-slate-800 antialiased min-h-screen flex flex-col font-sans">

    <!-- Admin Top Notice (Normal Document Flow) -->
    <div class="bg-slate-900 text-slate-200 text-xs py-2 px-4 border-b border-slate-800">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-2 font-mono">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>SIKKIM MANAGEMENT CONSOLE • MySQL Engine Connected</span>
            </div>
            <div class="flex items-center gap-4 text-[11px]">
                <a href="/" target="_blank" class="text-blue-400 hover:text-white transition">Visit Live Site &rarr;</a>
                <span class="text-slate-500">|</span>
                <span class="text-slate-300 font-bold"><?= e($adminUser['username']) ?></span>
                <a href="/logout.php" class="text-rose-400 hover:text-rose-300 font-bold">Sign Out</a>
            </div>
        </div>
    </div>

    <!-- Admin Main Navigation (Strict Normal Flow: NO sticky, NO fixed) -->
    <header class="w-full bg-white border-b border-slate-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <a href="/admin/" class="flex items-center gap-1.5">
                        <span class="text-2xl font-black italic tracking-tighter text-blue-600"><i>S</i>IKKIM</span>
                        <span class="text-[10px] font-black uppercase bg-slate-900 text-white px-2 py-0.5 rounded tracking-widest">Admin</span>
                    </a>
                </div>

                <!-- Admin Navigation Links (Horizontal Normal Flow) -->
                <nav class="flex flex-wrap items-center gap-1 text-xs font-bold">
                    <a href="/admin/" class="px-3 py-1.5 rounded-lg transition <?= $currentAdminUri === '/admin/' || $currentAdminUri === '/admin/index.php' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
                        Dashboard
                    </a>
                    <a href="/admin/users.php" class="px-3 py-1.5 rounded-lg transition <?= strpos($currentAdminUri, 'users.php') !== false ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
                        Users
                    </a>
                    <a href="/admin/games.php" class="px-3 py-1.5 rounded-lg transition <?= strpos($currentAdminUri, 'games.php') !== false ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
                        Games
                    </a>
                    <a href="/admin/categories.php" class="px-3 py-1.5 rounded-lg transition <?= strpos($currentAdminUri, 'categories.php') !== false ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
                        Categories
                    </a>
                    <a href="/admin/banners.php" class="px-3 py-1.5 rounded-lg transition <?= strpos($currentAdminUri, 'banners.php') !== false ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
                        Banners
                    </a>
                    <a href="/admin/announcements.php" class="px-3 py-1.5 rounded-lg transition <?= strpos($currentAdminUri, 'announcements.php') !== false ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
                        Announcements
                    </a>
                    <a href="/admin/transactions.php" class="px-3 py-1.5 rounded-lg transition <?= strpos($currentAdminUri, 'transactions.php') !== false ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
                        Transactions
                    </a>
                    <a href="/admin/rounds.php" class="px-3 py-1.5 rounded-lg transition <?= strpos($currentAdminUri, 'rounds.php') !== false ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
                        Rounds/Audit
                    </a>
                    <a href="/admin/support.php" class="px-3 py-1.5 rounded-lg transition <?= strpos($currentAdminUri, 'support.php') !== false ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' ?>">
                        Support Desk
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Global Flash Notice -->
    <?php $flash = getFlash(); if ($flash): ?>
        <div class="max-w-7xl mx-auto px-4 mt-4 w-full">
            <div class="rounded-xl p-3 text-xs font-bold flex items-center justify-between <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' ?>">
                <span><?= e($flash['message']) ?></span>
                <button onclick="this.parentElement.remove()" class="text-xs">&times;</button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Admin Content Area -->
    <main class="flex-1 w-full max-w-7xl mx-auto px-4 py-6">
