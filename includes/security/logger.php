<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

/**
 * Security Incident Logger
 * 
 * Centralized logging for security events (Honeypot, Rate Limit, etc.)
 */

if (!function_exists('logSecurityIncident')) {
    /**
     * Log a security incident to the database
     * 
     * @param string $type The type of incident (e.g., 'honeypot')
     * @param string $severity Severity level (low, medium, high, critical)
     * @param string $details Additional contextual information
     */
    function logSecurityIncident($type, $severity = 'low', $details = '') {
        global $pdo;
        
        $ip = get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // 1. Database Logging
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO security_incidents (ip_address, incident_type, severity, details, user_agent) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$ip, $type, $severity, $details, $user_agent]);
            } catch (PDOException $e) {
                // Fail silently or log to error_log
                error_log("Security Logger DB Error: " . $e->getMessage());
            }
        }
        
        // 2. JSON Fallback Logging (DISABLED for Scalability)
        // At 10M users, persistent file writes cause I/O locks. 
        // We rely 100% on the database (security_incidents table).
        
        /* 
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $ip,
            'type' => $type,
            'severity' => $severity,
            'details' => $details,
            'user_agent' => $user_agent
        ];
        // ... (File write logic removed)
        */
    }
}
?>
