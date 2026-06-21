<?php
require 'config/database.php';
$pdo = getDatabaseConnection();

$stmt = $pdo->query("SELECT id, username, email, activated_with_token FROM users WHERE email = 'xavi710rb@gmail.com'");
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "Usuario encontrado:\n";
    print_r($user);
    
    // Check token details
    if ($user['activated_with_token']) {
        $stmt2 = $pdo->prepare("SELECT token, discount_id FROM activation_tokens WHERE token = ?");
        $stmt2->execute([$user['activated_with_token']]);
        $token = $stmt2->fetch(PDO::FETCH_ASSOC);
        echo "\nToken usado:\n";
        print_r($token);
        
        if ($token['discount_id']) {
            $stmt3 = $pdo->prepare("SELECT code, type, value FROM discounts WHERE id = ?");
            $stmt3->execute([$token['discount_id']]);
            $discount = $stmt3->fetch(PDO::FETCH_ASSOC);
            echo "\nDescuento del token:\n";
            print_r($discount);
        }
    }
} else {
    echo "No se encontro el usuario xavi710rb@gmail.com";
}
