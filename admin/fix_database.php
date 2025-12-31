<?php
/**
 * Labour On Demand - Database Schema Fixer
 * 
 * This script will:
 * 1. Create missing tables (security_incidents, jobs, rate_limits)
 * 2. Add missing columns (user_uid, worker_uid, doc_update_status, etc.)
 * 3. Populate missing UIDs
 * 
 * Usage: Upload to 'admin/' and run it in your browser.
 */

define('EXECUTION_ALLOWED', true);
require_once '../includes/security.php';
require_once '../config/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    die("<h1>403 Forbidden</h1><p>Database management tools are restricted to authorized administrators.</p>");
}
require_once '../includes/utils.php'; // For generateUID

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Schema Fixer</h1><pre>";

function executeSql($pdo, $sql, $label) {
    echo "Executing: $label... ";
    try {
        $pdo->exec($sql);
        echo "<span style='color:green;'>SUCCESS</span>\n";
    } catch (PDOException $e) {
        // 1060 = Duplicate column name, 1061 = Duplicate key name, 1050 = Table already exists
        if (in_array($e->getCode(), ['42S21', '42000', '42S01']) || strpos($e->getMessage(), 'already exists') !== false) {
            echo "<span style='color:orange;'>SKIPPED (Already exists)</span>\n";
        } else {
            echo "<span style='color:red;'>FAILED: " . $e->getMessage() . "</span>\n";
        }
    }
}

// 1. Create Tables
executeSql($pdo, "CREATE TABLE IF NOT EXISTS `security_incidents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `incident_type` VARCHAR(50) NOT NULL,
    `severity` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    `details` TEXT,
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;", "Table: security_incidents");

executeSql($pdo, "CREATE TABLE IF NOT EXISTS `jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `type` VARCHAR(50) NOT NULL,
    `payload` TEXT NOT NULL,
    `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    `attempts` INT DEFAULT 0,
    `last_error` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB;", "Table: jobs");

executeSql($pdo, "CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL UNIQUE,
    `request_count` INT DEFAULT 1,
    `first_request_at` INT NOT NULL,
    `blocked_until` INT DEFAULT 0
) ENGINE=InnoDB;", "Table: rate_limits");

// 2. Add Columns
executeSql($pdo, "ALTER TABLE users ADD COLUMN user_uid VARCHAR(10) UNIQUE DEFAULT NULL AFTER id", "Column: users.user_uid");
executeSql($pdo, "ALTER TABLE workers ADD COLUMN worker_uid VARCHAR(8) UNIQUE DEFAULT NULL AFTER id", "Column: workers.worker_uid");
executeSql($pdo, "ALTER TABLE workers ADD COLUMN doc_update_status ENUM('approved', 'pending', 'rejected') DEFAULT NULL AFTER signature_photo_public_id", "Column: workers.doc_update_status");
executeSql($pdo, "ALTER TABLE worker_photo_history ADD COLUMN replaced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP", "Column: worker_photo_history.replaced_at");
executeSql($pdo, "ALTER TABLE workers ADD COLUMN avg_rating DECIMAL(3,2) DEFAULT 0.00", "Column: workers.avg_rating");
executeSql($pdo, "ALTER TABLE workers ADD COLUMN total_reviews INT DEFAULT 0", "Column: workers.total_reviews");

// 3. Populate missing UIDs
echo "\nPopulating missing UIDs...\n";
try {
    // Users
    $stmt = $pdo->query("SELECT id FROM users WHERE user_uid IS NULL");
    $users = $stmt->fetchAll();
    foreach ($users as $u) {
        $uid = generateUID($pdo, 'user');
        $pdo->prepare("UPDATE users SET user_uid = ? WHERE id = ?")->execute([$uid, $u['id']]);
        echo "  - Assigned $uid to User #{$u['id']}\n";
    }

    // Workers
    $stmt = $pdo->query("SELECT id FROM workers WHERE worker_uid IS NULL");
    $workers = $stmt->fetchAll();
    foreach ($workers as $w) {
        $uid = generateUID($pdo, 'worker');
        $pdo->prepare("UPDATE workers SET worker_uid = ? WHERE id = ?")->execute([$uid, $w['id']]);
        echo "  - Assigned $uid to Worker #{$w['id']}\n";
    }
    echo "<span style='color:green;'>UID population complete.</span>\n";
} catch (Exception $e) {
    echo "<span style='color:red;'>UID population failed: " . $e->getMessage() . "</span>\n";
}

echo "\n--- ALL OPERATIONS COMPLETE ---\n";
echo "Please delete this script after use for security.\n";
echo "</pre>";
?>
