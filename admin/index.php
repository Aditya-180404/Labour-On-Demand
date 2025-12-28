<?php
$path_prefix = '../';
require_once '../config/security.php';
require_once '../config/db.php';
require_once '../includes/captcha.php';

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
    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || !verifyCSRF($_POST['csrf_token'])) {
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

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid credentials.";
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
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
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
                            <div class="mb-3 text-center">
                                <div class="g-recaptcha d-inline-block" data-sitekey="6LfwHzgsAAAAAI0kyJ7g6V_S6uE0FFb4zDWpypmD"></div>
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
