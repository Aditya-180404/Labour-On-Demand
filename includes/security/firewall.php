<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

/**
 * Advanced PHP Firewall
 * 
 * Handles:
 * - User-Agent Blocking
 * - IP Blacklisting
 * - Request Method restriction
 * - Basic SQL Injection pattern detection
 */

// 1. Block suspicious user agents
$blocked_agents = [
    'curl', 'wget', 'python', 'sqlmap', 'nikto', 'nmap', 'httrack', 
    'kyptec', 'libwww', 'scrapy', 'go-http-client', 'php/', 'ia_archiver',
    'facebot', 'facebookexternalhit', 'twitterbot'
];
$user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

foreach ($blocked_agents as $agent) {
    if (strpos($user_agent, $agent) !== false) {
        logSecurityIncident('firewall_agent', 'medium', "Blocked User-Agent: $agent");
        http_response_code(403);
        exit('Forbidden: Access denied for automated tools.');
    }
}

// 2a. IP Blacklist (Database - The "Brain" Check)
// Check if IP is currently blocked in the database
global $pdo;
if (isset($pdo)) {
    $current_ip = get_client_ip();
    $stmt = $pdo->prepare("SELECT blocked_until FROM rate_limits WHERE ip_address = ? AND blocked_until > ?");
    $stmt->execute([$current_ip, time()]);
    if ($stmt->fetch()) {
        // Log is skipped here to avoid flooding logs with blocked attempts
        http_response_code(403);
        exit('<h1>Access Denied</h1><p>Your IP has been temporarily blocked due to suspicious activity.</p>');
    }
}

// 2b. IP Blacklist (File-based + Static - The "Hard" Check)
$blacklist_file = __DIR__ . '/blacklist.txt';
$blocked_ips = [
    '192.168.1.100',
    '203.0.113.45'
];

if (file_exists($blacklist_file)) {
    $file_blocked = file($blacklist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $blocked_ips = array_merge($blocked_ips, $file_blocked);
}

$client_ip = get_client_ip();
if (in_array($client_ip, $blocked_ips)) {
    logSecurityIncident('firewall_ip', 'high', "Blocked IP attempt: $client_ip");
    http_response_code(403);
    exit('Access denied: Your IP is blacklisted.');
}

// 3. Violation Threshold Tracking
if (!isset($_SESSION['violations'])) {
    $_SESSION['violations'] = 0;
}

if ($_SESSION['violations'] >= 2) {
    logSecurityIncident('violation_limit', 'high', "IP blocked due to multiple violations.");
    // Auto-ban for 5 minutes
    blockIP($client_ip, "Multiple Session Violations", 300);
    http_response_code(429);
    exit('Too many requests. Access restricted.');
}

// 4. Block dangerous request methods
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    logSecurityIncident('firewall_method', 'medium', "Method not allowed: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    exit('Method not allowed: Only GET and POST are supported.');
}

/**
 * Global IP Blocking Function
 */
if (!function_exists('blockIP')) {
    function blockIP($ip, $reason = 'Security Violation', $duration_seconds = 900) {
        global $pdo;
        if (!$pdo) return;

        $blocked_until = time() + $duration_seconds;
        
        try {
            $stmt = $pdo->prepare("SELECT id FROM rate_limits WHERE ip_address = ?");
            $stmt->execute([$ip]);
            $exists = $stmt->fetch();

            if ($exists) {
                $update = $pdo->prepare("UPDATE rate_limits SET blocked_until = ?, request_count = 0 WHERE ip_address = ?");
                $update->execute([$blocked_until, $ip]);
            } else {
                $insert = $pdo->prepare("INSERT INTO rate_limits (ip_address, blocked_until, request_count, first_request_at) VALUES (?, ?, 0, ?)");
                $insert->execute([$ip, $blocked_until, time()]);
            }
            
            if (function_exists('logSecurityIncident')) {
                logSecurityIncident('ip_ban', 'critical', "Banned IP: $ip for $duration_seconds seconds. Reason: $reason");
            }
        } catch (Exception $e) {}
    }
}

/**
 * Advanced Canonicalization for Encoded Attacks
 */
function canonicalizeRequest(string $data): string {
    $data = str_replace("\0", '', $data); // Remove null bytes
    
    // Recursive URL decode (max 3 levels)
    for ($i = 0; $i < 3; $i++) {
        $decoded = rawurldecode($data);
        if ($decoded === $data) break;
        $data = $decoded;
    }

    // HTML entity decoding
    $data = html_entity_decode($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Decode Unicode escapes (\uXXXX)
    $data = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UCS-2BE');
        }
        // Fallback for environments without mbstring (basic ASCII check)
        return hexdec($m[1]) < 128 ? chr(hexdec($m[1])) : $m[0];
    }, $data);

    // Normalize whitespace
    $data = preg_replace('/\s+/u', ' ', $data);
    return trim($data);
}

/**
 * Multi-Language Malicious Pattern Detection
 */
function detectMaliciousInput(string $input): bool {
    // Load centralized malicious patterns
    require_once __DIR__ . '/../../config/malicious_patterns.php';
    global $malicious_patterns;
    $patterns = $malicious_patterns;

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) return true;
    }
    return false;
}

// 5. Advanced Encoded Attack Detection
$current_page = basename($_SERVER['SCRIPT_NAME']);
$high_risk_pages = [
    'login.php', 'register.php', 'mfa_verify.php', 'forgot_password.php', 
    'edit_profile.php', 'upload_portfolio.php', 'my_jobs.php', 
    'process_feedback.php', 'admin_login.php'
];

if (defined('SQLI_STRICT_MODE') && SQLI_STRICT_MODE) {
    // A) Scan Request Data (GET, POST, COOKIE)
    $raw_payload = json_encode($_REQUEST);
    $clean_payload = canonicalizeRequest($raw_payload);

    // B) Scan File Uploads (Filenames)
    $file_payload = "";
    if (!empty($_FILES)) {
        foreach ($_FILES as $file) {
            if (isset($file['name'])) {
                if (is_array($file['name'])) {
                    $file_payload .= implode(" ", $file['name']);
                } else {
                    $file_payload .= " " . $file['name'];
                }
            }
        }
    }

    if (detectMaliciousInput($raw_payload) || detectMaliciousInput($clean_payload) || detectMaliciousInput($file_payload)) {
        logSecurityIncident('firewall_encoded', 'critical', "Malicious pattern detected. Page: $current_page. Content: " . substr($clean_payload . $file_payload, 0, 150));
        
        if (in_array($current_page, $high_risk_pages)) {
            blockIP($client_ip, "Automated Exploit Attempt on $current_page", 3600);
        }

        http_response_code(403);
        die('<!DOCTYPE html>
        <html>
        <head><title>Security Warning</title><style>body{font-family:sans-serif;padding:50px;line-height:1.6;color:#333;background:#f8f9fa;} .container{max-width:600px;margin:auto;background:#fff;padding:30px;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,0.1);border-left:5px solid #dc3545;} h2{color:#dc3545;margin-top:0;} .btn{display:inline-block;padding:10px 20px;background:#007bff;color:#fff;text-decoration:none;border-radius:5px;margin-top:20px;}</style></head>
        <body>
            <div class="container">
                <h2>Security Warning</h2>
                <p>Code-like content (PHP, Python, or Script) has been detected in your form submission.</p>
                <p>For security reasons, please do not include code snippets in text fields. If you were trying to share a document, please use the correct file upload field and ensure the file type is allowed.</p>
                <a href="javascript:history.back()" class="btn">Go Back</a>
            </div>
        </body>
        </html>');
    }
}
?>
