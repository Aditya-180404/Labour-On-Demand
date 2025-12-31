<?php
/**
 * Security Configuration Parameters
 */

// 1. Honeypot Config
define('HONEYPOT_BLOCK_DURATION', 86400); // 24 hours in seconds

// 2. SQL Injection detection strictness
define('SQLI_STRICT_MODE', true);

// 3. MFA Config
define('MFA_ENABLED', true); // Global toggle for MFA

// 4. Rate Limit Config
define('LOGIN_ATTEMPTS_LIMIT', 5);
define('LOGIN_BLOCK_DURATION', 900); // 15 minutes

// 5. Session Config
define('SESSION_IDLE_TIMEOUT', 1800); // 30 minutes
define('OTP_EXPIRY_SECONDS', 120); // 2 minutes

// 6. Secrets
define('OTP_SECRET_KEY', defined('OTP_SECRET_KEY') ? OTP_SECRET_KEY : 'otp_secret_key'); // REFACTOR: Now loaded via .env if available
// New security constants
define('RECAPTCHA_SECRET', defined('RECAPTCHA_SECRET') ? RECAPTCHA_SECRET : '6LfUczssAAAAAPQLRU7UJMb13pF2iQloXLkQ3K85'); // Production Secret Key
define('ALLOWED_DEBUG_IPS', ['127.0.0.1', '::1']); // IPs allowed to use debug_security flag
// Initialize randomized honeypot field name in session if not set
if (empty($_SESSION['honeypot_field_name'])) {
    $_SESSION['honeypot_field_name'] = 'hp_' . bin2hex(random_bytes(4));
}
define('HONEYPOT_FIELD_NAME', $_SESSION['honeypot_field_name']);
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10 MB max upload size
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']); // Extend as needed
?>
