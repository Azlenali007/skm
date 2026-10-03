<?php
/**
 * Sikkim Gaming Platform - Live Game Arena
 * Real server-side authoritative rounds, countdown and results.
 * STRICT NORMAL FLOW: NO sticky, NO fixed.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/round_engine.php';

$user = requireLogin();
$pdo = getDB();

$slug = trim($_GET['slug'] ?? 'win-go-1m');

if ($slug === 'colour-game') {
    header("Location: /colour-game.php");
    exit;
}

// Lookup Game in MySQL
$gameStmt = $pdo->prepare("
    SELECT g.*, c.name as category_name
    FROM `games` g
    LEFT JOIN `categories` c ON g.category_id = c.id
    WHERE g.slug = :slug AND g.status = 'active'
    LIMIT 1
");
$gameStmt->execute([':slug' => $slug]);
$game = $gameStmt->fetch();

if (!$game) {
    // Fallback to first available active game
    $gameStmt = $pdo->query("SELECT g.*, c.name as category_name FROM `games` g LEFT JOIN `categories` c ON g.category_id = c.id WHERE g.status = 'active' LIMIT 1");
    $game = $gameStmt->fetch();
}

$pageTitle = $game['name'] . " - Live Arena";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Game Header Breadcrumb (Strict Normal Flow) -->
<div class="w-full mb-4 flex items-center justify-between">
    <div class="flex items-center gap-2">
        <a href="/games.php" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-blue-600 transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="font-black text-lg sm:text-xl text-slate-900 leading-tight"><?= e($game['name']) ?></h1>
            <span class="text-xs text-slate-500"><?= e($game['category_name'] ?? 'Lottery') ?> • Provably Fair 1-Min Round</span>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <a href="/wallet.php" class="px-3 py-1.5 bg-blue-50 border border-blue-200 rounded-xl text-xs font-bold text-blue-700 hover:bg-blue-100 transition flex items-center gap-1.5">
            <span>Balance:</span>
            <span id="player-balance" class="font-black"><?= formatMoney((float)$user['balance']) ?></span>
        </a>
    </div>
</div>

<!-- Round Status & Countdown Card -->
<div class="w-full card-premium p-5 mb-6 bg-gradient-to-br from-blue-900 via-slate-900 to-indigo-950 text-white relative overflow-hidden">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
        <!-- Period Info -->
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                <span class="text-xs font-bold uppercase tracking-wider text-blue-300">Authoritative Period</span>
            </div>
            <div class="text-2xl sm:text-3xl font-black font-mono tracking-tight text-white" id="current-period">
                Loading...
            </div>
            <p class="text-[11px] text-blue-200/80 mt-1">Guaranteed Server Authoritative RNG • No Client Tampering</p>
        </div>

        <!-- Countdown Timer -->
        <div class="flex flex-col sm:items-end">
            <span class="text-xs font-bold uppercase tracking-wider text-blue-300 mb-1">Time Remaining</span>
            <div class="flex items-center gap-1.5 font-mono font-black text-3xl sm:text-4xl text-amber-400" id="countdown-display">
                <span id="time-min" class="bg-black/40 px-2.5 py-1 rounded-xl border border-white/10">00</span>
                <span>:</span>
                <span id="time-sec" class="bg-black/40 px-2.5 py-1 rounded-xl border border-white/10">00</span>
            </div>
            <div id="betting-status-badge" class="mt-2 text-[11px] font-bold text-emerald-400 bg-emerald-950/80 border border-emerald-500/30 px-2.5 py-0.5 rounded-full">
                ● Betting Open
            </div>
        </div>
    </div>
</div>

<!-- Betting Control Panel (Strict Normal Document Flow) -->
<div class="w-full card-premium p-5 sm:p-6 mb-8">
    <h2 class="font-black text-slate-900 text-base sm:text-lg mb-4 flex items-center gap-2">
        <span class="w-1.5 h-5 bg-blue-600 rounded-full inline-block"></span>
        Place Your Prediction
    </h2>

    <!-- Alert Box for Bet Feedback -->
    <div id="bet-alert" class="hidden mb-4 p-3 rounded-xl text-xs font-bold"></div>

    <!-- Step 1: Color Options -->
    <div class="grid grid-cols-3 gap-3 mb-4">
        <button type="button" onclick="selectChoice('green', 2.0)" class="choice-btn py-3 px-2 rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-black text-sm shadow-sm transition active:scale-95 flex flex-col items-center">
            <span>GREEN</span>
            <span class="text-[10px] font-semibold opacity-90">2.0x Payout</span>
        </button>

        <button type="button" onclick="selectChoice('violet', 4.5)" class="choice-btn py-3 px-2 rounded-xl bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white font-black text-sm shadow-sm transition active:scale-95 flex flex-col items-center">
            <span>VIOLET</span>
            <span class="text-[10px] font-semibold opacity-90">4.5x Payout</span>
        </button>

        <button type="button" onclick="selectChoice('red', 2.0)" class="choice-btn py-3 px-2 rounded-xl bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-600 hover:to-rose-700 text-white font-black text-sm shadow-sm transition active:scale-95 flex flex-col items-center">
            <span>RED</span>
            <span class="text-[10px] font-semibold opacity-90">2.0x Payout</span>
        </button>
    </div>

    <!-- Step 2: Number Grid (0-9) -->
    <div class="mb-4">
        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Or Select Exact Number (9.0x Payout)</div>
        <div class="grid grid-cols-5 sm:grid-cols-10 gap-2">
            <?php for ($num = 0; $num <= 9; $num++):
                $colorBg = ($num === 0) ? 'from-rose-500 to-purple-500' :
                           (($num === 5) ? 'from-emerald-500 to-purple-500' :
                           ((in_array($num, [1, 3, 7, 9])) ? 'from-emerald-500 to-emerald-600' : 'from-rose-500 to-rose-600'));
            ?>
                <button type="button" onclick="selectChoice('<?= $num ?>', 9.0)" class="choice-btn py-3 rounded-xl bg-gradient-to-tr <?= $colorBg ?> text-white font-black text-base shadow-sm hover:opacity-95 transition active:scale-95 flex flex-col items-center justify-center">
                    <span><?= $num ?></span>
                    <span class="text-[9px] opacity-80">9x</span>
                </button>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Step 3: Big / Small Options -->
    <div class="grid grid-cols-2 gap-3 mb-6">
        <button type="button" onclick="selectChoice('big', 2.0)" class="choice-btn py-3 px-4 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-black text-sm shadow-sm transition active:scale-95 flex items-center justify-between">
            <span>BIG (5 - 9)</span>
            <span class="text-xs bg-black/20 px-2 py-0.5 rounded-full">2.0x</span>
        </button>
        <button type="button" onclick="selectChoice('small', 2.0)" class="choice-btn py-3 px-4 rounded-xl bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white font-black text-sm shadow-sm transition active:scale-95 flex items-center justify-between">
            <span>SMALL (0 - 4)</span>
            <span class="text-xs bg-black/20 px-2 py-0.5 rounded-full">2.0x</span>
        </button>
    </div>

    <!-- Bet Confirmation Box (Shown in normal flow) -->
    <div id="bet-drawer" class="p-4 bg-slate-50 border border-slate-200 rounded-2xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-500">Selected Choice:</span>
                <span id="selected-choice-badge" class="px-3 py-1 rounded-lg bg-blue-600 text-white font-black text-xs uppercase tracking-wide">
                    Green
                </span>
                <span id="selected-multiplier-badge" class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                    2.0x Payout
                </span>
            </div>
            <div class="text-xs text-slate-500">
                Min: ₹10 • Max: ₹50,000
            </div>
        </div>

        <!-- Quick Amount Chips -->
        <div class="flex flex-wrap gap-2 mb-3">
            <button type="button" onclick="setBetAmount(10)" class="amount-btn px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:border-blue-500">₹10</button>
            <button type="button" onclick="setBetAmount(50)" class="amount-btn px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:border-blue-500">₹50</button>
            <button type="button" onclick="setBetAmount(100)" class="amount-btn px-3 py-1.5 rounded-lg bg-blue-50 border border-blue-500 text-xs font-bold text-blue-700">₹100</button>
            <button type="button" onclick="setBetAmount(500)" class="amount-btn px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:border-blue-500">₹500</button>
            <button type="button" onclick="setBetAmount(1000)" class="amount-btn px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:border-blue-500">₹1,000</button>
        </div>

        <div class="flex items-center gap-3">
            <div class="relative flex-1">
                <span class="absolute left-3 top-2.5 font-bold text-slate-400 text-sm">₹</span>
                <input type="number" id="bet-amount" value="100" min="10" max="50000" step="10" class="w-full pl-8 pr-3 py-2 bg-white border border-slate-300 rounded-xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="button" id="submit-bet-btn" onclick="submitBet()" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800 text-white font-black text-sm rounded-xl shadow-md transition active:scale-95 shrink-0">
                Confirm Bet
            </button>
        </div>
    </div>
</div>

<!-- Published Game Results Table (MySQL Real Records) -->
<div class="w-full card-premium p-5 sm:p-6 mb-8">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="font-black text-slate-900 text-base sm:text-lg flex items-center gap-2">
                <span class="w-1.5 h-5 bg-blue-600 rounded-full inline-block"></span>
                Game Record History
            </h3>
            <p class="text-xs text-slate-500">Recent official published outcomes from database</p>
        </div>
        <button type="button" onclick="fetchRoundStatus()" class="text-xs font-bold text-blue-600 hover:underline flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Refresh
        </button>
    </div>

    <div class="overflow-x-auto no-scrollbar">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="border-b border-slate-200 text-slate-400 font-bold uppercase">
                    <th class="py-2.5 px-3">Period</th>
                    <th class="py-2.5 px-3 text-center">Number</th>
                    <th class="py-2.5 px-3 text-center">Size</th>
                    <th class="py-2.5 px-3 text-center">Color</th>
                    <th class="py-2.5 px-3 text-right">Time</th>
                </tr>
            </thead>
            <tbody id="history-table-body" class="divide-y divide-slate-100 font-semibold">
                <tr>
                    <td colspan="5" class="py-4 text-center text-slate-400">Loading published records...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- User's My Bets for This Game -->
<div class="w-full card-premium p-5 sm:p-6 mb-8">
    <h3 class="font-black text-slate-900 text-base sm:text-lg mb-3 flex items-center gap-2">
        <span class="w-1.5 h-5 bg-blue-600 rounded-full inline-block"></span>
        My Recent Bets
    </h3>
    <div id="user-bets-list" class="divide-y divide-slate-100 text-xs">
        <div class="py-4 text-center text-slate-400">Loading your bet records...</div>
    </div>
</div>

<!-- User Bottom Navigation in normal document flow -->
<?php require_once __DIR__ . '/includes/user_nav.php'; ?>

<!-- Game Plain JS Logic (NO framework, NO fake simulation) -->
<script>
    let currentSelectedChoice = 'green';
    let currentMultiplier = 2.0;
    let serverRemainingSeconds = 60;
    let timerInterval = null;
    let pollInterval = null;
    const gameSlug = '<?= e($slug) ?>';

    function selectChoice(choice, multiplier) {
        currentSelectedChoice = choice;
        currentMultiplier = multiplier;

        const badge = document.getElementById('selected-choice-badge');
        const mulBadge = document.getElementById('selected-multiplier-badge');

        badge.innerText = choice.toUpperCase();
        mulBadge.innerText = multiplier.toFixed(1) + 'x Payout';

        // Update colors
        badge.className = 'px-3 py-1 rounded-lg text-white font-black text-xs uppercase tracking-wide ';
        if (choice === 'green') badge.classList.add('bg-emerald-600');
        else if (choice === 'violet') badge.classList.add('bg-purple-600');
        else if (choice === 'red') badge.classList.add('bg-rose-600');
        else if (choice === 'big') badge.classList.add('bg-amber-600');
        else if (choice === 'small') badge.classList.add('bg-blue-600');
        else badge.classList.add('bg-slate-800');
    }

    function setBetAmount(amt) {
        document.getElementById('bet-amount').value = amt;
        document.querySelectorAll('.amount-btn').forEach(b => {
            if (b.innerText === '₹' + amt.toLocaleString()) {
                b.className = 'amount-btn px-3 py-1.5 rounded-lg bg-blue-50 border border-blue-500 text-xs font-bold text-blue-700';
            } else {
                b.className = 'amount-btn px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-bold text-slate-700 hover:border-blue-500';
            }
        });
    }

    function updateTimerDisplay() {
        const minSpan = document.getElementById('time-min');
        const secSpan = document.getElementById('time-sec');
        const statusBadge = document.getElementById('betting-status-badge');
        const submitBtn = document.getElementById('submit-bet-btn');

        if (serverRemainingSeconds <= 0) {
            minSpan.innerText = '00';
            secSpan.innerText = '00';
            statusBadge.innerText = 'Calculating Result...';
            statusBadge.className = 'mt-2 text-[11px] font-bold text-amber-400 bg-amber-950/80 border border-amber-500/30 px-2.5 py-0.5 rounded-full';
            if (submitBtn) submitBtn.disabled = true;
            return;
        }

        const mins = Math.floor(serverRemainingSeconds / 60);
        const secs = serverRemainingSeconds % 60;

        minSpan.innerText = mins < 10 ? '0' + mins : mins;
        secSpan.innerText = secs < 10 ? '0' + secs : secs;

        if (serverRemainingSeconds <= 5) {
            statusBadge.innerText = '● Betting Locked (' + secs + 's)';
            statusBadge.className = 'mt-2 text-[11px] font-bold text-rose-400 bg-rose-950/80 border border-rose-500/30 px-2.5 py-0.5 rounded-full timer-urgent';
            if (submitBtn) submitBtn.disabled = true;
        } else {
            statusBadge.innerText = '● Betting Open';
            statusBadge.className = 'mt-2 text-[11px] font-bold text-emerald-400 bg-emerald-950/80 border border-emerald-500/30 px-2.5 py-0.5 rounded-full';
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    function fetchRoundStatus() {
        fetch('/api/round_status.php?game=' + encodeURIComponent(gameSlug))
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                // Sync server remaining seconds
                serverRemainingSeconds = data.active_round.remaining_seconds;
                document.getElementById('current-period').innerText = data.active_round.round_number;
                updateTimerDisplay();

                // Sync player balance
                if (data.user && typeof data.user.balance !== 'undefined') {
                    document.getElementById('player-balance').innerText = '₹' + parseFloat(data.user.balance).toFixed(2);
                }

                // Render published history
                renderHistory(data.history || []);

                // Render user bets
                renderUserBets(data.user ? data.user.current_bets : []);
            })
            .catch(err => console.error("Sync error:", err));
    }

    function renderHistory(records) {
        const tbody = document.getElementById('history-table-body');
        if (!records.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-slate-400">No published records yet.</td></tr>';
            return;
        }

        let html = '';
        records.forEach(r => {
            let colorBadge = '';
            if (r.result_color === 'green') {
                colorBadge = '<span class="inline-block w-4 h-4 rounded-full bg-emerald-500" title="Green"></span>';
            } else if (r.result_color === 'red') {
                colorBadge = '<span class="inline-block w-4 h-4 rounded-full bg-rose-500" title="Red"></span>';
            } else if (r.result_color === 'violet') {
                colorBadge = '<span class="inline-block w-4 h-4 rounded-full bg-purple-500" title="Violet"></span>';
            } else if (r.result_color === 'green-violet') {
                colorBadge = '<span class="inline-flex"><span class="w-2 h-4 rounded-l-full bg-emerald-500"></span><span class="w-2 h-4 rounded-r-full bg-purple-500"></span></span>';
            } else if (r.result_color === 'red-violet') {
                colorBadge = '<span class="inline-flex"><span class="w-2 h-4 rounded-l-full bg-rose-500"></span><span class="w-2 h-4 rounded-r-full bg-purple-500"></span></span>';
            }

            const sizeBadge = r.result_size === 'big'
                ? '<span class="bg-amber-100 text-amber-800 text-[10px] px-2 py-0.5 rounded font-black">BIG</span>'
                : '<span class="bg-blue-100 text-blue-800 text-[10px] px-2 py-0.5 rounded font-black">SMALL</span>';

            const timeStr = r.completed_at ? r.completed_at.split(' ')[1] : '';

            html += `
                <tr class="hover:bg-slate-50/80 transition">
                    <td class="py-2.5 px-3 font-mono font-bold text-slate-800">${r.round_number}</td>
                    <td class="py-2.5 px-3 text-center">
                        <span class="inline-block w-6 h-6 rounded-full text-white font-black text-xs leading-6 ${r.result_number % 2 === 0 ? 'bg-rose-500' : 'bg-emerald-500'}">${r.result_number}</span>
                    </td>
                    <td class="py-2.5 px-3 text-center">${sizeBadge}</td>
                    <td class="py-2.5 px-3 text-center">${colorBadge}</td>
                    <td class="py-2.5 px-3 text-right text-slate-400 font-mono text-[11px]">${timeStr}</td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    function renderUserBets(bets) {
        const list = document.getElementById('user-bets-list');
        if (!bets || !bets.length) {
            list.innerHTML = '<div class="py-4 text-center text-slate-400">No active bets on this round. Place your prediction above!</div>';
            return;
        }

        let html = '';
        bets.forEach(b => {
            const statusClass = b.status === 'won' ? 'text-emerald-600 bg-emerald-50 border-emerald-200' : (b.status === 'lost' ? 'text-rose-600 bg-rose-50 border-rose-200' : 'text-amber-600 bg-amber-50 border-amber-200');
            html += `
                <div class="py-3 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-slate-800">Choice: <span class="uppercase text-blue-600 font-black">${b.bet_choice}</span> (${parseFloat(b.multiplier).toFixed(1)}x)</div>
                        <div class="text-[10px] text-slate-400">Placed: ${b.created_at}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-black text-slate-900">₹${parseFloat(b.amount).toFixed(2)}</div>
                        <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded border ${statusClass}">${b.status} ${b.status === 'won' ? '+₹' + parseFloat(b.win_amount).toFixed(2) : ''}</span>
                    </div>
                </div>
            `;
        });
        list.innerHTML = html;
    }

    function submitBet() {
        const amountInput = document.getElementById('bet-amount');
        const amount = parseFloat(amountInput.value);
        const alertBox = document.getElementById('bet-alert');

        if (isNaN(amount) || amount < 10) {
            showAlert('Minimum bet amount is ₹10.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('game_slug', gameSlug);
        formData.append('choice', currentSelectedChoice);
        formData.append('amount', amount);

        const submitBtn = document.getElementById('submit-bet-btn');
        submitBtn.disabled = true;
        submitBtn.innerText = 'Placing...';

        fetch('/api/place_bet.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.innerText = 'Confirm Bet';

            if (data.success) {
                showAlert(data.message, 'success');
                // Refresh status and wallet
                fetchRoundStatus();
            } else {
                showAlert(data.message, 'error');
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            submitBtn.innerText = 'Confirm Bet';
            showAlert('Failed to place bet. Please check network connection.', 'error');
        });
    }

    function showAlert(msg, type) {
        const box = document.getElementById('bet-alert');
        box.classList.remove('hidden', 'bg-emerald-50', 'text-emerald-800', 'border-emerald-200', 'bg-rose-50', 'text-rose-800', 'border-rose-200');
        if (type === 'success') {
            box.classList.add('bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-200');
        } else {
            box.classList.add('bg-rose-50', 'text-rose-800', 'border', 'border-rose-200');
        }
        box.innerText = msg;

        setTimeout(() => {
            box.classList.add('hidden');
        }, 4000);
    }

    // Tick countdown every second
    timerInterval = setInterval(() => {
        if (serverRemainingSeconds > 0) {
            serverRemainingSeconds--;
            updateTimerDisplay();
        } else {
            // When timer hits 0, poll server for completed result and next sequential round!
            updateTimerDisplay();
            setTimeout(fetchRoundStatus, 1500);
        }
    }, 1000);

    // Poll authoritative server every 4 seconds to maintain sync
    pollInterval = setInterval(fetchRoundStatus, 4000);

    // Initial fetch
    fetchRoundStatus();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
