<?php
ob_start();
/**
 * DCIEN - GESTOR DE BONOS QR
 */

require_once 'config.php';
$pdo = get_db_connection();
$message = '';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['bonos_resultado'])) {
    $message = show_message('success', $_SESSION['bonos_resultado']);
    unset($_SESSION['bonos_resultado']);
}

function generar_codigo() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function format_date_input($dt) {
    if (!$dt || $dt === '0000-00-00 00:00:00') return '';
    return date('Y-m-d', strtotime($dt));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'crear_bono') {
        $code           = strtoupper(trim($_POST['code'] ?? ''));
        $nombre         = trim($_POST['nombre'] ?? '');
        $descripcion    = trim($_POST['descripcion'] ?? '');
        $discount_type  = $_POST['discount_type'] ?? 'percent';
        $discount_value = (float)($_POST['discount_value'] ?? 0);
        $applies_to     = $_POST['applies_to'] ?? 'total';
        $series_slug    = !empty($_POST['series_slug']) ? $_POST['series_slug'] : null;
        $max_uses       = !empty($_POST['max_uses']) ? (int)$_POST['max_uses'] : null;
        $valid_from     = !empty($_POST['valid_from']) ? $_POST['valid_from'] . ' 00:00:00' : null;
        $valid_until    = !empty($_POST['valid_until']) ? $_POST['valid_until'] . ' 23:59:59' : null;
        $campaign       = trim($_POST['campaign'] ?? '') ?: null;
        $created_by     = trim($_POST['created_by'] ?? '') ?: 'Admin';
        $cantidad       = max(1, min(500, (int)($_POST['cantidad'] ?? 1)));

        if (empty($nombre)) {
            $message = show_message('error', 'El nombre del bono es obligatorio.');
        } elseif ($discount_value <= 0) {
            $message = show_message('error', 'El valor del descuento debe ser mayor que 0.');
        } elseif ($cantidad > 1 && !empty($code)) {
            $message = show_message('error', 'Si creas varios bonos deja el codigo vacio.');
        } else {
            $expires_at    = $valid_until ?? date('Y-m-d H:i:s', strtotime('+30 days'));
            $bonos_creados = [];
            $errores       = [];

            for ($i = 1; $i <= $cantidad; $i++) {
                try {
                    $pdo->beginTransaction();

                    $bono_code   = ($cantidad > 1 || empty($code)) ? generar_codigo() : $code;
                    $bono_nombre = $cantidad > 1
                        ? $nombre . ' #' . str_pad($i, 3, '0', STR_PAD_LEFT)
                        : $nombre;

                    $stmt = $pdo->prepare('INSERT INTO qr_bonos (code, nombre, descripcion, discount_type, discount_value, applies_to, series_slug, max_uses, valid_from, valid_until, campaign, created_by, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
                    $stmt->execute([$bono_code, $bono_nombre, $descripcion, $discount_type, $discount_value, $applies_to, $series_slug, $max_uses, $valid_from, $valid_until, $campaign, $created_by]);
                    $bono_id = $pdo->lastInsertId();

                    $discount_code_bono = 'BONO_' . $bono_code;
                    $stmt2 = $pdo->prepare('INSERT INTO discounts (code, description, type, value, applies_to, series_slug, max_uses, valid_from, valid_until, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
                    $stmt2->execute([$discount_code_bono, $bono_nombre . ' (Bono QR)', $discount_type, $discount_value, $applies_to, $series_slug, $max_uses, $valid_from, $valid_until]);
                    $discount_id = $pdo->lastInsertId();

                    $instagram_placeholder = 'bono_' . strtolower($bono_code);
                    $temp_username  = 'BONO_' . $bono_code;
                    $temp_password  = strtolower($bono_code) . '_' . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                    $temp_pass_hash = password_hash($temp_password, PASSWORD_BCRYPT);
                    $token_str      = bin2hex(random_bytes(32));

                    $stmt3 = $pdo->prepare('INSERT INTO activation_tokens (token, instagram_username, temp_username, temp_password_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                    $stmt3->execute([$token_str, $instagram_placeholder, $temp_username, $temp_pass_hash, $expires_at]);

                    $pdo->prepare('UPDATE qr_bonos SET created_by = ?, temp_password = ? WHERE id = ?')->execute([
                        $created_by . ' | user:' . $temp_username . ' | discount_id:' . $discount_id,
                        $temp_password,
                        $bono_id
                    ]);

                    $pdo->commit();

                    $bonos_creados[] = [
                        'code'     => $bono_code,
                        'nombre'   => $bono_nombre,
                        'username' => $temp_username,
                        'password' => $temp_password,
                        'url'      => 'https://d-cien.es/bono/?code=' . $bono_code,
                    ];

                } catch (PDOException $eInner) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $errores[] = 'Bono #' . $i . ': ' . $eInner->getMessage();
                }
            }

            if (!empty($bonos_creados)) {
                $discount_label = $discount_value . ($discount_type === 'percent' ? '%' : 'EUR');
                $num     = count($bonos_creados);
                $err_txt = !empty($errores) ? ' | ' . count($errores) . ' errores.' : '';

                $filas = '';
                foreach ($bonos_creados as $b) {
                    $filas .= '<tr>'
                        . '<td style="padding:8px 12px;color:var(--sent);font-family:monospace;font-weight:bold;white-space:nowrap">' . htmlspecialchars($b['code']) . '</td>'
                        . '<td style="padding:8px 12px;color:var(--text)">' . htmlspecialchars($b['nombre']) . '</td>'
                        . '<td style="padding:8px 12px;color:var(--text);font-family:monospace">' . htmlspecialchars($b['username']) . '</td>'
                        . '<td style="padding:8px 12px;color:var(--text);font-family:monospace">' . htmlspecialchars($b['password']) . '</td>'
                        . '<td style="padding:8px 12px"><a href="' . htmlspecialchars($b['url']) . '" style="color:#2563eb;font-weight:600;font-size:11px" target="_blank">Ver QR</a></td>'
                        . '</tr>';
                }

                $tabla = '<div style="margin-top:16px;overflow-x:auto">'
                    . '<table style="width:100%;border-collapse:collapse;font-size:12px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius)">'
                    . '<thead><tr style="background:var(--surface-2);border-bottom:1px solid var(--border)">'
                    . '<th style="padding:10px 12px;color:var(--text-2);font-size:10px;text-align:left;text-transform:uppercase;letter-spacing:1px;font-weight:600">Código QR</th>'
                    . '<th style="padding:10px 12px;color:var(--text-2);font-size:10px;text-align:left;text-transform:uppercase;letter-spacing:1px;font-weight:600">Nombre</th>'
                    . '<th style="padding:10px 12px;color:var(--text-2);font-size:10px;text-align:left;text-transform:uppercase;letter-spacing:1px;font-weight:600">Usuario</th>'
                    . '<th style="padding:10px 12px;color:var(--text-2);font-size:10px;text-align:left;text-transform:uppercase;letter-spacing:1px;font-weight:600">Contraseña</th>'
                    . '<th style="padding:10px 12px;color:var(--text-2);font-size:10px;text-align:left;text-transform:uppercase;letter-spacing:1px;font-weight:600">Enlace</th>'
                    . '</tr></thead>'
                    . '<tbody>' . $filas . '</tbody>'
                    . '</table></div>'
                    . '<button onclick="copiarTabla()" class="btn btn-secondary btn-small" style="margin-top:12px">Copiar tabla</button>';

                if (session_status() === PHP_SESSION_NONE) session_start();
                $_SESSION['bonos_resultado'] = '<strong>' . $num . '</strong> bono(s) creados. Descuento: <strong>' . $discount_label . '</strong>. Validos hasta: <strong>' . $expires_at . '</strong>' . $err_txt . $tabla;
                header('Location: bonos.php?created=1');
                exit;
            } else {
                $message = show_message('error', 'No se pudo crear ningun bono. ' . implode(', ', $errores));
            }
        }
    }

    if ($action === 'toggle' && isset($_POST['id'])) {
        $pdo->prepare('UPDATE qr_bonos SET is_active = NOT is_active WHERE id = ?')->execute([(int)$_POST['id']]);
        $message = show_message('success', 'Estado actualizado.');
    }

    if ($action === 'editar_bono') {
        $id             = (int)$_POST['id'];
        $nombre         = trim($_POST['nombre'] ?? '');
        $descripcion    = trim($_POST['descripcion'] ?? '');
        $discount_type  = $_POST['discount_type'] ?? 'percent';
        $discount_value = (float)($_POST['discount_value'] ?? 0);
        $applies_to     = $_POST['applies_to'] ?? 'total';
        $series_slug    = !empty($_POST['series_slug']) ? $_POST['series_slug'] : null;
        $max_uses       = !empty($_POST['max_uses']) ? (int)$_POST['max_uses'] : null;
        $valid_from     = !empty($_POST['valid_from']) ? $_POST['valid_from'] . ' 00:00:00' : null;
        $valid_until    = !empty($_POST['valid_until']) ? $_POST['valid_until'] . ' 23:59:59' : null;
        $campaign       = trim($_POST['campaign'] ?? '') ?: null;
        $is_active      = isset($_POST['is_active']) ? 1 : 0;
        try {
            $pdo->prepare('UPDATE qr_bonos SET nombre=?, descripcion=?, discount_type=?, discount_value=?, applies_to=?, series_slug=?, max_uses=?, valid_from=?, valid_until=?, campaign=?, is_active=? WHERE id=?')
                ->execute([$nombre, $descripcion, $discount_type, $discount_value, $applies_to, $series_slug, $max_uses, $valid_from, $valid_until, $campaign, $is_active, $id]);
            $message = show_message('success', 'Bono actualizado correctamente.');
        } catch (Exception $e) {
            $message = show_message('error', 'Error: ' . $e->getMessage());
        }
    }

    if ($action === 'eliminar_bono' && isset($_POST['id'])) {
        $pdo->prepare('DELETE FROM qr_bonos WHERE id = ?')->execute([(int)$_POST['id']]);
        $message = show_message('success', 'Bono eliminado.');
    }
}

$bonos = $pdo->query('SELECT b.*, (SELECT COUNT(*) FROM qr_bonos_usos u WHERE u.bono_id = b.id) as total_scans FROM qr_bonos b ORDER BY b.is_active DESC, b.created_at DESC')->fetchAll();
$series = $pdo->query('SELECT slug, name FROM series ORDER BY name')->fetchAll();

$bono_editar = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM qr_bonos WHERE id = ?');
    $stmt->execute([(int)$_GET['edit']]);
    $bono_editar = $stmt->fetch();
}

$total_bonos = count($bonos);
$activos     = count(array_filter($bonos, fn($b) => $b['is_active'] == 1));
$total_scans = array_sum(array_column($bonos, 'total_scans'));

$ultimos_scans = $pdo->query('SELECT u.canjeado_at, u.ip, u.bono_code, b.nombre, b.discount_value, b.discount_type FROM qr_bonos_usos u JOIN qr_bonos b ON b.id = u.bono_id ORDER BY u.canjeado_at DESC LIMIT 10')->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonos QR - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body{margin:0;}
        .split-layout{display:grid;grid-template-columns:380px 1fr;gap:20px;margin-top:20px;align-items:start}
        .split-layout>div{min-width:0}
        .card{background:var(--surface);border:1px solid var(--border);padding:20px;margin-bottom:20px;border-radius:var(--radius);box-shadow:var(--shadow)}
        .form-group{margin-bottom:14px}
        .form-group label{display:block;margin-bottom:5px;font-size:10px;color:var(--text-2);text-transform:uppercase;letter-spacing:1px;font-weight:600}
        .form-group input,.form-group select,.form-group textarea{width:100%;padding:9px 11px;background:var(--surface);color:var(--text);border:1px solid var(--border-2);font-family:inherit;font-size:13px;box-sizing:border-box;border-radius:var(--radius)}
        .form-group input:focus,.form-group select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 2px rgba(0,0,0,.08)}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .section-sep{font-size:10px;font-weight:600;color:var(--text-2);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);padding-bottom:6px;margin:20px 0 14px}
        .table-wrap{overflow-x:auto;border:1px solid var(--border);border-radius:var(--radius)}
        table{width:100%;border-collapse:collapse;min-width:700px}
        th,td{padding:11px 12px;font-size:12px;text-align:left;border-bottom:1px solid var(--border)}
        th{background:var(--surface-2);color:var(--text-2);font-size:10px;text-transform:uppercase;letter-spacing:1px;font-weight:600}
        tr:hover td{background:var(--surface-2)}
        .badge-green{background:var(--sent-bg);color:var(--sent);border:1px solid var(--sent-border)}
        .badge-red{background:var(--new-bg);color:var(--new);border:1px solid var(--new-border)}
        .badge-gray{background:var(--border);color:var(--text-2);border:1px solid var(--border-2)}
        .badge-yellow{background:var(--prod-bg);color:var(--prod);border:1px solid var(--prod-border)}
        .btn-primary{background:var(--accent);color:var(--accent-text);border-color:var(--accent)}
        .btn-primary:hover{background:#333;border-color:#333}
        .btn-danger{background:var(--new-bg);color:var(--new);border-color:var(--new-border)}
        .btn-sm{padding:5px 9px;font-size:10px}
        .btn-qr{background:#eff6ff;color:#2563eb;border-color:#bfdbfe}
        .page-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:24px}
        .page-header h1{font-size:18px;letter-spacing:3px;margin:0}
        .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px}
        .stat-box{background:var(--surface);border:1px solid var(--border);padding:20px 24px;border-radius:var(--radius);box-shadow:var(--shadow)}
        .stat-num{font-size:32px;font-weight:700;color:var(--text);line-height:1;margin-bottom:4px}
        .stat-lbl{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--text-2)}
        .btn{justify-content:center}
        
        /* Modales */
        #create-modal, #qr-modal, #creds-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(28, 25, 23, 0.4);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            opacity: 0;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        #create-modal.open, #qr-modal.open, #creds-modal.open {
            display: flex;
            animation: fadeIn 0.2s ease-out forwards;
        }
        #create-modal.open .modal-inner, #qr-modal.open .qr-modal-inner, #creds-modal.open .qr-modal-inner {
            animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .modal-inner, .qr-modal-inner {
            background: var(--surface);
            border: 1px solid var(--border);
            padding: 28px;
            width: 90%;
            max-width: 520px;
            max-height: 92vh;
            overflow-y: auto;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            box-sizing: border-box;
        }
        .qr-modal-inner {
            max-width: 380px;
            text-align: center;
        }
        .modal-close-btn {
            background: none;
            border: none;
            color: var(--text-3);
            font-size: 22px;
            cursor: pointer;
            padding: 4px;
            line-height: 1;
            transition: color 0.15s ease;
        }
        .modal-close-btn:hover {
            color: var(--text);
        }
        
        /* Listado de escaneos */
        .scan-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .scan-item:last-child {
            border-bottom: none;
        }
        .scan-code {
            font-family: monospace;
            font-size: 12px;
            font-weight: bold;
            color: var(--sent);
            letter-spacing: 1px;
        }
        .scan-meta {
            font-size: 11px;
            color: var(--text-2);
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once dirname(__DIR__) . '/includes/nav.php'; ?>
        <div class="main-content">
            <div class="container">
    <header class="header">
        <div>
            <h1>BONOS QR</h1>
            <p>Genera accesos con descuento para campañas de marketing</p>
        </div>
        <div class="header-actions">
            <a href="/admin-descargas/">← Dashboard</a>
            <button class="btn" onclick="openCreateModal()">+ Nuevo Bono</button>
        </div>
    </header>

    <?php if ($message) echo $message; ?>

    <div class="stats-row">
        <div class="stat-box"><div class="stat-num"><?= $total_bonos ?></div><div class="stat-lbl">Bonos creados</div></div>
        <div class="stat-box"><div class="stat-num"><?= $activos ?></div><div class="stat-lbl">Activos ahora</div></div>
        <div class="stat-box"><div class="stat-num"><?= $total_scans ?></div><div class="stat-lbl">Escaneos totales</div></div>
    </div>

    <div class="split-layout">
        <div>
            <div class="card">
                <?php if ($bono_editar): ?>
                    <h3 style="font-size:13px;border-bottom:1px solid var(--border);padding-bottom:10px;margin-bottom:18px;color:var(--text);font-weight:700;letter-spacing:1.5px">
                        EDITANDO BONO: <span style="font-family:monospace;color:var(--sent)"><?= e($bono_editar['code']) ?></span>
                    </h3>
                    <form method="POST" action="bonos.php?edit=<?= $bono_editar['id'] ?>">
                        <input type="hidden" name="action" value="editar_bono">
                        <input type="hidden" name="id" value="<?= $bono_editar['id'] ?>">
                        <div class="form-group"><label>Nombre interno</label><input type="text" name="nombre" value="<?= e($bono_editar['nombre']) ?>" required></div>
                        <div class="form-group"><label>Descripcion</label><textarea name="descripcion" rows="2"><?= e($bono_editar['descripcion']) ?></textarea></div>
                        <div class="form-group"><label>Campana</label><input type="text" name="campaign" value="<?= e($bono_editar['campaign']) ?>"></div>
                        <div class="section-sep">Descuento</div>
                        <div class="grid-2">
                            <div class="form-group"><label>Tipo</label>
                                <select name="discount_type">
                                    <option value="percent" <?= $bono_editar['discount_type']==='percent'?'selected':'' ?>>Porcentaje (%)</option>
                                    <option value="fixed" <?= $bono_editar['discount_type']==='fixed'?'selected':'' ?>>Fijo (EUR)</option>
                                </select>
                            </div>
                            <div class="form-group"><label>Valor</label><input type="number" name="discount_value" step="0.01" value="<?= e($bono_editar['discount_value']) ?>" required></div>
                        </div>
                        <div class="grid-2">
                            <div class="form-group"><label>Aplica a</label>
                                <select name="applies_to">
                                    <option value="total" <?= $bono_editar['applies_to']==='total'?'selected':'' ?>>Total</option>
                                    <option value="base" <?= $bono_editar['applies_to']==='base'?'selected':'' ?>>Precio base</option>
                                </select>
                            </div>
                            <div class="form-group"><label>Serie (opcional)</label>
                                <select name="series_slug">
                                    <option value="">Todas</option>
                                    <?php foreach ($series as $s): ?>
                                        <option value="<?= e($s['slug']) ?>" <?= $bono_editar['series_slug']===$s['slug']?'selected':'' ?>><?= e($s['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="section-sep">Limites</div>
                        <div class="form-group"><label>Usos maximos</label><input type="number" name="max_uses" value="<?= e($bono_editar['max_uses']) ?>"></div>
                        <div class="grid-2">
                            <div class="form-group"><label>Desde</label><input type="date" name="valid_from" value="<?= format_date_input($bono_editar['valid_from']) ?>"></div>
                            <div class="form-group"><label>Hasta</label><input type="date" name="valid_until" value="<?= format_date_input($bono_editar['valid_until']) ?>"></div>
                        </div>
                        <div class="form-group" style="background:var(--surface-2);border:1px solid var(--border);padding:10px">
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;text-transform:none;font-size:13px;color:var(--text);margin:0">
                                <input type="checkbox" name="is_active" value="1" <?= $bono_editar['is_active']?'checked':'' ?> style="width:16px;height:16px">
                                Bono activo
                            </label>
                        </div>
                        <div style="display:flex;gap:10px;margin-top:18px">
                            <button type="submit" class="btn btn-primary" style="flex:1">Guardar</button>
                            <a href="bonos.php" class="btn btn-secondary" style="flex:1;text-align:center;justify-content:center">Cancelar</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div style="text-align:center;color:var(--text-3);padding:60px 0">
                        <div style="font-size:40px;margin-bottom:16px">🎟️</div>
                        <p style="font-size:13px;margin:0">Selecciona un bono para editarlo aquí.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3 style="font-size:12px;color:var(--text-2);text-transform:uppercase;letter-spacing:2px;margin-bottom:16px">Últimos escaneos</h3>
                <?php if (empty($ultimos_scans)): ?>
                    <p style="color:var(--text-3);font-size:12px;text-align:center;padding:20px 0">Sin escaneos aún</p>
                <?php else: ?>
                    <?php foreach ($ultimos_scans as $s): ?>
                        <div class="scan-item">
                            <div>
                                <div class="scan-code"><?= e($s['bono_code']) ?></div>
                                <div class="scan-meta"><?= e($s['nombre']) ?></div>
                            </div>
                            <div style="text-align:right">
                                <div style="color:var(--text);font-size:12px;font-weight:600"><?= $s['discount_type']==='percent' ? number_format($s['discount_value'],0).'%' : 'EUR '.number_format($s['discount_value'],2) ?></div>
                                <div class="scan-meta"><?= date('d/m H:i', strtotime($s['canjeado_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:12px;margin-bottom:16px">
                    <h3 style="font-size:13px;margin:0">Bonos creados</h3>
                    <span style="font-size:11px;color:var(--text-2)"><?= $total_bonos ?> bonos</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>Codigo / Nombre</th><th>Descuento</th><th>Usos</th><th>Vigencia</th><th>Estado</th><th>Acciones</th></tr>
                        </thead>
                        <tbody>
                        <?php if (empty($bonos)): ?>
                            <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-3)">No hay bonos creados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($bonos as $b):
                                $now      = time();
                                $expirado = $b['valid_until'] && strtotime($b['valid_until']) < $now;
                                $agotado  = $b['max_uses'] && $b['used_count'] >= $b['max_uses'];
                                $activo   = $b['is_active'] && !$expirado && !$agotado;
                                $bono_url = 'https://d-cien.es/bono/?code=' . urlencode($b['code']);
                                // Extraer username del campo created_by (formato: "Admin | user:BONO_XXXX | discount_id:X")
                                $temp_user = '';
                                if (preg_match('/user:([^\s|]+)/', $b['created_by'] ?? '', $m)) {
                                    $temp_user = $m[1];
                                }
                                $temp_pass = $b['temp_password'] ?? '';
                            ?>
                            <tr>
                                <td>
                                    <div style="font-family:monospace;font-size:13px;font-weight:bold;color:var(--sent);letter-spacing:2px"><?= e($b['code']) ?></div>
                                    <div style="font-size:11px;color:var(--text-2);margin-top:2px"><?= e($b['nombre']) ?></div>
                                    <?php if ($b['campaign']): ?><div style="font-size:9px;color:#2563eb;margin-top:2px;text-transform:uppercase"><?= e($b['campaign']) ?></div><?php endif; ?>
                                </td>
                                <td>
                                    <strong style="font-size:14px"><?= $b['discount_type']==='percent' ? number_format($b['discount_value'],0).'%' : 'EUR '.number_format($b['discount_value'],2) ?></strong>
                                    <?php if ($b['series_slug']): ?><div style="font-size:9px;color:var(--text-3);text-transform:uppercase;margin-top:2px"><?= e($b['series_slug']) ?></div><?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= (int)$b['used_count'] ?></strong>
                                    <span style="color:var(--text-3)">/ <?= $b['max_uses'] ?? 'inf' ?></span>
                                    <div style="font-size:9px;color:var(--text-3);margin-top:2px"><?= (int)$b['total_scans'] ?> escaneos</div>
                                </td>
                                <td style="font-size:11px;color:var(--text-2)">
                                    <?= $b['valid_until'] ? date('d/m/Y', strtotime($b['valid_until'])) : '<span style="color:var(--text-3)">Sin límite</span>' ?>
                                </td>
                                <td>
                                    <?php if ($expirado): ?>      <span class="badge badge-red">EXPIRADO</span>
                                    <?php elseif ($agotado): ?>   <span class="badge badge-yellow">AGOTADO</span>
                                    <?php elseif ($activo): ?>    <span class="badge badge-green">ACTIVO</span>
                                    <?php else: ?>                <span class="badge badge-gray">INACTIVO</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex;gap:4px;flex-wrap:wrap">
                                        <button class="btn btn-sm btn-qr" onclick="mostrarQR('<?= e($b['code']) ?>','<?= e(addslashes($b['nombre'])) ?>','<?= e($bono_url) ?>')">QR</button>
                                        <button class="btn btn-sm" style="background:#f0fdf4;color:#16a34a;border-color:#bbf7d0" onclick="mostrarCredenciales('<?= e(addslashes($temp_user)) ?>','<?= e(addslashes($temp_pass)) ?>','<?= e($b['code']) ?>')">CREDS</button>
                                        <a href="bonos.php?edit=<?= $b['id'] ?>" class="btn btn-sm">Edit</a>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                            <button type="submit" class="btn btn-sm"><?= $b['is_active'] ? 'Off' : 'On' ?></button>
                                        </form>
                                        <button class="btn btn-sm" onclick="copiarURL('<?= e($bono_url) ?>')">URL</button>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Eliminar?')">
                                            <input type="hidden" name="action" value="eliminar_bono">
                                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Del</button>
                                        </form>
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
</div>

<!-- MODAL CREAR -->
<div id="create-modal">
    <div class="modal-inner">
        <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:14px;margin-bottom:20px">
            <h3 style="margin:0;color:var(--text);font-size:14px;letter-spacing:1.5px;font-weight:700">NUEVO BONO QR</h3>
            <button type="button" class="modal-close-btn" onclick="closeCreateModal()">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="crear_bono">
            <div class="form-group">
                <label>Cantidad de bonos a crear</label>
                <div style="display:flex;align-items:center;gap:12px">
                    <input type="number" name="cantidad" min="1" max="500" value="1" required style="width:100px">
                    <span style="font-size:11px;color:var(--text-3)">Si es más de 1, el código se autogenerará</span>
                </div>
            </div>
            <div class="form-group"><label>Nombre interno</label><input type="text" name="nombre" placeholder="Ej: Competición CrossFit Mayo" required></div>
            <div class="form-group"><label>Código (vacío = autogenerado)</label><input type="text" name="code" placeholder="Dejar vacío para lote" style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()"></div>
            <div class="form-group"><label>Descripción (visible al usuario)</label><textarea name="descripcion" rows="2" placeholder="Ej: Acceso exclusivo con 20% de descuento"></textarea></div>
            <div class="form-group"><label>Campaña</label><input type="text" name="campaign" placeholder="Ej: Influencer @nombre"></div>
            <div class="form-group"><label>Creado por</label><input type="text" name="created_by" value="Admin"></div>
            <div class="section-sep">Descuento que otorga</div>
            <div class="grid-2">
                <div class="form-group"><label>Tipo</label>
                    <select name="discount_type">
                        <option value="percent">Porcentaje (%)</option>
                        <option value="fixed">Importe fijo (EUR)</option>
                    </select>
                </div>
                <div class="form-group"><label>Valor</label><input type="number" name="discount_value" step="0.01" min="0.01" placeholder="Ej: 20" required></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label>Aplica sobre</label>
                    <select name="applies_to">
                        <option value="total">Total del pedido</option>
                        <option value="base">Precio base</option>
                    </select>
                </div>
                <div class="form-group"><label>Restringir a serie</label>
                    <select name="series_slug">
                        <option value="">Todas las series</option>
                        <?php foreach ($series as $s): ?>
                            <option value="<?= e($s['slug']) ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="section-sep">Vigencia y límites</div>
            <div class="form-group"><label>Máximo de usos (vacío = ilimitado)</label><input type="number" name="max_uses" min="1" placeholder="inf"></div>
            <div class="grid-2">
                <div class="form-group"><label>Válido desde</label><input type="date" name="valid_from"></div>
                <div class="form-group"><label>Válido hasta</label><input type="date" name="valid_until"></div>
            </div>
            <div style="display:flex;gap:10px;margin-top:22px">
                <button type="submit" class="btn btn-primary" style="flex:1" onclick="this.disabled=true;this.textContent='Creando...';this.form.submit()">Crear Bono</button>
                <button type="button" class="btn btn-secondary" style="flex:1" onclick="closeCreateModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL QR -->
<div id="qr-modal">
    <div class="qr-modal-inner">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h3 style="margin:0;font-size:13px;color:var(--text);letter-spacing:1.5px;font-weight:700">CÓDIGO QR</h3>
            <button type="button" class="modal-close-btn" onclick="cerrarQR()">✕</button>
        </div>
        <div style="font-size:18px;font-weight:900;color:var(--sent);letter-spacing:4px;font-family:monospace;margin-bottom:4px" id="qr-code-label"></div>
        <div style="font-size:12px;color:var(--text-2);margin-bottom:16px" id="qr-nombre-label"></div>
        <div id="qr-canvas" style="background:#fff;padding:16px;display:inline-block;border:1px solid var(--border);border-radius:var(--radius);margin-bottom:14px"></div>
        <div style="font-size:11px;color:var(--text-3);word-break:break-all;margin-bottom:16px" id="qr-url-label"></div>
        <div style="display:flex;gap:10px">
            <button class="btn btn-primary" style="flex:1" onclick="descargarQR()">Descargar PNG</button>
            <button class="btn btn-secondary" style="flex:1" onclick="copiarURLModal()">Copiar URL</button>
        </div>
    </div>
</div>

<!-- MODAL CREDENCIALES -->
<div id="creds-modal">
    <div class="qr-modal-inner" style="max-width:440px;text-align:left">
        <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:14px;margin-bottom:20px">
            <h3 style="margin:0;font-size:14px;color:var(--text);letter-spacing:1.5px;font-weight:700">ACCESO TEMPORAL</h3>
            <button type="button" class="modal-close-btn" onclick="cerrarCreds()">✕</button>
        </div>
        <div style="font-family:monospace;font-size:18px;font-weight:900;color:var(--sent);letter-spacing:3px;margin-bottom:4px" id="creds-code-label"></div>
        <p style="font-size:12px;color:var(--text-2);margin:0 0 20px">Credenciales de acceso para este bono QR.</p>

        <div style="background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:12px">
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:var(--text-3);font-weight:600;margin-bottom:6px">Usuario</div>
            <div style="display:flex;align-items:center;gap:10px">
                <span style="font-family:monospace;font-size:14px;font-weight:700;color:var(--text);flex:1" id="creds-usuario"></span>
                <button class="btn btn-secondary btn-small" onclick="copiarCred('usuario')">Copiar</button>
            </div>
        </div>

        <div style="background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:20px">
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:var(--text-3);font-weight:600;margin-bottom:6px">Contraseña</div>
            <div style="display:flex;align-items:center;gap:10px">
                <span style="font-family:monospace;font-size:14px;font-weight:700;color:var(--text);flex:1" id="creds-password"></span>
                <button class="btn btn-secondary btn-small" onclick="copiarCred('password')">Copiar</button>
            </div>
        </div>

        <div style="display:flex;gap:10px">
            <button class="btn btn-primary" style="flex:1" onclick="copiarCredsCompleto()">Copiar todo</button>
            <button class="btn btn-secondary" style="flex:1" onclick="cerrarCreds()">Cerrar</button>
        </div>
    </div>
</div>

<script>
var _qrCurrentURL = '';

function openCreateModal() { document.getElementById('create-modal').classList.add('open'); }
function closeCreateModal() { document.getElementById('create-modal').classList.remove('open'); }
document.getElementById('create-modal').addEventListener('click', function(e) { if (e.target === this) closeCreateModal(); });

function mostrarQR(code, nombre, url) {
    _qrCurrentURL = url;
    document.getElementById('qr-code-label').textContent = code;
    document.getElementById('qr-nombre-label').textContent = nombre;
    document.getElementById('qr-url-label').textContent = url;
    var canvas = document.getElementById('qr-canvas');
    canvas.innerHTML = '';
    new QRCode(canvas, { text: url, width: 220, height: 220, colorDark: '#000000', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.H });
    document.getElementById('qr-modal').classList.add('open');
}

function cerrarQR() { document.getElementById('qr-modal').classList.remove('open'); }
document.getElementById('qr-modal').addEventListener('click', function(e) { if (e.target === this) cerrarQR(); });

function mostrarCredenciales(user, pass, code) {
    document.getElementById('creds-code-label').textContent = code;
    document.getElementById('creds-usuario').textContent = user;
    document.getElementById('creds-password').textContent = pass;
    document.getElementById('creds-modal').classList.add('open');
}
function cerrarCreds() { document.getElementById('creds-modal').classList.remove('open'); }
document.getElementById('creds-modal').addEventListener('click', function(e) { if (e.target === this) cerrarCreds(); });

function copiarCred(tipo) {
    var txt = document.getElementById('creds-' + tipo).textContent;
    navigator.clipboard.writeText(txt)
        .then(function() { showToast(tipo === 'usuario' ? 'Usuario copiado' : 'Contraseña copiada'); })
        .catch(function() { prompt('Copia:', txt); });
}

function copiarCredsCompleto() {
    var user = document.getElementById('creds-usuario').textContent;
    var pass = document.getElementById('creds-password').textContent;
    var txt  = 'Usuario: ' + user + '\nContraseña: ' + pass;
    navigator.clipboard.writeText(txt)
        .then(function() { showToast('Credenciales copiadas'); })
        .catch(function() { prompt('Copia:', txt); });
}

function descargarQR() {
    setTimeout(function() {
        var img = document.querySelector('#qr-canvas img');
        if (!img) { alert('Espera un momento e intentalo de nuevo.'); return; }
        var code = document.getElementById('qr-code-label').textContent;
        var a = document.createElement('a');
        a.href = img.src;
        a.download = 'bono-qr-' + code + '.png';
        a.click();
    }, 200);
}

function copiarURLModal() {
    navigator.clipboard.writeText(_qrCurrentURL)
        .then(function() { showToast('URL copiada'); })
        .catch(function() { prompt('Copia esta URL:', _qrCurrentURL); });
}

function copiarURL(url) {
    navigator.clipboard.writeText(url)
        .then(function() { showToast('URL copiada'); })
        .catch(function() { prompt('Copia esta URL:', url); });
}

function copiarTabla() {
    var rows = document.querySelectorAll('tbody tr');
    var sep = String.fromCharCode(9);
    var nl = String.fromCharCode(10);
    var txt = ['CODIGO', 'NOMBRE', 'USUARIO', 'CONTRASENA', 'ENLACE'].join(sep) + nl;
    rows.forEach(function(r) {
        var c = r.querySelectorAll('td');
        var url = c[4] && c[4].querySelector('a') ? c[4].querySelector('a').href : '';
        txt += [
            c[0] ? c[0].textContent.trim() : '',
            c[1] ? c[1].textContent.trim() : '',
            c[2] ? c[2].textContent.trim() : '',
            c[3] ? c[3].textContent.trim() : '',
            url
        ].join(sep) + nl;
    });
    navigator.clipboard.writeText(txt)
        .then(function() { showToast('Tabla copiada'); })
        .catch(function() {
            var ta = document.createElement('textarea');
            ta.value = txt;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
            showToast('Copiado');
        });
}

function showToast(msg) {
    var t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:var(--text);color:var(--surface);padding:12px 24px;font-weight:600;font-size:13px;border-radius:var(--radius);box-shadow:var(--shadow-md);z-index:9999;transition:opacity 0.3s ease, transform 0.3s ease;transform:translateY(10px);opacity:0;';
    document.body.appendChild(t);
    // Forzar reflujo para activar la transición
    t.offsetHeight;
    t.style.opacity = '1';
    t.style.transform = 'translateY(0)';
    setTimeout(function() { 
        t.style.opacity = '0'; 
        t.style.transform = 'translateY(10px)'; 
        setTimeout(function() { t.remove(); }, 300); 
    }, 2500);
}
</script>

        </div>
    </div>
</body>
</html>
