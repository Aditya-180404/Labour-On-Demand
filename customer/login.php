<?php
session_start();
require_once '../config/db.php';
require_once '../includes/mailer.php';

$error = "";
$success = "";
$otp_sent = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // ACTION: SEND OTP
    if (isset($_POST['send_otp'])) {
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
                    // OTP Valid
                    // Clear OTP
                    $pdo->prepare("UPDATE users SET otp = NULL WHERE id = ?")->execute([$user['id']]);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    header("Location: ../index.php");
                    exit;
                } else {
                    $error = "Invalid or expired OTP.";
                    $otp_sent = true;
                }
            } else {
                $error = "User not found.";
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

                        <!-- Standard Login Form -->
                        <form action="login.php" method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>

                            <?php if(!$otp_sent): ?>
                                <div class="mb-3 password-container">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password">
                                    <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                                    <ul class="password-requirements">
                                        <li id="req-length" class="invalid"><i class="fas fa-times-circle"></i> At least 8 characters</li>
                                        <li id="req-lower" class="invalid"><i class="fas fa-times-circle"></i> At least one lowercase letter</li>
                                        <li id="req-upper" class="invalid"><i class="fas fa-times-circle"></i> At least one uppercase letter</li>
                                        <li id="req-number" class="invalid"><i class="fas fa-times-circle"></i> At least one number</li>
                                        <li id="req-special" class="invalid"><i class="fas fa-times-circle"></i> At least one special character</li>
                                    </ul>
                                </div>
                                </div>
                                <button type="submit" name="login_password" class="btn btn-success w-100 mb-2">Login</button>
                                <div class="text-center mt-2">
                                    <button type="submit" name="send_otp" class="btn btn-link text-decoration-none p-0">Forgot Password?</button>
                                </div>
                            <?php else: ?>
                                <div class="mb-3">
                                    <label for="otp" class="form-label">Enter OTP</label>
                                    <input type="text" class="form-control" id="otp" name="otp" required placeholder="6-digit code">
                                </div>
                                <button type="submit" name="login_otp" class="btn btn-primary w-100 mb-2">Verify & Login</button>
                                <a href="login.php" class="btn btn-link w-100">Back to Login</a>
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
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Password Requirement Checker
        const passwordInput = document.getElementById('password');
        passwordInput.addEventListener('input', function () {
            const val = passwordInput.value;
            
            const requirements = [
                { id: 'req-length', valid: val.length >= 8 },
                { id: 'req-lower', valid: /[a-z]/.test(val) },
                { id: 'req-upper', valid: /[A-Z]/.test(val) },
                { id: 'req-number', valid: /\d/.test(val) },
                { id: 'req-special', valid: /[@$!%*?&]/.test(val) }
            ];

            requirements.forEach(req => {
                const el = document.getElementById(req.id);
                if (req.valid) {
                    el.classList.remove('invalid');
                    el.classList.add('valid');
                    el.querySelector('i').className = 'fas fa-check-circle';
                } else {
                    el.classList.remove('valid');
                    el.classList.add('invalid');
                    el.querySelector('i').className = 'fas fa-times-circle';
                }
            });
        });
    </script>
</body>
</html>
