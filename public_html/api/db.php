<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

requireAuth();
requireCsrf('POST');

$action = $_GET['action'] ?? '';
if (!$action) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
}

if (!in_array($action, ['setup', 'backup', 'fresh'])) {
    jsonResponse(['error' => 'Acao invalida. Use: setup, backup, fresh'], 400);
}

try {
    $pdo = getDb();
} catch (PDOException $e) {
    jsonResponse(['error' => 'Banco de dados indisponivel', 'detail' => $e->getMessage()], 503);
}

$createTableSQL = "CREATE TABLE IF NOT EXISTS receitas (
    id VARCHAR(100) PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    categoria VARCHAR(100) NOT NULL,
    data_receita DATE,
    descricao TEXT,
    ingredientes JSON,
    total_farinha VARCHAR(100),
    modo_preparo TEXT,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

function getSeedData() {
    $jsonPath = __DIR__ . '/../data/receitas.json';
    if (!file_exists($jsonPath)) return [];
    $raw = file_get_contents($jsonPath);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function seedTable($pdo, $receitas) {
    if (empty($receitas)) return 0;

    $count = 0;
    $stmt = $pdo->prepare("INSERT INTO receitas (id, titulo, categoria, data_receita, descricao, ingredientes, total_farinha, modo_preparo, observacoes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        titulo = VALUES(titulo), categoria = VALUES(categoria), data_receita = VALUES(data_receita),
        descricao = VALUES(descricao), ingredientes = VALUES(ingredientes), total_farinha = VALUES(total_farinha),
        modo_preparo = VALUES(modo_preparo), observacoes = VALUES(observacoes)");

    foreach ($receitas as $r) {
        $stmt->execute([
            $r['id'],
            $r['titulo'],
            $r['categoria'],
            $r['data'] ?? null,
            $r['descricao'] ?? '',
            json_encode($r['ingredientes'] ?? []),
            $r['total_farinha'] ?? null,
            $r['modo_preparo'] ?? '',
            $r['observacoes'] ?? null,
        ]);
        $count++;
    }
    return $count;
}

switch ($action) {
    case 'setup':
        $pdo->exec($createTableSQL);

        $stmt = $pdo->query("SELECT COUNT(*) as total FROM receitas");
        $row = $stmt->fetch();
        $exists = $row['total'] > 0;

        $seeded = 0;
        if (!$exists) {
            $receitas = getSeedData();
            $seeded = seedTable($pdo, $receitas);
        }

        jsonResponse([
            'success' => true,
            'action' => 'setup',
            'table_created' => true,
            'already_existed' => $exists,
            'seeded' => $seeded,
            'message' => $exists
                ? "Tabela ja existe com {$row['total']} receitas"
                : "Tabela criada e {$seeded} receitas importadas",
        ]);
        break;

    case 'backup':
        $stmt = $pdo->query("SELECT * FROM receitas ORDER BY data_receita DESC");
        $receitas = $stmt->fetchAll();
        foreach ($receitas as &$r) {
            $r['ingredientes'] = json_decode($r['ingredientes'], true);
        }

        jsonResponse([
            'success' => true,
            'action' => 'backup',
            'total' => count($receitas),
            'receitas' => $receitas,
            'database' => DB_NAME,
            'host' => DB_HOST,
            'is_local' => IS_LOCAL,
        ]);
        break;

    case 'fresh':
        $pdo->exec("DROP TABLE IF EXISTS receitas");
        $pdo->exec($createTableSQL);

        $receitas = getSeedData();
        $seeded = seedTable($pdo, $receitas);

        jsonResponse([
            'success' => true,
            'action' => 'fresh',
            'seeded' => $seeded,
            'message' => "Banco resetado e {$seeded} receitas importadas",
        ]);
        break;
}
