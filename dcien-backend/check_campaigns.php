<?php
require 'config/database.php';
$pdo = getDatabaseConnection();

$stmt = $pdo->query("
    SELECT 
        COALESCE(NULLIF(campaign, ''), 'Sin Campaña') as campaign_name,
        COUNT(id) as total_bonos,
        SUM(is_active) as activos,
        (SELECT COUNT(*) FROM qr_bonos_usos u JOIN qr_bonos b2 ON u.bono_id = b2.id WHERE COALESCE(NULLIF(b2.campaign, ''), 'Sin Campaña') = COALESCE(NULLIF(qr_bonos.campaign, ''), 'Sin Campaña')) as total_scans
    FROM qr_bonos
    GROUP BY COALESCE(NULLIF(campaign, ''), 'Sin Campaña')
    ORDER BY total_bonos DESC
");

print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
