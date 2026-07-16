<?php
/**
 * DCIEN - Leads de ARSENAL
 * Emails captados a cambio de desbloquear herramientas (o interés general).
 */

require_once 'config.php';

$pdo = get_db_connection();
$message = '';
$PER_PAGE = 20;

// ─── ACCIONES ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'eliminar_lead') {
        $id = (int)($_POST['lead_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM arsenal_leads WHERE id = ?")->execute([$id]);
            $message = show_message('success', "🗑️ Lead #$id eliminado.");
        }
    }

    if ($action === 'exportar_csv') {
        $where = ['1=1'];
        $params = [];

        if (!empty($_POST['search'])) {
            $where[] = 'email LIKE :search';
            $params['search'] = '%' . $_POST['search'] . '%';
        }
        if (!empty($_POST['tool_slug'])) {
            $where[] = 'tool_slug = :tool_slug';
            $params['tool_slug'] = $_POST['tool_slug'];
        }
        $whereClause = implode(' AND ', $where);

        $stmt = $pdo->prepare("SELECT * FROM arsenal_leads WHERE $whereClause ORDER BY created_at DESC");
        $stmt->execute($params);
        $leads_export = $stmt->fetchAll();

        $filename = "arsenal_leads_" . date('Ymd_His') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, ['ID', 'Email', 'Herramienta', 'Fecha']);
        foreach ($leads_export as $lead) {
            fputcsv($output, [$lead['id'], $lead['email'], $lead['tool_slug'], $lead['created_at']]);
        }

        fclose($output);
        exit;
    }
}

// ─── FILTROS ─────────────────────────────────────────────
$search   = trim($_GET['search'] ?? '');
$toolSlug = trim($_GET['tool_slug'] ?? '');
$pagina   = max(1, (int)($_GET['pagina'] ?? 1));

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = 'email LIKE :search';
    $params['search'] = '%' . $search . '%';
}
if ($toolSlug !== '') {
    $where[] = 'tool_slug = :tool_slug';
    $params['tool_slug'] = $toolSlug;
}
$whereClause = implode(' AND ', $where);

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM arsenal_leads WHERE $whereClause");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($total / $PER_PAGE));
$pagina = min($pagina, $totalPages);
$offset = ($pagina - 1) * $PER_PAGE;

$stmt = $pdo->prepare("SELECT * FROM arsenal_leads WHERE $whereClause ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
foreach ($params as $k => $v) {
    $stmt->bindValue(":$k", $v);
}
$stmt->bindValue(':limit', $PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$leads = $stmt->fetchAll();

$tools_disponibles = $pdo->query("SELECT DISTINCT tool_slug FROM arsenal_leads ORDER BY tool_slug")->fetchAll(PDO::FETCH_COLUMN);

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
    <title>Arsenal Leads - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css?v=<?php echo time(); ?>">
    <style>
        .filters-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px; margin-bottom:15px; }
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
                        <h1>🎯 ARSENAL LEADS</h1>
                        <p><?php echo $total; ?> email(s) captados en total.</p>
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
                                <input type="text" name="search" placeholder="Email..." value="<?php echo e($search); ?>">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label>Herramienta</label>
                                <select name="tool_slug">
                                    <option value="">Todas</option>
                                    <?php foreach ($tools_disponibles as $slug): ?>
                                        <option value="<?php echo e($slug); ?>" <?php echo $toolSlug === $slug ? 'selected' : ''; ?>>
                                            <?php echo e($slug); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div style="display:flex; gap:10px; border-top:1px solid #333; padding-top:15px; flex-wrap:wrap;">
                            <button type="submit" class="btn">🔍 Filtrar</button>
                            <a href="arsenal-leads.php" class="btn btn-secondary">✕ Limpiar</a>
                            <form method="POST" style="display:inline-block; margin-left:auto;">
                                <input type="hidden" name="action" value="exportar_csv">
                                <input type="hidden" name="search" value="<?php echo e($search); ?>">
                                <input type="hidden" name="tool_slug" value="<?php echo e($toolSlug); ?>">
                                <button type="submit" class="btn btn-secondary">⬇️ Exportar CSV</button>
                            </form>
                        </div>
                    </form>
                </div>

                <!-- TABLA -->
                <div class="card">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Herramienta</th>
                                    <th>Fecha</th>
                                    <th style="text-align:right;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($leads)): ?>
                                    <tr><td colspan="4" style="text-align:center; padding:40px; color:var(--text-2);">No hay leads con esos filtros</td></tr>
                                <?php else: ?>
                                    <?php foreach ($leads as $lead): ?>
                                        <tr>
                                            <td><strong><?php echo e($lead['email']); ?></strong></td>
                                            <td><?php echo e($lead['tool_slug']); ?></td>
                                            <td style="font-size:11px; color:var(--text-2); white-space:nowrap;"><?php echo format_date($lead['created_at']); ?></td>
                                            <td style="text-align:right;">
                                                <form method="POST" onsubmit="return confirm('¿Eliminar este lead permanentemente?');" style="display:inline-block;">
                                                    <input type="hidden" name="action" value="eliminar_lead">
                                                    <input type="hidden" name="lead_id" value="<?php echo $lead['id']; ?>">
                                                    <button type="submit" class="btn btn-small" style="color:var(--new)">Eliminar</button>
                                                </form>
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
