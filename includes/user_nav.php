<?php
/**
 * Sikkim Gaming Platform - Bottom 4-Option Navigation Bar
 * Fixed Viewport Positioning (Attached to bottom of the screen)
 */
$currentUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Active Tab Evaluation
$isActivity = ($currentUri === '/activity.php');
$isWallet = ($currentUri === '/wallet.php');
$isAccount = in_array($currentUri, ['/account.php', '/notifications.php', '/support.php'], true);
$isHome = !$isActivity && !$isWallet && !$isAccount;
?>
<!-- Fixed Bottom 4-Option Navigation Bar (Stays pinned to viewport bottom) -->
<nav class="fixed bottom-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-md border-t border-slate-200/90 shadow-[0_-4px_20px_rgba(0,0,0,0.07)]">
    <div class="max-w-md sm:max-w-lg md:max-w-xl mx-auto px-2 py-1.5 sm:py-2">
        <div class="grid grid-cols-4 gap-1 items-center text-center">
            <!-- 1. Home Tab -->
            <a href="/dashboard.php" class="flex flex-col items-center justify-center py-1.5 px-1 rounded-xl transition-all duration-150 <?= $isHome ? 'text-blue-600 bg-blue-50/80 font-bold shadow-xs' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50 font-medium' ?>">
                <svg class="w-5 h-5 mb-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span class="text-[11px] leading-tight">Home</span>
            </a>

            <!-- 2. Activity Tab -->
            <a href="/activity.php" class="flex flex-col items-center justify-center py-1.5 px-1 rounded-xl transition-all duration-150 <?= $isActivity ? 'text-blue-600 bg-blue-50/80 font-bold shadow-xs' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50 font-medium' ?>">
                <svg class="w-5 h-5 mb-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span class="text-[11px] leading-tight">Activity</span>
            </a>

            <!-- 3. Wallet Tab -->
            <a href="/wallet.php" class="flex flex-col items-center justify-center py-1.5 px-1 rounded-xl transition-all duration-150 <?= $isWallet ? 'text-blue-600 bg-blue-50/80 font-bold shadow-xs' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50 font-medium' ?>">
                <svg class="w-5 h-5 mb-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                <span class="text-[11px] leading-tight">Wallet</span>
            </a>

            <!-- 4. Account Tab -->
            <a href="/account.php" class="flex flex-col items-center justify-center py-1.5 px-1 rounded-xl transition-all duration-150 <?= $isAccount ? 'text-blue-600 bg-blue-50/80 font-bold shadow-xs' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50 font-medium' ?>">
                <svg class="w-5 h-5 mb-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span class="text-[11px] leading-tight">Account</span>
            </a>
        </div>
    </div>
</nav>
