<?php
/**
 * Sikkim Gaming Platform - Colour Game Screen
 * Strictly NO PAGE SCROLLING: Fits within viewport.
 * ONLY Game History has an internal scroll container.
 * Fixed bottom 4-option navigation bar.
 * Real MySQL database, authoritative server timer, and instant win/lose audit.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/round_engine.php';

$pdo = getDB();
$gameSlug = 'colour-game';
$currentUser = getCurrentUser();
$userId = $currentUser ? (int)$currentUser['id'] : 0;

// Fetch initial active round
$activeRound = processAndGetActiveRound($pdo, $gameSlug);
$remainingSeconds = max(0, strtotime($activeRound['end_time']) - time());

// Fetch user wallet balance
$userBalance = 0.00;
if ($userId > 0) {
    $wStmt = $pdo->prepare("SELECT balance FROM `wallets` WHERE `user_id` = :uid LIMIT 1");
    $wStmt->execute([':uid' => $userId]);
    $userBalance = (float)$wStmt->fetchColumn();
}

$pageTitle = "Colour Game - Official Sikkim Platform";
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
</head>
<body class="bg-slate-100 text-slate-800 antialiased font-sans h-full overflow-hidden select-none">

    <!-- Non-Scrollable Main Game Viewport Wrapper -->
    <div class="h-full max-w-md sm:max-w-lg md:max-w-xl mx-auto flex flex-col justify-between overflow-hidden px-3 pt-2 pb-16 sm:pb-20">

        <!-- 1. Compact Top Bar Header -->
        <header class="w-full bg-white rounded-xl shadow-xs border border-slate-200/90 px-3 py-2 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <a href="/dashboard.php" class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition" title="Back to Dashboard">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-sm font-black tracking-tight text-slate-900 leading-none">Colour Game</h1>
                    <span class="text-[10px] font-semibold text-blue-600">45s Fast Prediction</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <?php if ($currentUser): ?>
                    <a href="/wallet.php" class="flex items-center gap-1.5 bg-blue-50 border border-blue-200/80 px-2.5 py-1 rounded-lg text-xs font-bold text-blue-800 hover:bg-blue-100 transition shadow-2xs">
                        <span class="text-blue-500 text-[10px]">₹</span>
                        <span id="userBalanceDisplay"><?= number_format($userBalance, 2) ?></span>
                    </a>
                <?php else: ?>
                    <a href="/login.php?redirect=/colour-game.php" class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition shadow-xs">
                        Log in
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- 2. Live Win/Lose Flash Announcement (Dynamic Alert Banner) -->
        <div id="outcomeAlert" class="hidden shrink-0 my-1 p-2 rounded-xl text-xs font-bold flex items-center justify-between shadow-xs transition-all duration-300">
            <div class="flex items-center gap-2 overflow-hidden truncate">
                <span id="outcomeIcon" class="w-5 h-5 rounded-full flex items-center justify-center shrink-0 text-white">✓</span>
                <span id="outcomeText" class="truncate">Round Outcome</span>
            </div>
            <button onclick="document.getElementById('outcomeAlert').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 text-sm px-1 font-bold">&times;</button>
        </div>

        <!-- 3. Current Round & Server Authoritative Countdown Card -->
        <div class="w-full bg-gradient-to-r from-blue-700 via-blue-600 to-indigo-700 text-white rounded-2xl p-3 shadow-md shrink-0 border border-blue-500/30">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-blue-200">Current Period</span>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span id="roundNumber" class="text-base sm:text-lg font-black tracking-wider text-white">
                            #<?= e($activeRound['round_number']) ?>
                        </span>
                    </div>
                </div>

                <div class="text-right">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-blue-200">Countdown</span>
                    <div class="flex items-center justify-end gap-1">
                        <div id="timerCard" class="bg-black/30 backdrop-blur-xs border border-white/20 px-3 py-1 rounded-xl font-mono text-xl sm:text-2xl font-black text-amber-300 tracking-wider">
                            00:<span id="countdown"><?= str_pad((string)$remainingSeconds, 2, '0', STR_PAD_LEFT) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lock Banner (shows in last 5 seconds) -->
            <div id="lockWarning" class="<?= ($remainingSeconds <= 5) ? '' : 'hidden' ?> mt-2 bg-amber-400/20 border border-amber-300/40 rounded-lg py-0.5 px-2 text-center text-[10px] font-bold text-amber-200 animate-pulse">
                ⏳ Period locked for settlement. Next round starting shortly...
            </div>
        </div>

        <!-- 4. Colour Options (Touch-Friendly Selectable Cards) -->
        <div class="w-full shrink-0 my-1">
            <div class="grid grid-cols-3 gap-2">
                <!-- RED OPTION -->
                <button type="button" 
                        onclick="selectColour('red', 2.0)"
                        id="btn-red"
                        class="colour-btn group relative flex flex-col items-center justify-center p-2.5 rounded-2xl bg-gradient-to-br from-rose-500 to-red-600 text-white shadow-md hover:brightness-105 active:scale-95 transition transform border border-red-400/40 cursor-pointer">
                    <span class="text-xs sm:text-sm font-black tracking-wide uppercase">RED</span>
                    <span class="text-[10px] font-extrabold bg-white/20 px-1.5 py-0.5 rounded-full mt-1">2.0X</span>
                    <!-- Selection Indicator -->
                    <div class="indicator hidden absolute -top-1 -right-1 w-5 h-5 bg-white text-red-600 rounded-full flex items-center justify-center shadow-md text-xs font-bold">✓</div>
                </button>

                <!-- GREEN OPTION -->
                <button type="button" 
                        onclick="selectColour('green', 2.0)"
                        id="btn-green"
                        class="colour-btn group relative flex flex-col items-center justify-center p-2.5 rounded-2xl bg-gradient-to-br from-emerald-500 to-green-600 text-white shadow-md hover:brightness-105 active:scale-95 transition transform border border-emerald-400/40 cursor-pointer">
                    <span class="text-xs sm:text-sm font-black tracking-wide uppercase">GREEN</span>
                    <span class="text-[10px] font-extrabold bg-white/20 px-1.5 py-0.5 rounded-full mt-1">2.0X</span>
                    <!-- Selection Indicator -->
                    <div class="indicator hidden absolute -top-1 -right-1 w-5 h-5 bg-white text-green-600 rounded-full flex items-center justify-center shadow-md text-xs font-bold">✓</div>
                </button>

                <!-- VIOLET OPTION -->
                <button type="button" 
                        onclick="selectColour('violet', 4.5)"
                        id="btn-violet"
                        class="colour-btn group relative flex flex-col items-center justify-center p-2.5 rounded-2xl bg-gradient-to-br from-purple-500 to-violet-600 text-white shadow-md hover:brightness-105 active:scale-95 transition transform border border-purple-400/40 cursor-pointer">
                    <span class="text-xs sm:text-sm font-black tracking-wide uppercase">VIOLET</span>
                    <span class="text-[10px] font-extrabold bg-white/20 px-1.5 py-0.5 rounded-full mt-1">4.5X</span>
                    <!-- Selection Indicator -->
                    <div class="indicator hidden absolute -top-1 -right-1 w-5 h-5 bg-white text-purple-600 rounded-full flex items-center justify-center shadow-md text-xs font-bold">✓</div>
                </button>
            </div>
        </div>

        <!-- 5. User Selection & Amount Input Panel -->
        <div class="w-full bg-white rounded-2xl shadow-xs border border-slate-200/90 p-2.5 shrink-0">
            <div class="flex items-center justify-between mb-1.5">
                <div class="text-xs">
                    <span class="text-slate-500 font-medium">Selected:</span>
                    <span id="selectedChoiceText" class="font-extrabold uppercase text-slate-400 ml-1">None</span>
                </div>
                <div class="text-xs">
                    <span class="text-slate-500 font-medium">Potential Payout:</span>
                    <span id="potentialWinText" class="font-extrabold text-blue-600 ml-1">₹0.00</span>
                </div>
            </div>

            <!-- Amount Input & Quick Chips -->
            <div class="flex items-center gap-1.5 mb-2">
                <div class="relative flex-1">
                    <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold">₹</span>
                    <input type="number" 
                           id="betAmount" 
                           value="100" 
                           min="10" 
                           step="10"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-6 pr-2 py-1.5 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:bg-white"
                           placeholder="Bet Amount"
                           oninput="updatePayoutCalculation()">
                </div>

                <!-- Quick amount chip buttons -->
                <div class="flex items-center gap-1 shrink-0">
                    <button type="button" onclick="setQuickAmount(10)" class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-600 text-[10px] font-bold transition">+10</button>
                    <button type="button" onclick="setQuickAmount(50)" class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-600 text-[10px] font-bold transition">+50</button>
                    <button type="button" onclick="setQuickAmount(100)" class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-600 text-[10px] font-bold transition">+100</button>
                    <button type="button" onclick="setQuickAmount(500)" class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-600 text-[10px] font-bold transition">+500</button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="button" 
                    id="submitBetBtn"
                    onclick="submitBet()"
                    class="w-full py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-black text-xs uppercase tracking-wider shadow-sm transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                <span id="submitBtnText">SUBMIT BET</span>
            </button>
        </div>

        <!-- 6. Game History (ONLY THIS AREA SCROLLS) -->
        <div class="w-full flex-1 flex flex-col min-h-0 bg-white rounded-2xl shadow-xs border border-slate-200/90 p-2.5 my-1">
            <div class="flex items-center justify-between pb-1.5 mb-1 border-b border-slate-100 shrink-0">
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                    <h3 class="text-[11px] font-black uppercase tracking-wider text-slate-800">GAME HISTORY</h3>
                </div>
                <span class="text-[10px] text-slate-400 font-medium">Scroll down to view more</span>
            </div>

            <!-- Internal Scroll Container for Game History -->
            <div id="historyList" class="flex-1 overflow-y-auto space-y-1.5 pr-1 text-xs divide-y divide-slate-50">
                <!-- Dynamically populated via AJAX with fallback placeholders -->
                <div class="py-2 text-center text-slate-400 text-[11px]">Loading verified rounds...</div>
            </div>
        </div>

    </div>

    <!-- 7. Fixed Bottom 4-Option Navigation Bar -->
    <?php require_once __DIR__ . '/includes/user_nav.php'; ?>

    <!-- Plain JavaScript Game Client (No framework, pure AJAX & DOM) -->
    <script>
        const IS_LOGGED_IN = <?= $currentUser ? 'true' : 'false' ?>;
        let selectedColor = null;
        let selectedMultiplier = 2.0;
        let activeRoundNumber = <?= (int)$activeRound['round_number'] ?>;
        let remainingSeconds = <?= (int)$remainingSeconds ?>;
        let isLocked = <?= ($remainingSeconds <= 5) ? 'true' : 'false' ?>;
        let countdownTimer = null;
        let pollingInterval = null;
        let lastNotifiedRound = null;

        // Select a colour card
        function selectColour(color, multiplier) {
            selectedColor = color;
            selectedMultiplier = multiplier;

            // Reset all buttons
            document.querySelectorAll('.colour-btn').forEach(btn => {
                btn.classList.remove('ring-4', 'ring-offset-2', 'ring-blue-600', 'scale-[1.03]');
                btn.querySelector('.indicator').classList.add('hidden');
            });

            // Highlight selected button
            const activeBtn = document.getElementById('btn-' + color);
            if (activeBtn) {
                activeBtn.classList.add('ring-4', 'ring-offset-2', 'ring-blue-600', 'scale-[1.03]');
                activeBtn.querySelector('.indicator').classList.remove('hidden');
            }

            // Update UI
            const choiceText = document.getElementById('selectedChoiceText');
            choiceText.textContent = color.toUpperCase();
            choiceText.className = 'font-black uppercase ml-1 ' + (color === 'red' ? 'text-red-600' : (color === 'green' ? 'text-green-600' : 'text-purple-600'));

            updatePayoutCalculation();
        }

        function setQuickAmount(amount) {
            const input = document.getElementById('betAmount');
            let current = parseFloat(input.value) || 0;
            input.value = current + amount;
            updatePayoutCalculation();
        }

        function updatePayoutCalculation() {
            const amount = parseFloat(document.getElementById('betAmount').value) || 0;
            const payout = (amount * selectedMultiplier).toFixed(2);
            document.getElementById('potentialWinText').textContent = '₹' + payout;
        }

        // Submit Bet to Server
        function submitBet() {
            if (!IS_LOGGED_IN) {
                window.location.href = '/login.php?redirect=/colour-game.php';
                return;
            }

            if (!selectedColor) {
                alert('Please select a colour first (Red, Green, or Violet).');
                return;
            }

            const amount = parseFloat(document.getElementById('betAmount').value) || 0;
            if (amount < 10) {
                alert('Minimum bet amount is ₹10.00');
                return;
            }

            if (isLocked) {
                alert('Period #' + activeRoundNumber + ' is locked for calculation. Please wait for the next round.');
                return;
            }

            const btn = document.getElementById('submitBetBtn');
            const btnText = document.getElementById('submitBtnText');
            btn.disabled = true;
            btnText.textContent = 'PLACING BET...';

            const formData = new FormData();
            formData.append('choice', selectedColor);
            formData.append('amount', amount);

            fetch('/api/place_colour_bet.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btnText.textContent = 'SUBMIT BET';

                if (data.success) {
                    // Update user balance in top bar
                    if (data.new_balance !== undefined) {
                        const balEl = document.getElementById('userBalanceDisplay');
                        if (balEl) balEl.textContent = parseFloat(data.new_balance).toFixed(2);
                    }

                    // Show success confirmation
                    showOutcomeBanner(
                        'Bet of ₹' + amount.toFixed(2) + ' on [' + selectedColor.toUpperCase() + '] placed!',
                        'success'
                    );

                    // Refresh status
                    fetchGameStatus();
                } else {
                    alert(data.message || 'Could not place bet.');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btnText.textContent = 'SUBMIT BET';
                console.error(err);
                alert('Network error. Please try again.');
            });
        }

        // Show Win/Lose announcement banner
        function showOutcomeBanner(message, type) {
            const alertBox = document.getElementById('outcomeAlert');
            const alertText = document.getElementById('outcomeText');
            const alertIcon = document.getElementById('outcomeIcon');

            alertText.textContent = message;
            alertBox.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-800', 'border-emerald-200', 'bg-rose-50', 'text-rose-800', 'border-rose-200', 'bg-blue-50', 'text-blue-800', 'border-blue-200');

            if (type === 'win') {
                alertBox.classList.add('bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-300');
                alertIcon.className = 'w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center shrink-0 text-xs font-bold';
                alertIcon.textContent = '✓';
            } else if (type === 'lose') {
                alertBox.classList.add('bg-rose-50', 'text-rose-800', 'border', 'border-rose-300');
                alertIcon.className = 'w-5 h-5 rounded-full bg-rose-600 text-white flex items-center justify-center shrink-0 text-xs font-bold';
                alertIcon.textContent = '✕';
            } else {
                alertBox.classList.add('bg-blue-50', 'text-blue-800', 'border', 'border-blue-300');
                alertIcon.className = 'w-5 h-5 rounded-full bg-blue-600 text-white flex items-center justify-center shrink-0 text-xs font-bold';
                alertIcon.textContent = 'i';
            }
        }

        // Server Synchronized Countdown Timer
        function startCountdown() {
            clearInterval(countdownTimer);

            countdownTimer = setInterval(() => {
                if (remainingSeconds > 0) {
                    remainingSeconds--;
                    updateCountdownDisplay();
                } else {
                    // Timer reached 0: request authoritative state from server
                    fetchGameStatus();
                }
            }, 1000);
        }

        function updateCountdownDisplay() {
            const countdownEl = document.getElementById('countdown');
            const lockWarning = document.getElementById('lockWarning');
            const submitBtn = document.getElementById('submitBetBtn');

            countdownEl.textContent = (remainingSeconds < 10 ? '0' : '') + remainingSeconds;

            if (remainingSeconds <= 5) {
                isLocked = true;
                lockWarning.classList.remove('hidden');
                submitBtn.disabled = true;
                document.getElementById('timerCard').classList.add('text-rose-400');
            } else {
                isLocked = false;
                lockWarning.classList.add('hidden');
                submitBtn.disabled = false;
                document.getElementById('timerCard').classList.remove('text-rose-400');
            }
        }

        // Fetch authoritative server round & history
        function fetchGameStatus() {
            fetch('/api/colour_game_status.php')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                // Sync Round
                if (data.round) {
                    activeRoundNumber = data.round.round_number;
                    document.getElementById('roundNumber').textContent = '#' + activeRoundNumber;
                    remainingSeconds = data.round.remaining_seconds;
                    isLocked = data.round.is_locked;
                    updateCountdownDisplay();
                }

                // Sync Balance
                if (data.user && data.user.balance !== undefined) {
                    const balEl = document.getElementById('userBalanceDisplay');
                    if (balEl) balEl.textContent = parseFloat(data.user.balance).toFixed(2);
                }

                // Check for Win / Lose result notification
                if (data.last_outcome && data.last_outcome.round_number !== lastNotifiedRound) {
                    lastNotifiedRound = data.last_outcome.round_number;
                    const lo = data.last_outcome;
                    if (lo.status === 'won') {
                        showOutcomeBanner(
                            'ROUND ' + lo.round_number + ' RESULT: ' + lo.result_color.toUpperCase() + ' • ✓ WIN: ₹' + lo.win_amount.toFixed(2) + ' Credited!',
                            'win'
                        );
                    } else if (lo.status === 'lost') {
                        showOutcomeBanner(
                            'ROUND ' + lo.round_number + ' RESULT: ' + lo.result_color.toUpperCase() + ' • ✕ LOSE: ₹' + lo.amount.toFixed(2),
                            'lose'
                        );
                    }
                }

                // Populate Game History (internal scrolling list)
                if (data.history && Array.isArray(data.history)) {
                    renderHistoryList(data.history);
                }
            })
            .catch(err => console.error('Status fetch error:', err));
        }

        // Render Game History items inside internal scroll container
        function renderHistoryList(history) {
            const listEl = document.getElementById('historyList');
            if (!history || history.length === 0) {
                listEl.innerHTML = '<div class="py-2 text-center text-slate-400 text-[11px]">No rounds recorded yet.</div>';
                return;
            }

            let html = '';
            history.forEach(item => {
                const color = (item.result_color || 'pending').toLowerCase();
                let colorBadge = 'bg-slate-200 text-slate-700';
                if (color === 'red') colorBadge = 'bg-red-500 text-white';
                if (color === 'green') colorBadge = 'bg-emerald-500 text-white';
                if (color === 'violet') colorBadge = 'bg-purple-600 text-white';

                let userResultHtml = '<span class="text-slate-400 text-[10px]">--</span>';
                if (item.user_choice) {
                    if (item.user_status === 'won') {
                        userResultHtml = '<span class="font-black text-emerald-600 text-[10px]">WIN +₹' + (item.win_amount || 0).toFixed(2) + '</span>';
                    } else if (item.user_status === 'lost') {
                        userResultHtml = '<span class="font-bold text-rose-500 text-[10px]">LOSE -₹' + (item.amount || 0).toFixed(2) + '</span>';
                    }
                }

                html += `
                    <div class="flex items-center justify-between py-1 px-1.5 hover:bg-slate-50 rounded-lg transition">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-slate-700 text-xs">#${item.round_number}</span>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider ${colorBadge}">
                                ${color}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 text-right">
                            ${item.user_choice ? '<span class="text-[10px] text-slate-500 font-semibold">Your: ' + item.user_choice.toUpperCase() + '</span>' : ''}
                            ${userResultHtml}
                        </div>
                    </div>
                `;
            });

            listEl.innerHTML = html;
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            startCountdown();
            fetchGameStatus();

            // Poll every 3 seconds for live state and round finalization
            pollingInterval = setInterval(fetchGameStatus, 3000);
        });
    </script>
</body>
</html>
