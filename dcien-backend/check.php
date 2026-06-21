<?php
require 'config/database.php';
$pdo = getDatabaseConnection();
$stmt = $pdo->query("
    SELECT 
        b.code AS codigo_qr, 
        b.nombre AS nombre_bono, 
        b.campaign AS campana, 
        b.discount_value AS valor_prometido_qr, 
        d.id AS discount_id, 
        d.code AS codigo_descuento_real, 
        d.type AS tipo_descuento_real, 
        d.value AS valor_descuento_real 
    FROM qr_bonos b 
    LEFT JOIN discounts d ON d.code = CONCAT('BONO_', b.code) 
    WHERE b.nombre LIKE '%Ibi%' OR b.campaign LIKE '%Ibi%' OR b.descripcion LIKE '%Ibi%'
");
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($result)) {
    echo "NO_SE_ENCONTRARON_BONOS_IBI";
} else {
    print_r($result);
}
