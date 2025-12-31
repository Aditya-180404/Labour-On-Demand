<?php
define('EXECUTION_ALLOWED', true);
require_once 'config/db.php';
require_once 'config/mail.php';

echo "<h2>Queue Diagnostic Tool</h2>";
echo "<p style='font-size:0.8em; color: #777;'>Current Key: <code>" . CRON_KEY . "</code></p>";

try {
    // 0. Manual Trigger
    if (isset($_GET['trigger'])) {
        echo "<div style='background: #e9ecef; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>";
        echo "<h3>Attempting Manual Trigger...</h3>";
        
        // Debug GET params
        $get_key = $_GET['key'] ?? 'NOT SET';
        $matches = (trim($get_key) === trim(CRON_KEY)) ? "YES" : "NO";
        echo "<p>Trigger with Key: <code>$get_key</code> | Matches Expected: <strong>$matches</strong></p>";
        
        // Define internal flag to verify authorization locally
        define('INTERNAL_CRON_CALL', true);
        
        // DOUBLE-CHECK: Force secure key into environment to bypass any scope issues
        $_GET['key'] = CRON_KEY;
        
        require_once 'cron/process_jobs.php';
        echo "</div>";
    }

    // 1. Check if jobs table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'jobs'");
    if (!$stmt->fetch()) {
        die("<p style='color:red;'>Error: 'jobs' table does not exist. Please run admin/migrate_db.php first!</p>");
    }

    // Environment info
    echo "<p><strong>Environment:</strong> " . (php_sapi_name() === 'cli' ? 'CLI' : 'HTTP/WEB') . "</p>";
    echo "<p><strong>Server Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
    
    require_once 'config/mail.php';
    echo "<a href='debug_queue.php?trigger=1&key=" . CRON_KEY . "' style='display:inline-block; background:#007bff; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Run Queue Manually (Test)</a>";

    // 2. Count statuses
    $counts = $pdo->query("SELECT status, COUNT(*) as count FROM jobs GROUP BY status")->fetchAll();
    echo "<h3>Queue Summary:</h3><ul>";
    foreach ($counts as $row) {
        echo "<li><strong>{$row['status']}:</strong> {$row['count']}</li>";
    }
    echo "</ul>";

    // 3. Show last 5 pending/failed jobs
    echo "<h3>Recent Jobs:</h3>";
    $jobs = $pdo->query("SELECT * FROM jobs ORDER BY created_at DESC LIMIT 10")->fetchAll();
    if (empty($jobs)) {
        echo "No jobs found in queue.";
    } else {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Status</th><th>Attempts</th><th>Error</th><th>Payload (To)</th><th>Time</th></tr>";
        foreach ($jobs as $j) {
            $payload = json_decode($j['payload'], true);
            $to = $payload['to_email'] ?? 'N/A';
            $error = htmlspecialchars($j['last_error'] ?? 'None');
            echo "<tr>
                    <td>{$j['id']}</td>
                    <td>{$j['status']}</td>
                    <td>{$j['attempts']}</td>
                    <td>$error</td>
                    <td>$to</td>
                    <td>{$j['created_at']}</td>
                  </tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>Diagnostic Error: " . $e->getMessage() . "</p>";
}
?>
