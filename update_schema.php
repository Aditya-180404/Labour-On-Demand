<?php
require_once 'config/db.php';

try {
    // Modify Users Table
    $pdo->exec("ALTER TABLE users ADD COLUMN pin_code VARCHAR(10) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN location TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN address_details TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN otp VARCHAR(6) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN otp_expires_at DATETIME DEFAULT NULL");
    echo "Users table updated successfully.<br>";
} catch (PDOException $e) {
    echo "Error updating users table: " . $e->getMessage() . "<br>";
}

try {
    // Modify Workers Table
    $pdo->exec("ALTER TABLE workers ADD COLUMN adhar_card VARCHAR(20) DEFAULT NULL");
    $pdo->exec("ALTER TABLE workers ADD COLUMN address TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE workers ADD COLUMN pin_code VARCHAR(10) DEFAULT NULL");
    $pdo->exec("ALTER TABLE workers ADD COLUMN working_location TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE workers ADD COLUMN otp VARCHAR(6) DEFAULT NULL");
    $pdo->exec("ALTER TABLE workers ADD COLUMN otp_expires_at DATETIME DEFAULT NULL");
    echo "Workers table updated successfully.<br>";
} catch (PDOException $e) {
    echo "Error updating workers table: " . $e->getMessage() . "<br>";
}
?>
