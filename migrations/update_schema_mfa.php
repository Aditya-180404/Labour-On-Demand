<?php
define('EXECUTION_ALLOWED', true);
require_once 'config/db.php';

try {
    echo "Starting MFA Schema Update...\n";

    // Add mfa_enabled to users
    $pdo->exec("ALTER TABLE users ADD COLUMN mfa_enabled TINYINT(1) DEFAULT 0 AFTER location");
    echo "Added 'mfa_enabled' to 'users' table.\n";

    // Add mfa_enabled to workers
    $pdo->exec("ALTER TABLE workers ADD COLUMN mfa_enabled TINYINT(1) DEFAULT 0 AFTER is_available");
    echo "Added 'mfa_enabled' to 'workers' table.\n";

    echo "Schema update completed successfully!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
