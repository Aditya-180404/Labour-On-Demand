<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

/**
 * Honeypot Bot Protection
 * 
 * This module provides a simple but effective way to detect bots.
 * Bots often fill every field in a form, including hidden ones.
 */

/**
 * Check if a bot is detected via honeypot or rapid submission.
 */
if (!function_exists('isBotDetected')) {
    function isBotDetected($field_name = null) {
    // Use configured field name if not provided
    if ($field_name === null) {
        $field_name = defined('HONEYPOT_FIELD_NAME') ? HONEYPOT_FIELD_NAME : 'middle_name';
    }
        global $pdo;
        
        // 1. Check Standard Honeypot Field
        $bot_found = false;
        $reason = "";
        
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (!empty($_POST[$field_name])) {
                $bot_found = true;
                $reason = "Honeypot field '$field_name' filled.";
            }
            
            // 2. Add 'website' check (Common bot trap)
            if (!empty($_POST['website'])) {
                $bot_found = true;
                $reason = "Honeypot field 'website' filled.";
            }

            // 3. Form Submission Speed Check (Minimum 3 seconds)
            $min_time = 3; 
            if (isset($_SESSION['form_time'])) {
                if (time() - $_SESSION['form_time'] < $min_time) {
                    $bot_found = true;
                    $reason = "Form submitted too fast (" . (time() - $_SESSION['form_time']) . "s).";
                }
            }
        }

        if ($bot_found) {
            $ip = get_client_ip();
            
            // Increment Violations
            if (!isset($_SESSION['violations'])) $_SESSION['violations'] = 0;
            $_SESSION['violations']++;

            // A. Log to specific honeypot log (DISABLED for Scalability)
            // $log_file = __DIR__ . '/../../logs/honeypot.log';
            // if (!is_dir(dirname($log_file))) mkdir(dirname($log_file), 0755, true);
            // file_put_contents($log_file, date('Y-m-d H:i:s') . " | IP: $ip | Reason: $reason" . PHP_EOL, FILE_APPEND);

            // B. Add to blacklist.txt (DISABLED for Scalability - Database Blocking Only)
            // $blacklist_file = __DIR__ . '/blacklist.txt';
            // file_put_contents($blacklist_file, $ip . PHP_EOL, FILE_APPEND);

            // C. Log to main security incident table
            logSecurityIncident('honeypot', 'high', $reason);
            
            // D. Immediate Block
            $duration = defined('HONEYPOT_BLOCK_DURATION') ? HONEYPOT_BLOCK_DURATION : 86400;
            if (function_exists('blockIP')) {
                blockIP($ip, "Honeypot Triggered: $reason", $duration);
            } elseif (isset($pdo)) {
                // Fallback (should not happen if firewall is loaded)
                $blocked_until = time() + $duration;
                $pdo->prepare("INSERT INTO rate_limits (ip_address, first_request_at, request_count, blocked_until) 
                               VALUES (?, ?, 1, ?) 
                               ON DUPLICATE KEY UPDATE blocked_until = ?, request_count = request_count + 1")
                    ->execute([$ip, time(), $blocked_until, $blocked_until]);
            }
            
            http_response_code(403);
            exit("Bot detected: Your activity has been logged.");
        }
        
        return false;
    }
}

/**
 * Render the honeypot HTML fields.
 */
if (!function_exists('renderHoneypot')) {
    function renderHoneypot($field_name = null) {
        if ($field_name === null) {
            $field_name = defined('HONEYPOT_FIELD_NAME') ? HONEYPOT_FIELD_NAME : 'middle_name';
        }
        // Set form load time in session
        $_SESSION['form_time'] = time();

        echo '<!-- Honeypot Fields -->';
        echo '<div style="display:none; visibility:hidden; overflow:hidden; width:0; height:0;">';
        echo '<label for="' . $field_name . '">Middle Name</label>';
        echo '<input type="text" name="' . $field_name . '" id="' . $field_name . '" tabindex="-1" autocomplete="off">';
        
        echo '<label for="website">Website URL (leave blank)</label>';
        echo '<input type="url" name="website" id="website" tabindex="-1" autocomplete="off">';
        echo '</div>';
    }
}
?>
