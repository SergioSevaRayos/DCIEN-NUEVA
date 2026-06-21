<?php
require 'config/database.php';
$pdo = getDatabaseConnection();

echo "Tables:\n";
print_r($pdo->query("SHOW TABLES LIKE '%discount%'")->fetchAll(PDO::FETCH_ASSOC));

echo "Columns of user_discounts:\n";
print_r($pdo->query("SHOW COLUMNS FROM user_discounts")->fetchAll(PDO::FETCH_ASSOC));

echo "Check if there's any user_discounts for DCIEN10:\n";
print_r($pdo->query("SELECT * FROM user_discounts WHERE discount_id = 1")->fetchAll(PDO::FETCH_ASSOC));
