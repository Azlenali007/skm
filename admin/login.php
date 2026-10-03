<?php
/**
 * Sikkim Gaming Platform - Admin Authentication
 * Real MySQL PDO Login
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    $u = getCurrentUser();
    if ($u && $u['role'] === 'admin') {
        header("Location: /admin/");
        exit;
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        $error = "Session validation failed. Please retry.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = "Please enter admin username and password.";
        } else {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM `users` WHERE (`username` = :u OR `phone` = :p) AND `role` = 'admin' LIMIT 1");
            $stmt->execute([':u' => $username, ':p' => $username]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($password, $admin['password_hash'])) {
                $error = "Invalid administrator credentials.";
            } elseif ($admin['status'] !== 'active') {
                $error = "Admin account suspended.";
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$admin['id'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['role'] = 'admin';

                setFlash('success', 'Welcome to Sikkim Control Center, ' . e($admin['username']));
                header("Location: /admin/");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Sikkim Game</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-slate-800 border border-slate-700 rounded-2xl p-8 shadow-2xl">
        <div class="text-center mb-6">
            <span class="text-3xl font-black italic tracking-tighter text-blue-500">
                <i>S</i>IKKIM
            </span>
            <div class="mt-2 text-xs font-mono uppercase tracking-widest text-slate-400">Restricted Administration Access</div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 rounded-xl bg-rose-950/80 border border-rose-800 text-rose-300 text-xs font-semibold">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/admin/login.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Admin User / Phone</label>
                <input type="text" name="username" value="admin" required class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Password</label>
                <input type="password" name="password" value="Admin@123456" required class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-black text-sm rounded-xl shadow-lg transition">
                Authenticate & Enter Console
            </button>
        </form>

        <div class="mt-6 pt-5 border-t border-slate-700/60 text-center text-xs text-slate-400">
            <a href="/" class="hover:text-blue-400">&larr; Return to Player Site</a>
        </div>
    </div>
</body>
</html>
