<?php
/**
 * API: Estado de acceso prioritario para una serie
 * GET /api/series/priority-status.php?slug=serie-X
 * Público — devuelve si la serie está en periodo prioritario y si el usuario actual tiene acceso
 */

$backend_root = dirname(dirname(__DIR__));
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';
require_once $backend_root . '/config/database.php';

startSecureSession();

$series_slug = trim($_GET['slug'] ?? '');
if (empty($series_slug)) {
    jsonError('Slug requerido', 400);
}

// Comprobar si la serie existe y su estado activo
$seriesRow = queryOne(
    "SELECT is_active FROM series WHERE slug = :slug",
    ['slug' => $series_slug]
);
$is_active = $seriesRow ? (bool)$seriesRow['is_active'] : true;

$priority = queryOne(
    "SELECT priority_until, notes FROM series_priority WHERE series_slug = :slug AND priority_until > NOW()",
    ['slug' => $series_slug]
);

if (!$priority) {
    jsonResponse([
        'in_priority_mode' => false,
        'priority_until'   => null,
        'user_has_access'  => true,
        'is_active'        => $is_active,
    ], 200);
}

$user_id = isAuthenticated() ? getUserId() : null;
$user_has_access = false;

if ($user_id) {
    $access = queryOne(
        "SELECT id FROM series_priority_users WHERE series_slug = :slug AND user_id = :uid",
        ['slug' => $series_slug, 'uid' => $user_id]
    );
    $user_has_access = (bool)$access;
}

jsonResponse([
    'in_priority_mode' => true,
    'priority_until'   => $priority['priority_until'],
    'user_has_access'  => $user_has_access,
    'is_active'        => $is_active,
], 200);
