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
            SELECT u.email, u.username, d.code, d.description, d.type, d.value, d.series_slug 
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
                $valor_texto = $u['type'] === 'percent' ? number_format($u['value'], 0) . '%' : '€' . number_format($u['value'], 2);
                $nombre = strtoupper($u['username'] ?: 'Atleta');

                $subject = "DCIEN | Validación de Esfuerzo Completada: {$u['code']}";
                $message_html = "
                <html><head><style>
                    body { font-family:'Helvetica Neue',Arial,sans-serif; background:#f4f4f4; margin:0; padding:0; }
                    .container { max-width:600px; margin:40px auto; background:#ffffff; border-radius:8px; overflow:hidden; }
                    .header { background:#000000; color:#ffffff; padding:40px 20px; text-align:center; }
                    .header h1 { font-size:24px; letter-spacing:4px; margin:0; }
                    .content { padding:40px 30px; text-align:center; color:#333; line-height:1.6; }
                    .code-box { background:#000; color:#d4af37; padding:20px; font-size:28px; font-weight:bold; letter-spacing:4px; margin:20px 0; font-family:monospace; word-break:break-all; }
                    .btn { display:inline-block; padding:14px 30px; background:#000; color:#fff; text-decoration:none; text-transform:uppercase; letter-spacing:2px; font-weight:bold; margin-top:20px; border-radius:4px; }
                    .footer { background:#fafafa; padding:20px; text-align:center; font-size:12px; color:#888; border-top:1px solid #eee; }
                </style></head><body>
                    <div class='container'>
                        <div class='header'>
                            <h1>DCIEN</h1>
                        </div>
                        <div class='content'>
                            <h2 style='margin-top:0;'>NUEVA VALIDACIÓN: {$nombre}</h2>
                            <p>Se ha verificado tu perfil técnico y hemos emitido un crédito de desempeño a tu favor.</p>
                            
                            <div class='code-box'>{$u['code']}</div>
                            
                            <div style='background: #f9f9f9; border-left: 4px solid #000; padding: 15px; margin: 25px 0; text-align: left;'>
                                <strong>Recompensa:</strong> {$valor_texto}<br>
                                <strong>Detalle:</strong> {$u['description']}
                                " . ($u['series_slug'] ? "<br><strong>Válido para:</strong> " . strtoupper($u['series_slug']) : "") . "
                            </div>
                            
                            <p>Tu crédito ha sido vinculado a tu perfil. Cuando vayas a tu carrito de la compra, podrás activarlo seleccionando la casilla correspondiente o guardarlo para utilizarlo en el futuro.</p>
                            <a href='https://d-cien.es' class='btn'>Acceder a DCIEN</a>
                        </div>
                        <div class='footer'>
                            <p>DCIEN · Protocolo de Acceso Restringido</p>
                        </div>
                    </div>
                </body></html>";

                $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: DCIEN Protocolos <acceso@d-cien.es>\r\n";
                
                if (mail($u['email'], $subject, $message_html, $headers)) {
                    $exitosos++;
                } else {
                    $errores++;
                }
            }

            $message = show_message('success', "✅ Se han enviado {$exitosos} correos de validación. ({$errores} errores).");
        } else {
            $message = show_message('warning', "⚠️ No hay usuarios con este protocolo asignado y pendiente de uso.");
        }
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
        #create-modal, #bulk-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; padding:15px; }
        .modal-content { background:var(--surface); border:1px solid var(--border); padding:25px; width:100%; max-width:500px; border-radius:var(--radius); box-shadow:var(--shadow-md); max-height:90vh; overflow-y:auto; }

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

        <div class="split-layout">
            <div>
                <div class="card">
                    <?php if ($descuento_editar): ?>
                        <h3 style="border-bottom: 2px solid #00ff00; padding-bottom: 10px; margin-bottom: 20px; font-size:14px;">✏️ Editando: <?php echo htmlspecialchars($descuento_editar['code']); ?></h3>
                        
                        <form method="POST" action="descuentos.php?edit=<?php echo $descuento_editar['id']; ?>">
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
                                <button type="submit" class="btn" style="flex: 1;">💾 Guardar</button>
                                <a href="descuentos.php" class="btn btn-secondary" style="text-align: center;">Cancelar</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <div style="text-align: center; color:var(--text-2); padding: 80px 0;">
                            <div style="font-size: 50px; margin-bottom: 20px;">👈</div>
                            <p style="font-size: 14px;">Selecciona un protocolo de la tabla<br>para auditar su configuración.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px;">
                        <h3 style="margin:0; font-size:14px;">📋 Protocolos Existentes</h3>
                    </div>
                    
                    <div class="table-container" style="max-height: 70vh; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Código / Info</th>
                                    <th>Crédito</th>
                                    <th>Usos</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($descuentos)): ?>
                                    <tr><td colspan="5" style="text-align: center; padding: 40px; color:var(--text-2);">No hay validaciones configuradas</td></tr>
                                <?php else: ?>
                                    <?php foreach ($descuentos as $d): ?>
                                        <tr style="<?php echo (isset($descuento_editar) && $descuento_editar['id'] === $d['id']) ? 'background-color: rgba(0,255,0,0.1);' : ''; ?>">
                                            <td>
                                                <strong style="color:var(--sent);"><?php echo htmlspecialchars($d['code']); ?></strong><br>
                                                <span style="font-size: 9px; color:var(--text-2); display:block; max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo htmlspecialchars($d['description']); ?>">
                                                    <?php echo htmlspecialchars($d['description'] ?? '-'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo number_format($d['value'], 2); ?><?php echo $d['type'] === 'percent' ? '%' : '€'; ?></strong>
                                                <?php if ($d['series_slug']): ?>
                                                    <br><span style="font-size: 9px; color:#3498db; text-transform:uppercase;"><?php echo htmlspecialchars($d['series_slug']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php $usado = $d['used_count'] ?? 0; $maximo = $d['max_uses'] ?? null; ?>
                                                <?php if ($maximo): ?>
                                                    <strong><?php echo $usado; ?></strong>/<?php echo $maximo; ?>
                                                <?php else: ?>
                                                    <strong><?php echo $usado; ?></strong>/∞
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $activo = $d['is_active'];
                                                $expirado = $d['valid_until'] && strtotime($d['valid_until']) < time();
                                                $agotado = $d['max_uses'] && $d['used_count'] >= $d['max_uses'];
                                                
                                                if ($expirado) echo '<span class="badge-status bg-danger">EXPIRADO</span>';
                                                elseif ($agotado) echo '<span class="badge-status bg-danger">AGOTADO</span>';
                                                elseif ($activo) echo '<span class="badge-status bg-success">ACTIVO</span>';
                                                else echo '<span class="badge-status" style="background:#666;">INACTIVO</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <div style="display:flex; gap:4px; flex-wrap:wrap;">
                                                    <a href="descuentos.php?edit=<?php echo $d['id']; ?>" class="btn btn-small" title="Editar">✏️</a>
                                                    
                                                    <?php if (!$expirado && !$agotado): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="toggle">
                                                            <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                                                            <button type="submit" class="btn btn-small btn-secondary" title="<?php echo $activo ? 'Pausar' : 'Activar'; ?>">
                                                                <?php echo $activo ? '⏸️' : '▶️'; ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <?php if ($d['pendientes_notificar'] > 0): ?>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Enviar notificación por email a <?php echo $d['pendientes_notificar']; ?> atletas?');">
                                                            <input type="hidden" name="action" value="notificar_usuarios">
                                                            <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                                                            <button type="submit" class="btn btn-small" style="background:#3b82f6; border-color:#2563eb; color:#fff;" title="Notificar a <?php echo $d['pendientes_notificar']; ?> atletas">
                                                                📧 <span style="font-size:9px; font-weight:bold;"><?php echo $d['pendientes_notificar']; ?></span>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <?php 
            $total = count($descuentos);
            $activos = count(array_filter($descuentos, function($d) { return $d['is_active'] == 1; }));
            $total_usos = array_sum(array_column($descuentos, 'used_count'));
            ?>
            <div class="summary-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                <div><div style="font-size: 24px; font-weight: 900; color:var(--sent);"><?php echo $total; ?></div><div style="font-size: 11px; color:var(--text-2); text-transform:uppercase;">Protocolos Creados</div></div>
                <div><div style="font-size: 24px; font-weight: 900; color: #22c55e;"><?php echo $activos; ?></div><div style="font-size: 11px; color:var(--text-2); text-transform:uppercase;">Protocolos Activos</div></div>
                <div><div style="font-size: 24px; font-weight: 900; color: #f39c12;"><?php echo $total_usos; ?></div><div style="font-size: 11px; color:var(--text-2); text-transform:uppercase;">Validaciones Efectuadas</div></div>
            </div>
        </div>

        <footer style="margin-top: 40px; padding-bottom:20px; text-align:center; font-size:11px; color:var(--text-2);">
            <p>DCIEN · Sistema de Gestión de Validaciones</p>
        </footer>
    </div>
        </div>
    </div>
</body>
</html>