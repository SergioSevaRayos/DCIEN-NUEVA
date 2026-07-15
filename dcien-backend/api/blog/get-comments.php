<?php
/**
 * API: Listar comentarios aprobados de un artículo del blog
 * GET /api/blog/get-comments.php?slug=...
 */
$backend_root = dirname(dirname(__DIR__));
require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Metodo no permitido', 405);
}

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    jsonError('slug requerido', 400);
}

try {
    $comments = queryAll(
        "SELECT id, username, content, created_at
         FROM blog_comments
         WHERE blog_slug = :slug AND status = 'approved'
         ORDER BY created_at DESC",
        ['slug' => $slug]
    );

    jsonSuccess('Comentarios encontrados', ['comments' => $comments]);

} catch (Exception $e) {
    logError('Error listando comentarios de blog', ['error' => $e->getMessage()]);
    jsonError('Error del servidor', 500);
}
