<?php
/**
 * Sikkim Gaming Platform - Admin Colour Game Controls & Live Monitor
 * Real-time AJAX polling, Live Player & Points breakdown per colour and numbers 0-9,
 * Manual vs Automatic Mode Switcher, and Instant Result Publishing.
 */
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/round_engine.php';

$pdo = getDB();
$gameSlug = 'colour-game';

// Fetch active round
$activeRound = processAndGetActiveRound($pdo, $gameSlug);
$remaining = max(0, strtotime($activeRound['end_time']) - time());

$pageTitle = "Colour Game Management";
?>

<div class="w-full mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <div class="flex items-center gap-2 mb-1">
            <a href="/admin/games.php" class="text-xs font-bold text-blue-600 hover:underline">&larr; Games Catalog</a>
            <span class="text-slate-300">/</span>
            <span class="text-xs font-semibold text-slate-500">Colour Game</span>
        </div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
            <span class="w-2.5 h-6 bg-blue-600 rounded-full inline-block"></span>
            Colour Game Management
        </h1>
        <p class="text-xs text-slate-500 mt-0.5">Live period monitoring, real-time amounts, and result mode controls</p>
    </div>

    <div class="flex items-center gap-2">
        <a href="/colour-game.php" target="_blank" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
            <span>Open User Screen &nearr;</span>
        </a>
        <button onclick="fetchAdminState()" class="px-4 py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-xl text-xs font-bold transition border border-blue-200">
            🔄 Refresh Now
        </button>
    </div>
</div>

<!-- Alert Banner -->
<div id="adminNotice" class="hidden mb-6 p-3 rounded-xl text-xs font-bold flex items-center justify-between shadow-xs transition">
    <span id="adminNoticeText"></span>
    <button onclick="document.getElementById('adminNotice').classList.add('hidden')" class="text-slate-400 hover:text-slate-700 text-sm font-bold">&times;</button>
</div>

<!-- Top Metrics & Active Round Overview Grid -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    
    <!-- 1. Active Period & Timer Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">CURRENT ACTIVE ROUND</span>
                <span id="badgeRoundStatus" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800">
                    ACTIVE
                </span>
            </div>
            <div class="flex items-baseline gap-2 mb-2">
                <span class="text-3xl font-black text-slate-900 font-mono tracking-tight" id="displayRoundNumber">
                    #<?= e($activeRound['round_number']) ?>
                </span>
            </div>
            <p class="text-xs text-slate-500">
                Started: <span id="displayStartTime" class="font-mono text-slate-700"><?= date('H:i:s', strtotime($activeRound['start_time'])) ?></span>
            </p>
        </div>

        <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-xs font-bold text-slate-600">Time Remaining:</span>
            <span id="displayCountdown" class="font-mono text-lg font-black text-blue-600 bg-blue-50 px-2.5 py-0.5 rounded-lg border border-blue-100">
                <?= sprintf('%02d:%02d', floor($remaining / 60), $remaining % 60) ?>
            </span>
        </div>
    </div>

    <!-- 2. Result Mode & Status Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">RESULT CONTROL MODE</span>
                <span id="badgeMode" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-blue-100 text-blue-800">
                    <?= strtoupper($activeRound['result_mode'] ?? 'AUTO') ?>
                </span>
            </div>

            <!-- Mode Selector (Radio Buttons) -->
            <div class="space-y-2 mb-3">
                <label class="flex items-center gap-2.5 p-2 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition">
                    <input type="radio" name="result_mode" value="auto" id="modeRadioAuto" onchange="toggleMode('auto')" <?= ($activeRound['result_mode'] ?? 'auto') === 'auto' ? 'checked' : '' ?> class="w-4 h-4 text-blue-600">
                    <div>
                        <span class="text-xs font-bold text-slate-900 block leading-tight">Automatic Mode</span>
                        <span class="text-[10px] text-slate-400">Server generates weighted result at completion point</span>
                    </div>
                </label>

                <label class="flex items-center gap-2.5 p-2 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition">
                    <input type="radio" name="result_mode" value="manual" id="modeRadioManual" onchange="toggleMode('manual')" <?= ($activeRound['result_mode'] ?? 'auto') === 'manual' ? 'checked' : '' ?> class="w-4 h-4 text-blue-600">
                    <div>
                        <span class="text-xs font-bold text-slate-900 block leading-tight">Manual Mode</span>
                        <span class="text-[10px] text-slate-400">Admin selects authoritative outcome for this round</span>
                    </div>
                </label>
            </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
            <span class="text-slate-500 font-medium">Configured Outcome:</span>
            <span id="displayManualChoice" class="font-black uppercase px-2 py-0.5 rounded-md text-[11px] <?= !empty($activeRound['manual_result']) ? 'bg-purple-100 text-purple-800' : 'bg-slate-100 text-slate-500' ?>">
                <?= !empty($activeRound['manual_result']) ? strtoupper($activeRound['manual_result']) : 'None' ?>
            </span>
        </div>
    </div>

    <!-- 3. Manual Result Setter & Instant Publish Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between">
        <div>
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">SET MANUAL OUTCOME</span>
            <p class="text-xs text-slate-500 mb-2">Select Colour and/or Number 0-9. Takes strict priority over automatic processing.</p>

            <!-- 3 Color Setter Buttons -->
            <div class="grid grid-cols-3 gap-1.5 mb-2">
                <button type="button" 
                        onclick="setManualChoice('red', null)"
                        id="adminBtnRed"
                        class="p-2 rounded-xl bg-red-500 hover:bg-red-600 text-white font-black text-xs uppercase shadow-xs transition active:scale-95 border-2 border-transparent">
                    RED
                </button>
                <button type="button" 
                        onclick="setManualChoice('green', null)"
                        id="adminBtnGreen"
                        class="p-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-black text-xs uppercase shadow-xs transition active:scale-95 border-2 border-transparent">
                    GREEN
                </button>
                <button type="button" 
                        onclick="setManualChoice('violet', null)"
                        id="adminBtnViolet"
                        class="p-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-black text-xs uppercase shadow-xs transition active:scale-95 border-2 border-transparent">
                    VIOLET
                </button>
            </div>

            <!-- Numbers 0-9 Setter Buttons -->
            <div class="grid grid-cols-5 gap-1 mb-3">
                <?php for ($n = 0; $n <= 9; $n++): ?>
                    <button type="button"
                            onclick="setManualChoice(null, <?= $n ?>)"
                            id="adminBtnNum<?= $n ?>"
                            class="py-1 rounded-lg bg-slate-100 hover:bg-blue-50 text-slate-800 hover:text-blue-700 font-mono font-bold text-xs border border-slate-200 transition">
                        <?= $n ?>
                    </button>
                <?php endfor; ?>
            </div>
        </div>

        <div class="pt-2 border-t border-slate-100">
            <button type="button" 
                    onclick="publishResultNow()"
                    id="btnPublishNow"
                    class="w-full py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold rounded-xl text-xs shadow-xs transition active:scale-95 flex items-center justify-center gap-1.5 cursor-pointer">
                <span>⚡ Publish Result Immediately</span>
            </button>
        </div>
    </div>
</div>

<!-- LIVE ADMIN ROUND MONITOR (Colours & Numbers 0-9) -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 mb-4 border-b border-slate-100 gap-2">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">
                LIVE ADMIN ROUND MONITOR &bull; <span id="monitorRoundNumber">ROUND <?= e($activeRound['round_number']) ?></span>
            </h2>
        </div>
        <div class="flex items-center gap-3 text-xs">
            <span class="text-slate-400">Total Players: <strong id="monitorTotalPlayers" class="text-slate-800">0</strong></span>
            <span class="text-slate-300">|</span>
            <span class="text-slate-400">Total Points: <strong id="monitorTotalAmount" class="text-blue-600">0.00</strong></span>
        </div>
    </div>

    <!-- Live Colour Breakdown Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <!-- RED MONITOR CARD -->
        <div class="p-4 rounded-xl border border-red-200 bg-red-50/50 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-black uppercase text-red-700 tracking-wider">RED</span>
                <span class="text-[10px] font-bold bg-red-100 text-red-800 px-2 py-0.5 rounded-full">2.0X</span>
            </div>
            <div class="space-y-1">
                <div class="flex justify-between text-xs">
                    <span class="text-slate-500">Players:</span>
                    <span id="statRedPlayers" class="font-bold text-slate-800 font-mono">0</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-slate-500">Points:</span>
                    <span id="statRedAmount" class="font-black text-red-600 font-mono">0.00</span>
                </div>
            </div>
        </div>

        <!-- GREEN MONITOR CARD -->
        <div class="p-4 rounded-xl border border-emerald-200 bg-emerald-50/50 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-black uppercase text-emerald-700 tracking-wider">GREEN</span>
                <span class="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full">2.0X</span>
            </div>
            <div class="space-y-1">
                <div class="flex justify-between text-xs">
                    <span class="text-slate-500">Players:</span>
                    <span id="statGreenPlayers" class="font-bold text-slate-800 font-mono">0</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-slate-500">Points:</span>
                    <span id="statGreenAmount" class="font-black text-emerald-600 font-mono">0.00</span>
                </div>
            </div>
        </div>

        <!-- VIOLET MONITOR CARD -->
        <div class="p-4 rounded-xl border border-purple-200 bg-purple-50/50 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-black uppercase text-purple-700 tracking-wider">VIOLET</span>
                <span class="text-[10px] font-bold bg-purple-100 text-purple-800 px-2 py-0.5 rounded-full">4.5X</span>
            </div>
            <div class="space-y-1">
                <div class="flex justify-between text-xs">
                    <span class="text-slate-500">Players:</span>
                    <span id="statVioletPlayers" class="font-bold text-slate-800 font-mono">0</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-slate-500">Points:</span>
                    <span id="statVioletAmount" class="font-black text-purple-600 font-mono">0.00</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Numbers 0-9 Breakdown Grid -->
    <div class="pt-4 border-t border-slate-100">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 mb-3 flex items-center justify-between">
            <span>NUMBERS (0 – 9) LIVE DATA</span>
            <span class="text-[10px] text-slate-400 font-normal">Real MySQL Submissions</span>
        </h3>

        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
            <?php for ($num = 0; $num <= 9; $num++): ?>
                <div class="p-2.5 rounded-xl border border-slate-200 bg-slate-50/80 hover:bg-slate-100/80 transition flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-blue-600 text-white font-mono font-black text-xs flex items-center justify-center">
                            <?= $num ?>
                        </span>
                        <div class="text-[11px] leading-tight">
                            <span class="text-slate-500 text-[10px] block">Players: <strong id="numPlayers<?= $num ?>" class="text-slate-800">0</strong></span>
                            <span class="text-slate-500 text-[10px] block">Points: <strong id="numPoints<?= $num ?>" class="text-blue-600">0</strong></span>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- ROUND HISTORY & AUDIT LOG TABLE -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <div class="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
        <h3 class="font-black text-slate-900 text-xs uppercase tracking-wider">Recent Round Settlement Audit</h3>
        <span class="text-[11px] text-slate-400">Authoritative MySQL Records</span>
    </div>

    <div class="overflow-x-auto no-scrollbar">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="border-b border-slate-200 text-slate-400 uppercase font-bold bg-slate-50">
                    <th class="py-3 px-4">Round #</th>
                    <th class="py-3 px-4">Mode</th>
                    <th class="py-3 px-4">Manual Config</th>
                    <th class="py-3 px-4">Published Result</th>
                    <th class="py-3 px-4 text-right">Total Points</th>
                    <th class="py-3 px-4 text-right">Total Payout</th>
                    <th class="py-3 px-4">Settled At</th>
                </tr>
            </thead>
            <tbody id="auditTableBody" class="divide-y divide-slate-100">
                <tr><td colspan="7" class="py-4 text-center text-slate-400">Loading round records...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Plain JavaScript Live Monitor & Control Script -->
<script>
    let activeRoundId = <?= (int)$activeRound['id'] ?>;
    let activeRoundNumber = <?= (int)$activeRound['round_number'] ?>;
    let remainingSeconds = <?= (int)$remaining ?>;
    let currentMode = '<?= e($activeRound['result_mode'] ?? 'auto') ?>';
    let currentManualColor = '<?= e($activeRound['manual_result'] ?? '') ?>';
    let currentManualNumber = <?= ($activeRound['manual_number'] !== null) ? (int)$activeRound['manual_number'] : 'null' ?>;
    let timerInterval = null;
    let pollInterval = null;

    function showNotice(msg, isSuccess = true) {
        const box = document.getElementById('adminNotice');
        const text = document.getElementById('adminNoticeText');
        text.textContent = msg;
        box.className = 'mb-6 p-3 rounded-xl text-xs font-bold flex items-center justify-between shadow-xs transition ' +
            (isSuccess ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200');
        box.classList.remove('hidden');
    }

    function toggleMode(mode) {
        const fd = new FormData();
        fd.append('action', 'set_mode');
        fd.append('round_id', activeRoundId);
        fd.append('mode', mode);

        fetch('/api/admin_colour_game.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showNotice(data.message, true);
                fetchAdminState();
            } else {
                showNotice(data.message, false);
            }
        });
    }

    function setManualChoice(color, number) {
        if (color) currentManualColor = color;
        if (number !== null) currentManualNumber = number;

        const fd = new FormData();
        fd.append('action', 'set_manual_result');
        fd.append('round_id', activeRoundId);
        if (currentManualColor) fd.append('result_color', currentManualColor);
        if (currentManualNumber !== null) fd.append('result_number', currentManualNumber);

        fetch('/api/admin_colour_game.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showNotice(data.message, true);
                fetchAdminState();
            } else {
                showNotice(data.message, false);
            }
        });
    }

    function publishResultNow() {
        let color = currentManualColor || 'red';
        let number = currentManualNumber !== null ? currentManualNumber : 7;

        if (!confirm('Immediately publish outcome [' + color.toUpperCase() + ', Number ' + number + '] for Round #' + activeRoundNumber + '?')) {
            return;
        }

        const fd = new FormData();
        fd.append('action', 'publish_now');
        fd.append('round_id', activeRoundId);
        fd.append('result_color', color);
        fd.append('result_number', number);

        fetch('/api/admin_colour_game.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showNotice(data.message, true);
                fetchAdminState();
            } else {
                showNotice(data.message, false);
            }
        });
    }

    function fetchAdminState() {
        fetch('/api/admin_colour_game.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            if (data.round) {
                activeRoundId = data.round.id;
                activeRoundNumber = data.round.round_number;
                remainingSeconds = data.round.remaining_seconds;
                currentMode = data.round.result_mode;
                currentManualColor = data.round.manual_result || '';
                currentManualNumber = data.round.manual_number !== null ? data.round.manual_number : null;

                document.getElementById('displayRoundNumber').textContent = '#' + activeRoundNumber;
                document.getElementById('monitorRoundNumber').textContent = 'ROUND ' + activeRoundNumber;
                document.getElementById('badgeMode').textContent = currentMode.toUpperCase();

                if (currentMode === 'manual') {
                    document.getElementById('modeRadioManual').checked = true;
                } else {
                    document.getElementById('modeRadioAuto').checked = true;
                }

                // Display manual outcome label
                const manualEl = document.getElementById('displayManualChoice');
                let manualDesc = [];
                if (currentManualColor) manualDesc.push(currentManualColor.toUpperCase());
                if (currentManualNumber !== null) manualDesc.push('Num ' + currentManualNumber);

                if (manualDesc.length > 0) {
                    manualEl.textContent = manualDesc.join(', ');
                    manualEl.className = 'font-black uppercase px-2 py-0.5 rounded-md text-[11px] bg-purple-100 text-purple-800';
                } else {
                    manualEl.textContent = 'None';
                    manualEl.className = 'font-black uppercase px-2 py-0.5 rounded-md text-[11px] bg-slate-100 text-slate-500';
                }

                // Highlight active manual color button
                ['red', 'green', 'violet'].forEach(c => {
                    const btn = document.getElementById('adminBtn' + c.charAt(0).toUpperCase() + c.slice(1));
                    if (btn) {
                        if (c === currentManualColor) {
                            btn.classList.add('ring-4', 'ring-offset-2', 'ring-slate-900', 'scale-105');
                        } else {
                            btn.classList.remove('ring-4', 'ring-offset-2', 'ring-slate-900', 'scale-105');
                        }
                    }
                });

                // Highlight active manual number button
                for (let n = 0; n <= 9; n++) {
                    const btn = document.getElementById('adminBtnNum' + n);
                    if (btn) {
                        if (n === currentManualNumber) {
                            btn.className = 'py-1 rounded-lg bg-blue-600 text-white font-mono font-bold text-xs ring-2 ring-slate-900 scale-105 transition';
                        } else {
                            btn.className = 'py-1 rounded-lg bg-slate-100 hover:bg-blue-50 text-slate-800 hover:text-blue-700 font-mono font-bold text-xs border border-slate-200 transition';
                        }
                    }
                }
            }

            // Update Live Stats
            if (data.live_stats) {
                const s = data.live_stats;
                document.getElementById('monitorTotalPlayers').textContent = s.total_players;
                document.getElementById('monitorTotalAmount').textContent = parseFloat(s.total_amount).toFixed(2);

                if (s.colors) {
                    document.getElementById('statRedPlayers').textContent = s.colors.red.players;
                    document.getElementById('statRedAmount').textContent = parseFloat(s.colors.red.points).toFixed(2);

                    document.getElementById('statGreenPlayers').textContent = s.colors.green.players;
                    document.getElementById('statGreenAmount').textContent = parseFloat(s.colors.green.points).toFixed(2);

                    document.getElementById('statVioletPlayers').textContent = s.colors.violet.players;
                    document.getElementById('statVioletAmount').textContent = parseFloat(s.colors.violet.points).toFixed(2);
                }

                if (s.numbers) {
                    for (let n = 0; n <= 9; n++) {
                        const elP = document.getElementById('numPlayers' + n);
                        const elA = document.getElementById('numPoints' + n);
                        if (elP && s.numbers[n]) elP.textContent = s.numbers[n].players;
                        if (elA && s.numbers[n]) elA.textContent = parseFloat(s.numbers[n].points).toFixed(0);
                    }
                }
            }

            // Update Audit Table
            if (data.recent_rounds && Array.isArray(data.recent_rounds)) {
                renderAuditTable(data.recent_rounds);
            }
        })
        .catch(err => console.error('Admin poll error:', err));
    }

    function renderAuditTable(rounds) {
        const tb = document.getElementById('auditTableBody');
        if (!rounds || rounds.length === 0) {
            tb.innerHTML = '<tr><td colspan="7" class="py-4 text-center text-slate-400">No round audit records found.</td></tr>';
            return;
        }

        let html = '';
        rounds.forEach(r => {
            const isCompleted = (r.status === 'completed');
            const color = (r.result_color || '').toLowerCase();
            const num = r.result_number !== null ? r.result_number : '--';

            let colorBadge = '<span class="text-slate-400">Pending</span>';
            if (color === 'red') colorBadge = '<span class="bg-red-500 text-white font-black px-2 py-0.5 rounded text-[10px]">RED • ' + num + '</span>';
            if (color === 'green') colorBadge = '<span class="bg-emerald-500 text-white font-black px-2 py-0.5 rounded text-[10px]">GREEN • ' + num + '</span>';
            if (color === 'violet') colorBadge = '<span class="bg-purple-600 text-white font-black px-2 py-0.5 rounded text-[10px]">VIOLET • ' + num + '</span>';

            let manualSummary = '-';
            if (r.manual_result || r.manual_number !== null) {
                let parts = [];
                if (r.manual_result) parts.push(r.manual_result.toUpperCase());
                if (r.manual_number !== null) parts.push('Num ' + r.manual_number);
                manualSummary = '<span class="text-purple-700 font-bold">' + parts.join(', ') + '</span>';
            }

            html += `
                <tr class="hover:bg-slate-50 transition">
                    <td class="py-3 px-4 font-mono font-black text-slate-900">#${r.round_number}</td>
                    <td class="py-3 px-4">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${r.result_mode === 'manual' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700'}">
                            ${(r.result_mode || 'auto').toUpperCase()}
                        </span>
                    </td>
                    <td class="py-3 px-4 text-slate-600 font-bold uppercase">${manualSummary}</td>
                    <td class="py-3 px-4">${colorBadge}</td>
                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-700">${parseFloat(r.total_bets_amount || 0).toFixed(2)}</td>
                    <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600">${parseFloat(r.total_payout_amount || 0).toFixed(2)}</td>
                    <td class="py-3 px-4 text-slate-400 text-[11px] font-mono">${r.completed_at || r.created_at || '--'}</td>
                </tr>
            `;
        });
        tb.innerHTML = html;
    }

    function runCountdown() {
        clearInterval(timerInterval);
        timerInterval = setInterval(() => {
            if (remainingSeconds > 0) {
                remainingSeconds--;
                const mins = Math.floor(remainingSeconds / 60);
                const secs = remainingSeconds % 60;
                document.getElementById('displayCountdown').textContent = 
                    (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;
            } else {
                fetchAdminState();
            }
        }, 1000);
    }

    document.addEventListener('DOMContentLoaded', () => {
        fetchAdminState();
        runCountdown();
        pollInterval = setInterval(fetchAdminState, 2500);
    });
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
