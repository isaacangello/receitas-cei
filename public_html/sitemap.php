<?php
define('CONFIG_HTML_MODE', true);
require_once __DIR__ . '/api/config.php';

header('Content-Type: application/xml; charset=utf-8');

$siteUrl = 'https://receitas.free.nf';
$urls = [
    '/',
    '/receitas',
    '/sobre',
    '/contato',
];

try {
    $pdo = getDb();
    $stmt = $pdo->query("SELECT id FROM receitas ORDER BY data_receita DESC");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        $urls[] = '/receita/' . $id;
    }
} catch (PDOException $e) {
    error_log('sitemap.php: ' . $e->getMessage());
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($siteUrl . $url) . "</loc>\n";
    echo "  </url>\n";
}
echo '</urlset>' . "\n";
