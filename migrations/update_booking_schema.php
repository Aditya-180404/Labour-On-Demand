<?php
require_once 'config/db.php';

try {
    $pdo->exec("ALTER TABLE bookings ADD COLUMN completion_time DATETIME DEFAULT NULL");
    echo "Added completion_time to bookings table.<br>";
} catch (PDOException $e) {
    echo "Error (completion_time): " . $e->getMessage() . "<br>";
}

try {
    $pdo->exec("ALTER TABLE bookings ADD COLUMN amount_paid DECIMAL(10,2) DEFAULT NULL");
    echo "Added amount_paid to bookings table.<br>";
} catch (PDOException $e) {
    echo "Error (amount_paid): " . $e->getMessage() . "<br>";
}
?>
