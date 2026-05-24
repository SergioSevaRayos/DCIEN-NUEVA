<?php

$backend_root = realpath(__DIR__);

// Cargar Composer autoload
$autoload = $backend_root . '/vendor/autoload.php';

if (!file_exists($autoload)) {
    die('Autoload no encontrado en: ' . $autoload);
}

require_once $autoload;

// Cargar .env usando modo UNSAFE para habilitar getenv()
$envFile = $backend_root . '/.env';

if (!file_exists($envFile)) {
    die('.env no encontrado en: ' . $envFile);
}

$dotenv = Dotenv\Dotenv::createUnsafeImmutable($backend_root);
$dotenv->load();
