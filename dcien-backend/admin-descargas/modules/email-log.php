<?php
/**
 * DCIEN - Historial de Emails a Atletas
 * Registro de todo lo enviado desde el Gestor de Atletas (comunicados y
 * protocolos de descuento), alimentado centralizadamente por sendAdminMail().
 */

require_once 'config.php';

$pdo = get_db_connection();
$PER_PAGE = 20;

// ─── FILTROS ─────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$type    = trim($_GET['type'] ?? '');
$status  = trim($_GET['status'] ?? '');
$desde   = trim($_GET['desde'] ?? '');
$hasta   = trim($_GET['hasta'] ?? '');
$pagina  = max(1, (int)($_GET['pagina'] ?? 1));

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(recipient_email LIKE :search1 OR recipient_username LIKE :search2 OR subject LIKE :search3)';
    $params['search1'] = "%$search%";
    $params['search2'] = "%$search%";
    $params['search3'] = "%$search%";
}
if ($type !== '') {
    $where[] = 'email_type = :type';
    $params['type'] = $type;
}
if (in_array($status, ['sent', 'failed'], true)) {
    $where[] = 'status = :status';
    $params['status'] = $status;
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

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM admin_email_log WHERE $whereClause");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($total / $PER_PAGE));
$pagina = min($pagina, $totalPages);
$offset = ($pagina - 1) * $PER_PAGE;

$stmt = $pdo->prepare("SELECT id, user_id, recipient_email, recipient_username, email_type, subject, status, error_message, created_at
                        FROM admin_email_log WHERE $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) {
    $stmt->bindValue(":$k", $v);
}
$stmt->bindValue(':limit', $PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$total_fallos = (int)$pdo->query("SELECT COUNT(*) FROM admin_email_log WHERE status = 'failed'")->fetchColumn();
$tipos_disponibles = $pdo->query("SELECT DISTINCT email_type FROM admin_email_log ORDER BY email_type")->fetchAll(PDO::FETCH_COLUMN);

function build_query(array $overrides = []): string {
    $merged = array_merge($_GET, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($merged);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Emails - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css?v=<?php echo time(); ?>">
    <style>
        .filters-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:10px; margin-bottom:15px; }
        .badge-status { padding:3px 10px; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; border-radius:20px; display:inline-block; }
        .bg-success { background:var(--sent-bg); color:var(--sent); border:1px solid var(--sent-border); }
        .bg-error { background:var(--new-bg); color:var(--new); border:1px solid var(--new-border); }
        .subject-cell { max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
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
                        <h1>📧 HISTORIAL DE EMAILS</h1>
                        <p>
                            <?php echo $total; ?> email(s) registrados.
                            <?php if ($total_fallos > 0): ?>
                                <a href="<?php echo build_query(['status' => 'failed', 'pagina' => 1]); ?>" style="color:#ff6b6b; font-weight:600;">
                                    🚩 <?php echo $total_fallos; ?> fallido(s)
                                </a>
                            <?php else: ?>
                                Sin fallos registrados.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="header-actions">
                        <a href="/admin-descargas/">← Dashboard</a>
                    </div>
                </header>

                <!-- FILTROS -->
                <div class="card">
                    <form method="GET">
                        <div class="filters-grid">
                            <div class="form-group" style="margin:0;">
                                <label>Buscar</label>
                                <input type="text" name="search" placeholder="Email, atleta, asunto..." value="<?php echo e($search); ?>">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label>Tipo</label>
                                <select name="type">
                                    <option value="">Todos</option>
                                    <?php foreach ($tipos_disponibles as $t): ?>
                                        <option value="<?php echo e($t); ?>" <?php echo $type === $t ? 'selected' : ''; ?>><?php echo e($t); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label>Estado</label>
                                <select name="status">
                                    <option value="">Todos</option>
                                    <option value="sent" <?php echo $status === 'sent' ? 'selected' : ''; ?>>Enviados</option>
                                    <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Fallidos</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label>Desde</label>
                                <input type="date" name="desde" value="<?php echo e($desde); ?>">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label>Hasta</label>
                                <input type="date" name="hasta" value="<?php echo e($hasta); ?>">
                            </div>
                        </div>
                        <div style="display:flex; gap:10px; border-top:1px solid #333; padding-top:15px; flex-wrap:wrap;">
                            <button type="submit" class="btn">🔍 Filtrar</button>
                            <a href="email-log.php" class="btn btn-secondary">✕ Limpiar</a>
                        </div>
                    </form>
                </div>

                <!-- TABLA -->
                <div class="card">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Atleta</th>
                                    <th>Tipo</th>
                                    <th>Asunto</th>
                                    <th>Estado</th>
                                    <th style="text-align:right;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-2);">No hay emails con esos filtros</td></tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td style="font-size:11px; color:var(--text-2); white-space:nowrap;"><?php echo format_date($log['created_at']); ?></td>
                                            <td>
                                                <strong><?php echo e($log['recipient_username'] ?: 'N/A'); ?></strong><br>
                                                <span style="font-size:10px; color:var(--text-2);"><?php echo e($log['recipient_email']); ?></span>
                                            </td>
                                            <td style="font-size:11px;"><?php echo e($log['email_type']); ?></td>
                                            <td class="subject-cell" title="<?php echo e($log['subject']); ?>"><?php echo e($log['subject']); ?></td>
                                            <td>
                                                <?php if ($log['status'] === 'sent'): ?>
                                                    <span class="badge-status bg-success">ENVIADO</span>
                                                <?php else: ?>
                                                    <span class="badge-status bg-error" title="<?php echo e($log['error_message']); ?>">FALLIDO</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align:right;">
                                                <a href="email-log-ver.php?id=<?php echo $log['id']; ?>" class="btn btn-small" target="_blank">Ver</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination">
                        <a href="<?php echo build_query(['pagina' => max(1, $pagina - 1)]); ?>" class="btn btn-secondary" <?php echo $pagina <= 1 ? 'disabled' : ''; ?>>← Anterior</a>
                        <span style="font-size:12px; color:var(--text-2);">Página <?php echo $pagina; ?> de <?php echo $totalPages; ?> (<?php echo $total; ?> resultados)</span>
                        <a href="<?php echo build_query(['pagina' => min($totalPages, $pagina + 1)]); ?>" class="btn btn-secondary" <?php echo $pagina >= $totalPages ? 'disabled' : ''; ?>>Siguiente →</a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
