<?php
define('EXECUTION_ALLOWED', true);
$_SERVER['SERVER_NAME'] = 'localhost';
require_once 'config/db.php';

echo "--- UID MIGRATION ---\n";

try {
    // Add user_uid to users
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_uid'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN user_uid VARCHAR(10) UNIQUE DEFAULT NULL AFTER id");
        echo "Added 'user_uid' to users.\n";
    } else {
        echo "'user_uid' already exists in users.\n";
    }

    // Add worker_uid to workers
    $stmt = $pdo->query("SHOW COLUMNS FROM workers LIKE 'worker_uid'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE workers ADD COLUMN worker_uid VARCHAR(8) UNIQUE DEFAULT NULL AFTER id");
        echo "Added 'worker_uid' to workers.\n";
    } else {
        echo "'worker_uid' already exists in workers.\n";
    }

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
