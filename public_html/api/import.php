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

$jsonBody = json_decode(file_get_contents('php://input'), true);
$text = $jsonBody['text'] ?? '';
$filename = $jsonBody['filename'] ?? '';

if (!$text || !$filename) {
    jsonResponse(['error' => 'Campos text e filename obrigatorios'], 400);
}

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($ext, ['doc', 'docx', 'txt', 'md'])) {
    jsonResponse(['error' => "Formato nao suportado: .$ext"], 400);
}

$text = trim($text);
if (!$text) {
    jsonResponse(['error' => 'Texto vazio'], 422);
}

$recipe = parseRecipeFromText($text, $filename);
jsonResponse([
    'receita' => $recipe,
    'texto_original' => $text,
    'filename' => $filename,
]);

function detectCategoria($text) {
    $t = strtolower($text);
    if (preg_match('/pao|paes|brioche|hamburguer|frances|suico|forma/', $t)) return 'Paes';
    if (preg_match('/bolo|bola|cake/', $t)) return 'Bolos';
    if (preg_match('/broa|cavaca|corn/', $t)) return 'Broas';
    if (preg_match('/massa|pizza|folhad/', $t)) return 'Massas';
    if (preg_match('/doce|biscoito|cookie|sorvete|pudim|manjar|brigadeiro/', $t)) return 'Doces';
    if (preg_match('/salgado|coxinha|esfiha|empada|rissol/', $t)) return 'Salgados';
    return 'Outros';
}

function extractDateFromFilename($filename) {
    if (preg_match('/(\d{2})[_-](\d{2})[_-](\d{4})/', $filename, $m)) {
        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
    return '';
}

function slugify($text) {
    $text = strtolower($text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function parseSections($lines) {
    $section = '';
    $ingredientesLines = [];
    $modoLines = [];

    foreach ($lines as $trimmed) {
        if (preg_match('/^(ingredientes?|componentes?):?\s*$/i', $trimmed)) {
            $section = 'ingredientes';
            continue;
        }
        if (preg_match('/^(modo\s+(de\s+)?preparo|preparo|preparacao|instrucoes?):?\s*$/i', $trimmed)) {
            $section = 'modo';
            continue;
        }
        if ($trimmed === '') continue;

        if ($section === 'ingredientes') {
            $ingredientesLines[] = $trimmed;
        } elseif ($section === 'modo') {
            $modoLines[] = $trimmed;
        }
    }

    $ingredientes = [];
    foreach ($ingredientesLines as $line) {
        if (preg_match('/^(.+?)\s*[:=]\s*(.+)$/', $line, $m)) {
            $key = slugify($m[1]);
            $ingredientes[$key] = trim($m[2]);
        } else {
            $key = slugify($line);
            $ingredientes[$key] = $line;
        }
    }

    return [
        'ingredientes' => $ingredientes,
        'modo_preparo' => implode(' ', $modoLines),
    ];
}

function parseRecipeFromText($text, $filename) {
    $lines = array_filter(explode("\n", $text), function ($l) { return trim($l) !== ''; });
    $lines = array_map('trim', $lines);

    $titulo = !empty($lines) ? $lines[0] : pathinfo($filename, PATHINFO_FILENAME);
    $titulo = preg_replace('/[_-]/', ' ', $titulo);

    $id = slugify($titulo);
    $cat = detectCategoria($text);
    $data = extractDateFromFilename($filename);
    $sections = parseSections($lines);

    if (empty($sections['modo_preparo'])) {
        $sections['modo_preparo'] = implode(' ', array_map('trim', array_filter(explode("\n", $text), function ($l) {
            return trim($l) !== '';
        })));
    }

    return [
        'id' => $id,
        'titulo' => $titulo,
        'categoria' => $cat,
        'data' => $data,
        'descricao' => "Receita importada de $filename",
        'ingredientes' => $sections['ingredientes'],
        'modo_preparo' => $sections['modo_preparo'],
        'observacoes' => '',
    ];
}
