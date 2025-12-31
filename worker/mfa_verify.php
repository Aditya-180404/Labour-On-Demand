<?php
$path_prefix = '../';
require_once '../includes/security.php';
require_once '../config/db.php';
require_once '../includes/mailer.php';

if (!isset($_SESSION['mfa_worker_id'])) {
    header("Location: login.php");
    exit;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !verifyCSRF($_POST['csrf_token'])) {
        $error = "Invalid CSRF token.";
    } else {
        $entered_otp = trim($_POST['otp']);
        $worker_id = $_SESSION['mfa_worker_id'];

        if (isset($_SESSION['mfa_worker_otp']) && isset($_SESSION['mfa_worker_otp_expiry'])) {
            if (time() > $_SESSION['mfa_worker_otp_expiry']) {
                $error = "MFA code has expired. Please log in again.";
            } else {
                $expected_hash = $_SESSION['mfa_worker_otp'];
                $entered_hash = hash_hmac('sha256', (string)$entered_otp, 'otp_secret_key');

                if (hash_equals($expected_hash, $entered_hash)) {
                    // MFA Success!
                    $_SESSION['worker_id'] = $worker_id;
                    $_SESSION['worker_name'] = $_SESSION['mfa_temp_worker_name'];
                    
                    // Fetch email for session
                    $stmt = $pdo->prepare("SELECT email FROM workers WHERE id = ?");
                    $stmt->execute([$worker_id]);
                    $w_data = $stmt->fetch();
                    $_SESSION['worker_email'] = $w_data['email'];

                    // Cleanup MFA session
                    unset($_SESSION['mfa_worker_id']);
                    unset($_SESSION['mfa_worker_otp']);
                    unset($_SESSION['mfa_worker_otp_expiry']);
                    unset($_SESSION['mfa_temp_worker_name']);
                    
                    session_regenerate_id(true);
                    header("Location: dashboard.php");
                    exit;
                } else {
                    logSecurityIncident('worker_mfa_failed', 'medium', "Failed MFA attempt for Worker ID: $worker_id");
                    $error = "Invalid MFA code. Please try again.";
                }
            }
        } else {
            $error = "MFA session expired. Please log in again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker MFA Verification - Labour On Demand</title>
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
                    <div class="bg-dark text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="fas fa-user-shield fa-2x"></i>
                    </div>
                    <h2 class="fw-bold">Worker Verification</h2>
                    <p class="text-muted">Enter the 6-digit code sent to your email.</p>
                </div>

                <?php if($error): ?>
                    <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRF(); ?>">
                    <div class="mb-4">
                        <label for="otp" class="form-label fw-bold">Verification Code</label>
                        <input type="text" name="otp" id="otp" class="form-control form-control-lg rounded-4 text-center fw-bold letter-spacing-2" maxlength="6" placeholder="000000" autofocus required>
                    </div>
                    
                    <button type="submit" class="btn btn-dark w-100 rounded-4 py-3 fw-bold shadow-sm">
                        Verify & Login
                    </button>
                </form>

                <div class="text-center mt-4">
                    <p class="small text-muted mb-0">Didn't receive the code?</p>
                    <a href="login.php" class="text-decoration-none fw-bold text-dark">Try logging in again</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
