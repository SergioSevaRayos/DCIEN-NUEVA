<?php
require 'config/database.php';
$pdo = getDatabaseConnection();

$stmt = $pdo->query("
    UPDATE activation_tokens t
    JOIN qr_bonos b ON t.temp_username = CONCAT('BONO_', b.code)
    JOIN discounts d ON d.code = CONCAT('BONO_', b.code)
    SET t.discount_id = d.id
    WHERE (b.nombre LIKE '%Ibi%' OR b.campaign LIKE '%Ibi%') AND t.discount_id IS NULL
");

echo "Filas actualizadas: " . $stmt->rowCount() . "\n";
