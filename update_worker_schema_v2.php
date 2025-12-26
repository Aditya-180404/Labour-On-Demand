<?php
require_once 'config/db.php';

try {
    // Add signature_photo column
    $pdo->exec("ALTER TABLE workers ADD COLUMN signature_photo VARCHAR(255) DEFAULT NULL");
    echo "Added signature_photo column successfully.<br>";

    // Add previous_work_images column
    $pdo->exec("ALTER TABLE workers ADD COLUMN previous_work_images TEXT DEFAULT NULL");
    echo "Added previous_work_images column successfully.<br>";

} catch (PDOException $e) {
    echo "Error updating workers table: " . $e->getMessage() . "<br>";
}
?>
