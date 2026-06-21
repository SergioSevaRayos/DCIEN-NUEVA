<?php
require 'config/database.php';
$pdo = getDatabaseConnection();

// Encontrar tokens relacionados con bonos de Ibi
$stmt = $pdo->query("
    SELECT t.id, t.temp_username, t.discount_id, b.nombre AS bono_name, b.id AS qr_bono_id, d.id AS correct_discount_id
    FROM activation_tokens t
    JOIN qr_bonos b ON t.temp_username = CONCAT('BONO_', b.code)
    JOIN discounts d ON d.code = CONCAT('BONO_', b.code)
    WHERE b.nombre LIKE '%Ibi%' OR b.campaign LIKE '%Ibi%'
");
$tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($tokens);
