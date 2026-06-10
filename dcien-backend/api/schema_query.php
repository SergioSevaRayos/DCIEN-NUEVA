<?php
require __DIR__ . '/../admin-descargas/modules/config.php';
$pdo = get_db_connection();
$stmt = $pdo->query("DESCRIBE orders");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($results);
