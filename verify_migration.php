<?php
define('EXECUTION_ALLOWED', true);
require_once 'config/db.php';

echo "--- Verification Report ---\n";

$users_total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$users_migrated = $pdo->query("SELECT COUNT(*) FROM users WHERE profile_image_public_id IS NOT NULL OR profile_image = 'default.png'")->fetchColumn();

$workers_total = $pdo->query("SELECT COUNT(*) FROM workers")->fetchColumn();
$workers_migrated = $pdo->query("SELECT COUNT(*) FROM workers WHERE profile_image_public_id IS NOT NULL OR profile_image = 'default.png'")->fetchColumn();

$bookings_total = $pdo->query("SELECT COUNT(*) FROM bookings WHERE work_proof_images IS NOT NULL AND work_proof_images != ''")->fetchColumn();
$bookings_migrated = $pdo->query("SELECT COUNT(*) FROM bookings WHERE (work_done_public_ids IS NOT NULL OR work_proof_images LIKE 'https://res.cloudinary.com/%')")->fetchColumn();

echo "Users: $users_migrated / $users_total migrated.\n";
echo "Workers: $workers_migrated / $workers_total migrated.\n";
echo "Bookings (with proof): $bookings_migrated / $bookings_total migrated.\n";

if ($users_total == $users_migrated && $workers_total == $workers_migrated && $bookings_total == $bookings_migrated) {
    echo "\nAll assets migrated successfully!\n";
} else {
    echo "\nSome assets are still remaining to be migrated.\n";
}
?>
