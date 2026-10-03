<?php
/**
 * Sikkim Gaming Platform - Authentication & Session Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../config/database.php';

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUser(): ?array {
    $uid = getCurrentUserId();
    if (!$uid) {
        return null;
    }
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.phone, u.email, u.role, u.status, u.avatar, u.created_at,
               COALESCE(w.balance, 0.00) as balance,
               COALESCE(w.bonus_balance, 0.00) as bonus_balance,
               COALESCE(w.total_deposited, 0.00) as total_deposited,
               COALESCE(w.total_withdrawn, 0.00) as total_withdrawn
        FROM `users` u
        LEFT JOIN `wallets` w ON u.id = w.user_id
        WHERE u.id = :id AND u.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([':id' => $uid]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function requireLogin(): array {
    if (!isLoggedIn()) {
        header("Location: /login.php");
        exit;
    }
    $user = getCurrentUser();
    if (!$user) {
        // Session invalid or user suspended
        session_unset();
        session_destroy();
        header("Location: /login.php?error=session_expired");
        exit;
    }
    return $user;
}

function requireAdmin(): array {
    if (!isLoggedIn()) {
        header("Location: /admin/login.php");
        exit;
    }
    $user = getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        header("Location: /admin/login.php?error=unauthorized");
        exit;
    }
    return $user;
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function formatMoney(float $amount): string {
    return '₹' . number_format($amount, 2);
}
