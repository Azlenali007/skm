<?php
/**
 * Sikkim Gaming Platform - Crash Game Arena
 * Original Aviator-style real-time ascending multiplier game.
 * Strictly NO PAGE SCROLLING: Fits completely within viewport.
 * ONLY History has an internal scroll container.
 * Fixed bottom 4-option navigation bar.
 * Real MySQL database, authoritative server timer, and live outcome audit.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/crash_engine.php';

$pdo = getDB();
$currentUser = getCurrentUser();
$userId = $currentUser ? (int)$currentUser['id'] : 0;

// Fetch initial active crash round
$activeRound = processAndGetActiveCrashRound($pdo);

// Fetch user wallet balance
$userBalance = 0.00;
if ($userId > 0) {
    $wStmt = $pdo->prepare("SELECT balance FROM `wallets` WHERE `user_id` = :uid LIMIT 1");
    $wStmt->execute([':uid' => $userId]);
    $userBalance = (float)$wStmt->fetchColumn();
}

$pageTitle = "Crash Game - Official Sikkim Platform";
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= e($pageTitle) ?></title>
    <!-- Tailwind CSS Standalone CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        sikkim: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a'
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace']
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        @keyframes rocketFloat {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-3px) rotate(1deg); }
        }
        .rocket-flight {
            animation: rocketFloat 1.2s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased font-sans h-full overflow-hidden select-none">

    <!-- Non-Scrollable Main Game Viewport Wrapper -->
    <div class="h-full max-w-md sm:max-w-lg md:max-w-xl mx-auto flex flex-col justify-between overflow-hidden px-2.5 sm:px-3 pt-1.5 pb-16 sm:pb-20">

        <!-- 1. Compact Top Bar Header -->
        <header class="w-full bg-white rounded-xl shadow-xs border border-slate-200/90 px-3 py-1.5 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <a href="/dashboard.php" class="p-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition" title="Back to Dashboard">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-xs sm:text-sm font-black tracking-tight text-slate-900 leading-none">Crash Game</h1>
                    <span class="text-[10px] font-semibold text-blue-600">Ascending Multiplier Arena</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <?php if ($currentUser): ?>
                    <a href="/wallet.php" class="flex items-center gap-1 bg-blue-50 border border-blue-200/80 px-2 py-0.5 rounded-lg text-xs font-bold text-blue-800 hover:bg-blue-100 transition shadow-2xs">
                        <span class="text-blue-500 text-[10px]">₹</span>
                        <span id="userBalanceDisplay"><?= number_format($userBalance, 2) ?></span>
                    </a>
                <?php else: ?>
                    <a href="/login.php?redirect=/crash-game.php" class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition shadow-xs">
                        Log in
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- 2. Recent Multipliers Ribbon (Horizontal Live Badges) -->
        <div class="w-full shrink-0 my-1 overflow-x-auto no-scrollbar flex items-center gap-1.5 py-0.5">
            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider shrink-0 mr-1">History:</span>
            <div id="recentRibbon" class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                <!-- Dynamically populated via AJAX -->
                <span class="px-2 py-0.5 rounded-full text-[9px] font-mono font-bold bg-slate-200 text-slate-600">Loading...</span>
            </div>
        </div>

        <!-- 3. Animated Supersonic Flight Arena Canvas -->
        <div class="w-full flex-1 min-h-[170px] sm:min-h-[210px] max-h-[230px] rounded-2xl relative overflow-hidden shadow-md border border-slate-800 bg-gradient-to-b from-slate-950 via-slate-900 to-blue-950 shrink-0 flex flex-col justify-between p-3">
            
            <!-- Radar grid lines background -->
            <div class="absolute inset-0 opacity-15 pointer-events-none" style="background-image: radial-gradient(#38bdf8 1px, transparent 1px); background-size: 24px 24px;"></div>
            
            <!-- Top Arena Info Row: Round Number & Current Phase Badge -->
            <div class="relative z-10 flex items-center justify-between text-xs">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span id="displayRoundNumber" class="font-mono font-black text-white text-[11px] sm:text-xs tracking-wider">
                        ROUND #<?= e($activeRound['round_number']) ?>
                    </span>
                </div>
                <div id="phaseBadge" class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-blue-500/20 text-blue-300 border border-blue-400/30">
                    WAITING
                </div>
            </div>

            <!-- Central Multiplier & Aircraft Display Area -->
            <div class="relative z-10 my-auto text-center flex flex-col items-center justify-center">
                <!-- Big Live Multiplier Display -->
                <div id="multiplierText" class="font-mono font-black text-4xl sm:text-5xl tracking-tighter text-white drop-shadow-[0_4px_12px_rgba(59,130,246,0.5)] transition-all duration-75">
                    1.00x
                </div>

                <!-- Status Subtext (e.g. "WAITING FOR LAUNCH (04s)", "FLEW AWAY!", "IN FLIGHT") -->
                <div id="statusSubtext" class="text-xs font-bold text-blue-300/80 mt-1 uppercase tracking-wider">
                    Waiting for Next Round...
                </div>
            </div>

            <!-- Animated Aircraft Graphic with curved flight path -->
            <div id="aircraftContainer" class="absolute bottom-6 left-6 z-10 transition-all duration-300 ease-out pointer-events-none">
                <div class="rocket-flight relative flex items-center justify-center">
                    <!-- Jet SVG Icon -->
                    <svg id="aircraftSvg" class="w-10 h-10 sm:w-12 sm:h-12 text-rose-500 transform -rotate-12 filter drop-shadow-[0_2px_8px_rgba(244,63,94,0.6)]" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                    </svg>
                    <!-- Jet Exhaust Flame -->
                    <div id="exhaustGlow" class="absolute -bottom-1 -left-2 w-3 h-3 rounded-full bg-amber-400 blur-xs animate-ping"></div>
                </div>
            </div>

            <!-- Bottom Flight Horizon Indicator -->
            <div class="relative z-10 flex items-center justify-between text-[10px] text-slate-400 border-t border-white/10 pt-1">
                <span>Speed: <strong class="text-slate-200 font-mono">1.0X</strong></span>
                <span class="text-slate-400">Provably Fair Sequential</span>
            </div>
        </div>

        <!-- 4. Amount Entry & Action Controls Panel -->
        <div class="w-full bg-white rounded-xl shadow-xs border border-slate-200/90 p-2.5 shrink-0 my-1">
            <div class="flex items-center justify-between text-[11px] mb-1.5">
                <span class="text-slate-500 font-bold uppercase tracking-wider text-[10px]">ENTRY AMOUNT</span>
                <span id="betStateLabel" class="text-slate-400 text-[10px] font-medium">Place entry before round starts</span>
            </div>

            <!-- Amount Input & Quick Chips -->
            <div class="flex items-center gap-1.5 mb-2">
                <div class="relative flex-1">
                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold">₹</span>
                    <input type="number" 
                           id="crashAmountInput" 
                           value="100" 
                           min="10" 
                           step="10"
                           class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-6 pr-2 py-1.5 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:bg-white"
                           placeholder="Amount">
                </div>

                <div class="flex items-center gap-1 shrink-0">
                    <button type="button" onclick="adjustAmount(10)" class="px-2 py-1.5 rounded-md bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-700 text-[10px] font-bold transition">+10</button>
                    <button type="button" onclick="adjustAmount(50)" class="px-2 py-1.5 rounded-md bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-700 text-[10px] font-bold transition">+50</button>
                    <button type="button" onclick="adjustAmount(100)" class="px-2 py-1.5 rounded-md bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-700 text-[10px] font-bold transition">+100</button>
                    <button type="button" onclick="multiplyAmount(2)" class="px-2 py-1.5 rounded-md bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-700 text-[10px] font-bold transition">2X</button>
                </div>
            </div>

            <!-- Primary Action Button (State Transitions: ENTER ROUND -> WAITING -> CASH OUT -> CASHED OUT) -->
            <button type="button" 
                    id="crashActionBtn"
                    onclick="handleActionButton()"
                    class="w-full py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-black text-xs sm:text-sm uppercase tracking-wider shadow-sm transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                <span id="actionBtnText">ENTER ROUND (₹100)</span>
            </button>
        </div>

        <!-- 5. History Section (ONLY THIS AREA SCROLLS) -->
        <div class="w-full flex-1 flex flex-col min-h-0 bg-white rounded-xl shadow-xs border border-slate-200/90 p-2 my-0.5">
            <!-- Navigation Tabs: My History vs Game History -->
            <div class="flex items-center justify-between pb-1 mb-1 border-b border-slate-100 shrink-0">
                <div class="flex items-center gap-1 text-[11px] font-black">
                    <button type="button" id="tabMyHistory" onclick="switchTab('my')" class="px-2.5 py-0.5 rounded-lg bg-blue-600 text-white transition">
                        MY HISTORY
                    </button>
                    <button type="button" id="tabGameHistory" onclick="switchTab('game')" class="px-2.5 py-0.5 rounded-lg text-slate-600 hover:bg-slate-100 transition">
                        GAME HISTORY
                    </button>
                </div>
                <span class="text-[9px] text-slate-400 font-medium">Real MySQL Records</span>
            </div>

            <!-- Internal Scroll Container for History Entries -->
            <div id="crashHistoryScroll" class="flex-1 overflow-y-auto space-y-1 pr-1 text-xs divide-y divide-slate-50">
                <!-- Dynamically populated via AJAX -->
                <div class="py-2 text-center text-slate-400 text-[10px]">Loading history...</div>
            </div>
        </div>

    </div>

    <!-- 6. Fixed Bottom 4-Option Navigation Bar -->
    <?php require_once __DIR__ . '/includes/user_nav.php'; ?>

    <!-- Plain JavaScript Game Client (Zero Framework, Pure AJAX & CSS/DOM) -->
    <script>
        const IS_LOGGED_IN = <?= $currentUser ? 'true' : 'false' ?>;
        let activeRound = null;
        let userActiveBet = null;
        let pollTimer = null;
        let localMultiplierTimer = null;
        let currentTab = 'my';
        let latestMyHistory = [];
        let latestRecentCrashes = [];
        let isCashingOut = false;
        let serverTimeOffset = 0;

        function adjustAmount(val) {
            const input = document.getElementById('crashAmountInput');
            let cur = parseFloat(input.value) || 0;
            input.value = cur + val;
            updateBtnText();
        }

        function multiplyAmount(mult) {
            const input = document.getElementById('crashAmountInput');
            let cur = parseFloat(input.value) || 0;
            input.value = Math.max(10, Math.floor(cur * mult));
            updateBtnText();
        }

        function updateBtnText() {
            const amt = parseFloat(document.getElementById('crashAmountInput').value) || 0;
            const btn = document.getElementById('crashActionBtn');
            const btnText = document.getElementById('actionBtnText');

            if (!activeRound || activeRound.status === 'waiting') {
                if (userActiveBet && userActiveBet.status === 'pending') {
                    btnText.textContent = 'ENTRY PLACED (₹' + parseFloat(userActiveBet.amount).toFixed(0) + ')';
                    btn.className = 'w-full py-2.5 rounded-xl bg-amber-500 text-white font-black text-xs sm:text-sm uppercase tracking-wider shadow-sm flex items-center justify-center gap-2 cursor-default';
                    btn.disabled = true;
                } else {
                    btnText.textContent = 'ENTER ROUND (₹' + amt.toFixed(0) + ')';
                    btn.className = 'w-full py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-black text-xs sm:text-sm uppercase tracking-wider shadow-sm transition flex items-center justify-center gap-2 cursor-pointer';
                    btn.disabled = false;
                }
            }
        }

        // Primary Action Button Router
        function handleActionButton() {
            if (!IS_LOGGED_IN) {
                window.location.href = '/login.php?redirect=/crash-game.php';
                return;
            }

            if (!activeRound) return;

            // Scenario 1: WAITING -> Place Bet
            if (activeRound.status === 'waiting') {
                placeBet();
                return;
            }

            // Scenario 2: RUNNING -> Cash Out
            if (activeRound.status === 'running' && userActiveBet && userActiveBet.status === 'pending') {
                cashOut();
                return;
            }
        }

        function placeBet() {
            const amt = parseFloat(document.getElementById('crashAmountInput').value) || 0;
            if (amt < 10) {
                alert('Minimum entry is ₹10.00');
                return;
            }

            const btn = document.getElementById('crashActionBtn');
            const btnText = document.getElementById('actionBtnText');
            btn.disabled = true;
            btnText.textContent = 'PLACING ENTRY...';

            const fd = new FormData();
            fd.append('amount', amt);

            fetch('/api/place_crash_bet.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.new_balance !== undefined) {
                        const balEl = document.getElementById('userBalanceDisplay');
                        if (balEl) balEl.textContent = parseFloat(data.new_balance).toFixed(2);
                    }
                    userActiveBet = data.bet;
                    updateBtnText();
                    fetchCrashState();
                } else {
                    alert(data.message || 'Could not place entry.');
                    btn.disabled = false;
                    updateBtnText();
                }
            })
            .catch(err => {
                btn.disabled = false;
                updateBtnText();
                console.error(err);
            });
        }

        function cashOut() {
            if (isCashingOut) return;
            isCashingOut = true;

            const btn = document.getElementById('crashActionBtn');
            const btnText = document.getElementById('actionBtnText');
            btnText.textContent = 'CASHING OUT...';

            const fd = new FormData();
            if (userActiveBet) fd.append('bet_id', userActiveBet.id);

            fetch('/api/cashout_crash_bet.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                isCashingOut = false;
                if (data.success) {
                    if (data.new_balance !== undefined) {
                        const balEl = document.getElementById('userBalanceDisplay');
                        if (balEl) balEl.textContent = parseFloat(data.new_balance).toFixed(2);
                    }
                    if (userActiveBet) {
                        userActiveBet.status = 'cashed_out';
                        userActiveBet.cashed_out_multiplier = data.multiplier;
                        userActiveBet.win_amount = data.win_amount;
                    }
                    btnText.textContent = 'CASHED OUT AT ' + parseFloat(data.multiplier).toFixed(2) + 'x (+₹' + parseFloat(data.win_amount).toFixed(2) + ')';
                    btn.className = 'w-full py-2.5 rounded-xl bg-emerald-600 text-white font-black text-xs sm:text-sm uppercase tracking-wider shadow-sm flex items-center justify-center gap-2 cursor-default';
                    btn.disabled = true;

                    fetchCrashState();
                } else {
                    alert(data.message || 'Could not cash out.');
                }
            })
            .catch(err => {
                isCashingOut = false;
                console.error(err);
            });
        }

        function switchTab(tab) {
            currentTab = tab;
            const myBtn = document.getElementById('tabMyHistory');
            const gameBtn = document.getElementById('tabGameHistory');

            if (tab === 'my') {
                myBtn.className = 'px-2.5 py-0.5 rounded-lg bg-blue-600 text-white transition';
                gameBtn.className = 'px-2.5 py-0.5 rounded-lg text-slate-600 hover:bg-slate-100 transition';
                renderMyHistory(latestMyHistory);
            } else {
                gameBtn.className = 'px-2.5 py-0.5 rounded-lg bg-blue-600 text-white transition';
                myBtn.className = 'px-2.5 py-0.5 rounded-lg text-slate-600 hover:bg-slate-100 transition';
                renderGameHistory(latestRecentCrashes);
            }
        }

        // Render My History (Real MySQL Data)
        function renderMyHistory(history) {
            const listEl = document.getElementById('crashHistoryScroll');
            if (!IS_LOGGED_IN) {
                listEl.innerHTML = '<div class="py-3 text-center text-slate-400 text-[11px]"><a href="/login.php?redirect=/crash-game.php" class="text-blue-600 font-bold hover:underline">Log in</a> to view your real game entries.</div>';
                return;
            }

            if (!history || history.length === 0) {
                listEl.innerHTML = '<div class="py-3 text-center text-slate-400 text-[10px]">No entries placed yet. Enter the next flight above.</div>';
                return;
            }

            let html = '';
            history.forEach(item => {
                let badge = '<span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-100 text-amber-800">PENDING</span>';
                if (item.result === 'WIN') {
                    badge = '<span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800">WIN (₹' + parseFloat(item.win_amount || 0).toFixed(2) + ')</span>';
                } else if (item.result === 'LOSE') {
                    badge = '<span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-100 text-rose-800">CRASHED (LOSE)</span>';
                }

                html += `
                    <div class="flex items-center justify-between py-1 px-1.5 hover:bg-slate-50 rounded-lg transition text-[11px]">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono font-black text-slate-800">#${item.round_number}</span>
                            <span class="text-slate-300">|</span>
                            <span class="font-mono text-slate-600">₹${parseFloat(item.amount).toFixed(0)}</span>
                            ${item.cashed_out_multiplier ? '<span class="font-mono font-bold text-emerald-600 text-[10px]">@ ' + parseFloat(item.cashed_out_multiplier).toFixed(2) + 'x</span>' : ''}
                        </div>
                        <div>
                            ${badge}
                        </div>
                    </div>
                `;
            });
            listEl.innerHTML = html;
        }

        // Render Recent Game Crashes
        function renderGameHistory(history) {
            const listEl = document.getElementById('crashHistoryScroll');
            if (!history || history.length === 0) {
                listEl.innerHTML = '<div class="py-3 text-center text-slate-400 text-[10px]">No flights recorded yet.</div>';
                return;
            }

            let html = '';
            history.forEach(item => {
                const mult = parseFloat(item.crash_multiplier || 1.0);
                let badgeClass = 'bg-blue-100 text-blue-800';
                if (mult >= 10.0) badgeClass = 'bg-purple-100 text-purple-800 font-black';
                else if (mult >= 2.0) badgeClass = 'bg-emerald-100 text-emerald-800 font-black';

                html += `
                    <div class="flex items-center justify-between py-1 px-1.5 hover:bg-slate-50 rounded-lg transition text-[11px]">
                        <span class="font-mono font-bold text-slate-800">Round #${item.round_number}</span>
                        <span class="font-mono px-2 py-0.5 rounded-full text-[10px] ${badgeClass}">
                            ${mult.toFixed(2)}x
                        </span>
                    </div>
                `;
            });
            listEl.innerHTML = html;
        }

        // Render top ribbon
        function renderRecentRibbon(history) {
            const ribbon = document.getElementById('recentRibbon');
            if (!history || history.length === 0) return;

            let html = '';
            history.slice(0, 10).forEach(item => {
                const mult = parseFloat(item.crash_multiplier || 1.0);
                let badgeClass = 'bg-blue-100 text-blue-800 border-blue-200';
                if (mult >= 10.0) badgeClass = 'bg-purple-100 text-purple-900 border-purple-300 font-black';
                else if (mult >= 2.0) badgeClass = 'bg-emerald-100 text-emerald-900 border-emerald-300 font-bold';

                html += `
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-mono border ${badgeClass} shrink-0">
                        ${mult.toFixed(2)}x
                    </span>
                `;
            });
            ribbon.innerHTML = html;
        }

        // Live Server Polling & Flight Synchronization
        function fetchCrashState() {
            fetch('/api/crash_game_status.php')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                if (data.server_time) {
                    const serverMs = new Date(data.server_time.replace(' ', 'T')).getTime();
                    if (!isNaN(serverMs)) {
                        serverTimeOffset = Date.now() - serverMs;
                    }
                }

                activeRound = data.round;
                userActiveBet = data.user ? data.user.active_bet : null;

                if (data.user && data.user.balance !== undefined) {
                    const balEl = document.getElementById('userBalanceDisplay');
                    if (balEl) balEl.textContent = parseFloat(data.user.balance).toFixed(2);
                }

                // Update Round Number
                document.getElementById('displayRoundNumber').textContent = 'ROUND #' + activeRound.round_number;

                // Update Phase & UI Controls
                const badge = document.getElementById('phaseBadge');
                const subtext = document.getElementById('statusSubtext');
                const multText = document.getElementById('multiplierText');
                const btn = document.getElementById('crashActionBtn');
                const btnText = document.getElementById('actionBtnText');
                const aircraft = document.getElementById('aircraftContainer');
                const svg = document.getElementById('aircraftSvg');
                const exhaust = document.getElementById('exhaustGlow');

                if (activeRound.status === 'waiting') {
                    badge.textContent = 'WAITING';
                    badge.className = 'px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-400/30';
                    subtext.textContent = 'Next flight in ' + (activeRound.waiting_remaining || 0) + 's';
                    multText.textContent = '1.00x';
                    multText.className = 'font-mono font-black text-4xl sm:text-5xl tracking-tighter text-white';

                    // Aircraft at runway baseline
                    aircraft.style.bottom = '1.5rem';
                    aircraft.style.left = '1.5rem';
                    svg.className = 'w-10 h-10 sm:w-12 sm:h-12 text-blue-400 transform -rotate-12 transition-all';
                    exhaust.classList.add('hidden');

                    updateBtnText();
                } else if (activeRound.status === 'running') {
                    badge.textContent = 'IN FLIGHT';
                    badge.className = 'px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 animate-pulse';
                    subtext.textContent = 'Ascending Multiplier...';
                    multText.textContent = parseFloat(activeRound.current_multiplier).toFixed(2) + 'x';
                    multText.className = 'font-mono font-black text-4xl sm:text-5xl tracking-tighter text-amber-300 drop-shadow-[0_4px_16px_rgba(251,191,36,0.6)]';

                    // Smooth ascent coordinates
                    const elapsed = activeRound.flight_elapsed || 0;
                    const progressX = Math.min(80, 10 + elapsed * 3.5);
                    const progressY = Math.min(75, 10 + elapsed * 3.0);
                    aircraft.style.bottom = progressY + '%';
                    aircraft.style.left = progressX + '%';
                    svg.className = 'w-10 h-10 sm:w-12 sm:h-12 text-rose-500 transform -rotate-25 filter drop-shadow-[0_2px_12px_rgba(244,63,94,0.8)]';
                    exhaust.classList.remove('hidden');

                    // Button Cash Out behavior
                    if (userActiveBet && userActiveBet.status === 'pending') {
                        const curWin = (parseFloat(userActiveBet.amount) * parseFloat(activeRound.current_multiplier)).toFixed(2);
                        btnText.textContent = 'CASH OUT (₹' + curWin + ')';
                        btn.className = 'w-full py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white font-black text-xs sm:text-sm uppercase tracking-wider shadow-lg animate-pulse flex items-center justify-center gap-2 cursor-pointer';
                        btn.disabled = false;
                    } else if (userActiveBet && userActiveBet.status === 'cashed_out') {
                        btnText.textContent = 'CASHED OUT AT ' + parseFloat(userActiveBet.cashed_out_multiplier).toFixed(2) + 'x';
                        btn.className = 'w-full py-2.5 rounded-xl bg-emerald-700 text-white font-black text-xs sm:text-sm uppercase tracking-wider shadow-xs flex items-center justify-center gap-2 cursor-default';
                        btn.disabled = true;
                    } else {
                        btnText.textContent = 'FLIGHT IN PROGRESS...';
                        btn.className = 'w-full py-2.5 rounded-xl bg-slate-200 text-slate-500 font-black text-xs sm:text-sm uppercase tracking-wider shadow-xs flex items-center justify-center gap-2 cursor-not-allowed';
                        btn.disabled = true;
                    }
                } else if (activeRound.status === 'crashed') {
                    badge.textContent = 'CRASHED';
                    badge.className = 'px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-400/30';
                    const finalCrash = parseFloat(activeRound.crash_multiplier || activeRound.current_multiplier).toFixed(2);
                    subtext.textContent = 'FLEW AWAY AT ' + finalCrash + 'x';
                    multText.textContent = finalCrash + 'x';
                    multText.className = 'font-mono font-black text-4xl sm:text-5xl tracking-tighter text-rose-500 drop-shadow-[0_4px_16px_rgba(244,63,94,0.8)]';

                    // Aircraft flies off screen
                    aircraft.style.bottom = '95%';
                    aircraft.style.left = '95%';
                    svg.className = 'w-10 h-10 sm:w-12 sm:h-12 text-slate-500 opacity-20 transform -rotate-45';
                    exhaust.classList.add('hidden');

                    if (userActiveBet && userActiveBet.status === 'cashed_out') {
                        btnText.textContent = 'WON ₹' + parseFloat(userActiveBet.win_amount).toFixed(2);
                        btn.className = 'w-full py-2.5 rounded-xl bg-emerald-600 text-white font-black text-xs sm:text-sm uppercase tracking-wider shadow-xs flex items-center justify-center gap-2 cursor-default';
                    } else {
                        btnText.textContent = 'ROUND CRASHED';
                        btn.className = 'w-full py-2.5 rounded-xl bg-rose-600/90 text-white font-black text-xs sm:text-sm uppercase tracking-wider shadow-xs flex items-center justify-center gap-2 cursor-default';
                    }
                    btn.disabled = true;
                }

                // Update History
                latestMyHistory = data.my_history || [];
                latestRecentCrashes = data.recent_crashes || [];

                renderRecentRibbon(latestRecentCrashes);

                if (currentTab === 'my') {
                    renderMyHistory(latestMyHistory);
                } else {
                    renderGameHistory(latestRecentCrashes);
                }
            })
            .catch(err => console.error('Crash poll error:', err));
        }

        document.addEventListener('DOMContentLoaded', () => {
            fetchCrashState();
            pollTimer = setInterval(fetchCrashState, 1500);

            // High frequency local multiplier ticker during flight
            localMultiplierTimer = setInterval(() => {
                if (activeRound && activeRound.status === 'running') {
                    let startMs = 0;
                    if (activeRound.flight_start_ts) {
                        startMs = activeRound.flight_start_ts * 1000;
                    } else if (activeRound.flight_start_time) {
                        startMs = new Date(activeRound.flight_start_time.replace(' ', 'T')).getTime();
                    }
                    if (startMs > 0) {
                        const nowServerMs = Date.now() - serverTimeOffset;
                        const elapsed = Math.max(0, (nowServerMs - startMs) / 1000);
                        const m = Math.max(1.00, Math.floor(Math.exp(0.06 * elapsed) * 100) / 100);
                        document.getElementById('multiplierText').textContent = m.toFixed(2) + 'x';

                        // Dynamically update cash out button with live win amount
                        if (userActiveBet && userActiveBet.status === 'pending') {
                            const curWin = (parseFloat(userActiveBet.amount) * m).toFixed(2);
                            const btnText = document.getElementById('actionBtnText');
                            if (btnText && btnText.textContent.startsWith('CASH OUT')) {
                                btnText.textContent = 'CASH OUT (₹' + curWin + ')';
                            }
                        }

                        // Dynamically update aircraft smooth coordinates
                        const progressX = Math.min(80, 10 + elapsed * 3.5);
                        const progressY = Math.min(75, 10 + elapsed * 3.0);
                        const aircraft = document.getElementById('aircraftContainer');
                        if (aircraft) {
                            aircraft.style.bottom = progressY + '%';
                            aircraft.style.left = progressX + '%';
                        }
                    }
                }
            }, 80);
        });
    </script>
</body>
</html>
