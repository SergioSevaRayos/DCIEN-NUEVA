<?php
require __DIR__ . '/../admin-descargas/modules/config.php';
$pdo = get_db_connection();

// Buscar tablas que referencien users.id
$stmt = $pdo->query("
    SELECT table_name, column_name
    FROM information_schema.key_column_usage
    WHERE referenced_table_name = 'users'
      AND referenced_column_name = 'id'
      AND table_schema = DATABASE()
");
$relaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($relaciones);

$stmt2 = $pdo->query("SELECT id FROM users WHERE email = 'ssrspaingroup@gmail.com'");
$user = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "\nUsuario:\n";
print_r($user);
