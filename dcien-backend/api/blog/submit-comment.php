<?php
/**
 * API: Publicar un comentario en un artículo del blog
 * POST /api/blog/submit-comment.php  { "slug": "...", "content": "..." }
 * Requiere sesión autenticada (usuario dado de alta).
 */
$backend_root = dirname(dirname(__DIR__));
require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';
require_once $backend_root . '/includes/comment-filter.php';

startSecureSession();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Metodo no permitido', 405);
}

$input = getJsonInput();
validateRequired($input, ['slug', 'content']);

$slug = sanitizeInput($input['slug']);
$content = trim($input['content']);

if (mb_strlen($content) < 3 || mb_strlen($content) > 1000) {
    jsonError('El comentario debe tener entre 3 y 1000 caracteres', 400);
}

$content = sanitizeInput($content);

try {
    $userId = getUserId();
    $username = $_SESSION['username'] ?? null;

    if (!$username) {
        $user = queryOne('SELECT username FROM users WHERE id = :id', ['id' => $userId]);
        $username = $user['username'] ?? 'Atleta';
    }

    $flaggedTerm = containsOffensiveContent($content);
    $status = $flaggedTerm ? 'flagged' : 'approved';

    query(
        "INSERT INTO blog_comments (blog_slug, user_id, username, content, status, flag_reason)
         VALUES (:slug, :user_id, :username, :content, :status, :flag_reason)",
        [
            'slug' => $slug,
            'user_id' => $userId,
            'username' => $username,
            'content' => $content,
            'status' => $status,
            'flag_reason' => $flaggedTerm,
        ]
    );

    if ($status === 'flagged') {
        jsonSuccess('Tu comentario está en revisión antes de publicarse', ['status' => 'flagged']);
    } else {
        jsonSuccess('Comentario publicado', ['status' => 'approved', 'username' => $username]);
    }

} catch (Exception $e) {
    logError('Error publicando comentario de blog', ['error' => $e->getMessage()]);
    jsonError('Error del servidor', 500);
}
