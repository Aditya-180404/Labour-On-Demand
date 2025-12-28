<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

function verifyCaptcha($response) {
    if (empty($response)) {
        // Use a relative path for portability
        file_put_contents(__DIR__ . '/../captcha_debug.log', date('Y-m-d H:i:s') . " - Empty Response received.\n", FILE_APPEND);
        return false;
    }
    
    // REPLACE 'YOUR_SECRET_KEY' with your actual Google reCAPTCHA Secret Key
    $secret = "6LfwHzgsAAAAAL_6sH_wTe4xegNk9wVDMNRUhCXX";
    $url = "https://www.google.com/recaptcha/api/siteverify";
    $data = [
        'secret' => $secret,
        'response' => $response
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Localhost often has SSL issues

    $verify = curl_exec($ch);

    // Debug Logging (Portable path)
    $log_file = __DIR__ . '/../captcha_debug.log';
    $log_entry = date('Y-m-d H:i:s') . " - Response len: " . strlen($response) . "\n";
    
    if (curl_errno($ch)) {
        $log_entry .= "Curl Error: " . curl_error($ch) . "\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
        curl_close($ch);
        return false;
    }
    
    $log_entry .= "Google API Result: " . $verify . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    curl_close($ch);
    $result = json_decode($verify, true);
    
    return isset($result['success']) && $result['success'];
}
?>
