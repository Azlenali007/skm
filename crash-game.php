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
        <div id="flightArena" class="w-full flex-1 min-h-[170px] sm:min-h-[210px] max-h-[230px] rounded-2xl relative overflow-hidden shadow-md border border-slate-800 bg-gradient-to-b from-slate-950 via-slate-900 to-blue-950 shrink-0 flex flex-col justify-between p-3 select-none">
            
            <!-- Flight Path Canvas: Visible smooth upward curved path with glowing fill -->
            <canvas id="flightCanvas" class="absolute inset-0 w-full h-full pointer-events-none z-0"></canvas>

            <!-- Radar grid lines background -->
            <div class="absolute inset-0 opacity-15 pointer-events-none z-0" style="background-image: radial-gradient(#38bdf8 1px, transparent 1px); background-size: 24px 24px;"></div>
            
            <!-- Top Arena Info Row: Round Number & Current Phase Badge -->
            <div class="relative z-20 flex items-center justify-between text-xs pointer-events-none">
                <div class="flex items-center gap-1.5">
                    <span id="liveStatusDot" class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span id="displayRoundNumber" class="font-mono font-black text-white text-[11px] sm:text-xs tracking-wider">
                        ROUND #<?= e($activeRound['round_number']) ?>
                    </span>
                </div>
                <div id="phaseBadge" class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-blue-500/20 text-blue-300 border border-blue-400/30">
                    WAITING
                </div>
            </div>

            <!-- Central Multiplier & Aircraft Display Area -->
            <div class="relative z-20 my-auto text-center flex flex-col items-center justify-center pointer-events-none">
                <!-- Big Live Multiplier Display -->
                <div id="multiplierText" class="font-mono font-black text-4xl sm:text-5xl tracking-tighter text-white drop-shadow-[0_4px_12px_rgba(59,130,246,0.5)] transition-colors duration-150">
                    1.00x
                </div>

                <!-- Status Subtext (e.g. "WAITING FOR LAUNCH (04s)", "FLEW AWAY!", "IN FLIGHT") -->
                <div id="statusSubtext" class="text-xs font-bold text-blue-300/80 mt-1 uppercase tracking-wider">
                    Waiting for Next Round...
                </div>
            </div>

            <!-- Exactly ONE Single Animated Aircraft Element (Positioned along the flight path) -->
            <div id="aircraftContainer" class="absolute top-0 left-0 z-10 pointer-events-none will-change-transform" style="transform: translate3d(0, 0, 0); opacity: 1;">
                <div class="relative flex items-center justify-center">
                    <!-- Jet SVG Icon -->
                    <svg id="aircraftSvg" class="w-10 h-10 sm:w-12 sm:h-12 text-rose-500 filter drop-shadow-[0_2px_10px_rgba(244,63,94,0.7)] transition-colors duration-150" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                    </svg>
                    <!-- Jet Exhaust Flame -->
                    <div id="exhaustGlow" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-3.5 h-3.5 rounded-full bg-gradient-to-t from-amber-400 via-rose-500 to-transparent blur-[1px] animate-pulse hidden"></div>
                </div>
            </div>

            <!-- Bottom Flight Horizon Indicator -->
            <div class="relative z-20 flex items-center justify-between text-[10px] text-slate-400 border-t border-white/10 pt-1 pointer-events-none">
                <span>Flight Arena: <strong class="text-slate-200 font-mono">Real-Time</strong></span>
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
        let animFrameId = null;
        let isAnimationActive = false;
        let isFlightInitialized = false;
        let flightStartPerf = 0;
        let lastPlaneCoords = { x: 30, y: 170, tilt: 18 };
        let crashSnapshot = null;
        let flightCanvas = null;
        let flightCtx = null;
        let arenaWidth = 360;
        let arenaHeight = 200;
        let currentRoundNumber = null;
        let currentTab = 'my';
        let latestMyHistory = [];
        let latestRecentCrashes = [];
        let isCashingOut = false;

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

        // Canvas Resizing for Responsive Mobile/Tablet/Desktop Arena
        function resizeCanvas() {
            const arena = document.getElementById('flightArena');
            if (!arena || !flightCanvas || !flightCtx) return;
            const rect = arena.getBoundingClientRect();
            arenaWidth = Math.max(280, rect.width);
            arenaHeight = Math.max(160, rect.height);
            const dpr = window.devicePixelRatio || 1;
            flightCanvas.width = Math.floor(arenaWidth * dpr);
            flightCanvas.height = Math.floor(arenaHeight * dpr);
            flightCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }

        // Mathematical Flight Coordinate Model: Strictly Continuous & Proportional
        function getFlightCoordinates(multiplier, elapsed, W, H) {
            const x0 = W * 0.08;
            const y0 = H * 0.82;
            const maxX = W * 0.82;
            const minY = H * 0.20;

            const m = Math.max(1.0, multiplier);
            
            // Continuous upward climb from 1.00x through 50x+
            const p = Math.min(0.97, 1 - Math.exp(-0.35 * Math.log(m) - 0.08 * (m - 1) / (1 + 0.04 * m)));

            // Aerodynamic flight wave (hover, climb, dynamic banking) so aircraft is ALWAYS dynamically flying
            const waveX = Math.sin(elapsed * 2.8) * (W * 0.015) + Math.cos(elapsed * 1.4) * (W * 0.008);
            const waveY = Math.cos(elapsed * 2.4) * (H * 0.022) + Math.sin(elapsed * 1.6) * (H * 0.012);

            const x = x0 + (maxX - x0) * p + waveX;
            const y = y0 - (y0 - minY) * p + waveY;

            // Bezier trajectory control points for tangent alignment
            const cp1X = x0 + (x - x0) * 0.42;
            const cp1Y = y0;
            const cp2X = x0 + (x - x0) * 0.78;
            const cp2Y = y + (y0 - y) * 0.28;

            const dx = Math.max(0.001, x - cp2X);
            const dy = y - cp2Y;
            const angleRad = Math.atan2(dy, dx);
            const tilt = (angleRad * 180 / Math.PI) + 90;

            return { x, y, x0, y0, cp1X, cp1Y, cp2X, cp2Y, tilt, p };
        }

        // Draw Smooth Ascending Flight Curve & Translucent Gradient Fill
        function drawFlightPath(coord, W, H) {
            if (!flightCtx || !coord) return;
            flightCtx.clearRect(0, 0, W, H);

            const x0 = coord.x0;
            const y0 = coord.y0;
            const x = coord.x;
            const y = coord.y;

            // 1. Subtle dotted runway guideline
            flightCtx.save();
            flightCtx.beginPath();
            flightCtx.moveTo(x0 - 20, y0);
            flightCtx.lineTo(W * 0.96, y0);
            flightCtx.strokeStyle = 'rgba(255, 255, 255, 0.08)';
            flightCtx.setLineDash([4, 4]);
            flightCtx.lineWidth = 1;
            flightCtx.stroke();
            flightCtx.restore();

            // 2. Translucent Gradient Fill Under the Rising Curve
            flightCtx.save();
            flightCtx.beginPath();
            flightCtx.moveTo(x0, y0);
            flightCtx.bezierCurveTo(coord.cp1X, coord.cp1Y, coord.cp2X, coord.cp2Y, x, y);
            flightCtx.lineTo(x, y0);
            flightCtx.lineTo(x0, y0);
            flightCtx.closePath();

            const grad = flightCtx.createLinearGradient(0, y, 0, y0);
            grad.addColorStop(0, 'rgba(244, 63, 94, 0.35)');
            grad.addColorStop(0.55, 'rgba(244, 63, 94, 0.12)');
            grad.addColorStop(1, 'rgba(244, 63, 94, 0.00)');
            flightCtx.fillStyle = grad;
            flightCtx.fill();
            flightCtx.restore();

            // 3. Glowing Red/Rose Trajectory Curve Line
            flightCtx.save();
            flightCtx.beginPath();
            flightCtx.moveTo(x0, y0);
            flightCtx.bezierCurveTo(coord.cp1X, coord.cp1Y, coord.cp2X, coord.cp2Y, x, y);
            flightCtx.strokeStyle = '#f43f5e';
            flightCtx.lineWidth = 3.5;
            flightCtx.lineCap = 'round';
            flightCtx.lineJoin = 'round';
            flightCtx.shadowColor = 'rgba(244, 63, 94, 0.85)';
            flightCtx.shadowBlur = 12;
            flightCtx.stroke();
            flightCtx.restore();
        }

        // Draw Frozen Flight Path with Crash Explosion Burst
        function drawFrozenCrashPath(snapshot, W, H, crashProgress) {
            if (!flightCtx || !snapshot) return;
            flightCtx.clearRect(0, 0, W, H);

            const x0 = W * 0.08;
            const y0 = H * 0.82;
            const x = snapshot.x;
            const y = snapshot.y;

            // Runway guideline
            flightCtx.save();
            flightCtx.beginPath();
            flightCtx.moveTo(x0 - 20, y0);
            flightCtx.lineTo(W * 0.96, y0);
            flightCtx.strokeStyle = 'rgba(255, 255, 255, 0.08)';
            flightCtx.setLineDash([4, 4]);
            flightCtx.lineWidth = 1;
            flightCtx.stroke();
            flightCtx.restore();

            // Frozen trajectory curve
            const cp1X = x0 + (x - x0) * 0.42;
            const cp1Y = y0;
            const cp2X = x0 + (x - x0) * 0.78;
            const cp2Y = y + (y0 - y) * 0.28;

            flightCtx.save();
            flightCtx.beginPath();
            flightCtx.moveTo(x0, y0);
            flightCtx.bezierCurveTo(cp1X, cp1Y, cp2X, cp2Y, x, y);
            flightCtx.lineTo(x, y0);
            flightCtx.lineTo(x0, y0);
            flightCtx.closePath();
            const grad = flightCtx.createLinearGradient(0, y, 0, y0);
            grad.addColorStop(0, 'rgba(239, 68, 68, 0.25)');
            grad.addColorStop(1, 'rgba(239, 68, 68, 0.00)');
            flightCtx.fillStyle = grad;
            flightCtx.fill();
            flightCtx.restore();

            flightCtx.save();
            flightCtx.beginPath();
            flightCtx.moveTo(x0, y0);
            flightCtx.bezierCurveTo(cp1X, cp1Y, cp2X, cp2Y, x, y);
            flightCtx.strokeStyle = '#ef4444';
            flightCtx.lineWidth = 3.5;
            flightCtx.lineCap = 'round';
            flightCtx.stroke();
            flightCtx.restore();

            // Expanding crash shockwave ring & impact point
            flightCtx.save();
            const ringR = 6 + crashProgress * 22;
            const ringAlpha = Math.max(0, 1 - crashProgress);
            flightCtx.beginPath();
            flightCtx.arc(x, y, ringR, 0, Math.PI * 2);
            flightCtx.strokeStyle = `rgba(244, 63, 94, ${ringAlpha})`;
            flightCtx.lineWidth = 2.5;
            flightCtx.stroke();

            flightCtx.beginPath();
            flightCtx.arc(x, y, 6, 0, Math.PI * 2);
            flightCtx.fillStyle = '#ef4444';
            flightCtx.shadowColor = 'rgba(239, 68, 68, 0.9)';
            flightCtx.shadowBlur = 10;
            flightCtx.fill();
            flightCtx.restore();
        }

        // Clean Single Animation Lifecycle (Exactly ONE Active Animation Loop)
        function startFlightAnimation() {
            stopFlightAnimation();
            isAnimationActive = true;
            animFrameId = requestAnimationFrame(updateFlightAnimation);
        }

        function stopFlightAnimation() {
            isAnimationActive = false;
            if (animFrameId !== null) {
                cancelAnimationFrame(animFrameId);
                animFrameId = null;
            }
        }

        function updateFlightAnimation(timestamp) {
            if (!isAnimationActive) return;

            const W = arenaWidth;
            const H = arenaHeight;
            const aircraft = document.getElementById('aircraftContainer');
            const svg = document.getElementById('aircraftSvg');
            const exhaust = document.getElementById('exhaustGlow');
            const multText = document.getElementById('multiplierText');

            if (!activeRound || activeRound.status === 'waiting') {
                // WAITING STATE: Exactly ONE aircraft resting ready on runway
                const x0 = W * 0.08;
                const y0 = H * 0.82;
                lastPlaneCoords = { x: x0, y: y0, tilt: 18 };
                crashSnapshot = null;

                if (flightCtx) {
                    flightCtx.clearRect(0, 0, W, H);
                    flightCtx.save();
                    flightCtx.beginPath();
                    flightCtx.moveTo(x0 - 20, y0);
                    flightCtx.lineTo(W * 0.96, y0);
                    flightCtx.strokeStyle = 'rgba(255, 255, 255, 0.08)';
                    flightCtx.setLineDash([4, 4]);
                    flightCtx.lineWidth = 1;
                    flightCtx.stroke();
                    flightCtx.restore();
                }

                if (aircraft) {
                    aircraft.style.transform = `translate3d(${x0}px, ${y0}px, 0) translate(-50%, -50%) rotate(18deg)`;
                    aircraft.style.opacity = '1';
                }
                if (svg) svg.className = 'w-10 h-10 sm:w-12 sm:h-12 text-blue-400 filter drop-shadow-[0_2px_8px_rgba(59,130,246,0.6)]';
                if (exhaust) exhaust.classList.add('hidden');
                if (multText) {
                    multText.textContent = '1.00x';
                    multText.className = 'font-mono font-black text-4xl sm:text-5xl tracking-tighter text-white drop-shadow-[0_4px_12px_rgba(59,130,246,0.5)]';
                }

            } else if (activeRound.status === 'running') {
                // RUNNING STATE: Continuous active flight along rising trajectory
                crashSnapshot = null;

                if (!isFlightInitialized) {
                    const serverElapsed = parseFloat(activeRound.flight_elapsed) || 0;
                    flightStartPerf = performance.now() - (serverElapsed * 1000);
                    isFlightInitialized = true;
                }

                const elapsed = Math.max(0, (performance.now() - flightStartPerf) / 1000);
                const m = Math.max(1.00, Math.floor(Math.exp(0.06 * elapsed) * 100) / 100);

                // 1. Live Multiplier Display
                if (multText) {
                    multText.textContent = m.toFixed(2) + 'x';
                    multText.className = 'font-mono font-black text-4xl sm:text-5xl tracking-tighter text-amber-300 drop-shadow-[0_4px_16px_rgba(251,191,36,0.6)]';
                }

                // 2. Live Cashout Button Multiplier & Current Payout
                if (userActiveBet && userActiveBet.status === 'pending') {
                    const curWin = (parseFloat(userActiveBet.amount) * m).toFixed(2);
                    const btn = document.getElementById('crashActionBtn');
                    const btnText = document.getElementById('actionBtnText');
                    if (btnText && btn) {
                        btnText.textContent = 'CASH OUT ' + m.toFixed(2) + 'x (₹' + curWin + ')';
                        btn.className = 'w-full py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white font-black text-xs sm:text-sm uppercase tracking-wider shadow-lg animate-pulse flex items-center justify-center gap-2 cursor-pointer';
                        btn.disabled = false;
                    }
                }

                // 3. Smooth Flight Path & Coordinates
                const coord = getFlightCoordinates(m, elapsed, W, H);
                lastPlaneCoords = coord;
                drawFlightPath(coord, W, H);

                // 4. Update the Single Aircraft Position & Rotation
                if (aircraft) {
                    aircraft.style.transform = `translate3d(${coord.x}px, ${coord.y}px, 0) translate(-50%, -50%) rotate(${coord.tilt}deg)`;
                    aircraft.style.opacity = '1';
                }
                if (svg) svg.className = 'w-10 h-10 sm:w-12 sm:h-12 text-rose-500 filter drop-shadow-[0_2px_12px_rgba(244,63,94,0.85)]';
                if (exhaust) exhaust.classList.remove('hidden');

            } else if (activeRound.status === 'crashed') {
                // CRASHED STATE: Smooth crash fly-out animation & final result display
                isFlightInitialized = false;

                const finalCrash = parseFloat(activeRound.crash_multiplier || activeRound.current_multiplier || 1.0).toFixed(2);

                if (!crashSnapshot) {
                    crashSnapshot = {
                        x: lastPlaneCoords.x,
                        y: lastPlaneCoords.y,
                        tilt: lastPlaneCoords.tilt,
                        multiplier: finalCrash,
                        startTime: performance.now()
                    };
                }

                const elapsedSinceCrash = (performance.now() - crashSnapshot.startTime) / 1000;
                const crashProgress = Math.min(1.0, elapsedSinceCrash / 0.65);

                // Draw frozen path with impact burst
                drawFrozenCrashPath(crashSnapshot, W, H, crashProgress);

                // Smooth fly-out crash for single aircraft
                if (aircraft) {
                    if (crashProgress < 1.0) {
                        const flyX = crashSnapshot.x + crashProgress * (W * 0.28);
                        const flyY = crashSnapshot.y - crashProgress * (H * 0.22) + (crashProgress * crashProgress) * (H * 0.12);
                        const flyScale = Math.max(0.1, 1 - crashProgress * 0.85);
                        const flyOpacity = Math.max(0, 1 - crashProgress * 1.5);
                        aircraft.style.transform = `translate3d(${flyX}px, ${flyY}px, 0) translate(-50%, -50%) scale(${flyScale}) rotate(${crashSnapshot.tilt - crashProgress * 35}deg)`;
                        aircraft.style.opacity = flyOpacity;
                    } else {
                        aircraft.style.opacity = '0';
                    }
                }

                if (exhaust) exhaust.classList.add('hidden');
                if (multText) {
                    multText.textContent = finalCrash + 'x';
                    multText.className = 'font-mono font-black text-4xl sm:text-5xl tracking-tighter text-rose-500 drop-shadow-[0_4px_16px_rgba(244,63,94,0.8)]';
                }
            }

            if (isAnimationActive) {
                animFrameId = requestAnimationFrame(updateFlightAnimation);
            }
        }

        // Live Server Polling & Flight State Synchronization
        function fetchCrashState() {
            fetch('/api/crash_game_status.php')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                // Detect round change to reset state cleanly for the new round
                if (currentRoundNumber !== null && currentRoundNumber !== data.round.round_number) {
                    crashSnapshot = null;
                    isFlightInitialized = false;
                }
                currentRoundNumber = data.round.round_number;

                activeRound = data.round;
                userActiveBet = data.user ? data.user.active_bet : null;

                if (data.user && data.user.balance !== undefined) {
                    const balEl = document.getElementById('userBalanceDisplay');
                    if (balEl) balEl.textContent = parseFloat(data.user.balance).toFixed(2);
                }

                // Smooth time reconciliation without restarting flight
                if (activeRound.status === 'running') {
                    const serverElapsed = parseFloat(activeRound.flight_elapsed) || 0;
                    const now = performance.now();
                    if (!isFlightInitialized) {
                        flightStartPerf = now - (serverElapsed * 1000);
                        isFlightInitialized = true;
                    } else {
                        const localElapsed = (now - flightStartPerf) / 1000;
                        if (Math.abs(localElapsed - serverElapsed) > 0.45) {
                            flightStartPerf = now - (serverElapsed * 1000);
                        }
                    }
                } else if (activeRound.status === 'waiting') {
                    isFlightInitialized = false;
                    crashSnapshot = null;
                }

                // Update Round Number Display
                const roundEl = document.getElementById('displayRoundNumber');
                if (roundEl) roundEl.textContent = 'ROUND #' + activeRound.round_number;

                // Update Phase Badge & Subtexts
                const badge = document.getElementById('phaseBadge');
                const subtext = document.getElementById('statusSubtext');
                const btn = document.getElementById('crashActionBtn');
                const btnText = document.getElementById('actionBtnText');
                const statusDot = document.getElementById('liveStatusDot');

                if (activeRound.status === 'waiting') {
                    if (badge) {
                        badge.textContent = 'WAITING';
                        badge.className = 'px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-400/30';
                    }
                    if (statusDot) statusDot.className = 'w-2 h-2 rounded-full bg-amber-400 animate-pulse';
                    if (subtext) subtext.textContent = 'Next flight in ' + (activeRound.waiting_remaining || 0) + 's';
                    updateBtnText();

                } else if (activeRound.status === 'running') {
                    if (badge) {
                        badge.textContent = 'IN FLIGHT';
                        badge.className = 'px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 animate-pulse';
                    }
                    if (statusDot) statusDot.className = 'w-2 h-2 rounded-full bg-emerald-400 animate-pulse';
                    if (subtext) subtext.textContent = 'Ascending Multiplier...';

                    if (userActiveBet && userActiveBet.status === 'pending') {
                        // Live multiplier text is continuously updated in updateFlightAnimation
                    } else if (userActiveBet && userActiveBet.status === 'cashed_out') {
                        if (btnText) btnText.textContent = 'CASHED OUT AT ' + parseFloat(userActiveBet.cashed_out_multiplier).toFixed(2) + 'x';
                        if (btn) {
                            btn.className = 'w-full py-2.5 rounded-xl bg-emerald-700 text-white font-black text-xs sm:text-sm uppercase tracking-wider shadow-xs flex items-center justify-center gap-2 cursor-default';
                            btn.disabled = true;
                        }
                    } else {
                        if (btnText) btnText.textContent = 'FLIGHT IN PROGRESS...';
                        if (btn) {
                            btn.className = 'w-full py-2.5 rounded-xl bg-slate-200 text-slate-500 font-black text-xs sm:text-sm uppercase tracking-wider shadow-xs flex items-center justify-center gap-2 cursor-not-allowed';
                            btn.disabled = true;
                        }
                    }

                } else if (activeRound.status === 'crashed') {
                    if (badge) {
                        badge.textContent = 'CRASHED';
                        badge.className = 'px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-400/30';
                    }
                    if (statusDot) statusDot.className = 'w-2 h-2 rounded-full bg-rose-500';
                    const finalCrash = parseFloat(activeRound.crash_multiplier || activeRound.current_multiplier || 1.0).toFixed(2);
                    if (subtext) subtext.textContent = 'FLEW AWAY AT ' + finalCrash + 'x';

                    if (userActiveBet && userActiveBet.status === 'cashed_out') {
                        if (btnText) btnText.textContent = 'WON ₹' + parseFloat(userActiveBet.win_amount).toFixed(2);
                        if (btn) {
                            btn.className = 'w-full py-2.5 rounded-xl bg-emerald-600 text-white font-black text-xs sm:text-sm uppercase tracking-wider shadow-xs flex items-center justify-center gap-2 cursor-default';
                            btn.disabled = true;
                        }
                    } else {
                        if (btnText) btnText.textContent = 'ROUND CRASHED';
                        if (btn) {
                            btn.className = 'w-full py-2.5 rounded-xl bg-rose-600/90 text-white font-black text-xs sm:text-sm uppercase tracking-wider shadow-xs flex items-center justify-center gap-2 cursor-default';
                            btn.disabled = true;
                        }
                    }
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
            flightCanvas = document.getElementById('flightCanvas');
            if (flightCanvas) {
                flightCtx = flightCanvas.getContext('2d');
                resizeCanvas();
                window.addEventListener('resize', resizeCanvas);
            }

            fetchCrashState();
            pollTimer = setInterval(fetchCrashState, 1200);

            // Clean single animation lifecycle: exactly ONE loop active
            startFlightAnimation();
        });
    </script>
</body>
</html>
