<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$query = $_GET['q'] ?? '';
$categoria = $_GET['categoria'] ?? '';

if (empty($query)) {
    jsonResponse(['error' => 'Parametro q obrigatorio'], 400);
}

$searchTerm = buildSearchQuery($query, $categoria);
$encoded = urlencode($searchTerm);

// 1. Unsplash API (melhor relevancia)
$unsplashKey = env('UNSPLASH_ACCESS_KEY', '');
if (!empty($unsplashKey)) {
    $unsplashUrl = "https://api.unsplash.com/search/photos?query={$encoded}&per_page=1&client_id={$unsplashKey}";
    $unsplashData = fetchUrl($unsplashUrl);
    if ($unsplashData && isset($unsplashData['results'][0]['urls']['small'])) {
        jsonResponse(['url' => $unsplashData['results'][0]['urls']['small'], 'source' => 'unsplash']);
    }
}

// 2. TheMealDB (gratis, busca por keyword em ingles)
$mealDbUrl = "https://www.themealdb.com/api/json/v1/1/search.php?s={$encoded}";
$mealData = fetchUrl($mealDbUrl);
if ($mealData && isset($mealData['meals']) && count($mealData['meals']) > 0) {
    $thumb = $mealData['meals'][0]['strMealThumb'] ?? null;
    if ($thumb) {
        jsonResponse(['url' => $thumb . '/preview', 'source' => 'themealdb']);
    }
}

// 3. Picsum (fallback garantido - sempre retorna algo)
$seed = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($query));
$picsumUrl = "https://picsum.photos/seed/{$seed}/400/300";
jsonResponse(['url' => $picsumUrl, 'source' => 'picsum']);

function buildSearchQuery($titulo, $categoria) {
    $catMap = [
        'Paes'    => 'bread',
        'Bolos'   => 'cake',
        'Broas'   => 'corn bread biscuit',
        'Massas'  => 'pizza pasta dough',
        'Doces'   => 'dessert pastry sweet',
        'Salgados' => 'savory pastry snack',
        'Outros'  => 'baking homemade food',
    ];

    $tituloEn = stripAccents(strtolower($titulo));
    $tituloEn = preg_replace('/[^a-z0-9\s]/', ' ', $tituloEn);
    $tituloEn = trim(preg_replace('/\s+/', ' ', $tituloEn));

    $catEn = $catMap[$categoria] ?? 'baking';

    if (mb_strlen($tituloEn) > 3) {
        return $tituloEn;
    }

    return $catEn;
}

function stripAccents($str) {
    $map = [
        'a' => ['á','à','â','ã','ä','å'],
        'e' => ['é','è','ê','ë'],
        'i' => ['í','ì','î','ï'],
        'o' => ['ó','ò','ô','õ','ö'],
        'u' => ['ú','ù','û','ü'],
        'c' => ['ç'],
        'n' => ['ñ'],
    ];
    foreach ($map as $plain => $accents) {
        $str = str_replace($accents, $plain, $str);
    }
    return $str;
}

function fetchUrl($url) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true,
            'header' => "User-Agent: ReceitasCEI/1.0\r\n",
        ],
    ]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) return null;
    return json_decode($response, true);
}
