<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');


$host = 'localhost';
$db_name = 'labour_on_demand';
$username = 'root';
$password = ''; // Default XAMPP password is empty

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Define Base URL
// Robust local detection
$server_name = $_SERVER['SERVER_NAME'];
if ($server_name == 'localhost' || $server_name == '127.0.0.1') {
    define('BASE_URL', '/laubour');
} else {
    // For live server (InfinityFree), if it's in the root, leave empty
    define('BASE_URL', ''); 
}
// Remote configuration removed to prevent accidental overwrite.
?>
