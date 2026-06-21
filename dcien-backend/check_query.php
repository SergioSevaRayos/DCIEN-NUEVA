<?php
require 'config/database.php';
$pdo = getDatabaseConnection();

$stmt = $pdo->query("
    SELECT 
        COALESCE(NULLIF(campaign, ''), 'Sin Campaña') as campaign_name,
        COUNT(id) as total_bonos,
        SUM(is_active) as activos,
        SUM((SELECT COUNT(*) FROM qr_bonos_usos u WHERE u.bono_id = qr_bonos.id)) as total_scans
    FROM qr_bonos
    GROUP BY 1
    ORDER BY total_bonos DESC
");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
