<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php'; // Adjust path if needed
require_once __DIR__ . '/../config/mail.php';

// Generic Send Email Function
function sendEmail($to_email, $to_name, $subject, $body, $alt_body = '') {
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

function sendOTPEmail($to_email, $otp, $name = 'User') {
    $subject = 'Your OTP Code - Labour On Demand';
    $body = "
        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #28a745;'>Labour On Demand</h2>
            <p>Hello <strong>$name</strong>,</p>
            <p>Your One-Time Password (OTP) for verification is:</p>
            <h1 style='background: #f4f4f4; padding: 10px; display: inline-block; border-radius: 5px; color: #333;'>$otp</h1>
            <p>This OTP is valid for 10 minutes. Do not share this code with anyone.</p>
            <br>
            <p>Regards,<br>Team Labour On Demand</p>
        </div>
    ";
    $altBody = "Hello $name, Your OTP code is: $otp. Regards, Team Labour On Demand";

    return sendEmail($to_email, $name, $subject, $body, $altBody);
}

function sendBookingCompletionOTP($to_email, $otp, $customer_name, $worker_name) {
    $subject = "Job Completion Verification - Labour On Demand";
    $body = "
        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #0d6efd;'>Job Completion Request</h2>
            <p>Hello <strong>$customer_name</strong>,</p>
            <p>Your worker, <strong>$worker_name</strong>, has marked the job as completed.</p>
            <p>To verify this and authorize the completion, please provide the following OTP to the worker:</p>
            <h1 style='background: #e9ecef; padding: 10px; display: inline-block; border-radius: 5px; color: #495057; letter-spacing: 5px;'>$otp</h1>
            <p><strong>Note:</strong> Only share this code if you are satisfied that the work is done.</p>
            <br>
            <p>Regards,<br>Team Labour On Demand</p>
        </div>
    ";
    $altBody = "Hello $customer_name, Worker $worker_name has finished the job. Share this OTP to verify: $otp";
    return sendEmail($to_email, $customer_name, $subject, $body, $altBody);
}
?>
