<?php
/**
 * Importacao em lote de receitas .doc/.txt
 * CLI: php public_html/api/batch-import.php [docsDir]
 * API: POST /api/batch-import { "docsDir": "/caminho/para/docs" }
 */

$isCli = php_sapi_name() === 'cli';

require_once __DIR__ . '/import.php';

if (!$isCli) {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    requireAuth();
    requireCsrf('POST');
}

$jsonBody = $isCli ? [] : (json_decode(file_get_contents('php://input'), true) ?: []);
$docsDir = $argv[1] ?? $jsonBody['docsDir'] ?? '/home/isaacca/hd/Codigos/site_pessoal/pao.50webs.org/receitas/docs/Receitas';

$tmpDir = sys_get_temp_dir() . '/batch_import_' . uniqid();

$log = [];
function apiLog($msg) use (&$log, $isCli) {
    $log[] = $msg;
    if ($isCli) echo $msg . "\n";
}

apiLog("=== Importacao em lote de receitas ===");
apiLog("Pasta: $docsDir");

$docFiles = glob($docsDir . '/*.doc');
if (empty($docFiles)) {
    $resp = ['error' => "Nenhum arquivo .doc encontrado em $docsDir"];
    if ($isCli) { echo $resp['error'] . "\n"; exit(1); }
    jsonResponse($resp, 404);
}
apiLog("Encontrados " . count($docFiles) . " arquivos .doc");

apiLog("Convertendo .doc para .txt com LibreOffice...");
mkdir($tmpDir, 0755, true);
exec("libreoffice --headless --convert-to txt --outdir " . escapeshellarg($tmpDir) . " " . escapeshellarg($docsDir) . "/*.doc 2>&1", $convertOutput, $convertExit);
if ($convertExit !== 0) {
    $resp = ['error' => 'Erro na conversao', 'output' => $convertOutput];
    if ($isCli) { echo "Erro na conversao:\n" . implode("\n", $convertOutput) . "\n"; exit(1); }
    jsonResponse($resp, 500);
}

$txtFiles = glob($tmpDir . '/*.txt');
apiLog("Convertidos " . count($txtFiles) . " arquivos .txt");

try {
    $pdo = getDb();
} catch (PDOException $e) {
    $resp = ['error' => 'Erro ao conectar ao banco: ' . $e->getMessage()];
    if ($isCli) { echo $resp['error'] . "\n"; exit(1); }
    jsonResponse($resp, 500);
}

$stmt = $pdo->query("SELECT id FROM receitas");
$existingIds = array_column($stmt->fetchAll(), 'id');
apiLog("Receitas existentes no banco: " . count($existingIds));

$imported = 0;
$skipped = 0;
$errors = [];
$results = [];
$imagesFound = 0;
$imagesFallback = 0;

foreach ($txtFiles as $txtFile) {
    $basename = basename($txtFile, '.txt');
    $docFile = basename($docFiles[0]);

    foreach ($docFiles as $df) {
        if (basename($df, '.doc') === $basename) {
            $docFile = basename($df);
            break;
        }
    }

    $text = file_get_contents($txtFile);
    if ($text === false || trim($text) === '') {
        $errors[] = "$docFile: arquivo vazio";
        $skipped++;
        continue;
    }

    $text = trim($text);
    $recipe = parseRecipeFromText($text, $docFile);

    if (in_array($recipe['id'], $existingIds)) {
        apiLog("  PULADO: {$recipe['id']} ({$recipe['titulo']})");
        $skipped++;
        continue;
    }

    $imageResult = fetchRecipeImage($recipe['titulo'], $recipe['categoria']);
    $recipe['image_url'] = $imageResult['url'];
    $recipe['image_search_query'] = $imageResult['query'];

    if ($imageResult['source'] === 'picsum') {
        $imagesFallback++;
    } else {
        $imagesFound++;
    }

    apiLog("  OK: {$recipe['titulo']} [{$recipe['categoria']}] img:{$imageResult['source']}");

    $ingredientesJson = json_encode($recipe['ingredientes'], JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare("INSERT INTO receitas (id, titulo, categoria, data_receita, descricao, ingredientes, total_farinha, modo_preparo, observacoes, image_url, image_search_query)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        titulo = VALUES(titulo), categoria = VALUES(categoria), data_receita = VALUES(data_receita),
        descricao = VALUES(descricao), ingredientes = VALUES(ingredientes), total_farinha = VALUES(total_farinha),
        modo_preparo = VALUES(modo_preparo), observacoes = VALUES(observacoes), image_url = VALUES(image_url), image_search_query = VALUES(image_search_query)");

    try {
        $stmt->execute([
            $recipe['id'],
            $recipe['titulo'],
            $recipe['categoria'],
            $recipe['data'] ?: null,
            $recipe['descricao'],
            $ingredientesJson,
            $recipe['total_farinha'] ?? null,
            $recipe['modo_preparo'],
            $recipe['observacoes'] ?? '',
            $recipe['image_url'],
            $recipe['image_search_query'],
        ]);
        $existingIds[] = $recipe['id'];
        $imported++;
        $results[] = ['id' => $recipe['id'], 'titulo' => $recipe['titulo'], 'status' => 'ok'];
    } catch (PDOException $e) {
        $errors[] = "$docFile: " . $e->getMessage();
        $results[] = ['id' => $recipe['id'], 'titulo' => $recipe['titulo'], 'status' => 'error', 'error' => $e->getMessage()];
    }
}

exec("rm -rf " . escapeshellarg($tmpDir));

apiLog("");
apiLog("=== RELATORIO ===");
apiLog("Importadas: $imported");
apiLog("Puladas (duplicatas): $skipped");
apiLog("Erros: " . count($errors));
apiLog("Imagens Unsplash/MealDB: $imagesFound");
apiLog("Imagens fallback: $imagesFallback");

if ($isCli) {
    if (!empty($errors)) {
        echo "\nErros:\n";
        foreach ($errors as $err) echo "  - $err\n";
    }
    echo "\nConcluido!\n";
    exit(0);
}

jsonResponse([
    'success' => true,
    'imported' => $imported,
    'skipped' => $skipped,
    'errors' => count($errors),
    'images_found' => $imagesFound,
    'images_fallback' => $imagesFallback,
    'results' => $results,
    'error_details' => $errors,
]);
