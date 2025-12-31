<?php
require_once 'config/db.php';

$log_file = 'test_output.txt';
file_put_contents($log_file, "Starting Tests...\n");

function log_msg($msg) {
    global $log_file;
    file_put_contents($log_file, $msg . "\n", FILE_APPEND);
}

function test_customer_otp($pdo) {
    log_msg("Testing Customer OTP Flow...");
    $email = "test_customer_" . time() . "@example.com";
    $password = password_hash("Password@123", PASSWORD_DEFAULT);
    
    // 1. Create User
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, pin_code, address_details, location) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Test User', $email, $password, '1234567890', '110001', 'Test Address', 'Test Loc']);
    $user_id = $pdo->lastInsertId();
    log_msg("User created with ID: $user_id");

    // 2. Simulate Generate OTP
    $otp = "123456";
    $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));
    $update = $pdo->prepare("UPDATE users SET otp = ?, otp_expires_at = ? WHERE id = ?");
    $update->execute([$otp, $expiry, $user_id]);
    log_msg("OTP generated and stored.");

    // 3. Verify OTP Login Logic
    $check = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $check->execute([$email]);
    $user = $check->fetch();

    if ($user && $user['otp'] === $otp && strtotime($user['otp_expires_at']) > time()) {
        log_msg("SUCCESS: Customer OTP Validated correctly.");
        // Clear OTP
        $pdo->prepare("UPDATE users SET otp = NULL WHERE id = ?")->execute([$user_id]);
    } else {
        log_msg("FAILURE: Customer OTP Validation failed.");
        log_msg(print_r($user, true));
    }
    
    // Cleanup
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
    log_msg("Customer Cleanup done.");
    log_msg("--------------------------------");
}

function test_worker_otp($pdo) {
    log_msg("Testing Worker OTP Flow...");
    $email = "test_worker_" . time() . "@example.com";
    $password = password_hash("Password@123", PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO workers (name, email, password, status) VALUES (?, ?, ?, 'approved')");
        $stmt->execute(['Test Worker', $email, $password]);
        $worker_id = $pdo->lastInsertId();
        log_msg("Worker created with ID: $worker_id");

        // 2. Simulate Generate OTP
        $otp = "654321";
        $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));
        $update = $pdo->prepare("UPDATE workers SET otp = ?, otp_expires_at = ? WHERE id = ?");
        $update->execute([$otp, $expiry, $worker_id]);
        log_msg("OTP generated and stored.");

        // 3. Verify OTP Login Logic
        $check = $pdo->prepare("SELECT * FROM workers WHERE email = ?");
        $check->execute([$email]);
        $worker = $check->fetch();

        if ($worker && $worker['otp'] === $otp && strtotime($worker['otp_expires_at']) > time()) {
            log_msg("SUCCESS: Worker OTP Validated correctly.");
             // Clear OTP
            $pdo->prepare("UPDATE workers SET otp = NULL WHERE id = ?")->execute([$worker_id]);
        } else {
            log_msg("FAILURE: Worker OTP Validation failed.");
             log_msg(print_r($worker, true));
        }

        // Cleanup
        $pdo->prepare("DELETE FROM workers WHERE id = ?")->execute([$worker_id]);
        log_msg("Worker Cleanup done.");
        log_msg("--------------------------------");

    } catch (Exception $e) {
        log_msg("Worker Test Error: " . $e->getMessage());
    }
}

test_customer_otp($pdo);
test_worker_otp($pdo);
?>
