<?php
/**
 * Sikkim Gaming Platform - Colour Game Screen
 * Strictly NO PAGE SCROLLING: Fits completely within viewport.
 * ONLY Game History / My History has an internal scroll container.
 * Fixed bottom 4-option navigation bar.
 * Real MySQL database, authoritative server timer, and live outcome audit.
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
                    <h1 class="text-xs sm:text-sm font-black tracking-tight text-slate-900 leading-none">Colour Game</h1>
                    <span class="text-[10px] font-semibold text-blue-600">45s Prediction Round</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <?php if ($currentUser): ?>
                    <a href="/wallet.php" class="flex items-center gap-1 bg-blue-50 border border-blue-200/80 px-2 py-0.5 rounded-lg text-xs font-bold text-blue-800 hover:bg-blue-100 transition shadow-2xs">
                        <span class="text-blue-500 text-[10px]">Points:</span>
                        <span id="userBalanceDisplay"><?= number_format($userBalance, 2) ?></span>
                    </a>
                <?php else: ?>
                    <a href="/login.php?redirect=/colour-game.php" class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition shadow-xs">
                        Log in
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- 2. Live Outcome Notification Banner -->
        <div id="outcomeAlert" class="hidden shrink-0 my-1 p-2 rounded-xl text-xs font-bold flex items-center justify-between shadow-xs transition-all duration-300">
            <div class="flex items-center gap-2 overflow-hidden truncate">
                <span id="outcomeIcon" class="w-4 h-4 rounded-full flex items-center justify-center shrink-0 text-white text-[10px] font-bold">✓</span>
                <span id="outcomeText" class="truncate text-[11px]">Round Outcome</span>
            </div>
            <button onclick="document.getElementById('outcomeAlert').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 text-xs px-1 font-bold">&times;</button>
        </div>

        <!-- 3. Current Round & Authoritative Countdown Card -->
        <div class="w-full bg-gradient-to-r from-blue-700 via-blue-600 to-indigo-700 text-white rounded-2xl p-2.5 shadow-md shrink-0 border border-blue-500/30">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[9px] font-bold uppercase tracking-wider text-blue-200 block">Current Period</span>
                    <div class="flex items-center gap-1.5">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span id="roundNumber" class="text-base sm:text-lg font-black tracking-wider text-white">
                            #<?= e($activeRound['round_number']) ?>
                        </span>
                    </div>
                </div>

                <!-- Last Published Result Badge -->
                <div id="lastResultBox" class="text-center bg-white/10 px-2 py-0.5 rounded-lg border border-white/20">
                    <span class="text-[9px] font-semibold text-blue-100 block">Last Result</span>
                    <span id="lastResultText" class="text-[11px] font-black uppercase text-amber-300">--</span>
                </div>

                <div class="text-right">
                    <span class="text-[9px] font-bold uppercase tracking-wider text-blue-200 block">Countdown</span>
                    <div class="bg-black/30 backdrop-blur-xs border border-white/20 px-2.5 py-0.5 rounded-xl font-mono text-lg sm:text-xl font-black text-amber-300 tracking-wider">
                        00:<span id="countdown"><?= str_pad((string)$remainingSeconds, 2, '0', STR_PAD_LEFT) ?></span>
                    </div>
                </div>
            </div>

            <!-- Lock Banner (shows in last 5 seconds) -->
            <div id="lockWarning" class="<?= ($remainingSeconds <= 5) ? '' : 'hidden' ?> mt-1.5 bg-amber-400/20 border border-amber-300/40 rounded-lg py-0.5 px-2 text-center text-[10px] font-bold text-amber-200 animate-pulse">
                ⏳ Period locked for settlement. Next round starting shortly...
            </div>
        </div>

        <!-- 4. Colour Options (Red, Green, Violet) -->
        <div class="w-full shrink-0 my-1">
            <div class="grid grid-cols-3 gap-2">
                <!-- RED OPTION -->
                <button type="button" 
                        onclick="toggleColour('red', 2.0)"
                        id="btn-red"
                        class="colour-btn group relative flex flex-col items-center justify-center py-2 px-1 rounded-xl bg-gradient-to-br from-rose-500 to-red-600 text-white shadow-xs hover:brightness-105 active:scale-95 transition transform border border-red-400/40 cursor-pointer">
                    <span class="text-xs font-black tracking-wide uppercase">RED</span>
                    <span class="text-[9px] font-extrabold bg-white/20 px-1 py-0.2 rounded-full mt-0.5">2.0X</span>
                    <div class="indicator hidden absolute -top-1 -right-1 w-4 h-4 bg-white text-red-600 rounded-full flex items-center justify-center shadow-md text-[10px] font-bold">✓</div>
                </button>

                <!-- GREEN OPTION -->
                <button type="button" 
                        onclick="toggleColour('green', 2.0)"
                        id="btn-green"
                        class="colour-btn group relative flex flex-col items-center justify-center py-2 px-1 rounded-xl bg-gradient-to-br from-emerald-500 to-green-600 text-white shadow-xs hover:brightness-105 active:scale-95 transition transform border border-emerald-400/40 cursor-pointer">
                    <span class="text-xs font-black tracking-wide uppercase">GREEN</span>
                    <span class="text-[9px] font-extrabold bg-white/20 px-1 py-0.2 rounded-full mt-0.5">2.0X</span>
                    <div class="indicator hidden absolute -top-1 -right-1 w-4 h-4 bg-white text-green-600 rounded-full flex items-center justify-center shadow-md text-[10px] font-bold">✓</div>
                </button>

                <!-- VIOLET OPTION -->
                <button type="button" 
                        onclick="toggleColour('violet', 4.5)"
                        id="btn-violet"
                        class="colour-btn group relative flex flex-col items-center justify-center py-2 px-1 rounded-xl bg-gradient-to-br from-purple-500 to-violet-600 text-white shadow-xs hover:brightness-105 active:scale-95 transition transform border border-purple-400/40 cursor-pointer">
                    <span class="text-xs font-black tracking-wide uppercase">VIOLET</span>
                    <span class="text-[9px] font-extrabold bg-white/20 px-1 py-0.2 rounded-full mt-0.5">4.5X</span>
                    <div class="indicator hidden absolute -top-1 -right-1 w-4 h-4 bg-white text-purple-600 rounded-full flex items-center justify-center shadow-md text-[10px] font-bold">✓</div>
                </button>
            </div>
        </div>

        <!-- 5. Number Options (0 to 9 in 2 Rows) -->
        <div class="w-full bg-white rounded-xl shadow-xs border border-slate-200/90 p-2 shrink-0">
            <div class="flex items-center justify-between mb-1 text-[10px] text-slate-500 font-bold uppercase tracking-wider">
                <span>SELECT NUMBER (0 – 9)</span>
                <span class="text-blue-600 font-black">9.0X MULTIPLIER</span>
            </div>

            <!-- Numbers 0 to 9 Grid -->
            <div class="grid grid-cols-5 gap-1.5">
                <?php 
                $numberColors = [
                    0 => 'border-purple-300 text-purple-700',
                    1 => 'border-emerald-300 text-emerald-700',
                    2 => 'border-red-300 text-red-700',
                    3 => 'border-emerald-300 text-emerald-700',
                    4 => 'border-red-300 text-red-700',
                    5 => 'border-purple-300 text-purple-700',
                    6 => 'border-red-300 text-red-700',
                    7 => 'border-emerald-300 text-emerald-700',
                    8 => 'border-red-300 text-red-700',
                    9 => 'border-emerald-300 text-emerald-700'
                ];
                for ($n = 0; $n <= 9; $n++): 
                    $colStyle = $numberColors[$n] ?? 'border-slate-200 text-slate-800';
                ?>
                    <button type="button" 
                            onclick="toggleNumber(<?= $n ?>)"
                            id="num-btn-<?= $n ?>"
                            class="number-btn relative py-1.5 rounded-lg bg-slate-50 hover:bg-blue-50 border <?= $colStyle ?> font-mono font-black text-xs sm:text-sm text-center shadow-2xs hover:shadow-xs transition active:scale-95 cursor-pointer">
                        <?= $n ?>
                        <div class="num-indicator hidden absolute -top-1 -right-1 w-3.5 h-3.5 bg-blue-600 text-white rounded-full flex items-center justify-center text-[8px] font-bold">✓</div>
                    </button>
                <?php endfor; ?>
            </div>
        </div>

        <!-- 6. User Selection & Points Input Row -->
        <div class="w-full bg-white rounded-xl shadow-xs border border-slate-200/90 p-2 shrink-0">
            <div class="flex items-center justify-between text-[11px] mb-1">
                <div class="truncate">
                    <span class="text-slate-500 font-medium">Selected:</span>
                    <span id="selectedChoiceText" class="font-extrabold uppercase text-slate-400 ml-1">None</span>
                </div>
                <div class="shrink-0 text-right">
                    <span class="text-slate-500 font-medium">Potential:</span>
                    <span id="potentialWinText" class="font-extrabold text-blue-600 ml-1">0.00</span>
                </div>
            </div>

            <!-- Points Input & Quick Chips -->
            <div class="flex items-center gap-1.5 mb-1.5">
                <div class="relative flex-1">
                    <input type="number" 
                           id="betAmount" 
                           value="100" 
                           min="10" 
                           step="10"
                           class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:bg-white"
                           placeholder="Points"
                           oninput="updatePayoutCalculation()">
                </div>

                <div class="flex items-center gap-1 shrink-0">
                    <button type="button" onclick="setQuickAmount(10)" class="px-2 py-1 rounded-md bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-600 text-[10px] font-bold transition">+10</button>
                    <button type="button" onclick="setQuickAmount(50)" class="px-2 py-1 rounded-md bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-600 text-[10px] font-bold transition">+50</button>
                    <button type="button" onclick="setQuickAmount(100)" class="px-2 py-1 rounded-md bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-600 text-[10px] font-bold transition">+100</button>
                    <button type="button" onclick="setQuickAmount(500)" class="px-2 py-1 rounded-md bg-slate-100 hover:bg-blue-50 hover:text-blue-700 text-slate-600 text-[10px] font-bold transition">+500</button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="button" 
                    id="submitBetBtn"
                    onclick="submitBet()"
                    class="w-full py-2 rounded-xl bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-black text-xs uppercase tracking-wider shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                <span id="submitBtnText">SUBMIT ENTRY</span>
            </button>
        </div>

        <!-- 7. History Container (ONLY THIS AREA SCROLLS) -->
        <div class="w-full flex-1 flex flex-col min-h-0 bg-white rounded-xl shadow-xs border border-slate-200/90 p-2 my-0.5">
            <!-- Navigation Tabs: My History vs Game History -->
            <div class="flex items-center justify-between pb-1 mb-1 border-b border-slate-100 shrink-0">
                <div class="flex items-center gap-1 text-[11px] font-black">
                    <button type="button" id="tabMyHistory" onclick="switchHistoryTab('my')" class="px-2.5 py-0.5 rounded-lg bg-blue-600 text-white transition">
                        MY HISTORY
                    </button>
                    <button type="button" id="tabGameHistory" onclick="switchHistoryTab('game')" class="px-2.5 py-0.5 rounded-lg text-slate-600 hover:bg-slate-100 transition">
                        GAME HISTORY
                    </button>
                </div>
                <span class="text-[9px] text-slate-400 font-medium">Live Server Updates</span>
            </div>

            <!-- Internal Scroll Container for History Entries -->
            <div id="historyScrollContainer" class="flex-1 overflow-y-auto space-y-1 pr-1 text-xs divide-y divide-slate-50">
                <!-- Dynamically populated via AJAX -->
                <div class="py-2 text-center text-slate-400 text-[10px]">Loading history...</div>
            </div>
        </div>

    </div>

    <!-- 8. Fixed Bottom 4-Option Navigation Bar -->
    <?php require_once __DIR__ . '/includes/user_nav.php'; ?>

    <!-- Plain JavaScript Game Client (Zero Framework, Pure AJAX & DOM) -->
    <script>
        const IS_LOGGED_IN = <?= $currentUser ? 'true' : 'false' ?>;
        let selectedColor = null;
        let selectedNumber = null;
        let activeRoundNumber = <?= (int)$activeRound['round_number'] ?>;
        let remainingSeconds = <?= (int)$remainingSeconds ?>;
        let isLocked = <?= ($remainingSeconds <= 5) ? 'true' : 'false' ?>;
        let countdownTimer = null;
        let pollingInterval = null;
        let lastNotifiedRound = null;
        let currentHistoryTab = 'my';
        let latestMyHistory = [];
        let latestGameHistory = [];

        // Toggle Colour Selection
        function toggleColour(color, multiplier) {
            if (selectedColor === color) {
                selectedColor = null;
            } else {
                selectedColor = color;
            }

            document.querySelectorAll('.colour-btn').forEach(btn => {
                btn.classList.remove('ring-4', 'ring-offset-2', 'ring-blue-600', 'scale-[1.03]');
                btn.querySelector('.indicator').classList.add('hidden');
            });

            if (selectedColor) {
                const activeBtn = document.getElementById('btn-' + selectedColor);
                if (activeBtn) {
                    activeBtn.classList.add('ring-4', 'ring-offset-2', 'ring-blue-600', 'scale-[1.03]');
                    activeBtn.querySelector('.indicator').classList.remove('hidden');
                }
            }

            updateSelectionDisplay();
        }

        // Toggle Number Selection (0 to 9)
        function toggleNumber(num) {
            if (selectedNumber === num) {
                selectedNumber = null;
            } else {
                selectedNumber = num;
            }

            document.querySelectorAll('.number-btn').forEach(btn => {
                btn.classList.remove('ring-2', 'ring-blue-600', 'bg-blue-600', 'text-white', 'scale-105');
                btn.querySelector('.num-indicator').classList.add('hidden');
            });

            if (selectedNumber !== null) {
                const activeBtn = document.getElementById('num-btn-' + selectedNumber);
                if (activeBtn) {
                    activeBtn.classList.add('ring-2', 'ring-blue-600', 'bg-blue-600', 'text-white', 'scale-105');
                    activeBtn.querySelector('.num-indicator').classList.remove('hidden');
                }
            }

            updateSelectionDisplay();
        }

        function updateSelectionDisplay() {
            const textEl = document.getElementById('selectedChoiceText');
            let parts = [];
            if (selectedColor) {
                parts.push('<span class="' + (selectedColor === 'red' ? 'text-red-600' : (selectedColor === 'green' ? 'text-green-600' : 'text-purple-600')) + '">' + selectedColor.toUpperCase() + '</span>');
            }
            if (selectedNumber !== null) {
                parts.push('<span class="text-blue-700 font-mono">Num ' + selectedNumber + '</span>');
            }

            if (parts.length > 0) {
                textEl.innerHTML = parts.join(' + ');
            } else {
                textEl.textContent = 'None';
                textEl.className = 'font-extrabold uppercase text-slate-400 ml-1';
            }

            updatePayoutCalculation();
        }

        function setQuickAmount(amount) {
            const input = document.getElementById('betAmount');
            let current = parseFloat(input.value) || 0;
            input.value = current + amount;
            updatePayoutCalculation();
        }

        function updatePayoutCalculation() {
            const points = parseFloat(document.getElementById('betAmount').value) || 0;
            let mult = 0;
            if (selectedNumber !== null && selectedColor !== null) {
                mult = 9.0 + (selectedColor === 'violet' ? 4.5 : 2.0);
            } else if (selectedNumber !== null) {
                mult = 9.0;
            } else if (selectedColor === 'violet') {
                mult = 4.5;
            } else if (selectedColor) {
                mult = 2.0;
            }
            document.getElementById('potentialWinText').textContent = (points * mult).toFixed(2);
        }

        function switchHistoryTab(tab) {
            currentHistoryTab = tab;
            const myBtn = document.getElementById('tabMyHistory');
            const gameBtn = document.getElementById('tabGameHistory');

            if (tab === 'my') {
                myBtn.className = 'px-2.5 py-0.5 rounded-lg bg-blue-600 text-white transition';
                gameBtn.className = 'px-2.5 py-0.5 rounded-lg text-slate-600 hover:bg-slate-100 transition';
                renderMyHistory(latestMyHistory);
            } else {
                gameBtn.className = 'px-2.5 py-0.5 rounded-lg bg-blue-600 text-white transition';
                myBtn.className = 'px-2.5 py-0.5 rounded-lg text-slate-600 hover:bg-slate-100 transition';
                renderGameHistory(latestGameHistory);
            }
        }

        // Submit Bet to Server
        function submitBet() {
            if (!IS_LOGGED_IN) {
                window.location.href = '/login.php?redirect=/colour-game.php';
                return;
            }

            if (!selectedColor && selectedNumber === null) {
                alert('Please select a Colour (Red, Green, Violet) or a Number (0–9).');
                return;
            }

            const points = parseFloat(document.getElementById('betAmount').value) || 0;
            if (points < 10) {
                alert('Minimum entry is 10 points.');
                return;
            }

            if (isLocked) {
                alert('Period #' + activeRoundNumber + ' is locked for settlement. Please wait for the next round.');
                return;
            }

            const btn = document.getElementById('submitBetBtn');
            const btnText = document.getElementById('submitBtnText');
            btn.disabled = true;
            btnText.textContent = 'SUBMITTING...';

            const formData = new FormData();
            if (selectedColor) formData.append('colour', selectedColor);
            if (selectedNumber !== null) formData.append('number', selectedNumber);
            formData.append('points', points);

            fetch('/api/place_colour_bet.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btnText.textContent = 'SUBMIT ENTRY';

                if (data.success) {
                    if (data.new_balance !== undefined) {
                        const balEl = document.getElementById('userBalanceDisplay');
                        if (balEl) balEl.textContent = parseFloat(data.new_balance).toFixed(2);
                    }

                    // Immediately prepend PENDING entry into My History UI
                    if (data.entry) {
                        const optimisticEntry = {
                            id: data.entry.id,
                            round_number: data.entry.round_number,
                            selected_colour: data.entry.selected_colour,
                            selected_number: data.entry.selected_number,
                            points: data.entry.points,
                            status: 'pending',
                            result: 'PENDING',
                            win_amount: 0.00
                        };
                        latestMyHistory.unshift(optimisticEntry);
                        if (currentHistoryTab === 'my') {
                            renderMyHistory(latestMyHistory);
                        }
                    }

                    showOutcomeBanner(
                        'Entry of ' + points.toFixed(0) + ' points on ' + (data.entry.selected_colour || '') + (data.entry.selected_number !== null ? ' Num ' + data.entry.selected_number : '') + ' placed!',
                        'info'
                    );

                    fetchGameStatus();
                } else {
                    alert(data.message || 'Could not place entry.');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btnText.textContent = 'SUBMIT ENTRY';
                console.error(err);
                alert('Network error. Please try again.');
            });
        }

        function showOutcomeBanner(message, type) {
            const alertBox = document.getElementById('outcomeAlert');
            const alertText = document.getElementById('outcomeText');
            const alertIcon = document.getElementById('outcomeIcon');

            alertText.textContent = message;
            alertBox.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-800', 'border-emerald-200', 'bg-rose-50', 'text-rose-800', 'border-rose-200', 'bg-blue-50', 'text-blue-800', 'border-blue-200');

            if (type === 'win') {
                alertBox.classList.add('bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-300');
                alertIcon.className = 'w-4 h-4 rounded-full bg-emerald-600 text-white flex items-center justify-center shrink-0 text-[10px] font-bold';
                alertIcon.textContent = '✓';
            } else if (type === 'lose') {
                alertBox.classList.add('bg-rose-50', 'text-rose-800', 'border', 'border-rose-300');
                alertIcon.className = 'w-4 h-4 rounded-full bg-rose-600 text-white flex items-center justify-center shrink-0 text-[10px] font-bold';
                alertIcon.textContent = '✕';
            } else {
                alertBox.classList.add('bg-blue-50', 'text-blue-800', 'border', 'border-blue-300');
                alertIcon.className = 'w-4 h-4 rounded-full bg-blue-600 text-white flex items-center justify-center shrink-0 text-[10px] font-bold';
                alertIcon.textContent = 'i';
            }
        }

        function startCountdown() {
            clearInterval(countdownTimer);
            countdownTimer = setInterval(() => {
                if (remainingSeconds > 0) {
                    remainingSeconds--;
                    updateCountdownDisplay();
                } else {
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
            } else {
                isLocked = false;
                lockWarning.classList.add('hidden');
                submitBtn.disabled = false;
            }
        }

        // Fetch Authoritative Server State & Real MySQL History
        function fetchGameStatus() {
            fetch('/api/colour_game_status.php')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                // Sync Active Round
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

                // Last Round Published Result (Color & Number)
                if (data.last_round_result) {
                    const lr = data.last_round_result;
                    const resText = document.getElementById('lastResultText');
                    resText.textContent = lr.result_color.toUpperCase() + ' ' + lr.result_number;
                }

                // Check for User Win/Lose Announcement
                if (data.last_outcome && data.last_outcome.round_number !== lastNotifiedRound) {
                    lastNotifiedRound = data.last_outcome.round_number;
                    const lo = data.last_outcome;
                    if (lo.status === 'won') {
                        showOutcomeBanner(
                            'ROUND ' + lo.round_number + ' RESULT: ' + lo.result_color.toUpperCase() + ' (Num ' + lo.result_number + ') • ✓ WIN: ' + lo.win_amount.toFixed(2) + ' Points Credited!',
                            'win'
                        );
                    } else if (lo.status === 'lost') {
                        showOutcomeBanner(
                            'ROUND ' + lo.round_number + ' RESULT: ' + lo.result_color.toUpperCase() + ' (Num ' + lo.result_number + ') • ✕ LOSE',
                            'lose'
                        );
                    }
                }

                // Update My History & Game History lists
                latestMyHistory = data.my_history || [];
                latestGameHistory = data.game_history || [];

                if (currentHistoryTab === 'my') {
                    renderMyHistory(latestMyHistory);
                } else {
                    renderGameHistory(latestGameHistory);
                }
            })
            .catch(err => console.error('Status fetch error:', err));
        }

        // Render My History (Real MySQL Data: Round, Selection, Number, Points, Result PENDING -> WIN/LOSE)
        function renderMyHistory(history) {
            const listEl = document.getElementById('historyScrollContainer');
            if (!IS_LOGGED_IN) {
                listEl.innerHTML = '<div class="py-3 text-center text-slate-400 text-[11px]"><a href="/login.php?redirect=/colour-game.php" class="text-blue-600 font-bold hover:underline">Log in</a> to view your real game entries.</div>';
                return;
            }

            if (!history || history.length === 0) {
                listEl.innerHTML = '<div class="py-3 text-center text-slate-400 text-[11px]">No entries placed yet. Select a colour or number above.</div>';
                return;
            }

            let html = '';
            history.forEach(item => {
                let badge = '<span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 animate-pulse">PENDING</span>';
                if (item.result === 'WIN') {
                    badge = '<span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800">WIN +' + parseFloat(item.win_amount || 0).toFixed(0) + '</span>';
                } else if (item.result === 'LOSE') {
                    badge = '<span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-100 text-rose-800">LOSE</span>';
                }

                let selParts = [];
                if (item.selected_colour) {
                    let colClass = item.selected_colour === 'RED' ? 'text-red-600' : (item.selected_colour === 'GREEN' ? 'text-green-600' : 'text-purple-600');
                    selParts.push('<span class="' + colClass + ' font-bold">' + item.selected_colour + '</span>');
                }
                if (item.selected_number !== null) {
                    selParts.push('<span class="font-mono font-bold text-blue-700">Num: ' + item.selected_number + '</span>');
                }

                html += `
                    <div class="flex items-center justify-between py-1 px-1.5 hover:bg-slate-50 rounded-lg transition text-[11px]">
                        <div class="flex items-center gap-1.5">
                            <span class="font-mono font-black text-slate-800">#${item.round_number}</span>
                            <span class="text-slate-300">|</span>
                            <span>${selParts.join(' ')}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-slate-600">${parseFloat(item.points).toFixed(0)} pts</span>
                            ${badge}
                        </div>
                    </div>
                `;
            });

            listEl.innerHTML = html;
        }

        // Render Public Game History
        function renderGameHistory(history) {
            const listEl = document.getElementById('historyScrollContainer');
            if (!history || history.length === 0) {
                listEl.innerHTML = '<div class="py-3 text-center text-slate-400 text-[11px]">No rounds completed yet.</div>';
                return;
            }

            let html = '';
            history.forEach(item => {
                const color = (item.result_color || '').toLowerCase();
                let colorBadge = 'bg-slate-200 text-slate-700';
                if (color === 'red') colorBadge = 'bg-red-500 text-white';
                if (color === 'green') colorBadge = 'bg-emerald-500 text-white';
                if (color === 'violet') colorBadge = 'bg-purple-600 text-white';

                html += `
                    <div class="flex items-center justify-between py-1 px-1.5 hover:bg-slate-50 rounded-lg transition text-[11px]">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-black text-slate-800">#${item.round_number}</span>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider ${colorBadge}">
                                ${color}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-slate-400 text-[10px]">Number:</span>
                            <span class="w-5 h-5 rounded-full bg-slate-100 border border-slate-300 font-mono font-black text-slate-900 flex items-center justify-center text-xs">
                                ${item.result_number}
                            </span>
                        </div>
                    </div>
                `;
            });

            listEl.innerHTML = html;
        }

        document.addEventListener('DOMContentLoaded', () => {
            startCountdown();
            fetchGameStatus();
            pollingInterval = setInterval(fetchGameStatus, 2500);
        });
    </script>
</body>
</html>
