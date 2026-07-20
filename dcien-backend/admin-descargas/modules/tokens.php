<?php
/**
 * DCIEN - Gestor de Tokens de Activación
 * Seguimiento completo de las invitaciones creadas en activacion.php:
 * estado (activo/usado/caducado), descuento asociado y atleta resultante
 * una vez activada la cuenta.
 */

require_once 'config.php';

$pdo = get_db_connection();
$message = '';
$PER_PAGE = 20;

// ─── ACCIONES ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'eliminar_token' && $id > 0) {
        // Solo se puede revocar una invitación que todavía no se ha usado —
        // borrar un token ya canjeado dejaría el atleta sin rastro de cómo se activó.
        $stmt = $pdo->prepare("DELETE FROM activation_tokens WHERE id = ? AND used_at IS NULL");
        $stmt->execute([$id]);
        $message = $stmt->rowCount() > 0
            ? show_message('success', "🗑️ Token #$id revocado.")
            : show_message('error', 'No se pudo revocar (¿ya estaba usado?).');
    }

    if ($action === 'regenerar_password' && $id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM activation_tokens WHERE id = ? AND used_at IS NULL AND expires_at > NOW()");
        $stmt->execute([$id]);
        $tok = $stmt->fetch();

        if ($tok) {
            $nueva_password = $tok['instagram_username'] . '_' . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE activation_tokens SET temp_password_hash = ?, temp_password_plain = ? WHERE id = ?")
                ->execute([password_hash($nueva_password, PASSWORD_BCRYPT), $nueva_password, $id]);

            $regenerado = [
                'instagram' => $tok['instagram_username'],
                'temp_username' => $tok['temp_username'],
                'temp_password' => $nueva_password,
                'expires' => $tok['expires_at'],
                'nuevo' => true,
            ];
            $message = show_message('success', "🔁 Contraseña regenerada para @{$tok['instagram_username']} — cópiala abajo, la anterior ya no funciona.");
        } else {
            $message = show_message('error', 'No se pudo regenerar (¿token usado o caducado?).');
        }
    }

    if ($action === 'ver_credenciales' && $id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM activation_tokens WHERE id = ? AND used_at IS NULL");
        $stmt->execute([$id]);
        $tok = $stmt->fetch();

        if ($tok && $tok['temp_password_plain']) {
            $regenerado = [
                'instagram' => $tok['instagram_username'],
                'temp_username' => $tok['temp_username'],
                'temp_password' => $tok['temp_password_plain'],
                'expires' => $tok['expires_at'],
                'nuevo' => false,
            ];
        } else {
            $message = show_message('error', 'Esta invitación es anterior a que empezáramos a guardar la contraseña en claro — usa "Regenerar" para poder verla.');
        }
    }
}

// ─── FILTROS ─────────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$status  = trim($_GET['status'] ?? '');
$desde   = trim($_GET['desde'] ?? '');
$hasta   = trim($_GET['hasta'] ?? '');
$pagina  = max(1, (int)($_GET['pagina'] ?? 1));

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(t.instagram_username LIKE :search1 OR t.temp_username LIKE :search2)';
    $params['search1'] = "%$search%";
    $params['search2'] = "%$search%";
}
if ($status === 'activo') {
    $where[] = 't.used_at IS NULL AND t.expires_at > NOW()';
} elseif ($status === 'usado') {
    $where[] = 't.used_at IS NOT NULL';
} elseif ($status === 'caducado') {
    $where[] = 't.used_at IS NULL AND t.expires_at <= NOW()';
}
if ($desde !== '') {
    $where[] = 't.created_at >= :desde';
    $params['desde'] = $desde . ' 00:00:00';
}
if ($hasta !== '') {
    $where[] = 't.created_at <= :hasta';
    $params['hasta'] = $hasta . ' 23:59:59';
}
$whereClause = implode(' AND ', $where);

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM activation_tokens t WHERE $whereClause");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($total / $PER_PAGE));
$pagina = min($pagina, $totalPages);
$offset = ($pagina - 1) * $PER_PAGE;

$sql = "SELECT t.id, t.instagram_username, t.temp_username, t.expires_at, t.used_at, t.created_at, t.temp_password_plain,
               d.code AS discount_code,
               u.id AS activated_user_id, u.username AS activated_username
        FROM activation_tokens t
        LEFT JOIN discounts d ON d.id = t.discount_id
        LEFT JOIN users u ON u.activated_with_token = t.token
        WHERE $whereClause
        ORDER BY t.created_at DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(":$k", $v);
}
$stmt->bindValue(':limit', $PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$tokens = $stmt->fetchAll();

$stats = $pdo->query("
    SELECT
        SUM(used_at IS NOT NULL) AS usados,
        SUM(used_at IS NULL AND expires_at > NOW()) AS activos,
        SUM(used_at IS NULL AND expires_at <= NOW()) AS caducados
    FROM activation_tokens
")->fetch();

function build_query(array $overrides = []): string {
    $merged = array_merge($_GET, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($merged);
}

function token_status(array $t): array {
    if ($t['used_at']) return ['label' => 'USADO', 'class' => 'bg-success'];
    if (strtotime($t['expires_at']) <= time()) return ['label' => 'CADUCADO', 'class' => 'bg-error'];
    return ['label' => 'ACTIVO', 'class' => 'badge-vip'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de Tokens - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css?v=<?php echo time(); ?>">
    <style>
        .filters-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:10px; margin-bottom:15px; }
        .badge-status { padding:3px 10px; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; border-radius:20px; display:inline-block; }
        .bg-success { background:var(--sent-bg); color:var(--sent); border:1px solid var(--sent-border); }
        .bg-error { background:var(--new-bg); color:var(--new); border:1px solid var(--new-border); }
        .badge-vip { background:#fef3c7; color:#92400e; border:1px solid #fde68a; padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; }
        .pagination { display:flex; justify-content:center; align-items:center; gap:10px; margin-top:20px; flex-wrap:wrap; }
        .pagination .btn[disabled] { opacity:.4; pointer-events:none; }
        .stats-mini { display:flex; gap:20px; margin-bottom:15px; flex-wrap:wrap; }
        .stats-mini span { font-size:12px; color:var(--text-2); }
        .stats-mini strong { color:var(--text); }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once dirname(__DIR__) . '/includes/nav.php'; ?>
        <div class="main-content">
            <div class="container">
                <header class="header">
                    <div>
                        <h1>🎟️ GESTOR DE TOKENS</h1>
                        <p>Seguimiento de invitaciones: quién las tiene, quién las usó y quién sigue pendiente.</p>
                    </div>
                    <div class="header-actions">
                        <a href="/admin-descargas/modules/activacion.php">+ Nuevo Token</a>
                    </div>
                </header>

                <?php if ($message) echo $message; ?>

                <?php if ($regenerado ?? null): ?>
                    <div style="background:var(--surface); border:1px solid var(--sent-border); padding:24px; margin-bottom:24px; border-radius:var(--radius); box-shadow:var(--shadow); font-family:'Courier New',monospace;">
                        <h4 style="color:var(--sent); margin-bottom:12px;">📩 <?php echo $regenerado['nuevo'] ? 'NUEVO MENSAJE PARA ENVIAR POR DM' : 'MENSAJE PARA REENVIAR POR DM (mismo acceso de siempre)'; ?>:</h4>
                        <div style="background:var(--surface-2); padding:16px; border-left:3px solid var(--sent);">
                            <pre style="margin:0; white-space:pre-wrap; font-size:13px; line-height:1.6;">DCIEN no es para todos, pero sí para ti.
Te hemos dado acceso — úsalo bien.

Usuario:
<?php echo e($regenerado['temp_username']); ?>

Contraseña:
<?php echo e($regenerado['temp_password']); ?>

Activa tu cuenta:
https://d-cien.es/registro/activar

⏱️ Válido hasta <?php echo format_date($regenerado['expires'], 'd/m/Y'); ?></pre>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="stats-mini">
                    <span>Total: <strong><?php echo $total; ?></strong></span>
                    <span>Activos: <strong><?php echo (int)$stats['activos']; ?></strong></span>
                    <span>Usados: <strong><?php echo (int)$stats['usados']; ?></strong></span>
                    <span>Caducados: <strong><?php echo (int)$stats['caducados']; ?></strong></span>
                </div>

                <!-- FILTROS -->
                <div class="card">
                    <form method="GET">
                        <div class="filters-grid">
                            <div class="form-group" style="margin:0;">
                                <label>Buscar</label>
                                <input type="text" name="search" placeholder="Instagram, usuario..." value="<?php echo e($search); ?>">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label>Estado</label>
                                <select name="status">
                                    <option value="">Todos</option>
                                    <option value="activo" <?php echo $status === 'activo' ? 'selected' : ''; ?>>Activos</option>
                                    <option value="usado" <?php echo $status === 'usado' ? 'selected' : ''; ?>>Usados</option>
                                    <option value="caducado" <?php echo $status === 'caducado' ? 'selected' : ''; ?>>Caducados</option>
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
                            <a href="tokens.php" class="btn btn-secondary">✕ Limpiar</a>
                        </div>
                    </form>
                </div>

                <!-- TABLA -->
                <div class="card">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Instagram</th>
                                    <th>Usuario temporal</th>
                                    <th>Descuento</th>
                                    <th>Estado</th>
                                    <th>Creado</th>
                                    <th>Expira / Usado</th>
                                    <th>Atleta</th>
                                    <th style="text-align:right;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tokens)): ?>
                                    <tr><td colspan="8" style="text-align:center; padding:40px; color:var(--text-2);">No hay tokens con esos filtros</td></tr>
                                <?php else: ?>
                                    <?php foreach ($tokens as $t): $st = token_status($t); ?>
                                        <tr>
                                            <td><strong>@<?php echo e($t['instagram_username']); ?></strong></td>
                                            <td style="font-size:11px;"><?php echo e($t['temp_username']); ?></td>
                                            <td style="font-size:11px;"><?php echo e($t['discount_code'] ?: '—'); ?></td>
                                            <td><span class="badge-status <?php echo $st['class']; ?>"><?php echo $st['label']; ?></span></td>
                                            <td style="font-size:11px; color:var(--text-2); white-space:nowrap;"><?php echo format_date($t['created_at']); ?></td>
                                            <td style="font-size:11px; color:var(--text-2); white-space:nowrap;">
                                                <?php echo $t['used_at'] ? format_date($t['used_at']) : format_date($t['expires_at']); ?>
                                            </td>
                                            <td>
                                                <?php if ($t['activated_user_id']): ?>
                                                    <a href="perfil.php?id=<?php echo $t['activated_user_id']; ?>" style="font-weight:600; color:var(--accent);"><?php echo e($t['activated_username']); ?></a>
                                                <?php else: ?>
                                                    <span style="color:var(--text-3);">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align:right; white-space:nowrap;">
                                                <?php if (!$t['used_at']): ?>
                                                    <?php if (strtotime($t['expires_at']) > time()): ?>
                                                        <?php if ($t['temp_password_plain']): ?>
                                                            <form method="POST" style="display:inline-block;">
                                                                <input type="hidden" name="action" value="ver_credenciales">
                                                                <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                                                <button type="submit" class="btn btn-small" title="Ver acceso actual">👁️</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('¿Regenerar la contraseña de este token? La anterior dejará de funcionar.');">
                                                            <input type="hidden" name="action" value="regenerar_password">
                                                            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                                            <button type="submit" class="btn btn-small" title="Regenerar contraseña">🔁</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('¿Revocar esta invitación? No se podrá deshacer.');">
                                                        <input type="hidden" name="action" value="eliminar_token">
                                                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                                        <button type="submit" class="btn btn-small" style="color:var(--new)" title="Revocar">🗑️</button>
                                                    </form>
                                                <?php endif; ?>
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
