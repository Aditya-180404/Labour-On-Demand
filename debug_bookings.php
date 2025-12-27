<?php
define('EXECUTION_ALLOWED', true);
require_once 'config/db.php';

$stmt = $pdo->query("SELECT id, work_proof_images, work_done_public_ids FROM bookings WHERE work_proof_images IS NOT NULL AND work_proof_images != ''");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    echo "ID: {$row['id']}\n";
    echo "Images: {$row['work_proof_images']}\n";
    echo "Public IDs: {$row['work_done_public_ids']}\n";
    
    $imgs = explode(',', $row['work_proof_images']);
    foreach ($imgs as $img) {
        $fullPath = __DIR__ . '/uploads/work_proof/' . trim($img);
        echo "Check $img: " . (file_exists($fullPath) ? "EXISTS" : "NOT FOUND") . " at $fullPath\n";
    }
    echo "------------------\n";
}
?>
