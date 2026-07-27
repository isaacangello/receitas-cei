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
if (empty($query)) {
    jsonResponse(['error' => 'Parametro q obrigatorio'], 400);
}

$searchTerm = urlencode($query);

// 1. TheMealDB (gratis, sem API key)
$mealDbUrl = "https://www.themealdb.com/api/json/v1/1/search.php?s={$searchTerm}";
$mealData = fetchUrl($mealDbUrl);
if ($mealData && isset($mealData['meals']) && count($mealData['meals']) > 0) {
    $thumb = $mealData['meals'][0]['strMealThumb'] ?? null;
    if ($thumb) {
        jsonResponse(['url' => $thumb . '/preview', 'source' => 'themealdb']);
    }
}

// 2. Unsplash API (precisa de API key)
$unsplashKey = env('UNSPLASH_ACCESS_KEY', '');
if (!empty($unsplashKey)) {
    $unsplashUrl = "https://api.unsplash.com/search/photos?query={$searchTerm}&per_page=1&client_id={$unsplashKey}";
    $unsplashData = fetchUrl($unsplashUrl);
    if ($unsplashData && isset($unsplashData['results'][0]['urls']['small'])) {
        jsonResponse(['url' => $unsplashData['results'][0]['urls']['small'], 'source' => 'unsplash']);
    }
}

// 3. Picsum (fallback garantido - sempre retorna algo)
$seed = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($query));
$picsumUrl = "https://picsum.photos/seed/{$seed}/400/300";
jsonResponse(['url' => $picsumUrl, 'source' => 'picsum']);

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
