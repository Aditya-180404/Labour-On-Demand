<?php
require_once 'config/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) NOT NULL, -- e.g. 'email'
        payload TEXT NOT NULL, -- JSON encoded data
        status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        attempts INT DEFAULT 0,
        last_error TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB;";

    $pdo->exec($sql);
    echo "SUCCESS: 'jobs' table created successfully.";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
