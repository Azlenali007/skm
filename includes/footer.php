<?php
/**
 * Sikkim Gaming Platform - Footer Component
 * Strict Normal Document Flow (NO sticky, NO fixed)
 */
?>
    </main>

    <!-- Footer (Strictly Normal Document Flow) -->
    <footer class="w-full bg-white border-t border-slate-200 mt-auto py-8 text-slate-600 text-xs">
        <div class="max-w-6xl mx-auto px-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 mb-8">
                <div>
                    <div class="flex items-center mb-3">
                        <span class="text-2xl font-black italic tracking-tighter text-blue-600">
                            <i>S</i>IKKIM
                        </span>
                        <span class="ml-1 text-[9px] font-extrabold uppercase bg-blue-100 text-blue-800 px-1 py-0.5 rounded">Game</span>
                    </div>
                    <p class="text-slate-500 leading-relaxed mb-3">
                        Premium gaming platform with certified RNG fairness, instant automated withdrawals, and 24/7 continuous player support.
                    </p>
                    <div class="flex items-center gap-2 text-slate-400">
                        <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full text-[10px] font-bold border border-emerald-200">
                            ● 100% Server Provable
                        </span>
                    </div>
                </div>

                <div>
                    <h4 class="font-bold text-slate-900 text-sm mb-3 uppercase tracking-wider">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="/games.php" class="hover:text-blue-600 transition">Game Lobby</a></li>
                        <li><a href="/wallet.php" class="hover:text-blue-600 transition">Wallet & Deposits</a></li>
                        <li><a href="/activity.php" class="hover:text-blue-600 transition">Game Records</a></li>
                        <li><a href="/support.php" class="hover:text-blue-600 transition">24/7 Live Support</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold text-slate-900 text-sm mb-3 uppercase tracking-wider">Security & Fairness</h4>
                    <ul class="space-y-2">
                        <li><span class="text-slate-500">256-Bit SSL Encrypted</span></li>
                        <li><span class="text-slate-500">Authoritative MySQL Engine</span></li>
                        <li><span class="text-slate-500">Provably Fair Sequential Rounds</span></li>
                        <li><span class="text-slate-500">Automated UPI / IMPS Gateway</span></li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-bold text-slate-900 text-sm mb-3 uppercase tracking-wider">Help & Legal</h4>
                    <ul class="space-y-2">
                        <li><a href="/support.php" class="hover:text-blue-600 transition">Customer Service</a></li>
                        <li><span class="text-slate-400">18+ Only • Play Responsibly</span></li>
                        <li><a href="/cron/round_processor.php" target="_blank" class="text-slate-400 hover:text-blue-600 transition text-[11px]">System Cron Trigger</a></li>
                        <?php if (isLoggedIn() && ($u = getCurrentUser()) && $u['role'] === 'admin'): ?>
                            <li><a href="/admin/" class="text-blue-600 font-bold hover:underline">Admin Control Panel</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-slate-400 text-[11px]">
                <p>&copy; <?= date('Y') ?> Sikkim Gaming Platform. All rights reserved.</p>
                <p class="flex items-center gap-2">
                    <span>Server Time: <?= date('Y-m-d H:i:s') ?> UTC</span>
                </p>
            </div>
        </div>
    </footer>

    <!-- Plain JavaScript Assets (NO framework, NO compilation needed) -->
    <script src="/assets/js/main.js"></script>
</body>
</html>
