<?php
require_once 'includes/security.php';
echo "Session ID: " . session_id() . "<br>";
echo "CSRF Token in Session: " . ($_SESSION['csrf_token'] ?? 'MISSING') . "<br>";
echo "<a href='test_session.php'>Refresh</a>";
?>
