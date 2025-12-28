<?php
// 1. Define Execution Allowed Global Constant
// This allows included files (like db.php, footer.php) to verify they are being run from a valid entry point.
if (!defined('EXECUTION_ALLOWED')) {
    define('EXECUTION_ALLOWED', true);
}

// 0. Anti-DDoS Rate Limiting
require_once __DIR__ . '/rate_limiter.php';

// 1.5 Environment-Aware Error Reporting
$is_local_env = (isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1'));
if ($is_local_env) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// 2. Security Headers
// Prevent Clickjacking
header("X-Frame-Options: SAMEORIGIN");
// Protect against XSS
header("X-XSS-Protection: 1; mode=block");
// Prevent MIME-type sniffing
header("X-Content-Type-Options: nosniff");
// Referrer Policy
header("Referrer-Policy: strict-origin-when-cross-origin");

// 3. Secure Session Configuration
// Prevent JavaScript access to session cookie
ini_set('session.cookie_httponly', 1);
// Force cookies only (no URL based sessions)
ini_set('session.use_only_cookies', 1);
// Secure cookie for HTTPS
$is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

if ($is_https) {
    ini_set('session.cookie_secure', 1);
}

// 4. Start Session
// Centralized session start ensures consistent configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 5. CSRF Protection
// Generate CSRF Token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CSRF Verification Helper
if (!function_exists('verifyCSRF')) {
    function verifyCSRF($token) {
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            return false;
        }
        return true;
    }
}

// 6. Login Rate Limiting
if (!function_exists('checkLoginRateLimit')) {
    function checkLoginRateLimit() {
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['last_attempt_time'] = time();
        }

        // Reset if 15 minutes passed
        if (time() - $_SESSION['last_attempt_time'] > 900) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['last_attempt_time'] = time();
        }

        if ($_SESSION['login_attempts'] >= 5) {
            return false;
        }

        return true;
    }
}

if (!function_exists('incrementLoginAttempts')) {
    function incrementLoginAttempts() {
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
        }
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt_time'] = time();
    }
}

/**
 * Portable Base URL Helper
 * Returns the correct root URL regardless of protocol (HTTP/HTTPS) or subfolder.
 */
if (!function_exists('getBaseUrl')) {
    function getBaseUrl() {
        $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $is_https ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        
        // Dynamic base path detection
        // We know security.php is always in {ROOT}/config/security.php
        $current_script = str_replace('\\', '/', __FILE__);
        $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
        
        // Find the project root by stripping '/config/security.php' from the current file path
        $project_root_abs = str_replace('/config/security.php', '', $current_script);
        
        // The base URL path is the difference between project root and document root
        $base_path = str_replace($doc_root, '', $project_root_abs);
        $base_path = '/' . trim($base_path, '/') . '/';
        $base_path = str_replace('//', '/', $base_path);
        
        $base_url = $protocol . $host . $base_path;
        return $base_url;
    }
}
?>
