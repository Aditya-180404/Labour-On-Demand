<?php
require_once 'config/db.php';

echo "<h2>Updating Database Schema for OTP...</h2>";

try {
    // Add OTP columns to users table
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN otp VARCHAR(6) DEFAULT NULL");
        echo "Added 'otp' column to users table.<br>";
    } catch (PDOException $e) {
        echo "Column 'otp' already exists in users or error: " . $e->getMessage() . "<br>";
    }

    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN otp_expires_at DATETIME DEFAULT NULL");
        echo "Added 'otp_expires_at' column to users table.<br>";
    } catch (PDOException $e) {
        echo "Column 'otp_expires_at' already exists in users or error: " . $e->getMessage() . "<br>";
    }

    // Add OTP columns to workers table
    try {
        $pdo->exec("ALTER TABLE workers ADD COLUMN otp VARCHAR(6) DEFAULT NULL");
        echo "Added 'otp' column to workers table.<br>";
    } catch (PDOException $e) {
        echo "Column 'otp' already exists in workers or error: " . $e->getMessage() . "<br>";
    }

    try {
        $pdo->exec("ALTER TABLE workers ADD COLUMN otp_expires_at DATETIME DEFAULT NULL");
        echo "Added 'otp_expires_at' column to workers table.<br>";
    } catch (PDOException $e) {
        echo "Column 'otp_expires_at' already exists in workers or error: " . $e->getMessage() . "<br>";
    }

    echo "<h3>Schema update completed.</h3>";

} catch (PDOException $e) {
    echo "<h1>Error updating schema: " . $e->getMessage() . "</h1>";
}
?>
