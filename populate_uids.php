<?php
define('EXECUTION_ALLOWED', true);
$_SERVER['SERVER_NAME'] = 'localhost';
require_once 'config/db.php';
require_once 'includes/utils.php';

echo "--- POPULATING UIDs ---\n";

try {
    // Populate users
    $users = $pdo->query("SELECT id FROM users WHERE user_uid IS NULL")->fetchAll();
    foreach ($users as $u) {
        $uid = generateUID($pdo, 'user');
        $pdo->prepare("UPDATE users SET user_uid = ? WHERE id = ?")->execute([$uid, $u['id']]);
        echo "Assigned $uid to user ID " . $u['id'] . "\n";
    }

    // Populate workers
    $workers = $pdo->query("SELECT id FROM workers WHERE worker_uid IS NULL")->fetchAll();
    foreach ($workers as $w) {
        $uid = generateUID($pdo, 'worker');
        $pdo->prepare("UPDATE workers SET worker_uid = ? WHERE id = ?")->execute([$uid, $w['id']]);
        echo "Assigned $uid to worker ID " . $w['id'] . "\n";
    }

    echo "Population completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
