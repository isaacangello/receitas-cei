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
$isTextMode = !empty($jsonBody['text']) && !empty($jsonBody['filename']);

if ($isTextMode) {
    $text = $jsonBody['text'];
    $filename = $jsonBody['filename'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
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
}

if (!isset($_FILES['file'])) {
    jsonResponse(['error' => 'Nenhum arquivo enviado'], 400);
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'Arquivo excede o limite do servidor',
        UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o limite do formulario',
        UPLOAD_ERR_PARTIAL => 'Upload interrompido',
        UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado',
        UPLOAD_ERR_NO_TMP_DIR => 'Diretorio temporario ausente',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever disco',
        UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensao PHP',
    ];
    jsonResponse(['error' => $errors[$file['error']] ?? 'Erro desconhecido no upload'], 400);
}

if ($file['size'] > MAX_FILE_SIZE) {
    jsonResponse(['error' => 'Arquivo muito grande. Maximo: 5MB'], 413);
}

$filename = $file['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($ext, ALLOWED_EXTENSIONS)) {
    jsonResponse(['error' => "Formato nao suportado: .$ext. Aceitos: .doc, .docx, .txt, .md"], 400);
}

$tmp = $file['tmp_name'];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $tmp);
finfo_close($finfo);

if (!in_array($mime, ALLOWED_MIME)) {
    jsonResponse(['error' => 'Tipo de arquivo nao permitido: ' . $mime], 415);
}

$content = file_get_contents($tmp);
if ($content === false) {
    jsonResponse(['error' => 'Falha ao ler o arquivo'], 500);
}

if ($ext === 'doc') {
    $magic = substr($content, 0, 4);
    if ($magic !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" && $magic !== "\xD0\xCF\x11\xE0") {
        jsonResponse(['error' => 'Arquivo .doc invalido (magic bytes nao conferem)'], 422);
    }
}

if ($ext === 'docx') {
    $magic = substr($content, 0, 2);
    if ($magic !== 'PK') {
        jsonResponse(['error' => 'Arquivo .docx invalido (nao e um ZIP)'], 422);
    }
}

$text = '';

switch ($ext) {
    case 'doc':
        $text = extractDocText($tmp);
        break;
    case 'docx':
        $text = extractDocxText($tmp);
        break;
    case 'txt':
    case 'md':
        $text = $content;
        break;
}

if (!$text || !trim($text)) {
    jsonResponse(['error' => 'Nao foi possivel extrair texto do arquivo'], 422);
}

$text = trim($text);
$recipe = parseRecipeFromText($text, $filename);

jsonResponse([
    'receita' => $recipe,
    'texto_original' => $text,
    'filename' => $filename,
]);

function extractDocText($path) {
    $escaped = escapeshellarg($path);
    $text = '';

    if (function_exists('shell_exec')) {
        $result = @shell_exec("antiword -m UTF-8 $escaped 2>/dev/null");
        if ($result && trim($result)) {
            $text = $result;
        } else {
            $result = @shell_exec("catdoc -d UTF-8 $escaped 2>/dev/null");
            if ($result && trim($result)) {
                $text = $result;
            } else {
                $result = @shell_exec("strings $escaped 2>/dev/null");
                if ($result) {
                    $lines = preg_filter('/^\s*$/D', '', explode("\n", $result));
                    $text = implode("\n", $lines);
                }
            }
        }
    }

    return $text;
}

function extractDocxText($path) {
    if (!class_exists('ZipArchive')) return '';

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return '';

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if (!$xml) return '';

    $xmlObj = @simplexml_load_string($xml);
    if (!$xmlObj) return '';

    $paragraphs = [];
    foreach ($xmlObj->xpath('//w:p') as $p) {
        $runTexts = [];
        foreach ($p->xpath('.//w:t') as $t) {
            $runTexts[] = (string) $t;
        }
        $paragraphs[] = implode('', $runTexts);
    }

    return implode("\n\n", $paragraphs);
}

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
