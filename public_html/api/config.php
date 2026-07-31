<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (!defined('CONFIG_HTML_MODE')) {
    header('Content-Type: application/json');

    $allowedOrigins = ['http://localhost:3000', 'https://receitas.free.nf', 'https://pao.50webs.org'];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Admin-Key, X-CSRF-Token');
}

function loadEnv($path) {
    if (!file_exists($path)) return false;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim(trim($value), '"\'');
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
    return true;
}

function env($key, $default = null) {
    $value = getenv($key);
    if ($value !== false && $value !== '') return $value;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    return $default;
}

loadEnv(__DIR__ . '/../.env') || loadEnv(__DIR__ . '/../../.env');

$isLocal = env('DB_LOCAL_DATABASE') !== null && env('DB_LOCAL_DATABASE') !== '';

define('DB_HOST', $isLocal ? env('DB_LOCAL_HOST', '127.0.0.1') : env('DB_HOST', 'sql202.infinityfree.com'));
define('DB_PORT', $isLocal ? env('DB_LOCAL_PORT', '3306') : '3306');
define('DB_NAME', $isLocal ? env('DB_LOCAL_DATABASE') : env('DB_NAME', 'if0_42505744_receitas'));
define('DB_USERNAME', $isLocal ? env('DB_LOCAL_USERNAME') : env('DB_USERNAME', 'if0_42505744'));
define('DB_PASSWORD', $isLocal ? env('DB_LOCAL_PASSWORD', '') : env('DB_PASSWORD', ''));
define('IS_LOCAL', $isLocal);
define('ADMIN_KEY', env('ADMIN_KEY', ''));

define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['doc', 'docx', 'txt', 'md']);
define('ALLOWED_MIME', [
    'application/msword',
    'application/vnd.ms-word',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/octet-stream',
    'text/plain',
    'text/markdown',
    'text/x-markdown',
]);

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return !empty($_SESSION['csrf_token']) && !empty($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function requireAuth() {
    if (empty($_SESSION['authenticated']) || empty($_SESSION['admin_key'])) {
        jsonResponse(['error' => 'Nao autenticado'], 401);
    }
}

function requireCsrf($method) {
    if (!in_array($method, ['POST', 'PUT', 'DELETE'])) return;
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCsrfToken($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF invalido']);
        exit;
    }
}

function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
