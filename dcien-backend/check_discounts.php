<?php
require 'config/database.php';
$pdo = getDatabaseConnection();
print_r($pdo->query("SELECT id, code, description, type, value, is_active FROM discounts WHERE code NOT LIKE 'BONO_%'")->fetchAll(PDO::FETCH_ASSOC));
