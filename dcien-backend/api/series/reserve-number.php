<?php
/**
 * API: Reservar un número temporalmente (5 minutos)
 * POST /api/series/reserve-number.php
 * Body: {"series_slug": "serie-0", "unit_number": 42}
 */

$backend_root = dirname(dirname(__DIR__));

require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/cors.php';
require_once $backend_root . '/includes/session.php';
require_once $backend_root . '/includes/helpers.php';

startSecureSession();

// Requiere autenticación
requireAuth();

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$input = getJsonInput();
validateRequired($input, ['series_slug', 'unit_number']);

$series_slug = sanitizeInput($input['series_slug']);
$unit_number = (int)$input['unit_number'];
$user_id = getUserId();

// Validar número en rango válido
if ($unit_number < 1 || $unit_number > 100) {
    jsonError('Número debe estar entre 1 y 100', 400);
}

try {
    $pdo = getDatabaseConnection();

    // Verificar acceso prioritario antes de cualquier operación
    $priority = queryOne(
        "SELECT priority_until FROM series_priority WHERE series_slug = :slug AND priority_until > NOW()",
        ['slug' => $series_slug]
    );
    if ($priority) {
        $hasAccess = queryOne(
            "SELECT id FROM series_priority_users WHERE series_slug = :slug AND user_id = :uid",
            ['slug' => $series_slug, 'uid' => $user_id]
        );
        if (!$hasAccess) {
            jsonError('Esta serie está en periodo de acceso prioritario. Aún no está disponible para ti.', 403);
        }
    }

    // Iniciar transacción para atomicidad
    $pdo->beginTransaction();
    
    // 1. LIMPIEZA PROACTIVA: Liberar números de cualquier usuario cuya reserva haya expirado (> 5 min)
    // Esto asegura que si el EVENT de MySQL falla, la API libere los números al usarlos.
    query(
        "UPDATE series_units 
         SET status = 'available', 
             reserved_at = NULL, 
             reserved_by = NULL,
             order_id = NULL
         WHERE status = 'reserved' 
           AND reserved_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
        []
    );
    
    // 2. LIBERAR PREVIA: Liberar cualquier reserva que este usuario tuviera ANTES en esta serie
    query(
        "UPDATE series_units 
         SET status = 'available', 
             reserved_at = NULL, 
             reserved_by = NULL,
             order_id = NULL
         WHERE series_slug = :slug 
           AND reserved_by = :user_id 
           AND status = 'reserved'",
        [
            'slug' => $series_slug,
            'user_id' => $user_id
        ]
    );
    
    // 3. INTENTAR RESERVA: Solo si el número está 'available'
    // El uso de UPDATE con WHERE status='available' es la forma más segura de evitar condiciones de carrera (Race Conditions)
    $result = query(
      "UPDATE series_units
       SET status='reserved',
           reserved_at=NOW(),
           reserved_by=:uid
       WHERE series_slug=:slug
         AND unit_number=:num
         AND status='available'",
      [
        'slug' => $series_slug,
        'num'  => $unit_number,
        'uid'  => $user_id
      ]
    );

    
    // Verificar si se cambió alguna fila
    if ($result->rowCount() === 0) {
        $pdo->rollBack();
        // Intentar dar un mensaje más específico verificando si es que ya está vendido
        $check = queryOne("SELECT status FROM series_units WHERE series_slug = :slug AND unit_number = :num", ['slug' => $series_slug, 'num' => $unit_number]);
        $msg = ($check && $check['status'] === 'sold') ? 'Este número ya ha sido vendido.' : 'Este número ya está reservado por otro usuario.';
        jsonError($msg, 409);
    }
    
    // 4. PERSISTENCIA EN SESIÓN
    $_SESSION['reserved_number'] = $unit_number;
    $_SESSION['reserved_series'] = $series_slug;
    $_SESSION['reserved_at'] = time();
    
    $pdo->commit();
    
    jsonSuccess('Número reservado con éxito', [
        'number' => $unit_number,
        'expires_in' => 300 // 5 minutos
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logError('Error reserving number', [
        'error' => $e->getMessage(),
        'slug' => $series_slug,
        'number' => $unit_number
    ]);
    jsonError('Error del servidor: ' . $e->getMessage(), 500);
}