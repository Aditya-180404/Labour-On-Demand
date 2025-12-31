<?php
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$password_check = 'admin123';
$password_check2 = 'password';

echo "Hash from DB: $hash\n";
echo "Testing 'admin123': " . (password_verify($password_check, $hash) ? "MATCH" : "NO MATCH") . "\n";
echo "Testing 'password': " . (password_verify($password_check2, $hash) ? "MATCH" : "NO MATCH") . "\n";

echo "New hash for 'admin123': " . password_hash('admin123', PASSWORD_DEFAULT) . "\n";
?>
