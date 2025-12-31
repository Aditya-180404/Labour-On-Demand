<?php
$path_prefix = '../';
require_once '../includes/security.php';
require_once '../config/db.php';

// If already logged in, go to dashboard
if (isset($_SESSION['admin_logged_in'])) {
    header("Location: dashboard.php");
    exit;
}

// Secret Key Access Check
if (!isset($_GET['entry']) || $_GET['entry'] !== '1g2g4g6i3g4g5g56774b') {
    header("Location: ../"); // Redirect to home page
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Honeypot/Bot Detection
    if (isBotDetected()) {
        $error = "Bot detected. Access denied.";
    }
    // 1b. Special Admin Trap Password (Bots love 'password' fields)
    elseif (!empty($_POST['admin_trap_password'])) {
        logSecurityIncident('admin_honeypot', 'critical', "Admin Trap Password triggered by " . get_client_ip());
        blockIP(get_client_ip(), "Admin Honeypot Triggered", 86400); // 24 hour ban
        $error = "Security Violation: Your activity has been logged.";
    }
    // 2. Validate CSRF Token
    elseif (!isset($_POST['csrf_token']) || !verifyCSRF($_POST['csrf_token'])) {
        $error = "Invalid CSRF token. Please refresh the page and try again.";
    }
    // Validate CAPTCHA
    elseif (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response'])) {
        $error = "Please check the CAPTCHA box.";
    } elseif (!verifyCaptcha($_POST['g-recaptcha-response'])) {
        $error = "CAPTCHA verification failed. Please try again.";
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $error = "Please fill all fields.";
        } else {
            $stmt = $pdo->prepare("SELECT id, username, password FROM admin WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if (!checkLoginRateLimit($pdo) || (isset($_SESSION['admin_login_fails']) && $_SESSION['admin_login_fails'] >= 3)) {
                $error = "Too many login attempts. Access denied for your IP for 30 minutes.";
                if (!isset($_SESSION['admin_locked_logged'])) {
                    blockIP(get_client_ip(), "Admin Brute-Force (3 fails)", 1800);
                    $_SESSION['admin_locked_logged'] = true;
                }
            } elseif ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                session_regenerate_id(true); // Prevent session fixation
                unset($_SESSION['admin_login_fails']); // Reset fails on success
                rotateCSRF(); // Security Enhancement: Rotate token on successful login
                header("Location: dashboard.php");
                exit;
            } else {
                $_SESSION['admin_login_fails'] = ($_SESSION['admin_login_fails'] ?? 0) + 1;
                incrementLoginAttempts($pdo);
                $error = "Invalid credentials. Attempt " . $_SESSION['admin_login_fails'] . " of 3.";
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
    <title>Admin Login - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin_login.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card shadow-lg">
                    <div class="card-header bg-danger text-white text-center">
                        <h3>Admin Portal</h3>
                    </div>
                    <div class="card-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <form action="index.php?entry=1g2g4g6i3g4g5g56774b" method="POST">
                            <?php echo csrf_input(); ?>
                            <?php renderHoneypot(); ?>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <!-- Admin Trap Honeypot -->
                            <div style="display:none; visibility:hidden; height:0; overflow:hidden;">
                                <label>Leave this empty</label>
                                <input type="password" name="admin_trap_password" tabindex="-1" autocomplete="off">
                            </div>
                            <div class="mb-3 text-center">
                                <div class="g-recaptcha d-inline-block" data-sitekey="6LfUczssAAAAAJAyN5ozYXwMRzPfmfnzex9NRLdu"></div>
                            </div>
                            <button type="submit" class="btn btn-danger w-100">Login</button>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <small class="text-muted">Restricted Access</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordField.getAttribute('type') === 'password') {
                passwordField.setAttribute('type', 'text');
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordField.setAttribute('type', 'password');
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        // Prevent form submission if CAPTCHA is not checked
        document.querySelector('form').addEventListener('submit', function(e) {
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
