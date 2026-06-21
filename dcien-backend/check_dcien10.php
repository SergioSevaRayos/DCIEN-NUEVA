<?php
require 'config/database.php';
$pdo = getDatabaseConnection();
print_r($pdo->query("SELECT * FROM discounts WHERE code = 'DCIEN10'")->fetch(PDO::FETCH_ASSOC));
