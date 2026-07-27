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

function parseIngredientLine($line) {
    $line = trim($line);
    if ($line === '') return null;

    $tabs = preg_split('/\t+/', $line);
    $tabs = array_values(array_filter($tabs, function ($t) { return trim($t) !== ''; }));

    if (empty($tabs)) return null;

    $name = trim($tabs[0]);
    $pct = '';
    $qty = '';
    $unit = '';

    for ($i = 1; $i < count($tabs); $i++) {
        $val = trim($tabs[$i]);
        $valNorm = str_replace(',', '.', $val);

        if (strtoupper($val) === 'QB') {
            if ($qty === '') $qty = 'QB';
            else $unit = 'QB';
        } elseif (preg_match('/^(\d+(?:\.\d+)?)\s*%$/', $valNorm, $m)) {
            $pct = $m[1] . '%';
        } elseif (preg_match('/^(\d+(?:\.\d+)?)$/', $valNorm)) {
            $qty = $valNorm;
        } elseif (preg_match('/^(\d+(?:\.\d+)?)\s*(g|kg|ml|l|un|xic|colher|copo)$/i', $valNorm, $m)) {
            $qty = $m[1];
            $unit = $m[2];
        } elseif (preg_match('/^[a-zA-Z]+$/', $val) && strlen($val) <= 4) {
            $unit = $val;
        } elseif ($qty === '' && preg_match('/^(\d+(?:\.\d+)?)\s*%/', $valNorm, $m)) {
            $pct = $m[1] . '%';
        }
    }

    $parts = [];
    if ($pct !== '') $parts[] = $pct;
    if ($qty !== '' && $qty !== 'QB') $parts[] = $qty . ($unit ?: 'g');
    elseif ($qty === 'QB') $parts[] = 'QB';

    $value = !empty($parts) ? implode(' - ', $parts) : $name;

    return [
        'key' => slugify($name),
        'name' => $name,
        'value' => $value,
    ];
}

function isIngredientLine($line, $raw) {
    if ($raw === '' || $raw === null) return false;
    if (mb_strtolower(trim($raw)) === 'qb') return true;
    if (preg_match('/\t/', $raw)) return true;
    if (preg_match('/\d+\s*%/', $raw)) return true;
    if (preg_match('/\d+\s*g\b/i', $raw)) return true;
    return false;
}

function parseSections($text) {
    $lines = explode("\n", $text);
    $rawLines = $lines;
    $ingredientesLines = [];
    $modoLines = [];
    $mode = 'auto';

    $hasMarkers = preg_match('/^ingredientes\s*$/mi', $text) && preg_match('/^fimIngredientes\s*$/mi', $text);

    if ($hasMarkers) {
        $inSection = false;
        foreach ($lines as $raw) {
            $trimmed = trim($raw);
            if (preg_match('/^fimIngredientes\s*$/i', $trimmed)) {
                $inSection = false;
                continue;
            }
            if (preg_match('/^ingredientes\s*$/i', $trimmed)) {
                $inSection = true;
                continue;
            }
            if ($inSection && $trimmed !== '') {
                $ingredientesLines[] = ['raw' => $raw, 'trimmed' => $trimmed];
            }
        }
        $mode = 'markers';
    } else {
        $section = 'pre';
        $ingredientStart = -1;

        for ($i = 0; $i < count($lines); $i++) {
            $trimmed = trim($lines[$i]);

            if (preg_match('/^(ingredientes?|componentes?):?\s*$/i', $trimmed)) {
                $section = 'ingredientes';
                $ingredientStart = $i;
                continue;
            }
            if (preg_match('/^(modo\s+(de\s+)?preparo|preparo|preparacao|instrucoes?):?\s*$/i', $trimmed)) {
                $section = 'modo';
                continue;
            }
            if ($trimmed === '') continue;

            if ($section === 'ingredientes') {
                $ingredientesLines[] = ['raw' => $lines[$i], 'trimmed' => $trimmed];
            } elseif ($section === 'modo') {
                $modoLines[] = $trimmed;
            } elseif ($section === 'pre' && $ingredientStart === -1) {
                if (isIngredientLine($trimmed, $rawLines[$i])) {
                    $ingredientesLines[] = ['raw' => $lines[$i], 'trimmed' => $trimmed];
                }
            }
        }
        $mode = 'auto';
    }

    if (empty($modoLines)) {
        $inModo = false;
        foreach ($lines as $raw) {
            $trimmed = trim($raw);
            if (preg_match('/^(modo\s+(de\s+)?preparo|preparo|preparacao|instrucoes?):?\s*$/i', $trimmed)) {
                $inModo = true;
                continue;
            }
            if ($inModo && $trimmed !== '') {
                $modoLines[] = $trimmed;
            }
        }
    }

    $ingredientes = [];
    $subGrupo = '';

    foreach ($ingredientesLines as $item) {
        $line = $item['trimmed'];
        $raw = $item['raw'];

        if (preg_match('/\t/', $raw)) {
            $parsed = parseIngredientLine($line);
            if ($parsed) {
                $key = $subGrupo ? $subGrupo . '/' . $parsed['key'] : $parsed['key'];
                $ingredientes[$key] = $parsed['value'];
            }
            continue;
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*%/', $line)) {
            $parsed = parseIngredientLine($line);
            if ($parsed) {
                $key = $subGrupo ? $subGrupo . '/' . $parsed['key'] : $parsed['key'];
                $ingredientes[$key] = $parsed['value'];
            }
            continue;
        }

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(g|kg|ml|l)\b/i', $line)) {
            $parsed = parseIngredientLine($line);
            if ($parsed) {
                $key = $subGrupo ? $subGrupo . '/' . $parsed['key'] : $parsed['key'];
                $ingredientes[$key] = $parsed['value'];
            }
            continue;
        }

        if (strtoupper($line) === 'QB') {
            $ingredientes['ingrediente-' . (count($ingredientes) + 1)] = 'QB';
            continue;
        }

        if (preg_match('/^(modo\s+(de\s+)?preparo|preparo|preparacao|instrucoes?)/i', $line)) {
            $modoLines[] = $line;
            continue;
        }

        if (preg_match('/^obs/i', $line)) continue;

        $subGrupo = slugify($line);
    }

    return [
        'ingredientes' => $ingredientes,
        'modo_preparo' => implode(' ', $modoLines),
        '_mode' => $mode,
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
    $sections = parseSections($text);

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
