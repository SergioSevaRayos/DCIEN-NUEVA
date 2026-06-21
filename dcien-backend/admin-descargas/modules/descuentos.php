<?php
/**
 * DCIEN - GESTOR DE PROTOCOLOS DE RECOMPENSA (Validación de Perfil)
 * Permite crear, editar, gestionar créditos y NOTIFICAR a los atletas.
 * [Versión 100% Responsive - Layout Corregido]
 */

require_once 'config.php';
$pdo = get_db_connection();
$message = '';

// ═══════════════════════════════════════════════════════════════
// PROCESAR FORMULARIOS
// ═══════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ACCIÓN 1: ACTIVAR / DESACTIVAR RÁPIDO
    if ($action === 'toggle' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE discounts SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        $message = show_message('success', '✅ Estado del protocolo actualizado');
    }

    // ACCIÓN 2: CREAR NUEVO PROTOCOLO
    if ($action === 'crear_descuento') {
        $code = trim($_POST['code']);
        $description = trim($_POST['description']);
        $type = $_POST['type'];
        $value = (float)$_POST['value'];
        $applies_to   = $_POST['applies_to'];
        $is_stackable = isset($_POST['is_stackable']) ? 1 : 0;
        $series_slug  = !empty($_POST['series_slug']) ? $_POST['series_slug'] : null;
        $max_uses     = !empty($_POST['max_uses']) ? (int)$_POST['max_uses'] : null;
        $valid_from   = !empty($_POST['valid_from']) ? $_POST['valid_from'] . ' 00:00:00' : null;
        $valid_until  = !empty($_POST['valid_until']) ? $_POST['valid_until'] . ' 23:59:59' : null;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO discounts (code, description, type, value, applies_to, is_stackable, series_slug, max_uses, valid_from, valid_until, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$code, $description, $type, $value, $applies_to, $is_stackable, $series_slug, $max_uses, $valid_from, $valid_until]);
            $message = show_message('success', "✅ Protocolo '{$code}' creado con éxito.");
        } catch (Exception $e) {
            $message = show_message('error', "❌ Error al crear: " . $e->getMessage());
        }
    }

    // ACCIÓN 3: EDITAR PROTOCOLO EXISTENTE
    if ($action === 'editar_descuento') {
        $id = (int)$_POST['id'];
        $code = trim($_POST['code']);
        $description = trim($_POST['description']);
        $type = $_POST['type'];
        $value = (float)$_POST['value'];
        $applies_to   = $_POST['applies_to'];
        $is_stackable = isset($_POST['is_stackable']) ? 1 : 0;
        $series_slug  = !empty($_POST['series_slug']) ? $_POST['series_slug'] : null;
        $max_uses     = !empty($_POST['max_uses']) ? (int)$_POST['max_uses'] : null;
        $valid_from   = !empty($_POST['valid_from']) ? $_POST['valid_from'] . ' 00:00:00' : null;
        $valid_until  = !empty($_POST['valid_until']) ? $_POST['valid_until'] . ' 23:59:59' : null;
        $is_active    = isset($_POST['is_active']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("
                UPDATE discounts
                SET code = ?, description = ?, type = ?, value = ?, applies_to = ?, is_stackable = ?, series_slug = ?, max_uses = ?, valid_from = ?, valid_until = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$code, $description, $type, $value, $applies_to, $is_stackable, $series_slug, $max_uses, $valid_from, $valid_until, $is_active, $id]);
            $message = show_message('success', "✅ Protocolo '{$code}' actualizado correctamente.");
        } catch (Exception $e) {
            $message = show_message('error', "❌ Error al actualizar: " . $e->getMessage());
        }
    }

    // ACCIÓN 4: ENVIAR EMAIL A USUARIOS ASIGNADOS
    if ($action === 'notificar_usuarios' && isset($_POST['id'])) {
        $discount_id = (int)$_POST['id'];

        $stmt = $pdo->prepare("
            SELECT ud.id as ud_id, u.email, u.username, d.code, d.description, d.type, d.value, d.series_slug 
            FROM user_discounts ud
            JOIN users u ON ud.user_id = u.id
            JOIN discounts d ON ud.discount_id = d.id
            WHERE ud.discount_id = ? AND ud.used_at IS NULL
        ");
        $stmt->execute([$discount_id]);
        $usuarios_asignados = $stmt->fetchAll();

        if (count($usuarios_asignados) > 0) {
            $exitosos = 0;
            $errores = 0;

            foreach ($usuarios_asignados as $u) {
                require_once 'email_protocolo.php';
                
                if (enviar_email_protocolo($u['email'], $u['username'], $u['code'], $u['description'], $u['type'], $u['value'], $u['series_slug'])) {
                    $exitosos++;
                    
                    // Registrar el envío en base de datos
                    $stmt_up = $pdo->prepare("UPDATE user_discounts SET reminders_sent = reminders_sent + 1, last_reminder_at = NOW() WHERE id = ?");
                    $stmt_up->execute([$u['ud_id']]);
                } else {
                    $errores++;
                }
            }

            $message = show_message('success', "✅ Se han enviado {$exitosos} correos de validación. ({$errores} errores).");
        } else {
            $message = show_message('warning', "⚠️ No hay usuarios con este protocolo asignado y pendiente de uso.");
        }
    }

    // ACCIÓN 5: ELIMINAR PROTOCOLO
    if ($action === 'eliminar_descuento' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        try {
            // Eliminar asignaciones primero
            $pdo->prepare("DELETE FROM user_discounts WHERE discount_id = ?")->execute([$id]);
            // Eliminar protocolo
            $pdo->prepare("DELETE FROM discounts WHERE id = ?")->execute([$id]);
            $message = show_message('success', "✅ Protocolo eliminado permanentemente.");
        } catch (Exception $e) {
            $message = show_message('error', "❌ Error al eliminar: " . $e->getMessage());
        }
    }
}

// Cargar vista de atletas de un protocolo si se ha solicitado
$view_users_id = isset($_GET['view_users']) ? (int)$_GET['view_users'] : null;
$view_users_data = null;
$view_discount = null;

if ($view_users_id) {
    $stmt_d = $pdo->prepare("SELECT code FROM discounts WHERE id = ?");
    $stmt_d->execute([$view_users_id]);
    $view_discount = $stmt_d->fetch();
    
    if ($view_discount) {
        $stmt_u = $pdo->prepare("
            SELECT u.username, u.email, ud.assigned_at, ud.reminders_sent, ud.last_reminder_at, ud.used_at
            FROM user_discounts ud
            JOIN users u ON ud.user_id = u.id
            WHERE ud.discount_id = ?
            ORDER BY ud.assigned_at DESC
        ");
        $stmt_u->execute([$view_users_id]);
        $view_users_data = $stmt_u->fetchAll();
    }
}

// ═══════════════════════════════════════════════════════════════
// OBTENER DATOS
// ═══════════════════════════════════════════════════════════════

$stmt = $pdo->query("
    SELECT 
        d.*,
        (SELECT COUNT(*) FROM user_discounts ud WHERE ud.discount_id = d.id AND ud.used_at IS NULL) as pendientes_notificar
    FROM discounts d 
    WHERE d.code NOT LIKE 'BONO_%'
    ORDER BY d.is_active DESC, d.created_at DESC
");
$descuentos = $stmt->fetchAll();

$series = $pdo->query("SELECT slug, name FROM series ORDER BY name")->fetchAll();

$descuento_editar = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM discounts WHERE id = ?");
    $stmt->execute([$edit_id]);
    $descuento_editar = $stmt->fetch();
}

function format_date_for_input($datetime) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') return '';
    return date('Y-m-d', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Validación de Esfuerzo - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css?v=<?php echo time(); ?>">
    <style>
        /* Layout */
        .split-layout { display:grid; grid-template-columns:1fr 2fr; gap:20px; margin-top:20px; align-items:start; }
        .split-layout > div { min-width:0; }
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        .filters-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:10px; margin-bottom:15px; }
        .checkbox-column { width:40px; text-align:center; }
        .assign-actions { display:flex; gap:8px; }

        /* Cards de módulo */
        .card { background:var(--surface); border:1px solid var(--border); padding:20px; border-radius:var(--radius); box-sizing:border-box; width:100%; margin-bottom:20px; box-shadow:var(--shadow); }

        /* Títulos de sección */
        .section-title { font-size:11px; font-weight:600; color:var(--text-2); text-transform:uppercase; letter-spacing:1px; border-bottom:1px solid var(--border); padding-bottom:5px; margin:24px 0 12px; }
        .section-title.blue { color:#2563eb; }

        /* Ayuda */
        .help-text { font-size:11px; color:var(--text-2); margin-top:4px; display:block; line-height:1.4; }

        /* Badges de estado */
        .badge-status { padding:3px 10px; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; border-radius:20px; display:inline-block; }
        .bg-success { background:var(--sent-bg); color:var(--sent); border:1px solid var(--sent-border); }
        .bg-danger, .bg-error { background:var(--new-bg); color:var(--new); border:1px solid var(--new-border); }
        .badge-secondary { background:var(--border); color:var(--text-2); padding:3px 8px; border-radius:20px; font-size:10px; font-weight:600; white-space:nowrap; display:inline-block; }

        /* Regla de marca */
        .brand-rule { background:var(--surface-2); border-left:4px solid var(--border-2); padding:15px; font-size:11px; color:var(--text-2); margin-top:20px; border-radius:var(--radius); line-height:1.5; }

        /* Modales */
        #create-modal, #edit-modal, #bulk-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; padding:15px; }
        .modal-content { background:var(--surface); border:1px solid var(--border); padding:25px; width:100%; max-width:500px; border-radius:var(--radius); box-shadow:var(--shadow-md); max-height:90vh; overflow-y:auto; }

        /* Stats */
        .stats-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:16px; margin-bottom:28px; }
        .stat-box { background:var(--surface); border:1px solid var(--border); padding:20px 24px; border-radius:var(--radius); box-shadow:var(--shadow); }
        .stat-num { font-size:32px; font-weight:700; color:var(--text); line-height:1; margin-bottom:4px; }
        .stat-lbl { font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:var(--text-2); }
        .btn-sm { padding:5px 9px; font-size:11px; }

        /* Responsive */
        @media (max-width:1100px) { .split-layout { grid-template-columns:1fr; } }
        @media (max-width:768px) { .grid-2 { grid-template-columns:1fr; } .filters-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once dirname(__DIR__) . '/includes/nav.php'; ?>
        <div class="main-content">
            <div class="container">
        <header class="header">
            <div>
                <h1>🏅 VALIDACIÓN DE ESFUERZO</h1>
                <p>Gestión de protocolos, créditos y notificaciones</p>
            </div>
            <div class="header-actions">
                <a href="/admin-descargas/">← Dashboard</a>
                <button onclick="document.getElementById('create-modal').style.display='flex'" class="btn">➕ Nuevo Protocolo</button>
            </div>
        </header>

        <?php if ($message) echo $message; ?>

        <div id="create-modal">
            <div class="modal-content">
                <h3 style="color:var(--sent); margin-bottom:20px; border-bottom:1px solid #333; padding-bottom:10px;">➕ Crear Validación de Esfuerzo</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="crear_descuento">
                    <div class="form-group">
                        <label>Código de Protocolo</label>
                        <input type="text" name="code" placeholder="Ej: BURPEES-50" required>
                    </div>
                    <div class="form-group">
                        <label>Copywriting SEO / Descripción</label>
                        <textarea name="description" rows="2" placeholder="Registro de 50 burpees detectado. Validación de esfuerzo completada..." required></textarea>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Tipo de Crédito</label>
                            <select name="type" required>
                                <option value="percent">Porcentaje (%)</option>
                                <option value="fixed">Monto Fijo (€)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Valor</label>
                            <input type="number" name="value" step="0.01" required>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Aplica a</label>
                            <select name="applies_to" required>
                                <option value="total">Total del Pedido</option>
                                <option value="series">Serie Específica</option>
                                <option value="shipping">Envío Gratis</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Serie (Opcional)</label>
                            <select name="series_slug">
                                <option value="">Cualquiera...</option>
                                <?php foreach ($series as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['slug']); ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; text-transform:none; letter-spacing:0; font-size:13px; color:var(--text);">
                            <input type="checkbox" name="is_stackable" value="1" style="width:16px;height:16px;">
                            <span><strong>Acumulable</strong> — se combina con otros descuentos acumulables del usuario</span>
                        </label>
                    </div>
                    <div style="display:flex; gap:10px; margin-top: 20px;">
                        <button type="submit" class="btn" style="flex:1;">Activar Protocolo</button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('create-modal').style.display='none'">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="stats-row">
            <?php 
            $total = count($descuentos);
            $activos = count(array_filter($descuentos, function($d) { return $d['is_active'] == 1; }));
            $total_usos = array_sum(array_column($descuentos, 'used_count'));
            ?>
            <div class="stat-box"><div class="stat-num" style="color:var(--sent);"><?php echo $total; ?></div><div class="stat-lbl">Protocolos Creados</div></div>
            <div class="stat-box"><div class="stat-num" style="color:#22c55e;"><?php echo $activos; ?></div><div class="stat-lbl">Protocolos Activos</div></div>
            <div class="stat-box"><div class="stat-num" style="color:#f39c12;"><?php echo $total_usos; ?></div><div class="stat-lbl">Validaciones Efectuadas</div></div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:16px;">
            <?php if (empty($descuentos)): ?>
                <p style="color:var(--text-3);font-size:12px;text-align:center;grid-column:1/-1;padding:40px;">No hay protocolos configurados.</p>
            <?php else: ?>
                <?php foreach ($descuentos as $d): 
                    $activo = $d['is_active'];
                    $expirado = $d['valid_until'] && strtotime($d['valid_until']) < time();
                    $agotado = $d['max_uses'] && $d['used_count'] >= $d['max_uses'];
                ?>
                    <div class="card" style="margin-bottom:0; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                                <h4 style="margin:0;font-size:18px;color:var(--sent);font-family:monospace;letter-spacing:1px;"><?php echo htmlspecialchars($d['code']); ?></h4>
                                <div>
                                    <?php 
                                    if ($expirado) echo '<span class="badge-status bg-danger">EXPIRADO</span>';
                                    elseif ($agotado) echo '<span class="badge-status bg-danger">AGOTADO</span>';
                                    elseif ($activo) echo '<span class="badge-status bg-success">ACTIVO</span>';
                                    else echo '<span class="badge-status" style="background:#666;">INACTIVO</span>';
                                    ?>
                                </div>
                            </div>
                            
                            <p style="font-size:12px;color:var(--text);margin:0 0 16px 0;line-height:1.4;"><?php echo htmlspecialchars($d['description']); ?></p>
                            
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;background:var(--surface-2);padding:10px;border-radius:4px;">
                                <div>
                                    <div style="font-size:10px;color:var(--text-2);text-transform:uppercase;">Valor</div>
                                    <strong style="font-size:14px;color:var(--text);"><?php echo number_format($d['value'], 2); ?><?php echo $d['type'] === 'percent' ? '%' : '€'; ?></strong>
                                </div>
                                <div>
                                    <div style="font-size:10px;color:var(--text-2);text-transform:uppercase;">Usos</div>
                                    <strong style="font-size:14px;color:var(--text);"><?php echo (int)$d['used_count']; ?></strong><span style="font-size:12px;color:var(--text-3);">/<?php echo $d['max_uses'] ?: '∞'; ?></span>
                                </div>
                                <?php if ($d['series_slug']): ?>
                                <div style="grid-column:1/-1;">
                                    <div style="font-size:10px;color:var(--text-2);text-transform:uppercase;">Serie Exclusiva</div>
                                    <div style="font-size:12px;color:#3498db;text-transform:uppercase;font-weight:600;"><?php echo htmlspecialchars($d['series_slug']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;border-top:1px solid var(--border);padding-top:12px;">
                            <a href="descuentos.php?edit=<?php echo $d['id']; ?>" class="btn btn-sm btn-secondary" style="text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center;">✏️ Editar</a>
                            
                            <a href="descuentos.php?view_users=<?php echo $d['id']; ?>" class="btn btn-sm" style="text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center;background:#222;color:#fff;border:1px solid #444;">👥 Atletas</a>
                            
                            <?php if (!$expirado && !$agotado): ?>
                                <form method="POST" style="display:flex;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary" style="width:100%;"><?php echo $activo ? '⏸️ Pausar' : '▶️ Activar'; ?></button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" style="display:flex;" onsubmit="return confirm('⚠️ ¿Estás COMPLETAMENTE seguro de que deseas ELIMINAR este protocolo? Se borrarán sus asignaciones a atletas. Esta acción no tiene vuelta atrás.');">
                                <input type="hidden" name="action" value="eliminar_descuento">
                                <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                                <button type="submit" class="btn btn-sm" style="width:100%;background:#ef4444;color:#fff;border:none;">🗑️ Eliminar</button>
                            </form>

                            <?php if ($d['pendientes_notificar'] > 0): ?>
                                <form method="POST" style="display:flex; <?php if($expirado || $agotado) echo 'grid-column: 1 / -1;'; ?>" onsubmit="return confirm('¿Enviar email a <?php echo $d['pendientes_notificar']; ?> atletas?');">
                                    <input type="hidden" name="action" value="notificar_usuarios">
                                    <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                                    <button type="submit" class="btn btn-sm" style="width:100%;background:#3b82f6;border-color:#2563eb;color:#fff;">
                                        📧 Notif (<?php echo $d['pendientes_notificar']; ?>)
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($descuento_editar): ?>
        <div id="edit-modal">
            <div class="modal-content">
                <h3 style="border-bottom: 2px solid #00ff00; padding-bottom: 10px; margin-bottom: 20px; font-size:14px;">✏️ Editando: <?php echo htmlspecialchars($descuento_editar['code']); ?></h3>
                
                <form method="POST" action="descuentos.php">
                    <input type="hidden" name="action" value="editar_descuento">
                    <input type="hidden" name="id" value="<?php echo $descuento_editar['id']; ?>">
                    
                    <div class="form-group">
                        <label>Código (Texto que ingresa el usuario)</label>
                        <input type="text" name="code" value="<?php echo htmlspecialchars($descuento_editar['code']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Descripción de Validación</label>
                        <textarea name="description" rows="3" required><?php echo htmlspecialchars($descuento_editar['description']); ?></textarea>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>Tipo de Crédito</label>
                            <select name="type" required>
                                <option value="percent" <?php echo $descuento_editar['type'] === 'percent' ? 'selected' : ''; ?>>Porcentaje (%)</option>
                                <option value="fixed" <?php echo $descuento_editar['type'] === 'fixed' ? 'selected' : ''; ?>>Monto Fijo (€)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Valor</label>
                            <input type="number" name="value" step="0.01" value="<?php echo htmlspecialchars($descuento_editar['value']); ?>" required>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>Aplica a</label>
                            <select name="applies_to" required>
                                <option value="total" <?php echo $descuento_editar['applies_to'] === 'total' ? 'selected' : ''; ?>>Total del Pedido</option>
                                <option value="series" <?php echo $descuento_editar['applies_to'] === 'series' ? 'selected' : ''; ?>>Serie Específica</option>
                                <option value="shipping" <?php echo $descuento_editar['applies_to'] === 'shipping' ? 'selected' : ''; ?>>Envío Gratis</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Restringir a Serie</label>
                            <select name="series_slug">
                                <option value="">Cualquiera...</option>
                                <?php foreach ($series as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['slug']); ?>" <?php echo $descuento_editar['series_slug'] === $s['slug'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; text-transform:none; letter-spacing:0; font-size:13px; color:var(--text);">
                            <input type="checkbox" name="is_stackable" value="1" style="width:16px;height:16px;" <?php echo !empty($descuento_editar['is_stackable']) ? 'checked' : ''; ?>>
                            <span><strong>Acumulable</strong> — se combina con otros descuentos acumulables del usuario</span>
                        </label>
                    </div>

                    <div class="section-title">Límites y Fechas</div>
                    
                    <div class="form-group">
                        <label>Límite de Usos (Vacío = Infinito)</label>
                        <input type="number" name="max_uses" value="<?php echo htmlspecialchars($descuento_editar['max_uses']); ?>">
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>Válido Desde</label>
                            <input type="date" name="valid_from" value="<?php echo format_date_for_input($descuento_editar['valid_from']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Válido Hasta</label>
                            <input type="date" name="valid_until" value="<?php echo format_date_for_input($descuento_editar['valid_until']); ?>">
                        </div>
                    </div>

                    <div class="form-group" style="background: #222; padding: 12px; border: 1px solid #444; border-radius: 4px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                            <input type="checkbox" name="is_active" value="1" <?php echo $descuento_editar['is_active'] ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                            <span style="font-size: 13px; color: #fff; text-transform:none;">Protocolo Activo</span>
                        </label>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn" style="flex: 1;">💾 Guardar Cambios</button>
                        <a href="descuentos.php" class="btn btn-secondary" style="text-align: center;">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                document.getElementById('edit-modal').style.display = 'flex';
            });
        </script>
        <?php endif; ?>

        <?php if ($view_users_id && $view_discount): ?>
        <div id="users-modal" style="display:flex; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; padding:15px;">
            <div class="modal-content" style="max-width:800px; width:100%;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px;">
                    <h3 style="margin:0; font-size:16px;">👥 Atletas con Protocolo: <span style="color:var(--sent);font-family:monospace;"><?php echo htmlspecialchars($view_discount['code']); ?></span></h3>
                    <a href="descuentos.php" style="color:#aaa; text-decoration:none; font-size:20px; line-height:1;">&times;</a>
                </div>
                
                <div class="table-container" style="max-height: 60vh; overflow-y: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Atleta</th>
                                <th>Asignado el</th>
                                <th>Avisos Enviados</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($view_users_data)): ?>
                                <tr><td colspan="4" style="text-align:center; padding:40px; color:var(--text-2);">Nadie tiene asignado este protocolo todavía.</td></tr>
                            <?php else: ?>
                                <?php foreach ($view_users_data as $vu): ?>
                                    <tr style="border-bottom: 1px solid #222;">
                                        <td>
                                            <strong><?php echo htmlspecialchars($vu['username'] ?: 'Sin nombre'); ?></strong><br>
                                            <span style="font-size:11px; color:var(--text-2);"><?php echo htmlspecialchars($vu['email']); ?></span>
                                        </td>
                                        <td>
                                            <span style="font-size:13px;"><?php echo date('d/m/Y H:i', strtotime($vu['assigned_at'])); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($vu['reminders_sent'] > 0): ?>
                                                <strong style="color:#3b82f6; font-size:13px;"><?php echo $vu['reminders_sent']; ?> emails</strong><br>
                                                <span style="font-size:10px; color:var(--text-2);">Últ: <?php echo date('d/m/y H:i', strtotime($vu['last_reminder_at'])); ?></span>
                                            <?php else: ?>
                                                <span style="color:var(--text-3); font-size:12px;">0 (No avisado)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($vu['used_at']): ?>
                                                <span class="badge-status bg-success" style="color:#fff;">USADO (<?php echo date('d/m/y', strtotime($vu['used_at'])); ?>)</span>
                                            <?php else: ?>
                                                <span class="badge-status" style="background:#444; color:#fff;">PENDIENTE</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 20px; text-align:right;">
                    <a href="descuentos.php" class="btn btn-secondary">Cerrar Historial</a>
                </div>
            </div>
        </div>
        <script>
            // Cerrar el modal al hacer click fuera del contenido (en el overlay oscuro)
            document.getElementById('users-modal').addEventListener('click', function(e) {
                if(e.target === this) {
                    window.location.href = 'descuentos.php';
                }
            });
        </script>
        <?php endif; ?>

        <footer style="margin-top: 40px; padding-bottom:20px; text-align:center; font-size:11px; color:var(--text-2);">
            <p>DCIEN · Sistema de Gestión de Validaciones</p>
        </footer>
    </div>
        </div>
    </div>
</body>
</html>