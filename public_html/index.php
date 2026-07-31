<?php
define('CONFIG_HTML_MODE', true);
require_once __DIR__ . '/api/config.php';

$shell = @file_get_contents(__DIR__ . '/index.html');
if ($shell === false) {
    http_response_code(500);
    exit('index.html ausente no servidor');
}

$route = $_GET['route'] ?? 'home';
if (!in_array($route, ['home', 'receitas', 'sobre', 'contato'])) {
    $route = 'home';
}

$siteUrl = 'https://receitas.free.nf';
$baseTitle = 'Receitas CEI - Curso de Panificação';

$meta = [
    'home' => [
        'title' => $baseTitle,
        'desc'  => 'Receitas do curso de panificação do CEI de Quintino. Aprenda a fazer pães, bolos, broas e doces artesanais em porcentagem de panificação.',
        'canonical' => $siteUrl . '/',
    ],
    'receitas' => [
        'title' => 'Receitas de Panificação | Receitas CEI',
        'desc'  => 'Todas as receitas do curso de panificação do CEI de Quintino: pães, bolos, broas e doces artesanais.',
        'canonical' => $siteUrl . '/receitas',
    ],
    'sobre' => [
        'title' => 'Sobre o Curso | Receitas CEI',
        'desc'  => 'Conheça o curso de panificação do CEI de Quintino: receitas em porcentagem para facilitar os cálculos.',
        'canonical' => $siteUrl . '/sobre',
    ],
    'contato' => [
        'title' => 'Contato | Receitas CEI',
        'desc'  => 'Entre em contato com o desenvolvedor do site Receitas CEI.',
        'canonical' => $siteUrl . '/contato',
    ],
];

$m = $meta[$route];

$shell = preg_replace('#<title>.*?</title>#is', '', $shell, 1);
$shell = preg_replace('#<meta\s+name=["\']description["\'][^>]*>#i', '', $shell, 1);
$shell = preg_replace('#<link\s+rel=["\']canonical["\'][^>]*>#i', '', $shell, 1);
$shell = preg_replace('#<meta\s+property=["\']og:[^>]*>#i', '', $shell, 1);

$head = "\n";
$head .= "  <title>" . htmlspecialchars($m['title']) . "</title>\n";
$head .= '  <meta name="description" content="' . htmlspecialchars($m['desc']) . '">' . "\n";
$head .= '  <link rel="canonical" href="' . htmlspecialchars($m['canonical']) . '">' . "\n";
$head .= '  <meta property="og:type" content="website">' . "\n";
$head .= '  <meta property="og:site_name" content="Receitas CEI">' . "\n";
$head .= '  <meta property="og:title" content="' . htmlspecialchars($m['title']) . '">' . "\n";
$head .= '  <meta property="og:description" content="' . htmlspecialchars($m['desc']) . '">' . "\n";
$head .= '  <meta property="og:url" content="' . htmlspecialchars($m['canonical']) . '">' . "\n";
$head .= '  <meta property="og:locale" content="pt_BR">' . "\n";

$shell = str_replace('<head>', '<head>' . $head, $shell, $count);

echo $shell;
