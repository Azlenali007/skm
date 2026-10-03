<?php
/**
 * Sikkim Gaming Platform - User Registration
 * Real PHP + MySQL with PDO and Password Hashing
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: /dashboard.php");
    exit;
}

$error = '';
$phone = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = "Security validation failed. Please try again.";
    } else {
        $phone = trim($_POST['phone'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($phone) || empty($username) || empty($password)) {
            $error = "Please fill in all required fields.";
        } elseif (!preg_match('/^[0-9]{10,12}$/', $phone)) {
            $error = "Please enter a valid 10-12 digit mobile phone number.";
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $error = "Username must be 3-20 characters (letters, numbers, underscore only).";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match.";
        } else {
            $pdo = getDB();

            // Check if phone or username already registered
            $checkStmt = $pdo->prepare("SELECT id, phone, username FROM `users` WHERE `phone` = :phone OR `username` = :username LIMIT 1");
            $checkStmt->execute([':phone' => $phone, ':username' => $username]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                if ($existing['phone'] === $phone) {
                    $error = "A player account with this mobile number already exists.";
                } else {
                    $error = "This username is already taken. Please choose another.";
                }
            } else {
                // Register user in atomic transaction
                try {
                    $pdo->beginTransaction();

                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $insertUser = $pdo->prepare("
                        INSERT INTO `users` (`username`, `phone`, `email`, `password_hash`, `role`, `status`)
                        VALUES (:username, :phone, :email, :password_hash, 'user', 'active')
                    ");
                    $insertUser->execute([
                        ':username' => $username,
                        ':phone' => $phone,
                        ':email' => !empty($email) ? $email : null,
                        ':password_hash' => $passwordHash
                    ]);
                    $userId = (int)$pdo->lastInsertId();

                    // Create Wallet with Welcome Bonus (e.g. ₹50)
                    $welcomeBonus = 50.00;
                    $insertWallet = $pdo->prepare("
                        INSERT INTO `wallets` (`user_id`, `balance`, `bonus_balance`)
                        VALUES (:uid, :bal, :bonus)
                    ");
                    $insertWallet->execute([
                        ':uid' => $userId,
                        ':bal' => $welcomeBonus,
                        ':bonus' => $welcomeBonus
                    ]);

                    // Add Ledger Transaction for Welcome Bonus
                    $insertTx = $pdo->prepare("
                        INSERT INTO `transactions` (`user_id`, `type`, `amount`, `balance_before`, `balance_after`, `reference_id`, `status`, `notes`)
                        VALUES (:uid, 'bonus', :amt, 0.00, :after, :ref, 'completed', 'New Player Welcome Signup Bonus')
                    ");
                    $insertTx->execute([
                        ':uid' => $userId,
                        ':amt' => $welcomeBonus,
                        ':after' => $welcomeBonus,
                        ':ref' => 'BONUS-' . strtoupper(bin2hex(random_bytes(4)))
                    ]);

                    // Send Welcome Notification
                    $insertNotif = $pdo->prepare("
                        INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`)
                        VALUES (:uid, 'Welcome to Sikkim Gaming!', 'Your account has been registered successfully. A welcome bonus of ₹50 has been credited to your balance.', 'system')
                    ");
                    $insertNotif->execute([':uid' => $userId]);

                    $pdo->commit();

                    // Establish Session
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = 'user';

                    setFlash('success', 'Registration successful! Welcome bonus of ₹50.00 added to your balance.');
                    header("Location: /dashboard.php");
                    exit;

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = "Registration error: " . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = "Register New Account";
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
            <h1 class="text-xl font-black text-slate-900">Create Player Account</h1>
            <p class="text-xs text-slate-500 mt-1">Get instant ₹50 welcome bonus upon registration</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-5 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Form (Strict Normal Flow) -->
        <form method="POST" action="/register.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

            <div>
                <label for="phone" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Mobile Phone Number <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3 top-2.5 text-xs font-bold text-slate-400">+91</span>
                    <input type="tel" id="phone" name="phone" value="<?= e($phone) ?>" required placeholder="9876543210" maxlength="12" class="w-full pl-12 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
                </div>
            </div>

            <div>
                <label for="username" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Username <span class="text-rose-500">*</span>
                </label>
                <input type="text" id="username" name="username" value="<?= e($username) ?>" required placeholder="e.g. player_sikkim" maxlength="20" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
            </div>

            <div>
                <label for="email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Email Address <span class="text-slate-400 font-normal">(Optional)</span>
                </label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="player@example.com" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
            </div>

            <div>
                <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Set Password <span class="text-rose-500">*</span>
                </label>
                <input type="password" id="password" name="password" required placeholder="Minimum 6 characters" minlength="6" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
            </div>

            <div>
                <label for="confirm_password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Confirm Password <span class="text-rose-500">*</span>
                </label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Repeat password" minlength="6" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition">
            </div>

            <div class="text-[11px] text-slate-500 leading-relaxed">
                By clicking Register, you confirm that you are at least 18 years old and agree to the platform Terms & Fair Play Rules.
            </div>

            <button type="submit" class="w-full py-3 bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800 text-white font-black text-sm rounded-xl shadow-md transition transform active:scale-[0.99]">
                Register & Claim ₹50 Bonus
            </button>
        </form>

        <!-- Footer link -->
        <div class="text-center mt-6 pt-5 border-t border-slate-100 text-xs text-slate-600">
            Already have a Sikkim account?
            <a href="/login.php" class="font-bold text-blue-600 hover:underline ml-1">Log in here</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
