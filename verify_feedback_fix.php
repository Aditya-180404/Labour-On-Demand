<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
define('EXECUTION_ALLOWED', true);
$_SERVER['SERVER_NAME'] = 'localhost';
require_once 'config/db.php';

echo "--- FEEDBACK LOGIC VERIFICATION ---\n";

// 1. Verify Trimming and Search Logic
$test_queries = [
    "  " => "Should be empty after trim",
    "nonexistent" => "Should return 0 results",
    "@" => "Should return email matches"
];

foreach ($test_queries as $q => $desc) {
    $trimmed_q = trim($q);
    echo "\nTesting query: '$q' ($desc)\n";
    echo "Trimmed: '$trimmed_q'\n";
    
    if (!empty($trimmed_q)) {
        echo "Running search query...\n";
        $sql = "SELECT f.* FROM feedbacks f WHERE f.name LIKE ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(["%$trimmed_q%"]);
    } else {
        echo "Running refresh query...\n";
        $sql = "SELECT f.* FROM feedbacks f LIMIT 1";
        $stmt = $pdo->query($sql);
    }
    
    $results = $stmt->fetchAll();
    echo "Results found: " . count($results) . "\n";
    if (count($results) > 0) {
        $first = $results[0];
        echo "First result Name: " . $first['name'] . " | UserUID: " . ($first['user_uid'] ?? 'N/A') . " | WorkerUID: " . ($first['worker_uid'] ?? 'N/A') . "\n";
    }
}

// 2. Verify Refresh Logic (Join consistency)
echo "\nTesting Refresh Logic (Joins)...\n";
$stmt = $pdo->query("
    SELECT f.*, u.user_uid, w.worker_uid 
    FROM feedbacks f 
    LEFT JOIN users u ON f.user_id = u.id 
    LEFT JOIN workers w ON f.worker_id = w.id 
    ORDER BY f.created_at DESC
");
$results = $stmt->fetchAll();
$missing_uids = false;
foreach ($results as $fb) {
    if (($fb['sender_role'] == 'user' && empty($fb['user_uid'])) || ($fb['sender_role'] == 'worker' && empty($fb['worker_uid']))) {
        echo "WARNING: Potential UID inconsistency for ID " . $fb['id'] . " (Role: " . $fb['sender_role'] . ")\n";
        $missing_uids = true;
    }
}

if (!$missing_uids) {
    echo "Refresh logic verified: Joins work correctly.\n";
} else {
    echo "Refresh logic FAILED: Some UIDs are missing despite roles.\n";
}

echo "\n--- VERIFICATION COMPLETE ---\n";
?>
