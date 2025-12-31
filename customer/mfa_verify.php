<?php
$path_prefix = '../';
require_once '../includes/security.php';
require_once '../config/db.php';
require_once '../includes/mailer.php';

if (!isset($_SESSION['mfa_user_id'])) {
    header("Location: login.php");
    exit;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !verifyCSRF($_POST['csrf_token'])) {
        $error = "Invalid CSRF token.";
    } else {
        // Check rate limit
        if (!checkLoginRateLimit($pdo)) {
            $error = "Too many failed attempts. Locked for 15 minutes.";
        } else {
            $entered_otp = trim($_POST['otp']);
            $user_id = $_SESSION['mfa_user_id'];

            // Get stored OTP (it was stored in session or DB during login)
            if (isset($_SESSION['mfa_otp']) && isset($_SESSION['mfa_otp_expiry'])) {
                if (time() > $_SESSION['mfa_otp_expiry']) {
                    $error = "MFA code has expired. Please log in again.";
                } else {
                    $expected_hash = $_SESSION['mfa_otp'];
                    $entered_hash = hash_hmac('sha256', (string)$entered_otp, OTP_SECRET_KEY);

                    if (hash_equals($expected_hash, $entered_hash)) {
                        // MFA Success!
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['user_name'] = $_SESSION['mfa_temp_name'];
                        
                        // Cleanup MFA session
                        unset($_SESSION['mfa_user_id'], $_SESSION['mfa_otp'], $_SESSION['mfa_otp_expiry'], $_SESSION['mfa_temp_name'], $_SESSION['login_attempts']);
                        
                        session_regenerate_id(true);
                        header("Location: dashboard.php");
                        exit;
                    } else {
                        incrementLoginAttempts($pdo);
                        logSecurityIncident('mfa_failed', 'medium', "Failed MFA attempt for User ID: $user_id");
                        $error = "Invalid MFA code. Please try again.";
                    }
                }
            } else {
                $error = "MFA session expired. Please log in again.";
            }
        }
    }
}

// Handle Resend MFA OTP
if (isset($_POST['action']) && $_POST['action'] === 'resend_mfa' && isset($_SESSION['mfa_user_id'])) {
    if (!checkLoginRateLimit($pdo)) {
        $error = "Too many resend attempts. Please wait.";
    } else {
        $otp = rand(100000, 999999);
        $_SESSION['mfa_otp'] = hash_hmac('sha256', (string)$otp, OTP_SECRET_KEY);
        $_SESSION['mfa_otp_expiry'] = time() + 300;
        $res = sendOTPEmail($_SESSION['mfa_temp_email'] ?? 'User', $otp, $_SESSION['mfa_temp_name'] ?? 'User');
        if ($res['status']) $success = "New verification code sent.";
        else $error = "Failed to resend. Try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MFA Verification - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <style>
        body { background: var(--bg-color); color: var(--text-color); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .mfa-card { max-width: 400px; width: 100%; border-radius: 20px; border: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card mfa-card mx-auto shadow-lg bg-body-tertiary">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-shield-alt fa-2x"></i>
                    </div>
                    <h2 class="fw-bold">Two-Step Verification</h2>
                    <p class="text-muted">Enter the 6-digit code sent to your email.</p>
                </div>

                <?php if($error): ?>
                    <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <?php if(strpos($error, 'expired') !== false): ?>
                            <br><a href="login.php" class="alert-link text-decoration-none">Back to Login</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                    <div class="mb-4 text-center">
                        <label for="otp" class="form-label fw-bold">Verification Code</label>
                        <input type="text" name="otp" id="otp" class="form-control form-control-lg rounded-4 text-center fw-bold letter-spacing-2" maxlength="6" pattern="\d{6}" inputmode="numeric" title="6-digit numeric code" placeholder="000000" autofocus required>
                        <div class="mt-2 small text-muted">Expires in: <span id="timer" class="text-primary fw-bold">05:00</span></div>
                        <div id="resend-container" class="mt-2 d-none">
                            <button type="submit" name="action" value="resend_mfa" class="btn btn-link btn-sm text-primary fw-bold text-decoration-none p-0">Resend Code</button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 rounded-4 py-3 fw-bold shadow-sm">
                        Verify & Login
                    </button>
                </form>

                <div class="text-center mt-4">
                    <p class="small text-muted mb-0">Didn't receive the code?</p>
                    <a href="login.php" class="text-decoration-none fw-bold">Try logging in again</a>
                </div>
            </div>
        </div>
    </div>
<script>
function startTimer(duration, display) {
    var timer = duration, minutes, seconds;
    var interval = setInterval(function () {
        minutes = parseInt(timer / 60, 10);
        seconds = parseInt(timer % 60, 10);
        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;
        display.textContent = minutes + ":" + seconds;
        if (--timer < 0) {
            clearInterval(interval);
            display.textContent = "00:00";
            display.parentElement.innerHTML = '<span class="text-danger">Code expired. Please log in again.</span>';
            const btn = document.querySelector('button[type="submit"]');
            if(btn) btn.disabled = true;
        }
    }, 1000);
}
window.onload = function () {
    var timerDisplay = document.querySelector('#timer');
    if (timerDisplay) {
        startTimer(300, timerDisplay);
        setTimeout(() => {
            const resendCont = document.getElementById('resend-container');
            if(resendCont) resendCont.classList.remove('d-none');
        }, 30000);
    }
};
</script>
</body>
</html>
