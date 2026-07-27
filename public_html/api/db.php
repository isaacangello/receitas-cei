<?php
ob_start();
error_reporting(E_ERROR | E_PARSE);
require_once __DIR__ . '/config.php';
ob_end_clean();

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

if (!in_array($action, ['setup', 'export-sql', 'import-sql', 'fresh'])) {
    jsonResponse(['error' => 'Acao invalida. Use: setup, export-sql, import-sql, fresh'], 400);
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
    image_url VARCHAR(500),
    image_search_query VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

function escapeSqlValue($val) {
    if ($val === null) return 'NULL';
    if (is_int($val) || is_float($val)) return $val;
    $val = str_replace('\\', '\\\\', $val);
    $val = str_replace("'", "''", $val);
    $val = str_replace("\n", '\\n', $val);
    $val = str_replace("\r", '\\r', $val);
    $val = str_replace("\0", '', $val);
    return "'$val'";
}

switch ($action) {
    case 'setup':
        $pdo->exec($createTableSQL);

        $stmt = $pdo->query("SELECT COUNT(*) as total FROM receitas");
        $row = $stmt->fetch();
        $exists = $row['total'] > 0;

        jsonResponse([
            'success' => true,
            'action' => 'setup',
            'table_created' => true,
            'already_existed' => $exists,
            'message' => $exists
                ? "Tabela ja existe com {$row['total']} receitas"
                : "Tabela criada",
        ]);
        break;

    case 'export-sql':
        $stmt = $pdo->query("SHOW CREATE TABLE receitas");
        $tableRow = $stmt->fetch();
        $createBody = $tableRow['Create Table'];

        $stmt = $pdo->query("SELECT * FROM receitas ORDER BY data_receita DESC");
        $receitas = $stmt->fetchAll();

        $date = date('Y-m-d H:i:s');
        $lines = [];
        $lines[] = "-- Receitas CEI Backup";
        $lines[] = "-- Data: $date";
        $lines[] = "-- Banco: " . DB_NAME . " @ " . DB_HOST;
        $lines[] = "-- Total: " . count($receitas) . " receitas";
        $lines[] = "";
        $lines[] = "SET FOREIGN_KEY_CHECKS = 0;";
        $lines[] = "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';";
        $lines[] = "";
        $lines[] = "DROP TABLE IF EXISTS `receitas`;";
        $lines[] = $createBody . ";";
        $lines[] = "";

        $columns = ['id','titulo','categoria','data_receita','descricao','ingredientes','total_farinha','modo_preparo','observacoes','image_url','image_search_query'];

        foreach ($receitas as $r) {
            $vals = [];
            foreach ($columns as $col) {
                $val = $r[$col];
                if ($col === 'ingredientes') {
                    $val = json_encode(json_decode($r['ingredientes'], true), JSON_UNESCAPED_UNICODE);
                }
                $vals[] = escapeSqlValue($val);
            }
            $valsStr = implode(', ', $vals);
            $colsStr = '`' . implode('`, `', $columns) . '`';
            $lines[] = "INSERT INTO `receitas` ($colsStr) VALUES ($valsStr);";
        }

        $lines[] = "";
        $lines[] = "SET FOREIGN_KEY_CHECKS = 1;";
        $lines[] = "";
        $lines[] = "-- Fim do backup";

        $sql = implode("\n", $lines);

        jsonResponse([
            'success' => true,
            'action' => 'export-sql',
            'sql' => $sql,
            'total' => count($receitas),
            'filename' => 'receitas-backup-' . date('Y-m-d') . '.sql',
        ]);
        break;

    case 'import-sql':
        $body = json_decode(file_get_contents('php://input'), true);
        $sql = $body['sql'] ?? '';
        if (!$sql) {
            jsonResponse(['error' => 'Campo sql obrigatorio'], 400);
        }

        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            $lines = explode("\n", $sql);
            $stmts = [];
            $current = '';

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || $trimmed[0] === '-') {
                    if ($current !== '' && strtoupper(substr($current, 0, 3)) === 'SET') {
                        $stmts[] = $current;
                        $current = '';
                    }
                    continue;
                }
                $current .= ' ' . $trimmed;
                if (substr($trimmed, -1) === ';') {
                    $stmts[] = trim($current);
                    $current = '';
                }
            }
            if ($current !== '') $stmts[] = trim($current);

            $executed = 0;
            $errors = [];

            foreach ($stmts as $stmt) {
                if ($stmt === '' || strtoupper(substr($stmt, 0, 3)) === 'SET') {
                    if (strtoupper(substr($stmt, 0, 3)) === 'SET') {
                        try { $pdo->exec($stmt); $executed++; } catch (PDOException $e) {}
                    }
                    continue;
                }
                try {
                    $pdo->exec($stmt);
                    $executed++;
                } catch (PDOException $e) {
                    $short = substr($stmt, 0, 80);
                    $errors[] = "$short... => " . $e->getMessage();
                }
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            $countStmt = $pdo->query("SELECT COUNT(*) as total FROM receitas");
            $total = $countStmt->fetch()['total'];

            jsonResponse([
                'success' => true,
                'action' => 'import-sql',
                'statements_executed' => $executed,
                'total_receitas' => $total,
                'errors' => count($errors),
                'error_details' => $errors,
            ]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Erro na importacao: ' . $e->getMessage()], 500);
        }
        break;

    case 'fresh':
        $pdo->exec("DELETE FROM receitas");
        jsonResponse([
            'success' => true,
            'action' => 'fresh',
            'message' => 'Banco de dados zerado',
        ]);
        break;
}
