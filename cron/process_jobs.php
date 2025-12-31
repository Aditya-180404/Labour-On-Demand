<?php
// cron/process_jobs.php
define('EXECUTION_ALLOWED', true);
require_once __DIR__ . '/../config/mail.php'; // Load CRON_KEY

// Security check if run via browser/URL (bypass if internal call)
if (php_sapi_name() !== 'cli' && !defined('INTERNAL_CRON_CALL')) {
    $provided_key = trim($_GET['key'] ?? '');
    if (empty($provided_key) || $provided_key !== trim(CRON_KEY)) {
        header("HTTP/1.1 403 Forbidden");
        die("Access Denied: Invalid Cron Key.");
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';

// Prevent timeout for long batches
set_time_limit(60); 

// Max jobs per run (prevent memory leaks)
$batch_size = 20;

// Concurrency Lock
$lockFile = __DIR__ . '/process_jobs.lock';
$fp = fopen($lockFile, 'w+');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    echo "[".date('Y-m-d H:i:s')."] Another instance is already running. Exiting.\n";
    exit;
}

echo "[".date('Y-m-d H:i:s')."] Worker Started.\n";

// 1. Fetch Pending Jobs (Locking could be added here for high-scale, but simplified for now)
$stmt = $pdo->prepare("SELECT * FROM jobs WHERE status = 'pending' AND attempts < 3 ORDER BY created_at ASC LIMIT ?");
$stmt->bindParam(1, $batch_size, PDO::PARAM_INT);
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($jobs) === 0) {
    echo "No pending jobs.\n";
    exit;
}

echo "Found " . count($jobs) . " jobs.\n";

foreach ($jobs as $job) {
    $jobId = $job['id'];
    $payload = json_decode($job['payload'], true);
    
    // Mark as Processing
    $pdo->prepare("UPDATE jobs SET status = 'processing', updated_at = NOW() WHERE id = ?")->execute([$jobId]);
    
    $success = false;
    $errorMsg = '';

    try {
        if ($job['type'] === 'email') {
            // Use the synchronous internal mailer function, but now it runs in background
            $result = sendEmailNow(
                $payload['to_email'], 
                $payload['to_name'], 
                $payload['subject'], 
                $payload['body'], 
                $payload['alt_body'] ?? ''
            );
            
            if ($result['status']) {
                $success = true;
            } else {
                $errorMsg = $result['message'];
            }
        }
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }

    // Update Status
    if ($success) {
        $pdo->prepare("UPDATE jobs SET status = 'completed', updated_at = NOW() WHERE id = ?")->execute([$jobId]);
        echo "Job #$jobId (Email) Completed.\n";
    } else {
        $pdo->prepare("UPDATE jobs SET status = 'pending', attempts = attempts + 1, last_error = ?, updated_at = NOW() WHERE id = ?")->execute([$errorMsg, $jobId]);
        echo "Job #$jobId Failed: $errorMsg\n";
    }
}

echo "[".date('Y-m-d H:i:s')."] Worker Finished.\n";
?>
