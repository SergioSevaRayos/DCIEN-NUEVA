<?php
/**
 * DCIEN - Moderación de Comentarios del Blog
 * Buscador, filtros combinados, columnas ordenables y paginación (10 por página)
 */

require_once 'config.php';

$pdo = get_db_connection();
$message = '';
$PER_PAGE = 10;

// PROCESAR ACCIONES (conserva los filtros/página actuales al volver a redirigir)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $comment_id = (int)($_POST['comment_id'] ?? 0);

    if ($comment_id > 0 && $action === 'aprobar_comentario') {
        $pdo->prepare("UPDATE blog_comments SET status = 'approved' WHERE id = ?")->execute([$comment_id]);
        $message = show_message('success', "✅ Comentario #$comment_id aprobado y visible de nuevo.");
    }

    if ($comment_id > 0 && $action === 'ocultar_comentario') {
        $pdo->prepare("UPDATE blog_comments SET status = 'rejected' WHERE id = ?")->execute([$comment_id]);
        $message = show_message('warning', "🚫 Comentario #$comment_id ocultado de la vista pública.");
    }

    if ($comment_id > 0 && $action === 'eliminar_comentario') {
        $pdo->prepare("DELETE FROM blog_comments WHERE id = ?")->execute([$comment_id]);
        $message = show_message('success', "🗑️ Comentario #$comment_id eliminado permanentemente.");
    }
}

// ─── FILTROS ────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$estado  = $_GET['estado'] ?? '';
$articulo = $_GET['articulo'] ?? '';
$desde   = $_GET['desde'] ?? '';
$hasta   = $_GET['hasta'] ?? '';
$sort    = $_GET['sort'] ?? '';
$dir     = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$pagina  = max(1, (int)($_GET['pagina'] ?? 1));

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(content LIKE :search1 OR username LIKE :search2 OR blog_slug LIKE :search3)';
    $params['search1'] = "%$search%";
    $params['search2'] = "%$search%";
    $params['search3'] = "%$search%";
}
if (in_array($estado, ['approved', 'flagged', 'rejected'], true)) {
    $where[] = 'status = :estado';
    $params['estado'] = $estado;
}
if ($articulo !== '') {
    $where[] = 'blog_slug = :articulo';
    $params['articulo'] = $articulo;
}
if ($desde !== '') {
    $where[] = 'created_at >= :desde';
    $params['desde'] = $desde . ' 00:00:00';
}
if ($hasta !== '') {
    $where[] = 'created_at <= :hasta';
    $params['hasta'] = $hasta . ' 23:59:59';
}
$whereClause = implode(' AND ', $where);

// ─── ORDEN (columnas clicables) ─────────────────────────
$sortColumns = [
    'fecha'    => 'created_at',
    'usuario'  => 'username',
    'articulo' => 'blog_slug',
    'estado'   => 'status',
];
$orderBy = isset($sortColumns[$sort])
    ? $sortColumns[$sort] . ' ' . $dir
    : "FIELD(status, 'flagged','approved','rejected'), created_at DESC";

// ─── TOTAL + PAGINACIÓN ──────────────────────────────────
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM blog_comments WHERE $whereClause");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($total / $PER_PAGE));
$pagina = min($pagina, $totalPages);
$offset = ($pagina - 1) * $PER_PAGE;

$stmt = $pdo->prepare("SELECT * FROM blog_comments WHERE $whereClause ORDER BY $orderBy LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) {
    $stmt->bindValue(":$k", $v);
}
$stmt->bindValue(':limit', $PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$comentarios = $stmt->fetchAll();

$total_flagged = (int)$pdo->query("SELECT COUNT(*) FROM blog_comments WHERE status = 'flagged'")->fetchColumn();
$articulos_disponibles = $pdo->query("SELECT DISTINCT blog_slug FROM blog_comments ORDER BY blog_slug")->fetchAll(PDO::FETCH_COLUMN);

// ─── HELPER: construir URLs conservando filtros actuales ─
function build_query(array $overrides = []): string {
    $merged = array_merge($_GET, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($merged);
}

function sort_link(string $col, string $label): string {
    global $sort, $dir;
    $nextDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $icon = $sort === $col ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
    $url = build_query(['sort' => $col, 'dir' => $nextDir, 'pagina' => 1]);
    return "<a href=\"$url\" style=\"color:inherit; text-decoration:none;\">$label$icon</a>";
}

$current_action = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Moderación de Comentarios - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css?v=<?php echo time(); ?>">
    <style>
        .badge-status { padding:3px 10px; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; border-radius:20px; display:inline-block; }
        .bg-success { background:var(--sent-bg); color:var(--sent); border:1px solid var(--sent-border); }
        .bg-error { background:var(--new-bg); color:var(--new); border:1px solid var(--new-border); }
        .bg-flagged { background:#3a1a1a; color:#ff6b6b; border:1px solid #ff6b6b; }
        .comment-content { max-width: 320px; white-space: pre-wrap; word-break: break-word; }
        .filters-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:15px; }
        th a { white-space: nowrap; }
        .pagination { display:flex; justify-content:center; align-items:center; gap:10px; margin-top:20px; flex-wrap:wrap; }
        .pagination .btn[disabled] { opacity:.4; pointer-events:none; }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once dirname(__DIR__) . '/includes/nav.php'; ?>
        <div class="main-content">
            <div class="container">
        <header class="header">
            <div>
                <h1>💬 MODERACIÓN DE COMENTARIOS</h1>
                <p>
                    <?php echo $total; ?> comentario(s) en total.
                    <?php if ($total_flagged > 0): ?>
                        <a href="<?php echo build_query(['estado' => 'flagged', 'pagina' => 1]); ?>" style="color:#ff6b6b; font-weight:600;">
                            🚩 <?php echo $total_flagged; ?> pendiente(s) de revisión prioritaria
                        </a>
                    <?php else: ?>
                        Sin comentarios marcados pendientes.
                    <?php endif; ?>
                </p>
            </div>
            <div class="header-actions">
                <a href="/admin-descargas/">← Dashboard</a>
            </div>
        </header>

        <?php if ($message) echo $message; ?>

        <!-- FILTROS -->
        <div class="card">
            <form method="GET">
                <div class="filters-grid">
                    <div class="form-group" style="margin:0;">
                        <label>Buscar</label>
                        <input type="text" name="search" placeholder="Comentario, usuario, artículo..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Estado</label>
                        <select name="estado">
                            <option value="">Todos</option>
                            <option value="approved" <?php echo $estado === 'approved' ? 'selected' : ''; ?>>Publicados</option>
                            <option value="flagged" <?php echo $estado === 'flagged' ? 'selected' : ''; ?>>Marcados</option>
                            <option value="rejected" <?php echo $estado === 'rejected' ? 'selected' : ''; ?>>Ocultos</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Artículo</label>
                        <select name="articulo">
                            <option value="">Todos</option>
                            <?php foreach ($articulos_disponibles as $slug): ?>
                                <option value="<?php echo htmlspecialchars($slug); ?>" <?php echo $articulo === $slug ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($slug); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Desde</label>
                        <input type="date" name="desde" value="<?php echo htmlspecialchars($desde); ?>">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Hasta</label>
                        <input type="date" name="hasta" value="<?php echo htmlspecialchars($hasta); ?>">
                    </div>
                </div>
                <div style="display:flex; gap:10px; border-top:1px solid #333; padding-top:15px; flex-wrap:wrap;">
                    <button type="submit" class="btn">🔍 Filtrar</button>
                    <a href="comentarios.php" class="btn btn-secondary">✕ Limpiar</a>
                </div>
            </form>
        </div>

        <!-- TABLA -->
        <div class="card">
            <div class="table-container">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="text-align:left; border-bottom:1px solid #333;">
                            <th><?php echo sort_link('fecha', 'Fecha'); ?></th>
                            <th><?php echo sort_link('usuario', 'Usuario'); ?></th>
                            <th>Comentario</th>
                            <th><?php echo sort_link('articulo', 'Artículo'); ?></th>
                            <th><?php echo sort_link('estado', 'Estado'); ?></th>
                            <th style="text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($comentarios)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-2);">No hay comentarios con esos filtros</td></tr>
                        <?php else: ?>
                            <?php foreach ($comentarios as $c): ?>
                                <tr style="border-bottom:1px solid #222;">
                                    <td style="font-size:11px; color:var(--text-2); white-space:nowrap;"><?php echo format_date($c['created_at']); ?></td>
                                    <td><strong><?php echo e($c['username']); ?></strong></td>
                                    <td class="comment-content">
                                        <?php echo e($c['content']); ?>
                                        <?php if ($c['flag_reason']): ?>
                                            <br><span style="font-size:10px; color:#ff6b6b;">🚩 término detectado: "<?php echo e($c['flag_reason']); ?>"</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/blog/<?php echo e($c['blog_slug']); ?>/" target="_blank" style="font-size:11px;">
                                            <?php echo e($c['blog_slug']); ?> ↗
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($c['status'] === 'approved'): ?>
                                            <span class="badge-status bg-success">PUBLICADO</span>
                                        <?php elseif ($c['status'] === 'flagged'): ?>
                                            <span class="badge-status bg-flagged">MARCADO</span>
                                        <?php else: ?>
                                            <span class="badge-status bg-error">OCULTO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right; white-space:nowrap;">
                                        <?php if ($c['status'] !== 'approved'): ?>
                                            <form method="POST" action="<?php echo $current_action; ?>" style="display:inline-block;">
                                                <input type="hidden" name="action" value="aprobar_comentario">
                                                <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
                                                <button type="submit" class="btn" style="background:#10b981; color:#000; border:none; padding:8px 10px; border-radius:4px; margin-right:5px;" title="Aprobar y publicar">✅</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($c['status'] !== 'rejected'): ?>
                                            <form method="POST" action="<?php echo $current_action; ?>" style="display:inline-block;" onsubmit="return confirm('¿Ocultar este comentario de la vista pública?');">
                                                <input type="hidden" name="action" value="ocultar_comentario">
                                                <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
                                                <button type="submit" class="btn" style="background:#e3a008; color:#000; border:none; padding:8px 10px; border-radius:4px; margin-right:5px;" title="Ocultar">🚫</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="<?php echo $current_action; ?>" style="display:inline-block;" onsubmit="return confirm('⚠️ ¿Eliminar este comentario PERMANENTEMENTE? Esta acción no tiene vuelta atrás.');">
                                            <input type="hidden" name="action" value="eliminar_comentario">
                                            <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
                                            <button type="submit" class="btn" style="background:#ef4444; color:#fff; border:none; padding:8px 10px; border-radius:4px;" title="Eliminar permanentemente">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINACIÓN -->
            <div class="pagination">
                <a href="<?php echo build_query(['pagina' => max(1, $pagina - 1)]); ?>" class="btn btn-secondary" <?php echo $pagina <= 1 ? 'disabled' : ''; ?>>← Anterior</a>
                <span style="font-size:12px; color:var(--text-2);">Página <?php echo $pagina; ?> de <?php echo $totalPages; ?> (<?php echo $total; ?> resultados)</span>
                <a href="<?php echo build_query(['pagina' => min($totalPages, $pagina + 1)]); ?>" class="btn btn-secondary" <?php echo $pagina >= $totalPages ? 'disabled' : ''; ?>>Siguiente →</a>
            </div>
        </div>

        <footer style="margin-top: 40px; padding-bottom:20px; text-align:center; font-size:11px; color:var(--text-2);">
            <p>DCIEN · Moderación de Comentarios</p>
        </footer>
    </div>
        </div>
    </div>
</body>
</html>
