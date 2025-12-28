<?php
if (!defined('EXECUTION_ALLOWED')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| Database Configuration (Railway Compatible)
|--------------------------------------------------------------------------
| Uses environment variables defined in Railway
*/

$host     = getenv('DB_HOST') ?: getenv('MYSQLHOST');
$db_name  = getenv('DB_NAME') ?: getenv('MYSQLDATABASE');
$username = getenv('DB_USER') ?: getenv('MYSQLUSER');
$password = getenv('DB_PASS') ?: getenv('MYSQLPASSWORD');
$port     = getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: 3306;

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    exit('Database connection error.');
}
