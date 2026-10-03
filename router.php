<?php
/**
 * Sikkim Gaming Platform - Built-in Server Router
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve static assets directly if they exist
$filePath = __DIR__ . $uri;
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    if ($ext !== 'php') {
        // Return false to let PHP's built-in server serve the static file
        return false;
    }
}

// Check database connection and bootstrap if needed
require_once __DIR__ . '/config/init_db.php';
try {
    initDatabase();
} catch (Throwable $e) {
    error_log("Database initialization check error: " . $e->getMessage());
}

// Routing rules
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    exit;
}

if ($uri === '/admin' || $uri === '/admin/') {
    require __DIR__ . '/admin/index.php';
    exit;
}

// Route to .php if matching without extension
if (file_exists(__DIR__ . $uri . '.php')) {
    require __DIR__ . $uri . '.php';
    exit;
}

if (file_exists(__DIR__ . $uri) && is_file(__DIR__ . $uri)) {
    require __DIR__ . $uri;
    exit;
}

// Clean 404 response
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found - Sikkim Game</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center border border-slate-100">
        <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 font-black text-2xl">404</div>
        <h1 class="text-2xl font-bold text-slate-900 mb-2">Page Not Found</h1>
        <p class="text-sm text-slate-600 mb-6">The page or game you are looking for does not exist or has been moved.</p>
        <a href="/" class="inline-block px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-700 text-white rounded-xl font-semibold shadow hover:opacity-95 transition">Return to Home</a>
    </div>
</body>
</html>
