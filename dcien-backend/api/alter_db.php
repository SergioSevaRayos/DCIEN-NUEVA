<?php
require __DIR__ . '/../admin-descargas/modules/config.php';
$pdo = get_db_connection();
try {
    $pdo->exec("ALTER TABLE series_units ADD COLUMN notes VARCHAR(255) NULL");
    echo "OK added notes";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
