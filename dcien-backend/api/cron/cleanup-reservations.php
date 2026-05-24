<?php
/**
 * CRON JOB: Limpiar reservas expiradas
 */

// Ruta corregida a la raíz
$backend_root = dirname(dirname(__DIR__));

require_once $backend_root . '/config/database.php';
require_once $backend_root . '/includes/helpers.php';

try {
    // 1. Obtener la conexión explícitamente
    $pdo = getDatabaseConnection(); 
    
    // 2. Ejecutar la limpieza usando PDO directamente (más seguro)
    $sql = "UPDATE series_units 
            SET status = 'available',
                reserved_at = NULL,
                reserved_by = NULL
            WHERE status = 'reserved'
              AND reserved_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $cleaned = $stmt->rowCount();
    
    $log = [
        'success' => true,
        'cleaned' => $cleaned,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($cleaned > 0 && function_exists('logError')) {
        logError('Cron: Cleaned expired reservations', $log);
    }
    
    header('Content-Type: application/json');
    echo json_encode($log);
    
} catch (Exception $e) {
    $error = [
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if (function_exists('logError')) {
        logError('Cron: Error cleaning reservations', $error);
    }
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode($error);
}