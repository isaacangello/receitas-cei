<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    requireAuth();
    requireCsrf($method);
}

try {
    $pdo = getDb();
} catch (PDOException $e) {
    jsonResponse(['error' => 'Banco de dados indisponivel', 'detail' => $e->getMessage()], 503);
}

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT *, data_receita AS data FROM receitas WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $receita = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($receita) {
                $receita['ingredientes'] = json_decode($receita['ingredientes'], true);
                jsonResponse($receita);
            }
            jsonResponse(['error' => 'Receita nao encontrada'], 404);
        }

        $sql = "SELECT *, data_receita AS data FROM receitas";
        $params = [];
        if (isset($_GET['categoria'])) {
            $sql .= " WHERE categoria = ?";
            $params[] = $_GET['categoria'];
        }
        $sql .= " ORDER BY data_receita DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $receitas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($receitas as &$r) {
            $r['ingredientes'] = json_decode($r['ingredientes'], true);
        }
        jsonResponse($receitas);
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $_POST;

        if (empty($data['id']) || empty($data['titulo']) || empty($data['categoria'])) {
            jsonResponse(['error' => 'Campos obrigatorios: id, titulo, categoria'], 400);
        }

        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['id']);
        $stmt = $pdo->prepare("INSERT INTO receitas (id, titulo, categoria, data_receita, descricao, ingredientes, total_farinha, modo_preparo, observacoes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            titulo = VALUES(titulo), categoria = VALUES(categoria), data_receita = VALUES(data_receita),
            descricao = VALUES(descricao), ingredientes = VALUES(ingredientes), total_farinha = VALUES(total_farinha),
            modo_preparo = VALUES(modo_preparo), observacoes = VALUES(observacoes)");
        $stmt->execute([
            $id,
            $data['titulo'],
            $data['categoria'],
            $data['data'] ?? null,
            $data['descricao'] ?? '',
            json_encode($data['ingredientes'] ?? []),
            $data['total_farinha'] ?? null,
            $data['modo_preparo'] ?? '',
            $data['observacoes'] ?? null,
        ]);
        jsonResponse(['success' => true, 'id' => $id]);
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['id'])) {
            jsonResponse(['error' => 'Campo obrigatorio: id'], 400);
        }

        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['id']);
        $fields = [];
        $params = [];
        $map = ['titulo', 'categoria', 'data' => 'data_receita', 'descricao', 'total_farinha', 'modo_preparo', 'observacoes'];

        foreach ($map as $key => $col) {
            $field = is_int($key) ? $col : $key;
            if (isset($data[$field])) {
                $fields[] = "$col = ?";
                $params[] = $data[$field];
            }
        }
        if (isset($data['ingredientes'])) {
            $fields[] = "ingredientes = ?";
            $params[] = json_encode($data['ingredientes']);
        }
        if (!empty($fields)) {
            $params[] = $id;
            $stmt = $pdo->prepare("UPDATE receitas SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($params);
        }
        jsonResponse(['success' => true]);
        break;

    case 'DELETE':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            jsonResponse(['error' => 'Campo obrigatorio: id'], 400);
        }
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
        $stmt = $pdo->prepare("DELETE FROM receitas WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
