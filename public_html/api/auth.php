<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $key = $data['key'] ?? '';

    if ($key !== ADMIN_KEY) {
        jsonResponse(['error' => 'Chave invalida'], 401);
    }

    $_SESSION['authenticated'] = true;
    $_SESSION['admin_key'] = $key;
    $token = generateCsrfToken();

    jsonResponse(['success' => true, 'csrf_token' => $token]);
}

if ($method === 'GET') {
    if (!empty($_SESSION['authenticated']) && !empty($_SESSION['admin_key'])) {
        $token = generateCsrfToken();
        jsonResponse(['authenticated' => true, 'csrf_token' => $token]);
    }
    jsonResponse(['authenticated' => false], 401);
}

if ($method === 'DELETE') {
    session_destroy();
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
