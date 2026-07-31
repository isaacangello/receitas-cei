<?php
define('CONFIG_HTML_MODE', true);
require_once __DIR__ . '/api/config.php';

function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function flattenIngredients($ingredientes) {
    $flat = [];
    if (isset($ingredientes['esponja']) && is_array($ingredientes['esponja'])) {
        foreach ($ingredientes as $nomeGrupo => $grupo) {
            if (!is_array($grupo)) continue;
            foreach ($grupo as $item => $qtd) {
                $flat[] = ucfirst(str_replace('_', ' ', $item)) . ': ' . $qtd;
            }
        }
    } else {
        foreach ($ingredientes as $item => $qtd) {
            if (!is_string($qtd)) continue;
            $flat[] = ucfirst(str_replace('_', ' ', $item)) . ': ' . $qtd;
        }
    }
    return $flat;
}

function renderIngredientsHtml($ingredientes) {
    $html = '<ul>';
    if (isset($ingredientes['esponja']) && is_array($ingredientes['esponja'])) {
        foreach ($ingredientes as $nomeGrupo => $grupo) {
            if (!is_array($grupo)) continue;
            $html .= '<li><strong>' . h(ucfirst(str_replace('_', ' ', $nomeGrupo))) . '</strong><ul>';
            foreach ($grupo as $item => $qtd) {
                $html .= '<li>' . h(ucfirst(str_replace('_', ' ', $item))) . ': ' . h($qtd) . '</li>';
            }
            $html .= '</ul></li>';
        }
    } else {
        foreach ($ingredientes as $item => $qtd) {
            if (!is_string($qtd)) continue;
            $html .= '<li>' . h(ucfirst(str_replace('_', ' ', $item))) . ': ' . h($qtd) . '</li>';
        }
    }
    $html .= '</ul>';
    return $html;
}

$id = isset($_GET['id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['id']) : '';
$receita = null;
$dbError = null;

try {
    $pdo = getDb();
    $stmt = $pdo->prepare("SELECT *, data_receita AS data FROM receitas WHERE id = ?");
    $stmt->execute([$id]);
    $receita = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($receita) {
        $receita['ingredientes'] = json_decode($receita['ingredientes'], true) ?: [];
    }
} catch (PDOException $e) {
    $dbError = true;
}

$shell = @file_get_contents(__DIR__ . '/index.html');
if ($shell === false) {
    http_response_code(500);
    exit('index.html ausente no servidor');
}

$shell = preg_replace('#<title>.*?</title>#is', '', $shell, 1);
$shell = preg_replace('#<meta\s+name=["\']description["\'][^>]*>#i', '', $shell, 1);
$shell = preg_replace('#<link\s+rel=["\']canonical["\'][^>]*>#i', '', $shell, 1);
$shell = preg_replace('#<meta\s+property=["\']og:[^>]*>#i', '', $shell, 1);

$siteUrl = 'https://receitas.free.nf';

if (!$receita) {
    http_response_code(404);
    $title = 'Receita não encontrada | Receitas CEI';
    $desc = 'A receita que você procura não foi encontrada. Veja todas as receitas do curso de panificação do CEI de Quintino.';
    $canonical = $siteUrl . '/receitas';

    $head = "\n";
    $head .= "  <title>" . h($title) . "</title>\n";
    $head .= '  <meta name="description" content="' . h($desc) . '">' . "\n";
    $head .= '  <link rel="canonical" href="' . h($canonical) . '">' . "\n";
    $head .= '  <meta property="og:type" content="website">' . "\n";
    $head .= '  <meta property="og:title" content="' . h($title) . '">' . "\n";
    $head .= '  <meta property="og:description" content="' . h($desc) . '">' . "\n";
    $head .= '  <meta property="og:url" content="' . h($canonical) . '">' . "\n";
    $head .= '  <meta property="og:locale" content="pt_BR">' . "\n";

    $noscript = '<noscript><h1>Receita não encontrada</h1><p>' . h($desc) . '</p><p><a href="/receitas">Ver todas as receitas</a></p></noscript>';

    $shell = str_replace('<head>', '<head>' . $head, $shell, $count);
    $shell = str_replace('</body>', $noscript . "\n</body>", $shell);
    echo $shell;
    exit;
}

$ingredientes = $receita['ingredientes'];
$flat = flattenIngredients($ingredientes);
$preparo = trim($receita['modo_preparo'] ?? '');
$preparoSteps = $preparo ? array_values(array_filter(array_map('trim', explode("\n", $preparo)))) : [];

$title = $receita['titulo'] . ' | Receitas CEI';
$desc = $receita['descricao'] ?: ('Receita de ' . $receita['titulo'] . ' do curso de panificação do CEI de Quintino.');
$canonical = $siteUrl . '/receita/' . $receita['id'];
$image = $receita['image_url'] ?? '';
$datePublished = $receita['data'] ?: null;

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Recipe',
    'name' => $receita['titulo'],
    'description' => $desc,
    'recipeCategory' => $receita['categoria'],
    'recipeCuisine' => 'Brasileira',
    'keywords' => implode(', ', array_filter(['panificação', $receita['categoria'], $receita['titulo']])),
    'author' => ['@type' => 'Organization', 'name' => 'Curso de Panificação CEI de Quintino', 'url' => $siteUrl],
    'publisher' => ['@type' => 'Person', 'name' => 'Isaac Angelo', 'url' => 'https://isaacangelo.dev'],
    'inLanguage' => 'pt-BR',
    'isAccessibleForFree' => true,
    'mainEntityOfPage' => $canonical,
    'recipeIngredient' => $flat,
    'recipeInstructions' => array_map(function ($step) {
        return ['@type' => 'HowToStep', 'text' => $step];
    }, $preparoSteps),
];
if ($image) $jsonLd['image'] = $image;
if ($datePublished) $jsonLd['datePublished'] = $datePublished;

$head = "\n";
$head .= "  <title>" . h($title) . "</title>\n";
$head .= '  <meta name="description" content="' . h($desc) . '">' . "\n";
$head .= '  <link rel="canonical" href="' . h($canonical) . '">' . "\n";
$head .= '  <meta property="og:type" content="article">' . "\n";
$head .= '  <meta property="og:site_name" content="Receitas CEI">' . "\n";
$head .= '  <meta property="og:title" content="' . h($title) . '">' . "\n";
$head .= '  <meta property="og:description" content="' . h($desc) . '">' . "\n";
$head .= '  <meta property="og:url" content="' . h($canonical) . '">' . "\n";
$head .= '  <meta property="og:locale" content="pt_BR">' . "\n";
if ($image) {
    $head .= '  <meta property="og:image" content="' . h($image) . '">' . "\n";
}
$head .= '  <script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";

$noscript = '<noscript>';
$noscript .= '<article>';
$noscript .= '<h1>' . h($receita['titulo']) . '</h1>';
$noscript .= '<p>' . h($receita['categoria']) . ($datePublished ? ' · ' . h($datePublished) : '') . '</p>';
if ($image) {
    $noscript .= '<img src="' . h($image) . '" alt="' . h($receita['titulo']) . '" width="400">';
}
$noscript .= '<h2>Ingredientes</h2>';
$noscript .= renderIngredientsHtml($ingredientes);
if ($receita['total_farinha']) {
    $noscript .= '<p>Total de farinha: ' . h($receita['total_farinha']) . '</p>';
}
$noscript .= '<h2>Modo de Preparo</h2>';
$noscript .= $preparo ? '<p>' . nl2br(h($preparo)) . '</p>' : '<p></p>';
if (!empty($receita['observacoes'])) {
    $noscript .= '<h2>Observações</h2><p>' . nl2br(h($receita['observacoes'])) . '</p>';
}
$noscript .= '</article>';
$noscript .= '</noscript>';

$shell = str_replace('<head>', '<head>' . $head, $shell, $count);
$shell = str_replace('</body>', $noscript . "\n</body>", $shell);
echo $shell;
