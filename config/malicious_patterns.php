<?php
/**
 * Centralised regex patterns for firewall detection.
 * The array is loaded by includes/security/firewall.php.
 */
$malicious_patterns = [
    // SQL Injection (Comprehensive)
    '/\b(union\s+select|information_schema|sleep\s*\(|benchmark\s*\(|group_concat|waitfor\s+delay|db_name\s*\(|sysdatabases|into\s+outfile|load_file\s*\()/i',
    // XSS / HTML / Template Injection
    '/(<script\b|onerror\s*=|onmouseover\s*=|javascript\s*:|<iframe\b|<object\b|\{\{.*?\}\}|<%\s*=|data:text\/html)/i',
    // PHP Code & Execution
    '/\b(eval\s*\(|system\s*\(|passthru\s*\(|base64_decode\s*\(|include\s*\(|require\s*\(|phpinfo\s*\(|gzuncompress\s*\(|shell_exec\s*\(|popen\s*\()/i',
    // Python / RCE patterns
    '/\b(import\s+os|subprocess\.|__import__\s*\(|exec\s*\(|getattr\s*\(|os\s*\.\s*system)/i',
    // Path Traversal & Sensitive Files
    '/(\.\.\/|\.\.\\|\/etc\/passwd|win\.ini|\/proc\/self|boot\.ini|window\.location|document\.cookie)/i',
    // Shell & Network attacks
    '/(\/bin\/bash|chmod\s+\d+|curl\s+-s|wget\s+|nc\s+-e|bash\s+-i|ping\s+-c)/i',
];
?>
