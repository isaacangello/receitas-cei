<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');

$routes = [
    '/api'          => __DIR__ . '/receitas.php',
    '/api/csrf'     => __DIR__ . '/csrf.php',
    '/api/auth'     => __DIR__ . '/auth.php',
    '/api/import'   => __DIR__ . '/import.php',
    '/api/receitas' => __DIR__ . '/receitas.php',
];

if (isset($routes[$uri])) {
    require $routes[$uri];
    exit;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Endpoint nao encontrado']);
