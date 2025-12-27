<?php
// 1. Define Execution Allowed Global Constant
// This allows included files (like db.php, footer.php) to verify they are being run from a valid entry point.
if (!defined('EXECUTION_ALLOWED')) {
    define('EXECUTION_ALLOWED', true);
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
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
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
?>
