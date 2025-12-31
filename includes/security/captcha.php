<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

/**
 * reCAPTCHA Verification Logic
 */

/**
 * Verify Google reCAPTCHA v2 response
 * 
 * @param string $response The g-recaptcha-response from POST
 * @return bool
 */
if (!function_exists('verifyCaptcha')) {
    function verifyCaptcha($response) {
        if (empty($response)) {
            return false;
        }
        
        $secret = defined('RECAPTCHA_SECRET') ? RECAPTCHA_SECRET : "6LfwHzgsAAAAAL_6sH_wTe4xegNk9wVDMNRUhCXX";
        $url = "https://www.google.com/recaptcha/api/siteverify";
        $data = [
            'secret' => $secret,
            'response' => $response
        ];

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Enable SSL verification for production security

            $verify = curl_exec($ch);
            
            if (curl_errno($ch)) {
                // error_log("Captcha Curl Error: " . curl_error($ch));
                curl_close($ch);
                return false;
            }
            
            curl_close($ch);
            $result = json_decode($verify, true);
            $success = isset($result['success']) && $result['success'];
            
            if (!$success) {
                logSecurityIncident('captcha_fail', 'medium', 'Google verification returned failure.');
            }
            
            return $success;
        } catch (Exception $e) {
            logSecurityIncident('captcha_fail', 'low', 'Exception: ' . $e->getMessage());
            // error_log("Captcha Exception: " . $e->getMessage());
            return false;
        }
    }
}
?>
