<?php
require_once 'config/db.php';

echo "<h2>Recent Workers Debug Info</h2>";

// 1. Fetch last 5 workers
$stmt = $pdo->query("SELECT id, name, email, status, service_category_id, created_at FROM workers ORDER BY id DESC LIMIT 5");
$workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($workers)) {
    echo "No workers found in database.<br>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Category ID</th><th>Created At</th></tr>";
    foreach ($workers as $w) {
        echo "<tr>";
        echo "<td>{$w['id']}</td>";
        echo "<td>{$w['name']}</td>";
        echo "<td>{$w['email']}</td>";
        echo "<td>{$w['status']}</td>"; // This MUST be 'pending' to show up
        echo "<td>{$w['service_category_id']}</td>";
        echo "<td>{$w['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>Admin Query Test</h3>";
// 2. Simulate the exact query used in admin/dashboard.php
$sql = "SELECT w.*, c.name as category_name FROM workers w LEFT JOIN categories c ON w.service_category_id = c.id WHERE w.status = 'pending'";
$stmt2 = $pdo->query($sql);
$pending = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Number of pending workers returned by Admin Query: " . count($pending) . "<br>";
if (count($pending) > 0) {
    echo "<ul>";
    foreach ($pending as $p) {
        echo "<li>ID: {$p['id']} - Name: {$p['name']} (Category: {$p['category_name']})</li>";
    }
    echo "</ul>";
} else {
    echo "No pending workers found by the query.<br>";
}
?>
