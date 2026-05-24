<?php
/**
 * Script CLI para generar tokens de activación
 * Compatible con estructura actual de BD (sin metadata)
 * 
 * Uso:
 *   php scripts/create-activation-token.php nombre_instagram
 */

require_once __DIR__ . '/../config/database.php';

if ($argc < 2) {
    echo "Uso: php create-activation-token.php <nombre_instagram>\n";
    exit(1);
}

$instagram_username = trim($argv[1]);

if ($instagram_username === '') {
    echo "❌ El nombre de Instagram no puede estar vacío\n";
    exit(1);
}

// ════════════════════════════════════════════════════════════════
// 1. VERIFICAR TOKEN EXISTENTE
// ════════════════════════════════════════════════════════════════
$existing = queryOne(
    "SELECT id FROM activation_tokens
     WHERE instagram_username = :ig
       AND used_at IS NULL
       AND expires_at > NOW()
     LIMIT 1",
    ['ig' => $instagram_username]
);

if ($existing) {
    echo "⚠️ Ya existe un token activo para @{$instagram_username}\n";
    exit(0);
}

// ════════════════════════════════════════════════════════════════
// 2. VERIFICAR QUE EXISTE EL DESCUENTO DE BIENVENIDA
// ════════════════════════════════════════════════════════════════
$welcomeDiscount = queryOne(
    "SELECT id, code, description, type, value 
     FROM discounts 
     WHERE code = 'DCIEN10' 
       AND is_active = 1
     LIMIT 1"
);

if (!$welcomeDiscount) {
    echo "⚠️ ADVERTENCIA: El descuento de bienvenida 'DCIEN10' no existe o no está activo.\n";
    echo "   El token se creará, pero el descuento NO se asignará automáticamente.\n";
    echo "   Para crear el descuento, ejecuta:\n\n";
    echo "   INSERT INTO discounts (code, description, type, value, applies_to, is_active)\n";
    echo "   VALUES ('DCIEN10', 'Descuento bienvenida 10%', 'percent', 10, 'total', 1);\n\n";
}

// ════════════════════════════════════════════════════════════════
// 3. GENERAR CREDENCIALES TEMPORALES
// ════════════════════════════════════════════════════════════════
$token         = bin2hex(random_bytes(32));
$temp_username = 'temp_' . substr(bin2hex(random_bytes(6)), 0, 8);
$temp_password = generateRandomPassword();
$temp_password_hash = password_hash($temp_password, PASSWORD_BCRYPT);

// Expiración: 72 horas
$expires_at = date('Y-m-d H:i:s', strtotime('+72 hours'));

try {
    // ════════════════════════════════════════════════════════════════
    // 4. INSERTAR TOKEN DE ACTIVACIÓN
    // ════════════════════════════════════════════════════════════════
    query(
        "INSERT INTO activation_tokens
         (token, instagram_username, temp_username, temp_password_hash, expires_at, created_at)
         VALUES
         (:token, :instagram, :temp_username, :hash, :expires, NOW())",
        [
            'token'         => $token,
            'instagram'     => $instagram_username,
            'temp_username' => $temp_username,
            'hash'          => $temp_password_hash,
            'expires'       => $expires_at
        ]
    );

    // ════════════════════════════════════════════════════════════════
    // 5. MOSTRAR INFORMACIÓN
    // ════════════════════════════════════════════════════════════════
    echo "\n✅ TOKEN DE ACTIVACIÓN CREADO\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Instagram: @{$instagram_username}\n";
    
    if ($welcomeDiscount) {
        echo "🎁 Recibirá: {$welcomeDiscount['description']} (-{$welcomeDiscount['value']}%)\n";
    }
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "📩 ENVIAR POR DM:\n\n";
    echo "🎯 ACCESO EXCLUSIVO DCIEN\n\n";
    echo "Usuario temporal: {$temp_username}\n";
    echo "Contraseña temporal: {$temp_password}\n\n";
    echo "Activar cuenta:\n";
    echo "https://d-cien.es/registro/activar\n\n";
    
    if ($welcomeDiscount) {
        echo "🎁 REGALO DE BIENVENIDA\n";
        echo "Al activar tu cuenta recibirás automáticamente:\n";
        echo "• {$welcomeDiscount['description']}\n";
        echo "• Código: {$welcomeDiscount['code']}\n\n";
    }
    
    echo "⏱️ Válido durante 72 horas\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

} catch (Throwable $e) {
    echo "❌ Error al crear token:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

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