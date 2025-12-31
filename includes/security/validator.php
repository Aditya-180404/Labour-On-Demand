<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

/**
 * Global Input Validator Class
 */
class Validator {
    /**
     * Clean and trim input
     */
    public static function clean($data) {
        return trim($data);
    }

    /**
     * Validate Email
     */
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Validate Phone (10 digits)
     */
    public static function phone($phone) {
        return preg_match('/^\d{10}$/', $phone);
    }

    /**
     * Validate PIN Code (6 digits)
     */
    public static function pinCode($pin) {
        return preg_match('/^\d{6}$/', $pin);
    }

    /**
     * Validate Password Strength
     * Min 8 chars, 1 Uppercase, 1 Lowercase, 1 Number, 1 Special
     */
    public static function passwordStrength($password) {
        $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
        return preg_match($regex, $password);
    }

    /**
     * Validate Integer
     */
    public static function int($value) {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validate Float
     */
    public static function float($value) {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }

    /**
     * Check if a file upload is potentially malicious
     */
    public static function isMaliciousFile($file) {
        if (!isset($file['name'])) return false;

        $name = is_array($file['name']) ? implode(" ", $file['name']) : $file['name'];
        
        // 1. Check for dangerous extensions
        $dangerous_exts = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'py', 'js', 'html', 'htm', 'exe', 'sh', 'bat'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, $dangerous_exts)) return true;

        // 2. Check for double extensions like file.php.jpg
        if (preg_match('/\.(php|py|js|html|sh|bat)\./i', $name)) return true;

        // 3. Delegate to firewall pattern detection if available
        if (function_exists('detectMaliciousInput')) {
            if (detectMaliciousInput($name)) return true;
        }

        return false;
    }
}
?>
