<?php
require_once 'config/db.php';
// Reset Admin Password to 'password'
$password = 'password';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE admin SET password = ? WHERE username = 'admin'");
if ($stmt->execute([$hashed_password])) {
    echo "Admin password reset successfully to 'password'.";
} else {
    echo "Failed to reset password.";
}
?>
