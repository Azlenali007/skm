<?php
/**
 * Sikkim Gaming Platform - Admin Crash Game Management
 * Live monitor, real-time player volume, manual crash multiplier control,
 * automatic mode toggle, and audit history.
 */
require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/crash_engine.php';

$pdo = getDB();
$round = processAndGetActiveCrashRound($pdo);
$pageTitle = "Crash Game Management";
?>

<div class="w-full mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <div class="flex items-center gap-2 mb-1">
            <a href="/admin/games.php" class="text-xs font-bold text-blue-600 hover:underline">&larr; Games Catalog</a>
            <span class="text-slate-300">/</span>
            <span class="text-xs font-semibold text-slate-500">Crash Game</span>
        </div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
            <span class="w-2.5 h-6 bg-blue-600 rounded-full inline-block"></span>
            Crash Game Management
        </h1>
        <p class="text-xs text-slate-500 mt-0.5">Live flight telemetry, player volumes, manual crash overrides, and round audits</p>
    </div>

    <div class="flex items-center gap-2">
        <a href="/crash-game.php" target="_blank" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
            <span>Open User Arena &nearr;</span>
        </a>
        <button onclick="fetchAdminState()" class="px-4 py-2 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-xl text-xs font-bold transition border border-blue-200 cursor-pointer">
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
    
    <!-- 1. Active Round & Live Multiplier Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">ACTIVE FLIGHT ROUND</span>
                <span id="badgeRoundStatus" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800">
                    <?= strtoupper($round['status']) ?>
                </span>
            </div>
            <div class="flex items-baseline gap-2 mb-2">
                <span class="text-3xl font-black text-slate-900 font-mono tracking-tight" id="displayRoundNumber">
                    #<?= e($round['round_number']) ?>
                </span>
            </div>
            <p class="text-xs text-slate-500">
                Launch: <span id="displayLaunchTime" class="font-mono text-slate-700"><?= date('H:i:s', strtotime($round['flight_start_time'])) ?></span>
            </p>
        </div>

        <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-xs font-bold text-slate-600">Live Multiplier:</span>
            <span id="displayMultiplier" class="font-mono text-lg font-black text-blue-600 bg-blue-50 px-2.5 py-0.5 rounded-lg border border-blue-100">
                1.00x
            </span>
        </div>
    </div>

    <!-- 2. Result Mode & Status Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">RESULT CONTROL MODE</span>
                <span id="badgeMode" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-blue-100 text-blue-800">
                    <?= strtoupper($round['result_mode'] ?? 'AUTO') ?>
                </span>
            </div>

            <!-- Mode Selector (Radio Buttons) -->
            <div class="space-y-2 mb-3">
                <label class="flex items-center gap-2.5 p-2 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition">
                    <input type="radio" name="crash_mode" value="auto" id="modeRadioAuto" onchange="toggleMode('auto')" <?= ($round['result_mode'] ?? 'auto') === 'auto' ? 'checked' : '' ?> class="w-4 h-4 text-blue-600">
                    <div>
                        <span class="text-xs font-bold text-slate-900 block leading-tight">Automatic Mode</span>
                        <span class="text-[10px] text-slate-400">System determines crash point via server-side game logic</span>
                    </div>
                </label>

                <label class="flex items-center gap-2.5 p-2 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition">
                    <input type="radio" name="crash_mode" value="manual" id="modeRadioManual" onchange="toggleMode('manual')" <?= ($round['result_mode'] ?? 'auto') === 'manual' ? 'checked' : '' ?> class="w-4 h-4 text-blue-600">
                    <div>
                        <span class="text-xs font-bold text-slate-900 block leading-tight">Manual Mode</span>
                        <span class="text-[10px] text-slate-400">Admin sets target crash multiplier point</span>
                    </div>
                </label>
            </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
            <span class="text-slate-500 font-medium">Configured Crash Point:</span>
            <span id="displayConfiguredCrash" class="font-black font-mono text-purple-700 bg-purple-50 px-2 py-0.5 rounded-md text-[11px] border border-purple-200">
                <?= $round['manual_multiplier'] ? number_format((float)$round['manual_multiplier'], 2) . 'x' : 'Auto Generated' ?>
            </span>
        </div>
    </div>

    <!-- 3. Manual Multiplier Override & Emergency Crash Button -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between">
        <div>
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">SET MANUAL CRASH POINT</span>
            <p class="text-xs text-slate-500 mb-2">Configure target multiplier for this round. Manual results take strict priority.</p>

            <!-- Quick multiplier presets -->
            <div class="grid grid-cols-4 gap-1.5 mb-2">
                <button type="button" onclick="setPresetMultiplier(1.20)" class="py-1 rounded-lg bg-slate-100 hover:bg-blue-50 font-mono text-xs font-bold text-slate-700">1.20x</button>
                <button type="button" onclick="setPresetMultiplier(1.85)" class="py-1 rounded-lg bg-slate-100 hover:bg-blue-50 font-mono text-xs font-bold text-slate-700">1.85x</button>
                <button type="button" onclick="setPresetMultiplier(2.50)" class="py-1 rounded-lg bg-slate-100 hover:bg-blue-50 font-mono text-xs font-bold text-slate-700">2.50x</button>
                <button type="button" onclick="setPresetMultiplier(5.00)" class="py-1 rounded-lg bg-slate-100 hover:bg-blue-50 font-mono text-xs font-bold text-slate-700">5.00x</button>
            </div>

            <!-- Custom Multiplier Input -->
            <div class="flex items-center gap-1.5 mb-3">
                <input type="number" 
                       id="manualMultiplierInput" 
                       value="<?= $round['manual_multiplier'] ?: 2.00 ?>" 
                       step="0.05" 
                       min="1.01" 
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1 text-xs font-mono font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-600"
                       placeholder="e.g. 2.50">
                <button type="button" onclick="submitManualMultiplier()" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shrink-0">
                    Apply
                </button>
            </div>
        </div>

        <div class="pt-2 border-t border-slate-100">
            <button type="button" 
                    onclick="triggerCrashNow()"
                    id="btnCrashNow"
                    class="w-full py-2 bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-700 hover:to-red-700 text-white font-bold rounded-xl text-xs shadow-xs transition active:scale-95 flex items-center justify-center gap-1.5 cursor-pointer">
                <span>⚡ Force Immediate Crash</span>
            </button>
        </div>
    </div>
</div>

<!-- LIVE ADMIN ROUND MONITOR (Matches exact requested wireframe) -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 mb-4 border-b border-slate-100 gap-2">
        <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
            <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider font-mono">
                <span id="monitorRoundNumber">ROUND <?= e($round['round_number']) ?></span> &bull; 
                <span id="monitorStatusText" class="text-blue-600">STATUS: <?= strtoupper($round['status']) ?></span>
            </h2>
        </div>
        <div class="flex items-center gap-3 text-xs">
            <span class="text-slate-400">Live Multiplier: <strong id="monitorLiveMultiplier" class="text-amber-500 font-mono text-sm">1.00x</strong></span>
        </div>
    </div>

    <!-- Live Telemetry Stats Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
            <span class="text-[10px] font-bold uppercase text-slate-500 block mb-1">TOTAL PLAYERS</span>
            <span id="statTotalPlayers" class="font-mono font-black text-xl text-slate-900">0</span>
            <span class="text-[10px] text-slate-400 block mt-0.5">Active users entered</span>
        </div>

        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
            <span class="text-[10px] font-bold uppercase text-slate-500 block mb-1">TOTAL AMOUNT ENTERED</span>
            <span id="statTotalAmount" class="font-mono font-black text-xl text-blue-600">₹0.00</span>
            <span class="text-[10px] text-slate-400 block mt-0.5">Accumulated volume</span>
        </div>

        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
            <span class="text-[10px] font-bold uppercase text-slate-500 block mb-1">CASHED OUT PLAYERS</span>
            <span id="statCashedOut" class="font-mono font-black text-xl text-emerald-600">0</span>
            <span class="text-[10px] text-slate-400 block mt-0.5">Secured their wins</span>
        </div>

        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
            <span class="text-[10px] font-bold uppercase text-slate-500 block mb-1">IN-FLIGHT PENDING</span>
            <span id="statPending" class="font-mono font-black text-xl text-amber-500">0</span>
            <span class="text-[10px] text-slate-400 block mt-0.5">Still riding multiplier</span>
        </div>
    </div>
</div>

<!-- ADMIN ROUND HISTORY TABLE -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <div class="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
        <h3 class="font-black text-slate-900 text-xs uppercase tracking-wider">Crash Flight History (MySQL Authoritative)</h3>
        <span class="text-[11px] text-slate-400">Completed Sequential Flights</span>
    </div>

    <div class="overflow-x-auto no-scrollbar">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="border-b border-slate-200 text-slate-400 uppercase font-bold bg-slate-50">
                    <th class="py-3 px-4">Round #</th>
                    <th class="py-3 px-4">Final Multiplier</th>
                    <th class="py-3 px-4">Result Mode</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4 text-right">Total Volume</th>
                    <th class="py-3 px-4 text-right">Total Payout</th>
                    <th class="py-3 px-4">Created Time</th>
                    <th class="py-3 px-4">Completed Time</th>
                </tr>
            </thead>
            <tbody id="crashAuditTableBody" class="divide-y divide-slate-100">
                <tr><td colspan="8" class="py-4 text-center text-slate-400">Loading flight records...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Plain JavaScript Live Monitor & Control Script -->
<script>
    let activeRoundId = <?= (int)$round['id'] ?>;
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

        fetch('/api/admin_crash_game.php', { method: 'POST', body: fd })
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

    function setPresetMultiplier(val) {
        document.getElementById('manualMultiplierInput').value = val.toFixed(2);
        submitManualMultiplier();
    }

    function submitManualMultiplier() {
        const val = parseFloat(document.getElementById('manualMultiplierInput').value) || 0;
        if (val < 1.01) {
            alert('Multiplier must be at least 1.01x');
            return;
        }

        const fd = new FormData();
        fd.append('action', 'set_manual_multiplier');
        fd.append('round_id', activeRoundId);
        fd.append('multiplier', val);

        fetch('/api/admin_crash_game.php', { method: 'POST', body: fd })
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

    function triggerCrashNow() {
        if (!confirm('Force immediate crash on active round?')) return;

        const fd = new FormData();
        fd.append('action', 'crash_now');
        fd.append('round_id', activeRoundId);

        fetch('/api/admin_crash_game.php', { method: 'POST', body: fd })
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
        fetch('/api/admin_crash_game.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            if (data.round) {
                activeRoundId = data.round.id;
                document.getElementById('displayRoundNumber').textContent = '#' + data.round.round_number;
                document.getElementById('monitorRoundNumber').textContent = 'ROUND ' + data.round.round_number;
                document.getElementById('badgeRoundStatus').textContent = data.round.status;
                document.getElementById('monitorStatusText').textContent = 'STATUS: ' + data.round.status;

                document.getElementById('badgeMode').textContent = data.round.result_mode.toUpperCase();
                if (data.round.result_mode === 'manual') {
                    document.getElementById('modeRadioManual').checked = true;
                } else {
                    document.getElementById('modeRadioAuto').checked = true;
                }

                const curM = parseFloat(data.round.current_multiplier).toFixed(2) + 'x';
                document.getElementById('displayMultiplier').textContent = curM;
                document.getElementById('monitorLiveMultiplier').textContent = curM;

                const cfgEl = document.getElementById('displayConfiguredCrash');
                if (data.round.manual_multiplier) {
                    cfgEl.textContent = parseFloat(data.round.manual_multiplier).toFixed(2) + 'x (Manual)';
                } else {
                    cfgEl.textContent = 'Auto (' + parseFloat(data.round.crash_multiplier).toFixed(2) + 'x)';
                }
            }

            if (data.live_stats) {
                const s = data.live_stats;
                document.getElementById('statTotalPlayers').textContent = s.total_players;
                document.getElementById('statTotalAmount').textContent = '₹' + parseFloat(s.total_amount).toFixed(2);
                document.getElementById('statCashedOut').textContent = s.cashed_out_count;
                document.getElementById('statPending').textContent = s.pending_count;
            }

            if (data.recent_rounds && Array.isArray(data.recent_rounds)) {
                renderAuditTable(data.recent_rounds);
            }
        })
        .catch(err => console.error('Admin poll error:', err));
    }

    function renderAuditTable(rounds) {
        const tb = document.getElementById('crashAuditTableBody');
        if (!rounds || rounds.length === 0) {
            tb.innerHTML = '<tr><td colspan="8" class="py-4 text-center text-slate-400">No round audit records found.</td></tr>';
            return;
        }

        let html = '';
        rounds.forEach(r => {
            const mult = parseFloat(r.crash_multiplier || 1.0);
            let badgeClass = 'bg-blue-100 text-blue-800';
            if (mult >= 10.0) badgeClass = 'bg-purple-100 text-purple-800 font-black';
            else if (mult >= 2.0) badgeClass = 'bg-emerald-100 text-emerald-800 font-black';

            html += `
                <tr class="hover:bg-slate-50 transition">
                    <td class="py-3 px-4 font-mono font-black text-slate-900">#${r.round_number}</td>
                    <td class="py-3 px-4">
                        <span class="font-mono px-2 py-0.5 rounded-full text-[10px] ${badgeClass}">
                            ${mult.toFixed(2)}x
                        </span>
                    </td>
                    <td class="py-3 px-4">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${r.result_mode === 'manual' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700'}">
                            ${(r.result_mode || 'auto').toUpperCase()}
                        </span>
                    </td>
                    <td class="py-3 px-4">
                        <span class="text-[10px] uppercase font-bold text-slate-600">${r.status}</span>
                    </td>
                    <td class="py-3 px-4 text-right font-mono font-bold text-slate-700">₹${parseFloat(r.total_bets_amount || 0).toFixed(2)}</td>
                    <td class="py-3 px-4 text-right font-mono font-bold text-emerald-600">₹${parseFloat(r.total_payout_amount || 0).toFixed(2)}</td>
                    <td class="py-3 px-4 text-slate-400 text-[11px] font-mono">${r.created_at || '--'}</td>
                    <td class="py-3 px-4 text-slate-400 text-[11px] font-mono">${r.completed_at || '--'}</td>
                </tr>
            `;
        });
        tb.innerHTML = html;
    }

    document.addEventListener('DOMContentLoaded', () => {
        fetchAdminState();
        pollInterval = setInterval(fetchAdminState, 1500);
    });
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
