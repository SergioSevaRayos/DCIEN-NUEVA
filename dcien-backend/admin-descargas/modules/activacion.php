<?php
/**
 * DCIEN - Crear Token de Activación
 * Compatible con estructura CLI (sin metadata)
 */

require_once 'config.php';

$pdo = get_db_connection();
$message = '';
$token_generado = null;

// Cargar descuentos activos para el selector
$descuentos_disponibles = $pdo->query("
    SELECT id, code, description, type, value 
    FROM discounts 
    WHERE is_active = 1 
    ORDER BY code ASC
")->fetchAll();

/**
 * Generar password aleatoria segura
 */
function generateRandomPassword(int $length = 12): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $instagram   = trim($_POST['instagram'] ?? '');
    $discount_id = !empty($_POST['discount_id']) ? (int)$_POST['discount_id'] : null;
    
    if (empty($instagram)) {
        $message = show_message('error', '❌ El nombre de Instagram es obligatorio');
    } else {
        try {
            // 1. Verificar token existente
            $stmt = $pdo->prepare("
                SELECT id FROM activation_tokens
                WHERE instagram_username = :ig
                  AND used_at IS NULL
                  AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute(['ig' => $instagram]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $message = show_message('warning', "⚠️ Ya existe un token activo para @{$instagram}");
            } else {
                // 2. Obtener el descuento seleccionado (si hay)
                $selectedDiscount = null;
                if ($discount_id) {
                    $stmt = $pdo->prepare("SELECT id, code, description, type, value FROM discounts WHERE id = ? AND is_active = 1 LIMIT 1");
                    $stmt->execute([$discount_id]);
                    $selectedDiscount = $stmt->fetch();
                }
                
                // 3. Generar credenciales temporales
                $token = bin2hex(random_bytes(32));
                $temp_username = $instagram;
                $temp_password = $instagram . '_' . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $temp_password_hash = password_hash($temp_password, PASSWORD_BCRYPT);
                
                // Expiración: 1 año
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));
                
                // 4. Insertar token con discount_id
                $stmt = $pdo->prepare("
                    INSERT INTO activation_tokens
                    (token, instagram_username, temp_username, temp_password_hash, expires_at, discount_id, created_at)
                    VALUES
                    (:token, :instagram, :temp_username, :hash, :expires, :discount_id, NOW())
                ");
                $stmt->execute([
                    'token'       => $token,
                    'instagram'   => $instagram,
                    'temp_username' => $temp_username,
                    'hash'        => $temp_password_hash,
                    'expires'     => $expires_at,
                    'discount_id' => $discount_id
                ]);
                
                $token_generado = [
                    'token'        => $token,
                    'instagram'    => $instagram,
                    'temp_username' => $temp_username,
                    'temp_password' => $temp_password,
                    'expires'      => $expires_at,
                    'discount'     => $selectedDiscount
                ];
                
                $message = show_message('success', '✅ Token de activación creado correctamente');
            }
        } catch (Exception $e) {
            $message = show_message('error', '❌ Error al crear token: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Token - DCIEN</title>
    <link rel="stylesheet" href="/admin-descargas/assets/style.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div>
                <h1>CREAR TOKEN</h1>
                <p>Generar credencial de activación</p>
            </div>
            <div class="header-actions">
                <a href="/admin-descargas/">← Dashboard</a>
            </div>
        </header>

        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <?php if ($token_generado): ?>
            <!-- Token generado -->
            <div style="background:var(--surface); border:1px solid var(--sent-border); padding:24px; margin-bottom:24px; border-radius:var(--radius); box-shadow:var(--shadow);">
                <h3 style="margin-bottom: 16px; color:var(--sent);">✅ TOKEN DE ACTIVACIÓN CREADO</h3>
                
                <div style="background:var(--surface-2); padding:20px; margin-bottom:20px; border:1px solid var(--border); border-radius:var(--radius);">
                    <p style="margin-bottom: 8px;"><strong>Instagram:</strong> @<?php echo e($token_generado['instagram']); ?></p>
                    <?php if ($token_generado['discount']): ?>
                        <p style="color:var(--sent);">
                            🎁 Recibirá: <?php echo e($token_generado['discount']['description']); ?> 
                            (-<?php echo $token_generado['discount']['value']; ?>%)
                        </p>
                    <?php else: ?>
                        <p style="color: #f59e0b;">
                            ⚠️ ADVERTENCIA: El descuento 'DCIEN10' no está activo. El token se creó pero sin descuento.
                        </p>
                    <?php endif; ?>
                </div>

                <div style="background:var(--bg); padding:20px; border:1px solid var(--border); margin-bottom:20px; border-radius:var(--radius); font-family: 'Courier New', monospace;">
                    <h4 style="color:var(--sent); margin-bottom: 12px;">📩 ENVIAR POR DM:</h4>
                    <div style="background:var(--surface); padding:16px; border-left:3px solid var(--sent); margin-bottom:12px;">
                        <pre style="margin: 0; white-space: pre-wrap; font-size: 13px; line-height: 1.6;">DCIEN no es para todos, pero sí para ti.
Te hemos dado acceso — úsalo bien.

Usuario:
<?php echo e($token_generado['temp_username']); ?>

Contraseña:
<?php echo e($token_generado['temp_password']); ?>

Activa tu cuenta:
https://d-cien.es/registro/activar
<?php if ($token_generado['discount']): ?>

🎁 REGALO DE BIENVENIDA
Al activar tu cuenta recibirás automáticamente:
• <?php echo e($token_generado['discount']['description']); ?>

<?php endif; ?>

⏱️ Válido durante 1 año</pre>
                    </div>
                    <button onclick="copiarMensaje()" class="btn btn-small" style="margin-right: 8px;">📋 Copiar Mensaje</button>
                    <button onclick="this.previousElementSibling.querySelector('pre').select()" class="btn btn-small btn-secondary">📝 Seleccionar</button>
                </div>
                
                <div class="form-group">
                    <label>Detalles Técnicos (para debugging)</label>
                    <details style="background:var(--surface-2); padding:12px; border:1px solid var(--border); border-radius:var(--radius);">
                        <summary style="cursor: pointer; padding: 4px;">Ver datos completos</summary>
                        <div style="margin-top: 12px; font-size: 12px; font-family: monospace;">
                            <p><strong>Token:</strong><br><input type="text" value="<?php echo e($token_generado['token']); ?>" readonly onclick="this.select()" style="width: 100%; margin: 4px 0;"></p>
                            <p><strong>Usuario temporal:</strong> <?php echo e($token_generado['temp_username']); ?></p>
                            <p><strong>Password temporal:</strong> <?php echo e($token_generado['temp_password']); ?></p>
                            <p><strong>Expira:</strong> <?php echo format_date($token_generado['expires']); ?></p>
                        </div>
                    </details>
                </div>
                
                <a href="activacion.php" class="btn">+ Generar Otro Token</a>
            </div>

            <script>
            function copiarMensaje() {
                const texto = document.querySelector('pre').textContent;
                navigator.clipboard.writeText(texto).then(() => {
                    alert('✅ Mensaje copiado al portapapeles');
                }).catch(err => {
                    console.error('Error al copiar:', err);
                    alert('❌ Error al copiar. Usa el botón Seleccionar.');
                });
            }
            </script>
        <?php else: ?>
            <!-- Formulario -->
            <div class="table-container">
                <form method="POST" style="padding: 24px;">
                    <div class="form-group">
                        <label>Nombre de Instagram *</label>
                        <input type="text" name="instagram" required placeholder="usuario_instagram" autofocus>
                        <small style="font-size: 11px; color: #666;">Sin @ inicial, solo el nombre de usuario</small>
                    </div>

                    <div class="form-group" style="margin-top: 16px;">
                        <label>Descuento asociado al token</label>
                        <select name="discount_id" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);color:var(--text);font-size:13px;">
                            <option value="">— Sin descuento —</option>
                            <?php foreach ($descuentos_disponibles as $d): ?>
                                <option value="<?= (int)$d['id'] ?>">
                                    <?= e($d['code']) ?> — <?= e($d['description']) ?>
                                    (<?= $d['type'] === 'percent' ? $d['value'] . '%' : 'EUR ' . number_format($d['value'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="font-size: 11px; color: #666;">El usuario recibirá este descuento automáticamente al activar su cuenta</small>
                    </div>

                    <button type="submit" class="btn" style="margin-top: 20px;">🎟️ Generar Token de Activación</button>
                </form>
            </div>

            <div style="background:var(--surface); border:1px solid var(--border); padding:20px; margin-top:24px; border-radius:var(--radius); box-shadow:var(--shadow);">
                <h4 style="margin-bottom: 12px;">ℹ️ INFORMACIÓN</h4>
                <ul style="font-size: 12px; line-height: 1.8; color: #666; list-style-position: inside;">
                    <li>El sistema generará credenciales temporales automáticamente</li>
                    <li>El token expira en 1 año</li>
                    <li>Selecciona un descuento para que el usuario lo reciba al activar</li>
                    <li>Si no seleccionas descuento, el sistema asignará DCIEN10 por defecto (si está activo)</li>
                    <li>Las credenciales temporales permiten hacer login en /acceso</li>
                    <li>Una vez activada, las credenciales temporales ya no funcionan</li>
                </ul>
            </div>
        <?php endif; ?>

        <footer class="footer">
            <p>DCIEN Crear Token de Activación</p>
        </footer>
    </div>
</body>
</html>
