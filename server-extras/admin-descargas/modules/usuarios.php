<?php
/**
 * DCIEN - Gestor de Atletas (Usuarios)
 * Permite editar, ASIGNAR PROTOCOLOS (Individual y Masivo), NOTIFICAR y FILTRAR AVANZADO.
 * [Versión Responsive - Layout Corregido]
 */

require_once 'config.php';
$pdo = get_db_connection();
$message = '';

// Obtener siempre los descuentos activos
$descuentos_activos = $pdo->query("SELECT id, code, description, type, value, series_slug FROM discounts WHERE is_active = 1")->fetchAll();

// ═══════════════════════════════════════════════════════════════
// FUNCIÓN DE EMAIL
// ═══════════════════════════════════════════════════════════════
function enviar_email_protocolo($email, $username, $code, $description, $type, $value, $series_slug) {
    $valor_texto = $type === 'percent' ? number_format($value, 0) . '%' : '€' . number_format($value, 2);
    $nombre = strtoupper($username ?: 'Atleta');

    $subject = "DCIEN | Validación de Esfuerzo Completada: {$code}";
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
                
                <div class='code-box'>{$code}</div>
                
                <div style='background: #f9f9f9; border-left: 4px solid #000; padding: 15px; margin: 25px 0; text-align: left; font-size: 14px;'>
                    <strong>Recompensa:</strong> {$valor_texto}<br>
                    <strong>Detalle:</strong> {$description}
                    " . ($series_slug ? "<br><strong>Válido para:</strong> " . strtoupper($series_slug) : "") . "
                </div>
                
                <p>Tu crédito ha sido vinculado a tu perfil. Aplica el código en el proceso de checkout para acceder al activo.</p>
                <a href='https://d-cien.es' class='btn'>Acceder a DCIEN</a>
            </div>
            <div class='footer'>
                <p>DCIEN · Protocolo de Acceso Restringido</p>
            </div>
        </div>
    </body></html>";

    return sendAdminMail($email, $subject, $message_html);
}

// ═══════════════════════════════════════════════════════════════
// PROCESAR FORMULARIOS POST
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_purchase' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE users SET can_purchase = NOT can_purchase WHERE id = ?")->execute([$id]);
        $message = show_message('success', '✅ Permiso de compra actualizado');
    }
    
    if ($action === 'editar' && isset($_POST['user_id'])) {
        $id = (int)$_POST['user_id'];
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $instagram = trim($_POST['instagram'] ?? '');
        $can_purchase = isset($_POST['can_purchase']) ? 1 : 0;
        $new_password = trim($_POST['new_password'] ?? '');
        
        try {
            $pdo->prepare("UPDATE users SET username = ?, email = ?, instagram_username = ?, can_purchase = ? WHERE id = ?")
                ->execute([$username, $email, $instagram ?: null, $can_purchase, $id]);
            
            if (!empty($new_password)) {
                $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$password_hash, $id]);
                $message = show_message('success', '✅ Usuario y contraseña actualizados');
            } else {
                $message = show_message('success', '✅ Perfil de atleta actualizado correctamente');
            }
        } catch (Exception $e) {
            $message = show_message('error', '❌ Error: ' . $e->getMessage());
        }
    }

    if ($action === 'asignar_descuento' && isset($_POST['user_id']) && isset($_POST['discount_id'])) {
        $user_id = (int)$_POST['user_id'];
        $discount_id = (int)$_POST['discount_id'];
        $enviar_email = isset($_POST['enviar_email']);

        try {
            $check = $pdo->prepare("SELECT id FROM user_discounts WHERE user_id = ? AND discount_id = ? AND used_at IS NULL");
            $check->execute([$user_id, $discount_id]);
            
            if ($check->fetch()) {
                $message = show_message('warning', '⚠️ Este usuario ya tiene este protocolo asignado y pendiente de uso.');
            } else {
                $pdo->prepare("INSERT INTO user_discounts (user_id, discount_id, assigned_at) VALUES (?, ?, NOW())")->execute([$user_id, $discount_id]);
                
                if ($enviar_email) {
                    $stmt_info = $pdo->prepare("SELECT u.email, u.username, d.code, d.description, d.type, d.value, d.series_slug FROM users u JOIN discounts d ON d.id = ? WHERE u.id = ?");
                    $stmt_info->execute([$discount_id, $user_id]);
                    $info = $stmt_info->fetch();
                    
                    if ($info && enviar_email_protocolo($info['email'], $info['username'], $info['code'], $info['description'], $info['type'], $info['value'], $info['series_slug'])) {
                        $message = show_message('success', '✅ Protocolo asignado y 📧 Email enviado al atleta.');
                    } else {
                        $message = show_message('warning', '✅ Protocolo asignado, pero falló el envío del Email.');
                    }
                } else {
                    $message = show_message('success', '✅ Protocolo de validación asignado (Sin Email).');
                }
            }
        } catch (Exception $e) {
            $message = show_message('error', '❌ Error al asignar protocolo: ' . $e->getMessage());
        }
        header("Location: usuarios.php?edit=" . $user_id);
        exit;
    }

    if ($action === 'asignar_masivo' && !empty($_POST['user_ids']) && !empty($_POST['discount_id'])) {
        $user_ids_array = explode(',', $_POST['user_ids']);
        $discount_id = (int)$_POST['discount_id'];
        $enviar_email = isset($_POST['enviar_email']);

        $stmt_disc = $pdo->prepare("SELECT * FROM discounts WHERE id = ?");
        $stmt_disc->execute([$discount_id]);
        $discount = $stmt_disc->fetch();

        if ($discount) {
            $asignados = 0; $enviados = 0; $omitidos = 0;

            foreach ($user_ids_array as $uid) {
                $uid = (int)trim($uid);
                if ($uid <= 0) continue;

                $check = $pdo->prepare("SELECT id FROM user_discounts WHERE user_id = ? AND discount_id = ? AND used_at IS NULL");
                $check->execute([$uid, $discount_id]);
                
                if ($check->fetch()) {
                    $omitidos++; 
                    continue;
                }

                $pdo->prepare("INSERT INTO user_discounts (user_id, discount_id, assigned_at) VALUES (?, ?, NOW())")->execute([$uid, $discount_id]);
                $asignados++;

                if ($enviar_email) {
                    $stmt_u = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
                    $stmt_u->execute([$uid]);
                    $u = $stmt_u->fetch();

                    if ($u && enviar_email_protocolo($u['email'], $u['username'], $discount['code'], $discount['description'], $discount['type'], $discount['value'], $discount['series_slug'])) {
                        $enviados++;
                    }
                }
            }

            $resultado = "✅ Operación completada: <strong>$asignados asignados</strong>.";
            if ($enviar_email) $resultado .= " 📧 <strong>$enviados emails</strong> enviados.";
            if ($omitidos > 0) $resultado .= " ⚠️ <strong>$omitidos omitidos</strong> (ya lo tenían).";
            
            $message = show_message('success', $resultado);
        }
    }

    if ($action === 'recordatorio_descuento' && isset($_POST['user_id']) && isset($_POST['discount_id'])) {
        $user_id = (int)$_POST['user_id'];
        $discount_id = (int)$_POST['discount_id'];
        
        $stmt_info = $pdo->prepare("SELECT u.email, u.username, d.code, d.description, d.type, d.value, d.series_slug FROM users u JOIN discounts d ON d.id = ? WHERE u.id = ?");
        $stmt_info->execute([$discount_id, $user_id]);
        $info = $stmt_info->fetch();
        
        if ($info && enviar_email_protocolo($info['email'], $info['username'], $info['code'], $info['description'], $info['type'], $info['value'], $info['series_slug'])) {
            $message = show_message('success', "✅ Recordatorio enviado a {$info['email']}");
        } else {
            $message = show_message('error', '❌ Falló el envío del Email.');
        }
        header("Location: usuarios.php?edit=" . $user_id);
        exit;
    }

    if ($action === 'revocar_descuento' && isset($_POST['ud_id']) && isset($_POST['user_id'])) {
        $ud_id = (int)$_POST['ud_id'];
        $user_id = (int)$_POST['user_id'];
        try {
            $pdo->prepare("DELETE FROM user_discounts WHERE id = ? AND used_at IS NULL")->execute([$ud_id]);
            $message = show_message('success', '✅ Protocolo revocado con éxito.');
        } catch (Exception $e) {
            $message = show_message('error', '❌ Error al revocar: ' . $e->getMessage());
        }
        header("Location: usuarios.php?edit=" . $user_id);
        exit;
    }
    
    if ($action === 'eliminar' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->fetchColumn() > 0) {
            $message = show_message('warning', '⚠️ No se puede eliminar: el atleta tiene activos asociados');
        } else {
            $pdo->prepare("DELETE FROM user_discounts WHERE user_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            $message = show_message('success', '✅ Perfil eliminado');
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// OBTENER DATOS Y FILTRAR
// ═══════════════════════════════════════════════════════════════

$usuario_edit = null;
$descuentos_usuario = [];

if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$edit_id]);
    $usuario_edit = $stmt->fetch();

    if ($usuario_edit) {
        $stmt_ud = $pdo->prepare("
            SELECT ud.id as ud_id, ud.assigned_at, ud.used_at, d.id as discount_id, d.code, d.type, d.value 
            FROM user_discounts ud 
            JOIN discounts d ON ud.discount_id = d.id 
            WHERE ud.user_id = ? 
            ORDER BY ud.assigned_at DESC
        ");
        $stmt_ud->execute([$edit_id]);
        $descuentos_usuario = $stmt_ud->fetchAll();
    }
}

// LÓGICA DE FILTRADO AVANZADO
$search = $_GET['search'] ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$min_pedidos = $_GET['min_pedidos'] ?? '';

$where = ['1=1'];
$having = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(u.username LIKE :search_u OR u.email LIKE :search_e OR u.instagram_username LIKE :search_ig)";
    $params['search_u']  = "%$search%";
    $params['search_e']  = "%$search%";
    $params['search_ig'] = "%$search%";
}
if ($desde) {
    $where[] = "u.created_at >= :desde";
    $params['desde'] = $desde . ' 00:00:00';
}
if ($hasta) {
    $where[] = "u.created_at <= :hasta";
    $params['hasta'] = $hasta . ' 23:59:59';
}
if ($min_pedidos !== '') {
    $having[] = "total_pedidos >= :min_pedidos";
    $params['min_pedidos'] = (int)$min_pedidos;
}

$whereClause = implode(' AND ', $where);
$havingClause = implode(' AND ', $having);

$stmt = $pdo->prepare("
    SELECT u.*, COUNT(DISTINCT o.id) as total_pedidos, SUM(o.price) as total_gastado
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    WHERE $whereClause
    GROUP BY u.id
    HAVING $havingClause
    ORDER BY u.created_at DESC LIMIT 200
");
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$total_mostrados = count($usuarios);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gestor de Atletas - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css">
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
    <div class="container">
        <header class="header">
            <div>
                <h1>👥 GESTOR DE ATLETAS</h1>
                <p>Control de perfiles, accesos y campañas</p>
            </div>
            <div class="header-actions">
                <a href="/admin-descargas/">← Dashboard</a>
            </div>
        </header>

        <?php if ($message) echo $message; ?>

        <div id="bulk-modal">
            <div class="modal-content">
                <h3 style="color:var(--sent); margin-bottom:10px; border-bottom:1px solid #333; padding-bottom:10px;">🎁 Asignación Masiva</h3>
                <p style="color:var(--text-2); font-size:12px; margin-bottom:20px;">Aplicar protocolo a <strong id="bulk-count" style="color:#fff;">0</strong> atletas.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="asignar_masivo">
                    <input type="hidden" name="user_ids" id="bulk_user_ids" value="">
                    
                    <div class="form-group">
                        <label>Selecciona el Protocolo (Descuento)</label>
                        <select name="discount_id" required>
                            <option value="">-- Elige un crédito --</option>
                            <?php foreach ($descuentos_activos as $da): ?>
                                <option value="<?php echo $da['id']; ?>">
                                    <?php echo htmlspecialchars($da['code']); ?> 
                                    (<?php echo $da['type'] === 'percent' ? number_format($da['value'],0).'%' : '€'.number_format($da['value'],2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="background: #222; padding: 15px; border: 1px dashed #444; border-radius: 4px; margin-top: 20px;">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                            <input type="checkbox" name="enviar_email" value="1" checked style="width: 18px; height: 18px; flex-shrink: 0;">
                            <span style="font-size: 12px; color: #fff; text-transform:none; line-height: 1.4;">📧 Enviar Notificación por Email a los atletas</span>
                        </label>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:10px; margin-top: 25px;">
                        <button type="submit" class="btn" style="background:#3b82f6; border:none;">Lanzar Campaña</button>
                        <button type="button" class="btn btn-secondary" onclick="closeBulkModal()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <form method="GET">
                <div class="filters-grid">
                    <div class="form-group" style="margin: 0;">
                        <label>Buscar Texto</label>
                        <input type="text" name="search" placeholder="Username, email..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label>Reg. Desde</label>
                        <input type="date" name="desde" value="<?php echo htmlspecialchars($desde); ?>">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label>Reg. Hasta</label>
                        <input type="date" name="hasta" value="<?php echo htmlspecialchars($hasta); ?>">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label>Mínimo Pedidos</label>
                        <input type="number" name="min_pedidos" placeholder="Ej: 1" min="0" value="<?php echo htmlspecialchars($min_pedidos); ?>">
                    </div>
                </div>
                <div class="filter-actions" style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; border-top: 1px solid #333; padding-top: 15px; flex-wrap: wrap; gap: 15px;">
                    <div style="display:flex; gap:10px; flex-wrap: wrap;">
                        <button type="submit" class="btn">🔍 Filtrar</button>
                        <a href="usuarios.php" class="btn btn-secondary" style="text-align: center;">✕ Limpiar</a>
                    </div>
                    <button type="button" onclick="openBulkModal()" class="btn" style="background:#f39c12; color:#000; font-weight:bold; width: 100%; max-width: 250px;">🎁 Asignar a Seleccionados</button>
                </div>
            </form>
        </div>

        <div class="split-layout">
            
            <div>
                <div class="card">
                    <?php if ($usuario_edit): ?>
                        <h3 style="border-bottom: 2px solid #00ff00; padding-bottom: 10px; margin-bottom: 15px; font-size: 14px;">✏️ Perfil: <?php echo htmlspecialchars($usuario_edit['username'] ?: 'Atleta'); ?></h3>
                        
                        <form method="POST" action="usuarios.php?edit=<?php echo $usuario_edit['id']; ?>">
                            <input type="hidden" name="action" value="editar">
                            <input type="hidden" name="user_id" value="<?php echo $usuario_edit['id']; ?>">
                            
                            <div class="form-group">
                                <label>Username *</label>
                                <input type="text" name="username" value="<?php echo htmlspecialchars($usuario_edit['username']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email de Contacto *</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($usuario_edit['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Instagram (Sin @)</label>
                                <input type="text" name="instagram" value="<?php echo htmlspecialchars($usuario_edit['instagram_username'] ?: ''); ?>">
                            </div>
                            <div class="form-group" style="background: #222; padding: 12px; border: 1px solid #444; border-radius: 4px;">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                                    <input type="checkbox" name="can_purchase" value="1" <?php echo $usuario_edit['can_purchase'] ? 'checked' : ''; ?> style="width: 18px; height: 18px; flex-shrink:0;">
                                    <span style="font-size: 12px; color: #fff; text-transform:none;">Puede Comprar</span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label style="color:#f39c12;">Nueva Contraseña (Opcional)</label>
                                <input type="password" name="new_password" placeholder="Dejar en blanco si no cambias">
                            </div>
                            <div style="display: flex; gap: 10px; margin-top: 15px;">
                                <button type="submit" class="btn" style="flex: 1;">💾 Guardar</button>
                                <a href="usuarios.php" class="btn btn-secondary" style="text-align:center;">Cancelar</a>
                            </div>
                        </form>

                        <div class="section-title">🎁 Protocolos y Recompensas</div>
                        
                        <form method="POST" style="margin-bottom: 20px; background: #1a1a1a; padding: 15px; border: 1px dashed #333;">
                            <input type="hidden" name="action" value="asignar_descuento">
                            <input type="hidden" name="user_id" value="<?php echo $usuario_edit['id']; ?>">
                            
                            <select name="discount_id" required style="width: 100%; margin-bottom:15px;">
                                <option value="">Selecciona un protocolo...</option>
                                <?php foreach ($descuentos_activos as $da): ?>
                                    <option value="<?php echo $da['id']; ?>">
                                        <?php echo htmlspecialchars($da['code']); ?> 
                                        (<?php echo $da['type'] === 'percent' ? number_format($da['value'],0).'%' : '€'.number_format($da['value'],2); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <div class="assign-actions" style="display: flex; justify-content: space-between; align-items: center;">
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                                    <input type="checkbox" name="enviar_email" value="1" checked style="width: 16px; height: 16px; flex-shrink:0;">
                                    <span style="font-size: 11px; color:var(--text-2); text-transform:none;">Notificar por Email</span>
                                </label>
                                <button type="submit" class="btn btn-small" style="background:#3b82f6; border:none; padding: 10px 15px;">➕ Asignar</button>
                            </div>
                        </form>

                        <?php if (!empty($descuentos_usuario)): ?>
                            <div style="overflow-x: auto;">
                                <table class="w-full mini-table" style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="color:var(--text-2);">
                                            <th>Código</th>
                                            <th>Est.</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($descuentos_usuario as $du): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($du['code']); ?></strong><br>
                                                    <span style="font-size:9px; color:var(--text-2);"><?php echo $du['type'] === 'percent' ? number_format($du['value'],0).'%' : '€'.number_format($du['value'],2); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($du['used_at']): ?>
                                                        <span style="color:var(--text-2); font-size:10px;">Usado</span>
                                                    <?php else: ?>
                                                        <span style="color:var(--sent); font-size:10px;">Pendiente</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="text-align:right;">
                                                    <?php if (!$du['used_at']): ?>
                                                        <div style="display:flex; justify-content:flex-end; gap:5px;">
                                                            <form method="POST" onsubmit="return confirm('¿Reenviar correo a este atleta?');">
                                                                <input type="hidden" name="action" value="recordatorio_descuento">
                                                                <input type="hidden" name="user_id" value="<?php echo $usuario_edit['id']; ?>">
                                                                <input type="hidden" name="discount_id" value="<?php echo $du['discount_id']; ?>">
                                                                <button type="submit" style="background:none; border:none; color:#3b82f6; cursor:pointer;" title="Reenviar Email">📧</button>
                                                            </form>
                                                            <form method="POST" onsubmit="return confirm('¿Revocar este protocolo? El atleta ya no podrá usarlo.');">
                                                                <input type="hidden" name="action" value="revocar_descuento">
                                                                <input type="hidden" name="ud_id" value="<?php echo $du['ud_id']; ?>">
                                                                <input type="hidden" name="user_id" value="<?php echo $usuario_edit['id']; ?>">
                                                                <button type="submit" style="background:none; border:none; color:#e74c3c; cursor:pointer;" title="Revocar">✖</button>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="font-size:11px; color:var(--text-2); text-align:center; padding:10px;">Sin protocolos asignados.</p>
                        <?php endif; ?>

                    <?php else: ?>
                        <div style="text-align: center; color:var(--text-2); padding: 60px 0;">
                            <div style="font-size: 40px; margin-bottom: 15px;">👈</div>
                            <p style="font-size: 14px;">Selecciona un atleta para ver su perfil<br>o usa los filtros para una<br><strong>Campaña Masiva</strong>.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="card">
                    <h3 style="border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; font-size: 14px;">📋 Base de Datos (<?php echo $total_mostrados; ?>)</h3>
                    
                    <div class="table-container" style="max-height: 70vh;">
                        <table>
                            <thead>
                                <tr>
                                    <th class="checkbox-column">
                                        <input type="checkbox" onclick="toggleAll(this)" style="transform: scale(1.2);">
                                    </th>
                                    <th>ID / Perfil</th>
                                    <th>Contacto</th>
                                    <th>Métricas</th>
                                    <th>Acceso</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($usuarios)): ?>
                                    <tr><td colspan="6" style="text-align: center; padding: 40px; color:var(--text-2);">No se encontraron atletas con esos filtros</td></tr>
                                <?php else: ?>
                                    <?php foreach ($usuarios as $u): ?>
                                        <tr style="<?php echo (isset($usuario_edit) && $usuario_edit['id'] === $u['id']) ? 'background-color: rgba(0,255,0,0.1);' : ''; ?>">
                                            <td class="checkbox-column">
                                                <input type="checkbox" class="user-checkbox" value="<?php echo $u['id']; ?>" style="transform: scale(1.2);">
                                            </td>
                                            <td>
                                                <strong>#<?php echo $u['id']; ?></strong><br>
                                                <?php echo htmlspecialchars($u['username'] ?: '-'); ?>
                                            </td>
                                            <td>
                                                <span style="font-size: 10px;"><?php echo htmlspecialchars($u['email']); ?></span><br>
                                                <span style="font-size: 10px; color:var(--text-2);">@<?php echo htmlspecialchars($u['instagram_username'] ?: '-'); ?></span>
                                            </td>
                                            <td>
                                                Ped: <strong><?php echo $u['total_pedidos']; ?></strong><br>
                                                <span style="font-size: 10px; color:var(--text-2);"><?php echo format_date($u['created_at'], 'd/m/Y'); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($u['can_purchase']): ?>
                                                    <span class="badge-status bg-success">VAL</span>
                                                <?php else: ?>
                                                    <span class="badge-status bg-error">BLOQ</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display:flex; gap:5px;">
                                                    <?php 
                                                        $url_params = $_GET;
                                                        $url_params['edit'] = $u['id'];
                                                        $edit_link = 'usuarios.php?' . http_build_query($url_params);
                                                    ?>
                                                    <a href="<?php echo $edit_link; ?>" class="btn btn-small" title="Ver Perfil">👁️</a>
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

        <footer style="margin-top: 40px; padding-bottom:20px; text-align:center; font-size:11px; color:var(--text-2);">
            <p>DCIEN · Sistema de Gestión de Atletas y Campañas</p>
        </footer>
    </div>

    <script>
    function toggleAll(cb) {
        document.querySelectorAll('.user-checkbox').forEach(c => c.checked = cb.checked);
    }

    function openBulkModal() {
        const checked = document.querySelectorAll('.user-checkbox:checked');
        if (checked.length === 0) {
            alert('⚠️ Selecciona al menos un atleta marcando la casilla de la izquierda.');
            return;
        }
        
        const ids = Array.from(checked).map(c => c.value);
        document.getElementById('bulk_user_ids').value = ids.join(',');
        document.getElementById('bulk-count').innerText = ids.length;
        document.getElementById('bulk-modal').style.display = 'flex';
    }

    function closeBulkModal() {
        document.getElementById('bulk-modal').style.display = 'none';
    }
    </script>
</body>
</html>