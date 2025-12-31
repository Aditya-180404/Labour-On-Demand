<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload_path)) {
    require $autoload_path;
} else {
    error_log("Mailer Error: vendor/autoload.php not found. Emails will not send.");
}
require_once __DIR__ . '/../config/mail.php';

// 1. Actual Sender (Used by Worker) - Renamed from sendEmail
function sendEmailNow($to_email, $to_name, $subject, $body, $alt_body = '') {
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        return ['status' => false, 'message' => 'Mailer dependencies not installed (vendor missing).'];
    }
    
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $alt_body ? $alt_body : strip_tags($body);

        $mail->send();
        return ['status' => true, 'message' => 'Email sent successfully.'];
    } catch (Exception $e) {
        return ['status' => false, 'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"];
    }
}

// 2. Queue Pusher (Used by App) - New "sendEmail"
function sendEmail($to_email, $to_name, $subject, $body, $alt_body = '', $send_now = false) {
    global $pdo;

    // Emergency override or direct send request
    if ($send_now) {
        return sendEmailNow($to_email, $to_name, $subject, $body, $alt_body);
    }

    // Prepare Payload
    $payload = json_encode([
        'to_email' => $to_email,
        'to_name' => $to_name,
        'subject' => $subject,
        'body' => $body,
        'alt_body' => $alt_body
    ]);

    try {
        $stmt = $pdo->prepare("INSERT INTO jobs (type, payload, status, created_at) VALUES ('email', ?, 'pending', NOW())");
        $stmt->execute([$payload]);
        
        // SAFETY NET: Trigger the worker immediately (Fire-and-Forget)
        triggerAsyncWorker();
        
        return ['status' => true, 'message' => 'Email queued successfully.'];
    } catch (PDOException $e) {
        error_log("Queue Failed: " . $e->getMessage());
        return sendEmailNow($to_email, $to_name, $subject, $body, $alt_body);
    }
}

// 3. Safety Net: Poke the worker script via HTTP (Non-Blocking)
function triggerAsyncWorker() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    
    // Adjust path based on common directory structures
    $path = "";
    if (strpos($uri, 'admin') !== false || strpos($uri, 'customer') !== false || strpos($uri, 'worker') !== false) {
        $path = "/../cron/process_jobs.php";
    } else {
        $path = "/cron/process_jobs.php";
    }

    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $scheme = $is_https ? 'ssl://' : '';
    $port = $is_https ? 443 : 80;
    
    // Attempt to open socket with very short timeout
    $fp = @fsockopen($scheme . $host, $port, $errno, $errstr, 0.5);
    
    if ($fp) {
        // Build the full request path with security key
        $full_path = $uri . $path . "?key=" . CRON_KEY;
        $full_path = str_replace(['//', '\\'], '/', $full_path);
        
        $out = "GET " . $full_path . " HTTP/1.1\r\n";
        $out .= "Host: $host\r\n";
        $out .= "Connection: Close\r\n\r\n";
        fwrite($fp, $out);
        fclose($fp);
    }
}

function sendOTPEmail($to_email, $otp, $name = 'User', $uid = null) {
    $subject = 'Your OTP Code - Labour On Demand';
    $uid_html = $uid ? "<p style='color: #666; font-size: 0.9em;'>Your Unique ID: <strong style='color: #333;'>$uid</strong></p>" : "";
    $body = "
        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #28a745;'>Labour On Demand</h2>
            <p>Hello <strong>$name</strong>,</p>
            $uid_html
            <p>Your One-Time Password (OTP) for verification is:</p>
            <h1 style='background: #f4f4f4; padding: 10px; display: inline-block; border-radius: 5px; color: #333;'>$otp</h1>
            <p>This OTP is valid for 10 minutes. Do not share this code with anyone.</p>
            <br>
            <p>Regards,<br>Team Labour On Demand</p>
        </div>
    ";
    $altBody = "Hello $name, " . ($uid ? "Your Unique ID is: $uid. " : "") . "Your OTP code is: $otp. Regards, Team Labour On Demand";

    // OTPs are urgent, so we use sendEmail which triggers the worker immediately
    return sendEmail($to_email, $name, $subject, $body, $altBody);
}

function sendBookingCompletionOTP($to_email, $otp, $customer_name, $worker_name, $domain = 'Service', $pid = 'N/A', $cost = '0') {
    $subject = "Job Completion Verification - Labour On Demand";
    $body = "
        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #0d6efd;'>Job Completion Request</h2>
            <p>Hello <strong>$customer_name</strong>,</p>
            <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                <p style='margin: 0 0 10px 0;'><strong>Worker Details:</strong></p>
                <ul style='list-style: none; padding: 0; margin: 0;'>
                    <li>Name: <strong>$worker_name</strong></li>
                    <li>Service: <strong>$domain</strong></li>
                    <li>Worker ID (PID): <strong>$pid</strong></li>
                    <li style='margin-top: 10px; font-size: 1.1em;'>Total Charged: <strong style='color: #28a745;'>₹" . number_format($cost, 2) . "</strong></li>
                </ul>
            </div>
            <p>The worker has marked the job as completed. To verify this and authorize the completion, please provide the following OTP to the worker:</p>
            <h1 style='background: #e9ecef; padding: 10px; display: inline-block; border-radius: 5px; color: #495057; letter-spacing: 5px;'>$otp</h1>
            <p><strong>Note:</strong> Only share this code if you are satisfied that the work is done. By sharing this, you confirm the amount mentioned above.</p>
            <br>
            <p>Regards,<br>Team Labour On Demand</p>
        </div>
    ";
    $altBody = "Hello $customer_name, Worker $worker_name ($pid) has finished the $domain job. Cost: ₹$cost. Share this OTP to verify: $otp";
    return sendEmail($to_email, $customer_name, $subject, $body, $altBody);
}
?>
