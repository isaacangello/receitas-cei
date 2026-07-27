<?php
/**
 * Importacao em lote de receitas .doc/.txt
 * Rodar: php public_html/api/batch-import.php
 */

$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    http_response_code(403);
    echo 'Acesso negado';
    exit;
}

require_once __DIR__ . '/import.php';

$docsDir = $argv[1] ?? '/home/isaacca/hd/Codigos/site_pessoal/pao.50webs.org/receitas/docs/Receitas';
$tmpDir = sys_get_temp_dir() . '/batch_import_' . uniqid();

echo "=== Importacao em lote de receitas ===\n";
echo "Pasta: $docsDir\n\n";

// 1. Listar arquivos .doc
$docFiles = glob($docsDir . '/*.doc');
if (empty($docFiles)) {
    echo "Nenhum arquivo .doc encontrado em $docsDir\n";
    exit(1);
}
echo "Encontrados " . count($docFiles) . " arquivos .doc\n";

// 2. Converter .doc -> .txt com LibreOffice
echo "Convertendo .doc para .txt com LibreOffice...\n";
mkdir($tmpDir, 0755, true);
exec("libreoffice --headless --convert-to txt --outdir " . escapeshellarg($tmpDir) . " " . escapeshellarg($docsDir) . "/*.doc 2>&1", $convertOutput, $convertExit);
if ($convertExit !== 0) {
    echo "Erro na conversao:\n" . implode("\n", $convertOutput) . "\n";
    exit(1);
}

$txtFiles = glob($tmpDir . '/*.txt');
echo "Convertidos " . count($txtFiles) . " arquivos .txt\n\n";

// 3. Conectar ao banco
try {
    $pdo = getDb();
    echo "Conectado ao banco de dados\n\n";
} catch (PDOException $e) {
    echo "Erro ao conectar ao banco: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Verificar receitas existentes
$stmt = $pdo->query("SELECT id FROM receitas");
$existingIds = array_column($stmt->fetchAll(), 'id');
echo "Receitas existentes no banco: " . count($existingIds) . "\n\n";

// 5. Processar cada arquivo
$imported = 0;
$skipped = 0;
$errors = [];
$imagesFound = 0;
$imagesFallback = 0;

foreach ($txtFiles as $txtFile) {
    $basename = basename($txtFile, '.txt');
    $docFile = basename($docFiles[0]); // fallback

    // Encontrar o .doc original correspondente
    foreach ($docFiles as $df) {
        if (basename($df, '.doc') === $basename) {
            $docFile = basename($df);
            break;
        }
    }

    echo "--- Processando: $docFile ---\n";

    $text = file_get_contents($txtFile);
    if ($text === false || trim($text) === '') {
        echo "  AVISO: Arquivo vazio, pulando\n";
        $errors[] = "$docFile: arquivo vazio";
        $skipped++;
        continue;
    }

    $text = trim($text);
    $recipe = parseRecipeFromText($text, $docFile);

    // Pular duplicatas
    if (in_array($recipe['id'], $existingIds)) {
        echo "  PULADO (duplicata): {$recipe['id']}\n";
        $skipped++;
        continue;
    }

    // Buscar imagem
    $imageResult = fetchRecipeImage($recipe['titulo'], $recipe['categoria']);
    $recipe['image_url'] = $imageResult['url'];
    $recipe['image_search_query'] = $imageResult['query'];

    if ($imageResult['source'] === 'picsum') {
        $imagesFallback++;
    } else {
        $imagesFound++;
    }

    echo "  ID: {$recipe['id']}\n";
    echo "  Titulo: {$recipe['titulo']}\n";
    echo "  Categoria: {$recipe['categoria']}\n";
    echo "  Imagem: {$imageResult['source']}\n";

    // Salvar no banco
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
        echo "  OK\n";
        $existingIds[] = $recipe['id'];
        $imported++;
    } catch (PDOException $e) {
        echo "  ERRO: " . $e->getMessage() . "\n";
        $errors[] = "$docFile: " . $e->getMessage();
    }

    echo "\n";
}

// 6. Limpar temporarios
exec("rm -rf " . escapeshellarg($tmpDir));

// 7. Relatorio
echo "=== RELATORIO ===\n";
echo "Importadas: $imported\n";
echo "Puladas (duplicatas): $skipped\n";
echo "Erros: " . count($errors) . "\n";
echo "Imagens do Unsplash/MealDB: $imagesFound\n";
echo "Imagens fallback (Picsum): $imagesFallback\n";
if (!empty($errors)) {
    echo "\nErros:\n";
    foreach ($errors as $err) {
        echo "  - $err\n";
    }
}
echo "\nConcluido!\n";
