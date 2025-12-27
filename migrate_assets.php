<?php
define('EXECUTION_ALLOWED', true);
require_once 'config/db.php';
require_once 'includes/cloudinary_helper.php';

$cld = CloudinaryHelper::getInstance();

function migrateFile($localPath, $folder, $type = 'standard') {
    global $cld;
    if (empty($localPath) || $localPath == 'default.png') return null;
    
    $fullPath = __DIR__ . '/' . $localPath;
    if (!file_exists($fullPath)) {
        echo "File not found: $fullPath\n";
        return null;
    }

    echo "Migrating $localPath to $folder...\n";
    $upload = $cld->uploadImage($fullPath, $folder, $type);
    if ($upload) {
        return $upload;
    }
    return null;
}

echo "--- Starting Migration ---\n";

// 1. Users Profile Images
$stmt = $pdo->query("SELECT id, profile_image FROM users WHERE profile_image_public_id IS NULL AND profile_image != 'default.png'");
while ($user = $stmt->fetch()) {
    $upload = migrateFile('uploads/users/' . $user['profile_image'], CLD_FOLDER_USERS);
    if ($upload) {
        $pdo->prepare("UPDATE users SET profile_image = ?, profile_image_public_id = ? WHERE id = ?")
            ->execute([$upload['url'], $upload['public_id'], $user['id']]);
    }
}

// 2. Workers Profile & Documents
$stmt = $pdo->query("SELECT id, profile_image, aadhar_photo, pan_photo, signature_photo, previous_work_images FROM workers");
while ($worker = $stmt->fetch()) {
    $wid = $worker['id'];
    
    // Profile
    if ($worker['profile_image'] && $worker['profile_image'] != 'default.png' && !filter_var($worker['profile_image'], FILTER_VALIDATE_URL)) {
        $up = migrateFile('uploads/workers/' . $worker['profile_image'], CLD_FOLDER_WORKER_PROFILE, 'high-res');
        if ($up) {
            $pdo->prepare("UPDATE workers SET profile_image = ?, profile_image_public_id = ? WHERE id = ?")
                ->execute([$up['url'], $up['public_id'], $wid]);
        }
    }

    // Aadhar
    if ($worker['aadhar_photo'] && !filter_var($worker['aadhar_photo'], FILTER_VALIDATE_URL)) {
        $up = migrateFile('uploads/documents/' . $worker['aadhar_photo'], CLD_FOLDER_WORKER_DOCS . 'aadhar/', 'high-res');
        if ($up) {
            $pdo->prepare("UPDATE workers SET aadhar_photo = ?, aadhar_photo_public_id = ? WHERE id = ?")
                ->execute([$up['url'], $up['public_id'], $wid]);
        }
    }

    // PAN
    if ($worker['pan_photo'] && !filter_var($worker['pan_photo'], FILTER_VALIDATE_URL)) {
        $up = migrateFile('uploads/documents/' . $worker['pan_photo'], CLD_FOLDER_WORKER_DOCS . 'pan/', 'high-res');
        if ($up) {
            $pdo->prepare("UPDATE workers SET pan_photo = ?, pan_photo_public_id = ? WHERE id = ?")
                ->execute([$up['url'], $up['public_id'], $wid]);
        }
    }

    // Signature
    if ($worker['signature_photo'] && !filter_var($worker['signature_photo'], FILTER_VALIDATE_URL)) {
        $up = migrateFile('uploads/documents/' . $worker['signature_photo'], CLD_FOLDER_WORKER_DOCS . 'signature/', 'high-res');
        if ($up) {
            $pdo->prepare("UPDATE workers SET signature_photo = ?, signature_photo_public_id = ? WHERE id = ?")
                ->execute([$up['url'], $up['public_id'], $wid]);
        }
    }

    // Previous Work (Gallery)
    if ($worker['previous_work_images'] && !strpos($worker['previous_work_images'], 'cloudinary.com')) {
        $imgs = array_filter(explode(',', $worker['previous_work_images']));
        $newUrls = [];
        $newIds = [];
        foreach ($imgs as $img) {
            $up = migrateFile('uploads/work_images/' . trim($img), CLD_FOLDER_WORKER_PREV_WORK, 'high-res');
            if ($up) {
                $newUrls[] = $up['url'];
                $newIds[] = $up['public_id'];
            }
        }
        if (!empty($newUrls)) {
            $pdo->prepare("UPDATE workers SET previous_work_images = ?, previous_work_public_ids = ? WHERE id = ?")
                ->execute([implode(',', $newUrls), implode(',', $newIds), $wid]);
        }
    }
}

// 3. Bookings Work Done Proofs
$stmt = $pdo->query("SELECT id, work_proof_images FROM bookings WHERE work_proof_images IS NOT NULL AND work_proof_images != '' AND work_done_public_ids IS NULL");
while ($booking = $stmt->fetch()) {
    $imgs = array_filter(explode(',', $booking['work_proof_images']));
    $newUrls = [];
    $newIds = [];
    foreach ($imgs as $img) {
        $up = migrateFile('uploads/work_proof/' . trim($img), CLD_FOLDER_WORKER_WORK_DONE, 'standard');
        if ($up) {
            $newUrls[] = $up['url'];
            $newIds[] = $up['public_id'];
        }
    }
    if (!empty($newUrls)) {
        $pdo->prepare("UPDATE bookings SET work_proof_images = ?, work_done_public_ids = ? WHERE id = ?")
            ->execute([implode(',', $newUrls), implode(',', $newIds), $booking['id']]);
    }
}

echo "--- Migration Completed ---\n";
?>
