<?php
/**
 * Sikkim Gaming Platform - User Navigation Bar
 * STRICT NORMAL DOCUMENT FLOW (NO sticky, NO fixed)
 */
$currentUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
?>
<!-- User Mobile/Desktop Quick Navigation (Strictly Normal Document Flow) -->
<nav class="w-full bg-white border border-slate-200/80 shadow-md rounded-2xl p-2 my-6">
    <div class="grid grid-cols-5 gap-1 items-center text-center">
        <!-- Promotion Tab -->
        <a href="/dashboard.php" class="flex flex-col items-center justify-center py-2 px-1 rounded-xl transition <?= ($currentUri === '/dashboard.php' || $currentUri === '/') ? 'text-blue-600 bg-blue-50/70 font-bold' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50' ?>">
            <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V4a2 2 0 10-2 2h2zm0 13a4 4 0 01-4-4v-1h8v1a4 4 0 01-4 4z"></path>
            </svg>
            <span class="text-[11px] leading-tight">Home</span>
        </a>

        <!-- Activity Tab -->
        <a href="/activity.php" class="flex flex-col items-center justify-center py-2 px-1 rounded-xl transition <?= ($currentUri === '/activity.php') ? 'text-blue-600 bg-blue-50/70 font-bold' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50' ?>">
            <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            <span class="text-[11px] leading-tight">Activity</span>
        </a>

        <!-- Central Highlighted Game Icon -->
        <a href="/games.php" class="flex flex-col items-center justify-center -my-2 py-1 px-1 group">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center shadow-lg shadow-blue-500/30 group-hover:scale-105 transition transform">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M7 6h10a5 5 0 015 5v3a5 5 0 01-5 5H7a5 5 0 01-5-5v-3a5 5 0 015-5zm0 2a3 3 0 00-3 3v3a3 3 0 003 3h10a3 3 0 003-3v-3a3 3 0 00-3-3H7zm1 3h2v2h2v2h-2v2H8v-2H6v-2h2v-2zm8 1a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm2.5 2a1.5 1.5 0 110 3 1.5 1.5 0 010-3z"/>
                </svg>
            </div>
            <span class="text-[10px] font-bold text-blue-700 mt-1">Games</span>
        </a>

        <!-- Wallet Tab -->
        <a href="/wallet.php" class="flex flex-col items-center justify-center py-2 px-1 rounded-xl transition <?= ($currentUri === '/wallet.php') ? 'text-blue-600 bg-blue-50/70 font-bold' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50' ?>">
            <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
            </svg>
            <span class="text-[11px] leading-tight">Wallet</span>
        </a>

        <!-- Account Tab -->
        <a href="/account.php" class="flex flex-col items-center justify-center py-2 px-1 rounded-xl transition <?= ($currentUri === '/account.php') ? 'text-blue-600 bg-blue-50/70 font-bold' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50' ?>">
            <svg class="w-5 h-5 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            <span class="text-[11px] leading-tight">Account</span>
        </a>
    </div>
</nav>
