<?php
/**
 * Advanced IP Rate Limiter & Anti-DDoS Script
 * Limit: 25 requests per minute
 * Penalty: 5-minute block
 */

// If database isn't connected yet, we need it
global $pdo;
if (!isset($pdo)) {
    require_once __DIR__ . '/db.php';
}

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

$ip = get_client_ip();
if ($ip === 'UNKNOWN') return;

$now = time();
$limit = 25;
$window = 60;
$block_duration = 300;
$sync_threshold = 10; // Sync to DB every 10 hits or 15 seconds
$sync_time_threshold = 15;

// Initialize session trackers if not present
if (!isset($_SESSION['rl_hits'])) {
    $_SESSION['rl_hits'] = 0;
    $_SESSION['rl_last_sync'] = 0;
}

try {
    // 1. ALWAYS check if blocked (SELECT is fast)
    $stmt = $pdo->prepare("SELECT blocked_until, request_count, first_request_at FROM rate_limits WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $rec = $stmt->fetch();

    if ($rec && $rec['blocked_until'] > $now) {
        $wait = $rec['blocked_until'] - $now;
        header('HTTP/1.1 429 Too Many Requests');
        header('Retry-After: ' . $wait);
        header('Cache-Control: no-cache, no-store, must-revalidate'); // Avoid caching the error page
        die("<!DOCTYPE html><html><head><title>Access Blocked</title><meta name='viewport' content='width=device-width, initial-scale=1'><style>body{font-family:sans-serif;text-align:center;padding:20px;background:#f8f9fa;margin-top:10vh;}div{max-width:500px;margin:auto;background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1); border-top: 5px solid #dc3545;}h1{color:#2d3436;font-size:1.5rem;}.btn{display:inline-block;padding:10px 20px;background:#0d6efd;color:white;text-decoration:none;border-radius:20px;margin-top:20px;font-weight:bold;}</style></head><body><div><h1>Security Protocol Enabled</h1><p>You have made too many requests in a short period (Limit: 25/min).</p><p>For the protection of our servers, your IP <b>$ip</b> has been temporarily throttled.</p><p style='color:#dc3545; font-weight:bold;'>Please try again in " . ceil($wait / 60) . " minutes.</p><a href='#' onclick='window.location.reload()' class='btn'>Check Again</a><hr style='border:0;border-top:1px solid #eee;margin:20px 0;'><small style='color:#999;'>Reference Code: ANTI_DDOS_THROTTLE</small></div></body></html>");
    }

    // 2. Increment session hits
    $_SESSION['rl_hits']++;

    // 3. Decide whether to sync with DB
    $should_sync = false;
    if (!isset($_SESSION['rl_verified_ip']) || $_SESSION['rl_verified_ip'] !== $ip) {
        $should_sync = true; // New IP or first run in session
        $_SESSION['rl_verified_ip'] = $ip;
    } elseif ($_SESSION['rl_hits'] >= $sync_threshold) {
        $should_sync = true;
    } elseif ($now - $_SESSION['rl_last_sync'] >= $sync_time_threshold) {
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
                    $blocked_until = $now + $block_duration;
                    $pdo->prepare("UPDATE rate_limits SET blocked_until = ?, request_count = ? WHERE ip_address = ?")->execute([$blocked_until, $new_count, $ip]);
                } else {
                    $pdo->prepare("UPDATE rate_limits SET request_count = ? WHERE ip_address = ?")->execute([$new_count, $ip]);
                }
            }
        } else {
            $pdo->prepare("INSERT INTO rate_limits (ip_address, first_request_at, request_count) VALUES (?, ?, ?)")->execute([$ip, $now, $hits_to_add]);
        }
    }
} catch (PDOException $e) {
    // Fail silently
}
