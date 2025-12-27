<?php
define('EXECUTION_ALLOWED', true);
require_once 'config/db.php';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_image_public_id VARCHAR(255) DEFAULT NULL;");
    
    $pdo->exec("ALTER TABLE workers 
        ADD COLUMN IF NOT EXISTS profile_image_public_id VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS aadhar_photo_public_id VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS pan_photo_public_id VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS signature_photo_public_id VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS previous_work_public_ids TEXT DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS pending_profile_image_public_id VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS pending_aadhar_photo_public_id VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS pending_pan_photo_public_id VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS pending_signature_photo_public_id VARCHAR(255) DEFAULT NULL;");
    
    $pdo->exec("ALTER TABLE worker_photo_history ADD COLUMN IF NOT EXISTS photo_public_id VARCHAR(255) DEFAULT NULL;");
    
    $pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS work_done_public_ids TEXT DEFAULT NULL;");

    echo "Database schema updated successfully with Cloudinary Public ID columns.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
