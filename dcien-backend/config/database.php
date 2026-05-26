<?php
/**
 * Configuración de Base de Datos
 */

// Cargar .env si existe el archivo
if (file_exists(dirname(__DIR__) . '/.env')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createUnsafeImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

function getDatabaseConnection()
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host     = getenv('DB_HOST')    ?: 'localhost';
    $dbname   = getenv('DB_NAME')    ?: 'u755459505_limited_tees';
    $username = getenv('DB_USER')    ?: 'root';
    // DB_PASS puede ser cadena vacía (local sin contraseña): no usar ?: para evitar caer al fallback
    $password = getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '';
    $charset  = getenv('DB_CHARSET') ?: 'utf8mb4';

    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset COLLATE utf8mb4_unicode_ci"
        ]);

        return $pdo;

    } catch (PDOException $e) {
        error_log('Database connection error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error de conexión con la base de datos']);
        exit;
    }
}

function query($sql, $params = [])
{
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function queryOne($sql, $params = [])
{
    return query($sql, $params)->fetch();
}

function queryAll($sql, $params = [])
{
    return query($sql, $params)->fetchAll();
}