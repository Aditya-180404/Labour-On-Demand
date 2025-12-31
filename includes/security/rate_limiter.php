<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

/**
 * Advanced IP Rate Limiter & Anti-DDoS Module
 */

if (!function_exists('get_client_ip')) {
    function get_client_ip() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return explode(',', $ipaddress)[0]; // Handle proxy lists
    }
}

/**
 * Apply Global DDoS Throttling
 */
function applyRateLimiting($pdo) {
    $ip = get_client_ip();
    if ($ip === 'UNKNOWN') return;

    $now = time();
    $limit = 25; // Default limit
    $window = 60;
    $block_duration = 300;
    
    // Distinguish between Page requests (PHP) and potential static hits through PHP
    $is_html = (strpos($_SERVER['REQUEST_URI'], '.php') !== false || substr($_SERVER['REQUEST_URI'], -1) === '/');
    if ($is_html) $limit = 15; // Tighter limit for HTML pages
    
    $sync_threshold = 10;
    $sync_time_threshold = 15;

    // Burst Detection: Max 10 requests in 2 seconds
    if (!isset($_SESSION['last_req_time'])) {
        $_SESSION['last_req_time'] = microtime(true);
        $_SESSION['burst_count'] = 0;
    }
    $curr_time = microtime(true);
    if ($curr_time - $_SESSION['last_req_time'] < 2) {
        $_SESSION['burst_count']++;
        if ($_SESSION['burst_count'] > 12) {
            blockIP($ip, "Burst detected (Anti-Mirroring)", 1800);
            exit("Access denied: Suspicious activity detected.");
        }
    } else {
        $_SESSION['burst_count'] = 1;
        $_SESSION['last_req_time'] = $curr_time;
    }

    // Initialize session trackers
    if (!isset($_SESSION['rl_hits'])) {
        $_SESSION['rl_hits'] = 0;
        $_SESSION['rl_last_sync'] = 0;
    }

    try {
        // 1. Check if blocked
        $stmt = $pdo->prepare("SELECT blocked_until, request_count, first_request_at FROM rate_limits WHERE ip_address = ?");
        $stmt->execute([$ip]);
        $rec = $stmt->fetch();

        if ($rec && $rec['blocked_until'] > $now) {
            $wait = $rec['blocked_until'] - $now;
            header('HTTP/1.1 429 Too Many Requests');
            header('Retry-After: ' . $wait);
            header('X-RateLimit-Limit: ' . $limit);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . $rec['blocked_until']);
            header('Cache-Control: no-cache, no-store, must-revalidate');
            include __DIR__ . '/../../error_templates/429.php';
            exit;
        }

        $_SESSION['rl_hits']++;

        // 2. Decide whether to sync with DB
        $should_sync = false;
        if (!isset($_SESSION['rl_verified_ip']) || $_SESSION['rl_verified_ip'] !== $ip) {
            $should_sync = true;
            $_SESSION['rl_verified_ip'] = $ip;
        } elseif ($_SESSION['rl_hits'] >= $sync_threshold || ($now - $_SESSION['rl_last_sync'] >= $sync_time_threshold)) {
            $should_sync = true;
        }

        if ($should_sync) {
            $hits_to_add = $_SESSION['rl_hits'];
            $_SESSION['rl_hits'] = 0;
            $_SESSION['rl_last_sync'] = $now;

            if ($rec) {
                if ($now - $rec['first_request_at'] > $window) {
                    $pdo->prepare("UPDATE rate_limits SET request_count = ?, first_request_at = ? WHERE ip_address = ?")->execute([$hits_to_add, $now, $ip]);
                } else {
                    $new_count = $rec['request_count'] + $hits_to_add;
                    if ($new_count > $limit) {
                        // REFACTOR: Use central blockIP function
                        if (function_exists('blockIP')) {
                            blockIP($ip, "Rate Limit Exceeded ($new_count requests/min)", $block_duration);
                        } else {
                            // Fallback if firewall not loaded yet (unlikely)
                            $blocked_until = $now + $block_duration;
                            $pdo->prepare("UPDATE rate_limits SET blocked_until = ?, request_count = ? WHERE ip_address = ?")->execute([$blocked_until, $new_count, $ip]);
                        }
                    } else {
                        $pdo->prepare("UPDATE rate_limits SET request_count = ? WHERE ip_address = ?")->execute([$new_count, $ip]);
                    }
                }
            } else {
                $pdo->prepare("INSERT INTO rate_limits (ip_address, first_request_at, request_count) VALUES (?, ?, ?)")->execute([$ip, $now, $hits_to_add]);
            }
        }
    } catch (PDOException $e) {
        // Fail silently to prevent site crash on DB issues
    }
}

/**
 * Login Rate Limiting Logic
 */
if (!function_exists('checkLoginRateLimit')) {
    function checkLoginRateLimit($pdo = null) {
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['last_attempt_time'] = time();
        }

        $lockout_duration = defined('LOGIN_BLOCK_DURATION') ? LOGIN_BLOCK_DURATION : 900;
        // Reset if lockout duration passed
        if (time() - $_SESSION['last_attempt_time'] > $lockout_duration) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['last_attempt_time'] = time();
        }

        // Session-based lockout
        $limit = defined('LOGIN_ATTEMPTS_LIMIT') ? LOGIN_ATTEMPTS_LIMIT : 5;
        if ($_SESSION['login_attempts'] >= $limit) {
            return false;
        }

        // DB-based persistent lockout check
        if ($pdo) {
            $ip = get_client_ip();
            try {
                $stmt = $pdo->prepare("SELECT blocked_until FROM rate_limits WHERE ip_address = ?");
                $stmt->execute([$ip]);
                $row = $stmt->fetch();
                if ($row && $row['blocked_until'] > time()) {
                    return false;
                }
            } catch (Exception $e) { /* Fail safe to session limit */ }
        }

        return true;
    }
}

if (!function_exists('incrementLoginAttempts')) {
    function incrementLoginAttempts($pdo = null) {
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
        }
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt_time'] = time();

        // If threshold reached, trigger persistent block if possible
        $limit = defined('LOGIN_ATTEMPTS_LIMIT') ? LOGIN_ATTEMPTS_LIMIT : 5;
        if ($_SESSION['login_attempts'] >= $limit) {
            $ip = get_client_ip();
            if (function_exists('blockIP')) {
                // Persistent ban
                $lockout_duration = defined('LOGIN_BLOCK_DURATION') ? LOGIN_BLOCK_DURATION : 900;
                blockIP($ip, "Brute force prevention ($limit failed logins)", $lockout_duration);
            }
        }
    }
}
?>
