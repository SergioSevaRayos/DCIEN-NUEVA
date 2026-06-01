<?php
/**
 * API: Completar registro definitivo o Recuperación de cuenta
 * ACTUALIZADO: Asigna descuento de bienvenida automáticamente
 */

$backend_root = dirname(dirname(__DIR__));

require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';

startSecureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

// Validar que existe un token en sesión (sea de activación o de reset)
if (empty($_SESSION['activation_token'])) {
    jsonError('Sesión inválida o expirada', 403);
}

$input = getJsonInput();
$email    = sanitizeInput($input['email'] ?? '');
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';
$token    = $_SESSION['activation_token'];

// Flags de estado
$isRecovery = !empty($_SESSION['is_recovery']);
$userIdToUpdate = $_SESSION['temp_user_id'] ?? null;

/* ───── 1. Validaciones de Formato ───── */

if (!validateEmail($email)) {
    jsonError('Email inválido', 400);
}

if (!preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $username)) {
    jsonError('Username inválido (3-30 caracteres, sin espacios)', 400);
}

if (strlen($password) < 8) {
    jsonError('La contraseña debe tener al menos 8 caracteres', 400);
}

try {
    /* ───── 2. Comprobaciones de Disponibilidad (Username/Email) ───── */
    
    // Si es recuperación, permitimos que el email/user sea el mismo del usuario actual, 
    // pero no uno que pertenezca a OTRO usuario.
    $excludeClause = $isRecovery ? " AND id != " . (int)$userIdToUpdate : "";

    if (queryOne("SELECT id FROM users WHERE username = BINARY :u $excludeClause LIMIT 1", ['u' => $username])) {
        jsonError('El nombre de usuario ya está en uso', 409);
    }

    if (queryOne("SELECT id FROM users WHERE email = :e $excludeClause LIMIT 1", ['e' => $email])) {
        jsonError('Este email ya está registrado por otro usuario', 409);
    }

    /* ───── 3. Ejecución del Proceso (Update o Insert) ───── */
    
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $instagramUsername = $_SESSION['instagram_username'] ?? 'Usuario';

    if ($isRecovery) {
        // CASO RECUPERACIÓN: Actualizar usuario existente
        $userExists = queryOne(
            "SELECT id FROM users WHERE id = :id AND reset_token = :token AND reset_expires_at > NOW()",
            ['id' => $userIdToUpdate, 'token' => $token]
        );

        if (!$userExists) jsonError('El enlace de recuperación ha expirado', 403);

        query(
            "UPDATE users SET 
                username = :username, 
                email = :email, 
                password_hash = :hash, 
                reset_token = NULL, 
                reset_expires_at = NULL,
                updated_at = NOW() 
             WHERE id = :id",
            ['username' => $username, 'email' => $email, 'hash' => $hash, 'id' => $userIdToUpdate]
        );
        $userId = $userIdToUpdate;
        $message = 'Credenciales actualizadas correctamente';
        
    } else {
        // CASO REGISTRO: Crear nuevo usuario
        $tokenData = queryOne(
            "SELECT instagram_username, discount_id FROM activation_tokens WHERE token = :token AND used_at IS NULL AND expires_at > NOW() LIMIT 1",
            ['token' => $token]
        );

        if (!$tokenData) jsonError('El token de activación es inválido o ya se usó', 403);

        query(
            "INSERT INTO users (username, email, password_hash, instagram_username, is_verified, can_purchase, activated_with_token, activation_date, created_at, updated_at)
             VALUES (:username, :email, :hash, :instagram, 1, 1, :token, NOW(), NOW(), NOW())",
            [
                'username'  => $username, 
                'email'     => $email, 
                'hash'      => $hash, 
                'instagram' => $tokenData['instagram_username'], 
                'token'     => $token
            ]
        );
        
        $userId = getDatabaseConnection()->lastInsertId();
        
        // ═══════════════════════════════════════════════════════════════
        // ASIGNAR DESCUENTO: del token, bono QR, o bienvenida DCIEN10
        // ═══════════════════════════════════════════════════════════════
        try {
            $discountToAssign = null;

            // Prioridad 1: descuento específico vinculado al token de activación
            if (!empty($tokenData['discount_id'])) {
                $discountToAssign = queryOne(
                    "SELECT id FROM discounts WHERE id = :id AND is_active = 1 LIMIT 1",
                    ['id' => $tokenData['discount_id']]
                );
            }

            // Prioridad 2: bono QR (si viene de un escaneo QR)
            if (!$discountToAssign && !empty($_SESSION['qr_bono'])) {
                $bonoCode = 'BONO_' . $_SESSION['qr_bono']['code'];
                $discountToAssign = queryOne(
                    "SELECT id FROM discounts WHERE code = :code AND is_active = 1 LIMIT 1",
                    ['code' => $bonoCode]
                );
                unset($_SESSION['qr_bono']);
            }

            // Prioridad 3: fallback a DCIEN10 (retrocompatibilidad)
            if (!$discountToAssign) {
                $discountToAssign = queryOne(
                    "SELECT id FROM discounts WHERE code = 'DCIEN10' AND is_active = 1 LIMIT 1"
                );
            }

            
            if ($discountToAssign) {
                query(
                    "INSERT INTO user_discounts 
                     (user_id, discount_id, assigned_at)
                     VALUES (:user_id, :discount_id, NOW())",
                    [
                        'user_id'     => $userId,
                        'discount_id' => $discountToAssign['id']
                    ]
                );
                
                logError('DISCOUNT_ASSIGNED', [
                    'user_id'    => $userId,
                    'username'   => $username,
                    'discount_id'=> $discountToAssign['id']
                ]);
            } else {
                logError('WARNING: No discount found to assign', [
                    'user_id'  => $userId,
                    'username' => $username
                ]);
            }
        } catch (Exception $discountError) {
            // No fallar el registro si el descuento no se puede asignar
            logError('ERROR: Could not assign welcome discount', [
                'user_id' => $userId,
                'username' => $username,
                'error' => $discountError->getMessage()
            ]);
        }
        // ═══════════════════════════════════════════════════════════════
        
        query("UPDATE activation_tokens SET used_at = NOW() WHERE token = :token", ['token' => $token]);

        // Incrementar used_count del bono QR si viene de uno
        if (!empty($_SESSION['qr_bono'])) {
            $bono_code = $_SESSION['qr_bono']['code'];
            query("UPDATE qr_bonos SET used_count = used_count + 1 WHERE code = :code", ['code' => $bono_code]);
        }
        $message = 'Cuenta creada correctamente';
    }

    /* ───── 4. Envío de Email (Común para ambos) ───── */
    
    $mailerPath = $backend_root . '/includes/mailer.php';
    if (file_exists($mailerPath)) {
        try {
            require_once $mailerPath;
            $template = $isRecovery ? 'password_reset' : 'welcome';

            $htmlContent = getEmailTemplate($template, [
                'username' => $username,
                'email'    => $email,
            ]);

            sendEmail([
                'to'      => $email,
                'subject' => $isRecovery ? 'Contraseña actualizada - DCIEN' : 'Bienvenido a DCIEN',
                'html'    => $htmlContent
            ]);
        } catch (Throwable $e) {
            logError('MAIL ERROR', ['error' => $e->getMessage()]);
        }
    }

    /* ───── 5. Finalización ───── */

    // Limpiar todas las variables de sesión del proceso
    unset(
        $_SESSION['activation_token'], 
        $_SESSION['instagram_username'], 
        $_SESSION['is_recovery'], 
        $_SESSION['temp_user_id']
    );

    jsonSuccess($message, [
        'id' => $userId, 
        'username' => $username,
        'requires_login' => true
    ]);

} catch (Throwable $e) {
    logError('PROCESS ERROR', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    jsonError('Error interno del servidor', 500);
}