<?php
require 'config/database.php';
$pdo = getDatabaseConnection();
print_r($pdo->query('SHOW COLUMNS FROM user_discounts')->fetchAll(PDO::FETCH_ASSOC));
