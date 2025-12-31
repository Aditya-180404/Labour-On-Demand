<?php
/**
 * Labour On Demand - Centralized Security Entry Point
 * 
 * This file is included at the top of every entry-point PHP file.
 * It manages modular security layers: sessions, headers, CSRF, CAPTCHA, and rate limiting.
 */

// 1. Define Execution Allowed Global Constant
if (!defined('EXECUTION_ALLOWED')) {
    define('EXECUTION_ALLOWED', true);
}

// Load Environment Variables immediately
$env_loader = __DIR__ . '/env_loader.php';
if (file_exists($env_loader)) {
    include_once $env_loader;
}

// 2. Start Secure Session
if (session_status() === PHP_SESSION_NONE) {
    // Prevent JavaScript access to session cookie
    ini_set('session.cookie_httponly', 1);
    // Force cookies only (no URL based sessions)
    ini_set('session.use_only_cookies', 1);
// Enforce SameSite cookie attribute for better CSRF protection
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params(['samesite' => 'Lax']);
}
    
    // Secure cookie for HTTPS
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if ($is_https) {
        ini_set('session.cookie_secure', 1);
    }
    
    session_start();
}

// 3. Include Security Config and Database
global $pdo;
require_once __DIR__ . '/../config/security_config.php';

if (!isset($pdo)) {
    // Moved from config/ to includes/, so db.php is in ../config/
    require_once __DIR__ . '/../config/db.php';
}

// 2.1 Session Idle Timeout
$timeout_duration = defined('SESSION_IDLE_TIMEOUT') ? SESSION_IDLE_TIMEOUT : 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
    // Regenerate session ID to prevent fixation
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
    $_SESSION['session_expired'] = true;
}
$_SESSION['last_activity'] = time();

// 4. Environment-Aware Error Reporting
$is_local_env = (isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1'));
// Remote debug override (Secret parameter to see errors on production)
if (isset($_GET['debug_security']) && $_GET['debug_security'] === '1') {
    // Allow debug only from whitelisted IPs
    $allowed = defined('ALLOWED_DEBUG_IPS') ? ALLOWED_DEBUG_IPS : [];
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (in_array($client_ip, $allowed)) {
        $is_local_env = true;
    }
}

if ($is_local_env) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// 5. Load Security Modules (Now in security/ subfolder)
require_once __DIR__ . '/security/rate_limiter.php'; // Contains get_client_ip()
require_once __DIR__ . '/security/logger.php';       // Contains logSecurityIncident()
require_once __DIR__ . '/security/firewall.php';     // Dependencies loaded
require_once __DIR__ . '/security/headers.php';
require_once __DIR__ . '/security/csrf.php';
require_once __DIR__ . '/security/captcha.php';
require_once __DIR__ . '/security/honeypot.php';
require_once __DIR__ . '/security/validator.php';
require_once __DIR__ . '/security/upload_scanner.php';

// 6. Apply Global Throttling
applyRateLimiting($pdo);

// 7. JavaScript Browser Challenge (Anti-Scraping / Anti-Mirroring)
// Skip for POST requests, AJAX, and already verified sessions
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
$is_post = ($_SERVER['REQUEST_METHOD'] === 'POST');
$is_media = preg_match('/\.(jpg|jpeg|png|gif|webp|css|js|ico|svg|pdf)$/i', $_SERVER['REQUEST_URI']);

if (!$is_media && !isset($_SESSION['js_verified'])) {
    // Handle solve attempt
    if ($is_post && isset($_POST['js_challenge_solved']) && isset($_POST['challenge_token'])) {
        if (isset($_SESSION['js_verify_token']) && hash_equals($_SESSION['js_verify_token'], $_POST['challenge_token'])) {
            $_SESSION['js_verified'] = true;
            unset($_SESSION['js_verify_token']);
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
    
    // Serve challenge for normal GET requests to PHP pages
    if (!$is_post && !$is_ajax) {
        $allowed_exceptions = ['js_challenge.php', 'api/']; // Add exceptions if needed
        $current_script = basename($_SERVER['PHP_SELF']);
        
        if (!in_array($current_script, $allowed_exceptions)) {
            include_once __DIR__ . '/security/js_challenge.php';
            exit;
        }
    }
}

// Global file upload validation
if (!empty($_FILES)) {
    foreach ($_FILES as $file) {
        if (!isUploadSafe($file)) {
            http_response_code(400);
            exit('Invalid file upload detected.');
        }
    }
}

/**
 * Portable Base URL Helper
 */
if (!function_exists('getBaseUrl')) {
    function getBaseUrl() {
        $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $is_https ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        
        $current_script = str_replace('\\', '/', __FILE__);
        $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
        // Updated path for detection
        $project_root_abs = str_replace('/includes/security.php', '', $current_script);
        $base_path = str_replace($doc_root, '', $project_root_abs);
        $base_path = '/' . trim($base_path, '/') . '/';
        $base_path = str_replace('//', '/', $base_path);
        
        return $protocol . $host . $base_path;
    }
}
?>
