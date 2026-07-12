<?php
/**
 * API: Listado de series desde BD
 * GET /api/series/list.php?status=active|archived|all
 * Público — sin autenticación requerida
 */

$backend_root = dirname(dirname(__DIR__));
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/config/database.php';

$status = $_GET['status'] ?? 'active';

if ($status === 'archived') {
    $where = 'WHERE is_active = 0';
} elseif ($status === 'all') {
    $where = '';
} else {
    $where = 'WHERE is_active = 1';
}

$pdo = getDatabaseConnection();
$stmt = $pdo->query("
    SELECT slug, name, description, price, images, colors, types, sizes,
           release_date, end_date, is_active,
           COALESCE(gender, 'unisex') AS gender,
           seo_title, seo_description, seo_keywords
    FROM series
    $where
    ORDER BY release_date DESC
");
$series = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($series as &$s) {
    $s['images']   = json_decode($s['images']   ?? '[]', true) ?: [];
    $s['colors']   = json_decode($s['colors']   ?? '[]', true) ?: [];
    $s['types']    = json_decode($s['types']    ?? '[]', true) ?: [];
    $s['sizes']    = json_decode($s['sizes']    ?? '[]', true) ?: [];
    $s['price']    = (float)$s['price'];
    $s['is_active'] = (bool)$s['is_active'];
}
unset($s);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'series' => $series], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
