<?php
define('EXECUTION_ALLOWED', true);
require_once 'config/db.php';

try {
    // Add columns to bookings table
    $sql = "ALTER TABLE bookings 
            ADD COLUMN IF NOT EXISTS work_proof_images TEXT DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS completion_otp VARCHAR(6) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS completion_otp_expires_at DATETIME DEFAULT NULL";
    
    $pdo->exec($sql);
    echo "Database schema updated successfully.";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>
