<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

// Robust environment detection
$server_name = $_SERVER['SERVER_NAME'];
$is_local = ($server_name == 'localhost' || $server_name == '127.0.0.1');

if ($is_local) {
    // ---------------------------------------------------------
    // Local (XAMPP) Configuration
    // ---------------------------------------------------------
    $host = 'localhost';
    $db_name = 'labour_on_demand';
    $username = 'root';
    $password = '';
} else {
    // ---------------------------------------------------------
    // Live Configuration (InfinityFree / Hostinger)
    // ---------------------------------------------------------
    // NOTE: When moving to Hostinger, simply update these 4 values:
    $host = 'sql100.infinityfree.com';
    $db_name = 'if0_40768493_labour_on_demand';
    $username = 'if0_40768493';
    $password = 'AdityaRoy12345';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    if ($is_local) {
        die("Connection failed: " . $e->getMessage());
    } else {
        // Silent fail for production: No technical details leaked to users
        die("<div style='text-align:center; padding-top:100px; font-family:sans-serif;'>
                <h2>Service Temporarily Unavailable</h2>
                <p>We are experiencing database connectivity issues. Please try again in a few minutes.</p>
                <small>Error Reference: PROD_DB_CONNECT_FAIL</small>
             </div>");
    }
}

// BASE_URL is now deprecated in favor of $path_prefix in individual files for better portability.
?>

