<?php
require_once '../config/security.php';
require_once '../config/db.php';
require_once '../includes/mailer.php';

$error = "";
$success = "";
$otp_sent = false;
$reset_mode = false;

// Check if we are in reset mode (from session)
if (isset($_SESSION['reset_user_email'])) {
    $reset_mode = true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once '../includes/captcha.php';
    
    // Check Rate Limit
    if (!checkLoginRateLimit()) {
        $error = "Too many login attempts. Please try again after 15 minutes.";
    }
    // Validate CAPTCHA for Login or OTP Request (Skip for Final Reset Action)
    elseif (!isset($_POST['reset_password_action']) && (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response']))) {
        $error = "Please check the CAPTCHA box.";
    } elseif (!isset($_POST['reset_password_action']) && !verifyCaptcha($_POST['g-recaptcha-response'])) {
        $error = "CAPTCHA verification failed. (Check logs)";
    }
    // Validate CSRF Token
    elseif (!isset($_POST['csrf_token']) || !verifyCSRF($_POST['csrf_token'])) {
        $error = "Invalid CSRF token. Please refresh the page and try again.";
    }
    // Continue with existing logic only if CAPTCHA and CSRF passed
    elseif (isset($_POST['send_otp'])) {
        $email = trim($_POST['email']);
        if (empty($email)) {
            $error = "Please enter your email to reset password.";
        } else {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate 6-digit OTP
                $otp = rand(100000, 999999);
                $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

                // Store OTP in DB
                $update = $pdo->prepare("UPDATE users SET otp = ?, otp_expires_at = ? WHERE id = ?");
                $update->execute([$otp, $expiry, $user['id']]);

                // Send OTP via Email
                $mail_result = sendOTPEmail($email, $otp, $user['name']);

                if ($mail_result['status']) {
                    $success = "OTP sent to your email. Please check your inbox.";
                    $otp_sent = true;
                } else {
                    $error = "Error sending email: " . $mail_result['message'];
                    $otp_sent = false;
                }
            } else {
                $error = "Email not found.";
            }
        }
    }

    // ACTION: LOGIN WITH PASSOWRD
    elseif (isset($_POST['login_password'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $error = "Please fill all fields.";
        } else {
            $stmt = $pdo->prepare("SELECT id, name, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $email;
                header("Location: ../index.php");
                exit;
            } else {
                incrementLoginAttempts();
                $error = "Invalid email or password.";
            }
        }
    }

    // ACTION: LOGIN WITH OTP
    elseif (isset($_POST['login_otp'])) {
        $email = trim($_POST['email']);
        $entered_otp = trim($_POST['otp']);

        if (empty($email) || empty($entered_otp)) {
            $error = "Please enter Email and OTP.";
            $otp_sent = true; // Keep OTP field visible
        } else {
            $stmt = $pdo->prepare("SELECT id, name, otp, otp_expires_at FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                if ($user['otp'] === $entered_otp && strtotime($user['otp_expires_at']) > time()) {
                    // OTP Valid - Enter Reset Mode
                    $pdo->prepare("UPDATE users SET otp = NULL WHERE id = ?")->execute([$user['id']]);
                    $_SESSION['reset_user_email'] = $email;
                    $success = "OTP verified! Please set your new password.";
                    $reset_mode = true;
                } else {
                    $error = "Invalid or expired OTP.";
                    $otp_sent = true;
                }
            } else {
                $error = "User not found.";
            }
        }
    }

    // ACTION: RESET PASSWORD
    elseif (isset($_POST['reset_password_action'])) {
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];
        $email = $_SESSION['reset_user_email'];

        if (empty($new_pass) || empty($confirm_pass)) {
            $error = "Please fill both password fields.";
            $reset_mode = true;
        } elseif ($new_pass !== $confirm_pass) {
            $error = "Passwords do not match.";
            $reset_mode = true;
        } elseif (strlen($new_pass) < 8) {
            $error = "Password must be at least 8 characters.";
            $reset_mode = true;
        } else {
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            if ($stmt->execute([$hashed_pass, $email])) {
                unset($_SESSION['reset_user_email']);
                $_SESSION['toast_success'] = "Password reset successfully! Please login with your new password.";
                header("Location: login.php");
                exit;
            } else {
                $error = "Failed to update password.";
                $reset_mode = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/login.css">
    <script src="../assets/js/theme.js"></script>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
    <div class="container py-5 px-3">
        <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
            <div class="col-lg-4 col-md-6 col-sm-10">
                <div class="card shadow">
                    <div class="card-header bg-success text-white text-center">
                        <h3>Customer Login</h3>
                    </div>
                    <div class="card-body">
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form action="login.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <?php if(!$otp_sent && !$reset_mode): ?>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                                <div class="mb-3 password-container">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password">
                                    <p class="text-white-50 mb-4">Please enter your login and password!</p>
                                    <i class="fas fa-eye toggle-password" data-target="password"></i>
                                    <ul class="password-requirements">
                                        <li id="req-length" class="invalid"><i class="fas fa-times-circle"></i> At least 8 characters</li>
                                        <li id="req-lower" class="invalid"><i class="fas fa-times-circle"></i> At least one lowercase letter</li>
                                        <li id="req-upper" class="invalid"><i class="fas fa-times-circle"></i> At least one uppercase letter</li>
                                        <li id="req-number" class="invalid"><i class="fas fa-times-circle"></i> At least one number</li>
                                        <li id="req-special" class="invalid"><i class="fas fa-times-circle"></i> At least one special character</li>
                                    </ul>
                                </div>

                                <div class="mb-3">
                                    <div class="g-recaptcha" data-sitekey="6LfwHzgsAAAAAI0kyJ7g6V_S6uE0FFb4zDWpypmD"></div>
                                </div>
                                <button type="submit" name="login_password" class="btn btn-success w-100 mb-2">Login</button>
                                <div class="text-center mt-2">
                                    <button type="submit" name="send_otp" class="btn btn-link text-decoration-none p-0">Forgot Password?</button>
                                </div>
                            <?php elseif($otp_sent): ?>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" readonly value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="otp" class="form-label">Enter OTP</label>
                                    <input type="text" class="form-control" id="otp" name="otp" required placeholder="6-digit code">
                                </div>
                                <div class="mb-3">
                                    <div class="g-recaptcha" data-sitekey="6LfwHzgsAAAAAI0kyJ7g6V_S6uE0FFb4zDWpypmD"></div>
                                </div>
                                <button type="submit" name="login_otp" class="btn btn-primary w-100 mb-2">Verify & Reset Password</button>
                                <a href="login.php" class="btn btn-link w-100">Back to Login</a>
                            <?php elseif($reset_mode): ?>
                                <div class="mb-3">
                                    <p class="text-muted small">Setting password for: <strong><?php echo htmlspecialchars($_SESSION['reset_user_email']); ?></strong></p>
                                </div>
                                <div class="mb-3 password-container">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <i class="fas fa-eye toggle-password" data-target="new_password"></i>
                                    <ul class="password-requirements" id="reset-requirements">
                                        <li id="reset-req-length" class="invalid"><i class="fas fa-times-circle"></i> At least 8 characters</li>
                                        <li id="reset-req-lower" class="invalid"><i class="fas fa-times-circle"></i> At least one lowercase letter</li>
                                        <li id="reset-req-upper" class="invalid"><i class="fas fa-times-circle"></i> At least one uppercase letter</li>
                                        <li id="reset-req-number" class="invalid"><i class="fas fa-times-circle"></i> At least one number</li>
                                        <li id="reset-req-special" class="invalid"><i class="fas fa-times-circle"></i> At least one special character</li>
                                    </ul>
                                </div>
                                <div class="mb-3 password-container">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <i class="fas fa-eye toggle-password" data-target="confirm_password"></i>
                                </div>
                                <button type="submit" name="reset_password_action" class="btn btn-success w-100 mb-2">Update Password</button>
                                <a href="login.php" class="btn btn-link w-100">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <small>Don't have an account? <a href="register.php">Register here</a></small> <br>
                        <small>Are you a worker? <a href="../worker/login.php">Worker Login</a></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.querySelectorAll('.toggle-password').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target') || 'password';
                const password = document.getElementById(targetId);
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });

        // Password Requirement Checker Helper
        function setupPasswordChecker(inputId, prefix) {
            const input = document.getElementById(inputId);
            if (!input) return;

            input.addEventListener('input', function () {
                const val = input.value;
                const requirements = [
                    { id: prefix + 'req-length', valid: val.length >= 8 },
                    { id: prefix + 'req-lower', valid: /[a-z]/.test(val) },
                    { id: prefix + 'req-upper', valid: /[A-Z]/.test(val) },
                    { id: prefix + 'req-number', valid: /\d/.test(val) },
                    { id: prefix + 'req-special', valid: /[@$!%*?&]/.test(val) }
                ];

                requirements.forEach(req => {
                    const el = document.getElementById(req.id);
                    if (el) {
                        if (req.valid) {
                            el.classList.remove('invalid');
                            el.classList.add('valid');
                            el.querySelector('i').className = 'fas fa-check-circle';
                        } else {
                            el.classList.remove('valid');
                            el.classList.add('invalid');
                            el.querySelector('i').className = 'fas fa-times-circle';
                        }
                    }
                });
            });
        }

        setupPasswordChecker('password', '');
        setupPasswordChecker('new_password', 'reset-');

            // Prevent form submission if CAPTCHA is not checked
            document.querySelector('form').addEventListener('submit', function(e) {
                // Check if CAPTCHA exists on page
                if (document.querySelector('.g-recaptcha')) {
                    var response = grecaptcha.getResponse();
                    if (response.length === 0) {
                        e.preventDefault();
                        alert("Please check the CAPTCHA box to verify you are not a robot.");
                    }
                }
            });
    </script>
</body>
</html>
