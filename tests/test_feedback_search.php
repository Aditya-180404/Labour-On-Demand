<?php
define('EXECUTION_ALLOWED', true);
$_SERVER['SERVER_NAME'] = 'localhost';
require_once 'config/db.php';

$feedback_q = "XYZ123NONEXISTENT"; // Should match nothing

$feedbacks_stmt = $pdo->prepare("
    SELECT f.*, u.user_uid, w.worker_uid 
    FROM feedbacks f 
    LEFT JOIN users u ON f.user_id = u.id 
    LEFT JOIN workers w ON f.worker_id = w.id
    WHERE f.name LIKE ? OR f.email LIKE ? OR f.subject LIKE ? OR f.message LIKE ? OR u.user_uid LIKE ? OR w.worker_uid LIKE ?
    ORDER BY f.created_at DESC
");
$feedbacks_stmt->execute(["%$feedback_q%", "%$feedback_q%", "%$feedback_q%", "%$feedback_q%", "%$feedback_q%", "%$feedback_q%"]);
$feedbacks = $feedbacks_stmt->fetchAll();

echo "Search query: " . $feedback_q . "\n";
echo "Results found: " . count($feedbacks) . "\n";

foreach ($feedbacks as $fb) {
    echo "ID: " . $fb['id'] . " | Name: " . $fb['name'] . " | UserUID: " . ($fb['user_uid'] ?? 'NULL') . " | WorkerUID: " . ($fb['worker_uid'] ?? 'NULL') . "\n";
}

// Test with empty search
$feedback_q = "";
$all_stmt = $pdo->query("
    SELECT f.*, u.user_uid, w.worker_uid 
    FROM feedbacks f 
    LEFT JOIN users u ON f.user_id = u.id 
    LEFT JOIN workers w ON f.worker_id = w.id 
    ORDER BY f.created_at DESC
");
$all_feedbacks = $all_stmt->fetchAll();
echo "\nTotal feedbacks (no search): " . count($all_feedbacks) . "\n";
?>
