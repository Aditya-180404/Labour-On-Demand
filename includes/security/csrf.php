<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

/**
 * CSRF Protection Logic
 */

// Generate CSRF Token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * CSRF Verification Helper
 * 
 * @param string $token The token to verify
 * @return bool
 */
if (!function_exists('verifyCSRF')) {
    function verifyCSRF($token) {
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            logSecurityIncident('csrf_fail', 'medium', 'Missing token or session.');
            return false;
        }
        $valid = hash_equals($_SESSION['csrf_token'], $token);
        if (!$valid) {
            logSecurityIncident('csrf_fail', 'high', 'Invalid token mismatch.');
        }
        return $valid;
    }
}

if (!function_exists('generateCSRF')) {
    function generateCSRF() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
if (!function_exists('csrf_input')) {
    function csrf_input() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRF()) . '">';
    }
}

if (!function_exists('rotateCSRF')) {
    /**
     * Regenerates the CSRF token to prevent token reuse after successful actions.
     */
    function rotateCSRF() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}
?>
