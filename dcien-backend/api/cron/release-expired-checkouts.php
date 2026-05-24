<?php
/**
 * CRON: Liberar checkouts expirados (5 minutos)
 */

// Estamos en api/cron/. Necesitamos subir dos niveles para llegar a la raíz.
$base_path = dirname(__DIR__, 2); 

// Rutas correctas según tu árbol de archivos
require_once $base_path . '/config/database.php';
require_once $base_path . '/includes/helpers.php';

try {
    // Usamos la función que suele estar en config/database.php
    $pdo = getDatabaseConnection(); 
    
    // Es mejor usar una consulta directa si no tienes la función helper 'query' disponible
    $sql = "UPDATE series_units 
            SET status = 'available', 
                checkout_started_at = NULL, 
                reserved_at = NULL, 
                reserved_by = NULL, 
                order_id = NULL, 
                updated_at = NOW() 
            WHERE status = 'checkout' 
            AND checkout_started_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $released = $stmt->rowCount();

    // Si usas la función logError de includes/helpers.php
    if (function_exists('logError')) {
        logError('CRON: expired checkouts released', ['released_units' => $released]);
    }

    echo "CRON FINALIZADO: $released unidades liberadas.\n";

} catch (Exception $e) {
    echo "ERROR EN CRON: " . $e->getMessage() . "\n";
}