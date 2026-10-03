<?php
/**
 * Sikkim Gaming Platform - User Login
 * Real PHP + MySQL with PDO, Password Verification & Sessions
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: /dashboard.php");
    exit;
}

$error = '';
$loginInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = "Security session expired. Please reload and try again.";
    } else {
        $loginInput = trim($_POST['login_input'] ?? '');
        $password = $_POST['password'] ?? '';
        $redirect = trim($_POST['redirect'] ?? '');

        if (empty($loginInput) || empty($password)) {
            $error = "Please enter your phone number/username and password.";
        } else {
            $pdo = getDB();
            // Lookup by phone number or username
            $stmt = $pdo->prepare("
                SELECT id, username, phone, email, password_hash, role, status
                FROM `users`
                WHERE `phone` = :p_input OR `username` = :u_input
                LIMIT 1
            ");
            $stmt->execute([':p_input' => $loginInput, ':u_input' => $loginInput]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = "Invalid mobile number, username, or password.";
            } elseif ($user['status'] !== 'active') {
                $error = "Your account has been suspended. Please contact customer support.";
            } else {
                // Successful Login
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                setFlash('success', 'Welcome back, ' . e($user['username']) . '!');

                if ($user['role'] === 'admin' && empty($redirect)) {
                    header("Location: /admin/");
                    exit;
                }

                if (!empty($redirect) && $redirect === 'game') {
                    $slug = trim($_GET['slug'] ?? 'win-go-1m');
                    header("Location: /game-play.php?slug=" . urlencode($slug));
                    exit;
                }

                header("Location: /dashboard.php");
                exit;
            }
        }
    }
}

$pageTitle = "Login to Your Account";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-md mx-auto my-4 sm:my-8">
    <div class="card-premium p-6 sm:p-8">
        <!-- Header -->
        <div class="text-center mb-6">
            <div class="flex items-center justify-center mb-2">
                <span class="text-3xl font-black italic tracking-tighter text-blue-600">
                    <i>S</i>IKKIM
                </span>
            </div>
            <h1 class="text-xl font-black text-slate-900">Player Sign In</h1>
            <p class="text-xs text-slate-500 mt-1">Enter your registered mobile number or username</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-5 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Form (Strict Normal Flow) -->
        <form method="POST" action="/login.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="redirect" value="<?= e($_GET['redirect'] ?? '') ?>">

            <div>
                <label for="login_input" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Phone Number or Username <span class="text-rose-500">*</span>
                </label>
                <input type="text" id="login_input" name="login_input" value="<?= e($loginInput) ?>" required placeholder="e.g. 9876543210 or username" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
            </div>

            <div>
                <div class="flex justify-between items-center mb-1">
                    <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Password <span class="text-rose-500">*</span>
                    </label>
                    <a href="/support.php" class="text-[11px] font-semibold text-blue-600 hover:underline">Forgot password?</a>
                </div>
                <input type="password" id="password" name="password" required placeholder="Enter your password" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
            </div>

            <button type="submit" class="w-full py-3 bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800 text-white font-black text-sm rounded-xl shadow-md transition transform active:scale-[0.99]">
                Log In to Play
            </button>
        </form>

        <!-- Quick Demo Hint (Admin Login) -->
        <div class="mt-5 p-3 rounded-xl bg-blue-50/70 border border-blue-100 text-[11px] text-blue-800">
            <div class="font-bold flex items-center justify-between">
                <span>Default Admin Credentials:</span>
                <span class="text-[10px] bg-blue-200 text-blue-900 px-1.5 py-0.5 rounded font-mono font-bold">Admin Role</span>
            </div>
            <div class="mt-1 flex justify-between font-mono text-[11px]">
                <span>User: <strong>admin</strong></span>
                <span>Pass: <strong>Admin@123456</strong></span>
            </div>
        </div>

        <!-- Footer link -->
        <div class="text-center mt-6 pt-5 border-t border-slate-100 text-xs text-slate-600">
            Don't have an account yet?
            <a href="/register.php" class="font-bold text-blue-600 hover:underline ml-1">Register now</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
