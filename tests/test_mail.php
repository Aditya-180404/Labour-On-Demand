<?php
require_once 'includes/mailer.php';

// Check for default values
if (SMTP_USERNAME === 'your_email@gmail.com' || SMTP_PASSWORD === 'your_app_password') {
    die("<h3 style='color: red'>Configuration Error</h3>
         <p>You have not updated <code>config/mail.php</code> with your actual email credentials.</p>
         <p>Please open that file and replace <code>your_email@gmail.com</code> and <code>your_app_password</code> with your real details.</p>");
}

// Test Email
$to = isset($_GET['email']) ? $_GET['email'] : "test@example.com"; 
$otp = "123456";

echo "Attempting to send email to <strong>$to</strong>...<br>";

if ($to === "test@example.com") {
    echo "<p style='color: orange'>Warning: sending to default 'test@example.com'. Add ?email=your_email to url to test real delivery.</p>";
}

$result = sendOTPEmail($to, $otp, "Test User");

if ($result['status']) {
    echo "<h3 style='color: green'>Success! Email sent.</h3>";
} else {
    echo "<h3 style='color: red'>Failed to send email.</h3>";
    echo "Error: " . $result['message'];
    echo "<br><br><strong>Tip:</strong> If using Gmail, make sure you are using an <u>App Password</u>, not your login password.";
}
?>
