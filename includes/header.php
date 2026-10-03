<?php
/**
 * Sikkim Gaming Platform - Header Component
 * Strict Normal Document Flow (NO sticky, NO fixed)
 */
require_once __DIR__ . '/auth.php';
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - Sikkim' : 'Sikkim - Official Premium Gaming Platform' ?></title>
    <!-- Tailwind CSS (CDN standalone runtime for zero-bundle PHP architecture) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        sikkim: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                            950: '#172554'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col font-sans">

    <!-- Top Announcement Ticker / Notification (Normal Flow) -->
    <div class="bg-gradient-to-r from-blue-700 via-sikkim-600 to-indigo-800 text-white text-xs py-1.5 px-4">
        <div class="max-w-6xl mx-auto flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 overflow-hidden">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="truncate font-medium">⚡ Fair & Transparent RNG • Official Sikkim Gaming Gateway • 24/7 Fast Payouts</span>
            </div>
            <div class="hidden sm:flex items-center gap-4 text-blue-100 text-xs shrink-0">
                <a href="/support.php" class="hover:text-white transition">24/7 Support</a>
                <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                    <a href="/admin/" class="bg-white/20 hover:bg-white/30 text-white px-2 py-0.5 rounded text-[11px] font-bold">Admin Panel</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Navigation Header (Strictly Normal Document Flow: NO sticky, NO fixed) -->
    <header class="w-full bg-white border-b border-slate-200 shadow-sm relative z-10">
        <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
            <!-- Brand Logo -->
            <a href="<?= $currentUser ? '/dashboard.php' : '/' ?>" class="flex items-center gap-2 group">
                <div class="flex items-center">
                    <span class="text-2xl sm:text-3xl font-black italic tracking-tighter text-blue-600 group-hover:text-blue-700 transition">
                        <i>S</i>IKKIM
                    </span>
                    <span class="ml-1 text-[10px] font-extrabold uppercase tracking-widest bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded">Game</span>
                </div>
            </a>

            <!-- Right Actions -->
            <div class="flex items-center gap-2 sm:gap-3">
                <?php if ($currentUser): ?>
                    <!-- User Wallet Quick Balance -->
                    <div class="flex items-center bg-blue-50 border border-blue-100 rounded-xl px-2.5 sm:px-3 py-1.5 text-xs sm:text-sm">
                        <div class="flex flex-col text-right mr-2">
                            <span class="text-[10px] uppercase font-semibold text-slate-500">Balance</span>
                            <span class="font-bold text-blue-800 leading-tight"><?= formatMoney((float)$currentUser['balance']) ?></span>
                        </div>
                        <a href="/wallet.php" class="bg-blue-600 hover:bg-blue-700 text-white p-1 rounded-lg transition" title="Deposit / Wallet">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        </a>
                    </div>

                    <!-- User Profile Button -->
                    <a href="/account.php" class="flex items-center gap-1.5 p-1.5 rounded-xl hover:bg-slate-100 transition border border-transparent hover:border-slate-200">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-bold flex items-center justify-center text-xs shadow-inner">
                            <?= strtoupper(substr($currentUser['username'], 0, 1)) ?>
                        </div>
                        <span class="hidden md:inline font-semibold text-xs text-slate-700"><?= e($currentUser['username']) ?></span>
                    </a>
                <?php else: ?>
                    <!-- Guest Auth Buttons -->
                    <a href="/login.php" class="px-4 py-2 text-xs sm:text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-sm hover:shadow transition">
                        Log in
                    </a>
                    <a href="/register.php" class="px-4 py-2 text-xs sm:text-sm font-bold text-blue-600 hover:bg-blue-50 border border-blue-600 rounded-xl transition">
                        Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Global Flash Notification Message (if set) -->
    <?php $flash = getFlash(); if ($flash): ?>
        <div class="max-w-6xl mx-auto px-4 mt-3 w-full">
            <div class="rounded-xl p-3 text-sm flex items-center justify-between <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : ($flash['type'] === 'error' ? 'bg-rose-50 text-rose-800 border border-rose-200' : 'bg-blue-50 text-blue-800 border border-blue-200') ?>">
                <div class="flex items-center gap-2">
                    <span class="font-bold"><?= $flash['type'] === 'success' ? '✓' : '!' ?></span>
                    <span><?= e($flash['message']) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-xs font-bold opacity-60 hover:opacity-100">&times;</button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Container -->
    <main class="flex-1 w-full max-w-6xl mx-auto px-4 py-4 sm:py-6">
