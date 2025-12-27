<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>CAPTCHA Debug Tool</h3>";

// 1. Test File Writing
$log_file = __DIR__ . '/captcha_test.log';
if (file_put_contents($log_file, "Test log entry\n")) {
    echo "<p style='color:green'>[OK] File writing is working. Log file created at: $log_file</p>";
} else {
    echo "<p style='color:red'>[FAIL] Cannot write to file system. Check permissions.</p>";
}

// 2. Test Network Connectivity
echo "<p>Testing connection to Google...</p>";
$ch = curl_init("https://www.google.com/recaptcha/api/siteverify");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Match the actual implementation
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($response === false) {
    echo "<p style='color:red'>[FAIL] cURL Error: " . curl_error($ch) . "</p>";
} else {
    echo "<p style='color:green'>[OK] Connection to Google successful. HTTP Code: $http_code</p>";
}
curl_close($ch);

// 3. Test Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h4>Form Submission Results:</h4>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";

    if (isset($_POST['g-recaptcha-response'])) {
        $token = $_POST['g-recaptcha-response'];
        echo "<p>Token received (length " . strlen($token) . "). Verifying...</p>";
        
        define('EXECUTION_ALLOWED', true);
        require_once 'includes/captcha.php';
        
        $is_valid = verifyCaptcha($token);
        echo "<p><strong>Verification Result: " . ($is_valid ? "<span style='color:green'>SUCCESS</span>" : "<span style='color:red'>FAILURE</span>") . "</strong></p>";
        
        // Show what was logged
        if (file_exists('captcha_debug.log')) {
            echo "<h5>Contents of captcha_debug.log:</h5>";
            echo "<pre>" . file_get_contents('captcha_debug.log') . "</pre>";
        } else {
             echo "<p style='color:red'>captcha_debug.log was NOT created during verification.</p>";
        }
    } else {
        echo "<p style='color:red'>No CAPTCHA token received.</p>";
    }
}
?>

<hr>
<h4>Test Form</h4>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<form method="POST">
    <div class="g-recaptcha" data-sitekey="6LfwHzgsAAAAAI0kyJ7g6V_S6uE0FFb4zDWpypmD"></div>
    <br>
    <button type="submit">Test Verification</button>
</form>
