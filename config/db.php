<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

// 1. Environment Detection
$server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
$is_local = in_array($server_name, ['localhost', '127.0.0.1', '::1']);

// 2. Credential Selection (Prioritize .env, then local/live defaults)
if ($is_local) {
    // Local Defaults
    $host     = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
    $db_name  = defined('DB_NAME') ? DB_NAME : 'labour_on_demand';
    $username = defined('DB_USER') ? DB_USER : 'root';
    $password = defined('DB_PASS') ? DB_PASS : ''; 
} else {
    // Live Credentials (ONLY use constants if they don't look like local settings)
    $env_host = defined('DB_HOST') ? DB_HOST : '';
    $env_user = defined('DB_USER') ? DB_USER : '';
    $env_db   = defined('DB_NAME') ? DB_NAME : '';
    
    // Fallback to specific LIVE_ constants if they exist
    $host = (defined('LIVE_DB_HOST')) ? LIVE_DB_HOST : (($env_host !== '' && $env_host !== '127.0.0.1' && $env_host !== 'localhost') ? $env_host : 'sql100.infinityfree.com');
    $username = (defined('LIVE_DB_USER')) ? LIVE_DB_USER : (($env_user !== '' && $env_user !== 'root') ? $env_user : 'if0_40768493');
    $db_name = (defined('LIVE_DB_NAME')) ? LIVE_DB_NAME : (($env_db !== '' && strpos($env_db, 'if0_') === 0) ? $env_db : 'if0_40768493_labour_on_demand');
    
    // Choose Password: Never fallback to empty/local password on live
    if (defined('LIVE_DB_PASS')) {
        $password = LIVE_DB_PASS;
    } elseif (defined('DB_PASS') && DB_PASS !== '' && DB_PASS !== 'root') {
        $password = DB_PASS;
    } else {
        $password = 'AdityaRoy12345';
    }
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch(PDOException $e) {
    // Secret debug parameter for developers (?debug_db=1)
    $debug_requested = (isset($_GET['debug_db']) && $_GET['debug_db'] === '1');
    
    if ($is_local || $debug_requested) {
        // Detailed error for local or authorized debug
        die("<h3>Database Connection Error</h3>
             <p>Check your configuration in .env or config/db.php</p>
             <pre>Error: " . $e->getMessage() . "\nHost: $host\nUser: $username\nDB: $db_name</pre>");
    } else {
        // Generic error for production
        die("<div style='text-align:center; padding-top:100px; font-family:sans-serif; color: #334155;'>
                <h2>Service Temporarily Unavailable</h2>
                <p>We are experiencing a temporary technical issue. Please try again later.</p>
                <code style='color: #94a3b8;'>Error Reference: PROD_DB_CONNECT_FAIL</code>
             </div>");
    }
}

// BASE_URL is now deprecated in favor of $path_prefix in individual files for better portability.
?>

