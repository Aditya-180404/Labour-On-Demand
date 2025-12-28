<?php
define('EXECUTION_ALLOWED', true);
$_SERVER['SERVER_NAME'] = 'localhost';
require_once 'config/db.php';

echo "--- SCHEMA CHECK ---\n";

echo "Current Database: " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "\n";

echo "\nTables in the database:\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    echo "- $t\n";
}

function checkTable($pdo, $table) {
    echo "\nColumns in '$table':\n";
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        while ($row = $stmt->fetch()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

checkTable($pdo, 'users');
checkTable($pdo, 'workers');
checkTable($pdo, 'feedbacks');
?>
