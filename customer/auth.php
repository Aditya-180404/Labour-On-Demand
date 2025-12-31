<?php
/**
 * Labour On Demand - Unified Customer Authentication
 * Handles Login, Multi-step Registration, and Password Recovery.
 * Features: Password Toggle, Strength Meter, Confirm Password, OTP, Honeypot.
 */

require_once '../includes/security.php';
require_once '../config/db.php';
require_once '../includes/mailer.php';
require_once '../includes/utils.php';

$error = "";
$success = "";
$mode = $_GET['mode'] ?? 'login';
$sub_mode = $_POST['sub_mode'] ?? ($_GET['sub_mode'] ?? 'init');

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isBotDetected()) die("Bot detected.");

    if (!isset($_POST['csrf_token']) || !verifyCSRF($_POST['csrf_token'])) {
        $error = "Invalid security token. Please refresh.";
    } elseif (!isset($_POST['g-recaptcha-response']) || !verifyCaptcha($_POST['g-recaptcha-response'])) {
        $error = "CAPTCHA failed. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';

        // A. LOGIN
        if ($action === 'login') {
            $email = trim($_POST['email']);
            $password = $_POST['password'];

            if (!checkLoginRateLimit($pdo)) {
                $error = "Too many attempts. Locked for 15 minutes.";
            } else {
                $stmt = $pdo->prepare("SELECT id, name, password FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $email;
                    rotateCSRF(); // Security Enhancement: Rotate token on successful login
                    if (isset($_SESSION['login_attempts'])) unset($_SESSION['login_attempts']);
                    header("Location: ../index.php");
                    exit;
                } else {
                    incrementLoginAttempts($pdo);
                    $error = "Invalid email or password.";
                }
            }
        }

        // B. REGISTER INIT
        elseif ($action === 'register_init') {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $confirm_pass = $_POST['confirm_password'];
            $phone = trim($_POST['phone']);
            $pin_code = trim($_POST['pin_code']);
            $address = trim($_POST['address_details']);

            if ($password !== $confirm_pass) {
                $error = "Passwords do not match.";
            } elseif (strlen($password) < 8) {
                $error = "Password too weak. Must be 8+ chars.";
            } elseif (empty($name) || empty($email) || empty($phone) || empty($pin_code)) {
                $error = "All fields are required.";
            } elseif (!preg_match('/^\d{10}$/', $phone)) {
                $error = "Invalid 10-digit phone number.";
            } elseif (!preg_match('/^\d{6}$/', $pin_code)) {
                $error = "Invalid 6-digit PIN code.";
            } else {
                // Check Email
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->rowCount() > 0) {
                    $error = "Email is already registered.";
                } else {
                    $user_uid = generateUID($pdo, 'user');
                    $otp = random_int(100000, 999999);
                    
                    $_SESSION['reg_data'] = [
                        'name' => $name, 'email' => $email,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'phone' => $phone, 'pin_code' => $pin_code,
                        'address_details' => $address, 'user_uid' => $user_uid
                    ];
                    $_SESSION['reg_otp'] = hash_hmac('sha256', (string)$otp, OTP_SECRET_KEY);
                    $_SESSION['reg_otp_expiry'] = time() + 300; 

                    $res = sendOTPEmail($email, $otp, $name, $user_uid); // Using mailer.php
                    if ($res['status']) {
                        $mode = 'register'; $sub_mode = 'verify';
                        $success = "Verification code sent to $email.";
                    } else {
                        $error = "Could not send email. Try again.";
                    }
                }
            }
        }

        // C. REGISTER VERIFY
        elseif ($action === 'register_verify') {
            $otp = trim($_POST['otp']);
            if (!isset($_SESSION['reg_otp']) || time() > $_SESSION['reg_otp_expiry']) {
                $error = "Code expired."; $mode = 'register'; $sub_mode = 'init';
            } elseif (hash_equals($_SESSION['reg_otp'], hash_hmac('sha256', $otp, OTP_SECRET_KEY))) {
                if (isset($_SESSION['login_attempts'])) unset($_SESSION['login_attempts']);
                $u = $_SESSION['reg_data'];
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, pin_code, address_details, user_uid) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$u['name'], $u['email'], $u['password'], $u['phone'], $u['pin_code'], $u['address_details'], $u['user_uid']])) {
                    $_SESSION['user_id'] = $pdo->lastInsertId();
                    $_SESSION['user_name'] = $u['name'];
                    $_SESSION['user_email'] = $u['email'];
                    unset($_SESSION['reg_data'], $_SESSION['reg_otp']);
                    header("Location: ../index.php");
                    exit;
                }
            } else {
                incrementLoginAttempts($pdo);
                $error = "Invalid code."; $mode = 'register'; $sub_mode = 'verify';
            }
        }

        // D. FORGOT INIT
        elseif ($action === 'forgot_init') {
            if (!checkLoginRateLimit($pdo)) {
                $error = "Too many attempts. Please wait 15 minutes.";
            } else {
                $email = trim($_POST['email']);
                $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user) {
                    $otp = random_int(100000, 999999);
                    $expiry = date('Y-m-d H:i:s', time() + 300);
                    
                    // Store in DB for persistence
                    $upd = $pdo->prepare("UPDATE users SET otp = ?, otp_expires_at = ? WHERE email = ?");
                    if ($upd->execute([$otp, $expiry, $email])) {
                        $_SESSION['forgot_email'] = $email; // Keep email in session to identify user during reset
                        $res = sendOTPEmail($email, $otp, $user['name']);
                        if ($res['status']) {
                            $mode = 'forgot'; $sub_mode = 'verify';
                            $success = "Reset code sent to $email.";
                        } else {
                            $error = "Failed to send email. Try again later.";
                        }
                    } else {
                        $error = "Database error. Please try again.";
                    }
                } else {
                    incrementLoginAttempts($pdo);
                    $error = "Email not found.";
                }
            }
        }

        // E. FORGOT VERIFY
        elseif ($action === 'forgot_verify') {
            $otp = trim($_POST['otp']);
            $email = $_SESSION['forgot_email'] ?? '';

            if (empty($email)) {
                $error = "Session expired. Restart the process.";
                $mode = 'forgot'; $sub_mode = 'init';
            } else {
                $stmt = $pdo->prepare("SELECT otp, otp_expires_at FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $row = $stmt->fetch();

                if (!$row || empty($row['otp']) || strtotime($row['otp_expires_at']) < time()) {
                    $error = "Code expired. Request a new one.";
                    $mode = 'forgot'; $sub_mode = 'init';
                } elseif ($row['otp'] === $otp) {
                    $mode = 'forgot'; $sub_mode = 'reset';
                    $success = "Code verified. Please set your new password.";
                    // Clear OTP after successful verification to prevent reuse
                    $pdo->prepare("UPDATE users SET otp = NULL WHERE email = ?")->execute([$email]);
                } else {
                    incrementLoginAttempts($pdo);
                    $error = "Invalid code."; $mode = 'forgot'; $sub_mode = 'verify';
                }
            }
        }

        // F. FORGOT RESET
        elseif ($action === 'forgot_reset') {
            $pass = $_POST['password'];
            $conf = $_POST['confirm_password'];
            if ($pass !== $conf) {
                $error = "Passwords do not match."; $mode = 'forgot'; $sub_mode = 'reset';
            } elseif (strlen($pass) < 8) {
                $error = "Password too short."; $mode = 'forgot'; $sub_mode = 'reset';
            } else {
                $hashed = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ?, otp = NULL, otp_expires_at = NULL WHERE email = ?")->execute([$hashed, $_SESSION['forgot_email']]);
                unset($_SESSION['forgot_email']);
                $success = "Password changed! Please login with your new password.";
                $mode = 'login';
                $sub_mode = 'init';
            }
        }
        // G. RESEND OTP
        elseif ($action === 'resend_otp') {
            if (!checkLoginRateLimit($pdo)) {
                $error = "Too many resend attempts. Please wait.";
            } else {
                $type = $_POST['otp_type'] ?? ''; // 'register' or 'forgot'
                if ($type === 'register' && isset($_SESSION['reg_data'])) {
                    $otp = random_int(100000, 999999);
                    $_SESSION['reg_otp'] = hash_hmac('sha256', (string)$otp, OTP_SECRET_KEY);
                    $_SESSION['reg_otp_expiry'] = time() + 300;
                    $res = sendOTPEmail($_SESSION['reg_data']['email'], $otp, $_SESSION['reg_data']['name'], $_SESSION['reg_data']['user_uid']);
                    if ($res['status']) $success = "New verification code sent.";
                    else $error = "Failed to resend. Try again.";
                    $mode = 'register'; $sub_mode = 'verify';
                } elseif ($type === 'forgot' && isset($_SESSION['forgot_email'])) {
                    $email = $_SESSION['forgot_email'];
                    $otp = random_int(100000, 999999);
                    $expiry = date('Y-m-d H:i:s', time() + 300);
                    $upd = $pdo->prepare("UPDATE users SET otp = ?, otp_expires_at = ? WHERE email = ?");
                    if ($upd->execute([$otp, $expiry, $email])) {
                        $stmt = $pdo->prepare("SELECT name FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        $un = $stmt->fetchColumn();
                        $res = sendOTPEmail($email, $otp, $un);
                        if ($res['status']) $success = "New reset code sent.";
                        else $error = "Failed to resend. Try again.";
                    }
                    $mode = 'forgot'; $sub_mode = 'verify';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Access - Labour On Demand</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        :root { --primary: #3b82f6; --bg: #0f172a; --card: rgba(30, 41, 59, 0.8); }
        body { font-family: system-ui, sans-serif; background: var(--bg); color: #fff; min-height: 100vh; display: grid; place-items: center; }
        .auth-card { background: var(--card); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; width: 100%; max-width: 480px; overflow: hidden; backdrop-filter: blur(10px); }
        .auth-header { background: linear-gradient(135deg, var(--primary), #2563eb); padding: 2rem; text-align: center; }
        .auth-body { padding: 2rem; }
        .form-control { background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding-right: 40px; }
        .form-control:focus { background: rgba(0,0,0,0.5); color: #fff; border-color: var(--primary); box-shadow: none; }
        .pass-wrapper { position: relative; }
        .toggle-pass { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; z-index: 10; }
        .strength-meter { height: 4px; border-radius: 2px; background: #334155; margin-top: 6px; overflow: hidden; transition: 0.3s; }
        .strength-fill { height: 100%; width: 0%; background: #ef4444; transition: width 0.3s, background 0.3s; }
        .strength-text { font-size: 0.75rem; color: #94a3b8; margin-top: 4px; display: block; }
        .btn-auth { background: var(--primary); color: #fff; width: 100%; padding: 0.8rem; border-radius: 8px; font-weight: 600; border: none; }
        .btn-auth:hover { opacity: 0.9; }
        a { color: var(--primary); text-decoration: none; }
        .is-invalid { border-color: #ef4444 !important; }
        .invalid-feedback { color: #ef4444; font-size: 0.8em; margin-top: 4px; display: none; }
        .is-invalid + .invalid-feedback { display: block; }
        .req-list { list-style: none; padding: 0; margin-top: 5px; font-size: 0.75em; display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
        .req-item { color: #94a3b8; display: flex; align-items: center; gap: 5px; }
        .req-item.met { color: #22c55e; }
        .req-item i { font-size: 0.8em; }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-header">
        <h3 class="mb-0 fw-bold"><?php echo $mode === 'register' ? 'Register' : ($mode === 'forgot' ? 'Recovery' : 'Welcome Back'); ?></h3>
        <p class="mb-0 text-white-50 small">Customer Portal</p>
    </div>
    <div class="auth-body">
        <?php if($error): ?><div class="alert alert-danger py-2"><?php echo $error; ?></div><?php endif; ?>
        <?php if($success): ?><div class="alert alert-success py-2"><?php echo $success; ?></div><?php endif; ?>

        <form method="POST" action="?mode=<?php echo $mode; ?>&sub_mode=<?php echo $sub_mode; ?>">
            <?php echo csrf_input(); ?>
            <?php renderHoneypot(); ?>

            <?php if($mode === 'login'): ?>
                <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between"><label>Password</label><a href="?mode=forgot" class="small">Forgot?</a></div>
                    <div class="pass-wrapper">
                        <input type="password" name="password" class="form-control" required>
                        <i class="fas fa-eye toggle-pass"></i>
                    </div>
                </div>
                <input type="hidden" name="action" value="login">

            <?php elseif($mode === 'register'): ?>
                <?php if($sub_mode === 'init'): ?>
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label>Name</label>
                        <input type="text" name="name" id="regName" class="form-control" required placeholder="Full Name">
                        <div class="invalid-feedback">Please enter your full name.</div>
                    </div>
                    <div class="col">
                        <label>Phone</label>
                        <input type="text" name="phone" id="regPhone" class="form-control" required pattern="\d{10}" placeholder="10 digits">
                        <div class="invalid-feedback">Enter a 10-digit number.</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" id="regEmail" class="form-control" required>
                    <div class="invalid-feedback">Enter a valid email address.</div>
                </div>
                <div class="mb-3">
                    <label>Create Password</label>
                    <div class="pass-wrapper">
                        <input type="password" name="password" id="regPass" class="form-control" required onkeyup="checkStrength(this.value)">
                        <i class="fas fa-eye toggle-pass"></i>
                    </div>
                    <div class="strength-meter"><div class="strength-fill" id="strBar"></div></div>
                    <ul class="req-list" id="passReqs">
                        <li class="req-item" data-id="len"><i class="fas fa-circle"></i> 8+ Characters</li>
                        <li class="req-item" data-id="up"><i class="fas fa-circle"></i> Uppercase</li>
                        <li class="req-item" data-id="low"><i class="fas fa-circle"></i> Lowercase</li>
                        <li class="req-item" data-id="num"><i class="fas fa-circle"></i> Number</li>
                        <li class="req-item" data-id="spec"><i class="fas fa-circle"></i> Special</li>
                    </ul>
                    <div class="invalid-feedback">Password requirements not met.</div>
                </div>
                <div class="mb-3">
                    <label>Confirm Password</label>
                    <div class="pass-wrapper">
                        <input type="password" name="confirm_password" id="regConfirm" class="form-control" required>
                        <i class="fas fa-eye toggle-pass"></i>
                    </div>
                    <div class="invalid-feedback">Passwords do not match.</div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <label>PIN</label>
                        <input type="text" name="pin_code" id="regPin" class="form-control" required placeholder="000000" pattern="\d{6}">
                        <div class="invalid-feedback">6 digits.</div>
                    </div>
                    <div class="col-8">
                        <label>Address</label>
                        <input type="text" name="address_details" id="regAddr" class="form-control" required>
                        <div class="invalid-feedback">Enter address.</div>
                    </div>
                </div>
                <input type="hidden" name="action" value="register_init">
                <?php else: ?>
                    <div class="mb-3 text-center">
                        <label>Enter Verification Code</label>
                        <input type="text" name="otp" class="form-control text-center fs-4 letter-spacing-2" maxlength="6" pattern="\d{6}" inputmode="numeric" title="6-digit numeric code" required>
                        <div class="mt-2 small text-white-50">Expires in: <span id="timer" class="text-primary fw-bold">05:00</span></div>
                        <div id="resend-container" class="mt-2 d-none text-center">
                            <button type="submit" name="action" value="resend_otp" class="btn btn-link btn-sm text-primary p-0 text-decoration-none">Resend Code</button>
                            <input type="hidden" name="otp_type" value="register">
                        </div>
                    </div>
                    <input type="hidden" name="action" value="register_verify">
                <?php endif; ?>

            <?php elseif($mode === 'forgot'): ?>
                <?php if($sub_mode === 'init'): ?>
                    <div class="mb-3"><label>Registered Email</label><input type="email" name="email" class="form-control" required></div>
                    <input type="hidden" name="action" value="forgot_init">
                <?php elseif($sub_mode === 'verify'): ?>
                    <div class="mb-3 text-center">
                        <label>Enter Reset Code</label>
                        <input type="text" name="otp" class="form-control text-center fs-4 letter-spacing-2" maxlength="6" pattern="\d{6}" inputmode="numeric" title="6-digit numeric code" required>
                        <div class="mt-2 small text-white-50">Expires in: <span id="timer" class="text-primary fw-bold">05:00</span></div>
                        <div id="resend-container" class="mt-2 d-none text-center">
                            <button type="submit" name="action" value="resend_otp" class="btn btn-link btn-sm text-primary p-0 text-decoration-none">Resend Code</button>
                            <input type="hidden" name="otp_type" value="forgot">
                        </div>
                    </div>
                    <input type="hidden" name="action" value="forgot_verify">
                <?php elseif($sub_mode === 'reset'): ?>
                    <div class="mb-3">
                        <label>New Password</label>
                        <div class="pass-wrapper"><input type="password" name="password" id="regPass" class="form-control" required onkeyup="checkStrength(this.value)"><i class="fas fa-eye toggle-pass"></i></div>
                        <div class="strength-meter"><div class="strength-fill" id="strBar"></div></div>
                        <ul class="req-list" id="passReqs">
                            <li class="req-item" data-id="len"><i class="fas fa-circle"></i> 8+ Characters</li>
                            <li class="req-item" data-id="up"><i class="fas fa-circle"></i> Uppercase</li>
                            <li class="req-item" data-id="low"><i class="fas fa-circle"></i> Lowercase</li>
                            <li class="req-item" data-id="num"><i class="fas fa-circle"></i> Number</li>
                            <li class="req-item" data-id="spec"><i class="fas fa-circle"></i> Special</li>
                        </ul>
                    </div>
                    <div class="mb-3">
                        <label>Confirm New Password</label>
                        <div class="pass-wrapper"><input type="password" name="confirm_password" id="regConfirm" class="form-control" required><i class="fas fa-eye toggle-pass"></i></div>
                    </div>
                    <input type="hidden" name="action" value="forgot_reset">
                <?php endif; ?>
            <?php endif; ?>

            <?php echo '<div class="g-recaptcha my-3" data-sitekey="6LfUczssAAAAAJAyN5ozYXwMRzPfmfnzex9NRLdu"></div>'; ?>
            
            <button class="btn-auth"><?php echo $sub_mode === 'init' && $mode === 'login' ? 'Login' : 'Continue'; ?></button>
        </form>
        
        <div class="text-center mt-3 pt-3 border-top border-secondary">
            <?php if($mode === 'login'): ?>
                <a href="?mode=register" class="small text-decoration-none text-light opacity-75">New user? <strong>Register now</strong></a>
                <a href="../worker/auth.php" class="small text-primary mt-2 d-block fw-bold">Worker Portal</a>
            <?php else: ?>
                <a href="?mode=login" class="btn btn-outline-light btn-sm rounded-pill px-4 mt-2">Back to Login</a>
                <div class="mt-2"><a href="../worker/auth.php?mode=register" class="small text-white-50 opacity-75">Are you a Worker? <strong>Join us here</strong></a></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
// OTP Countdown Timer
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
            display.parentElement.innerHTML = '<span class="text-danger">Code expired. Please refresh to resend.</span>';
            const btn = document.querySelector('.btn-auth');
            if(btn) btn.disabled = true;
        }
    }, 1000);
}

window.onload = function () {
    var timerDisplay = document.querySelector('#timer');
    if (timerDisplay) {
        startTimer(300, timerDisplay);
        // Show resend button after 30 seconds
        setTimeout(() => {
            const resendCont = document.getElementById('resend-container');
            if(resendCont) resendCont.classList.remove('d-none');
        }, 30000);
    }
};

// Toggle Password
document.querySelectorAll('.toggle-pass').forEach(icon => {
    icon.addEventListener('click', function() {
        const input = this.parentElement.querySelector('input');
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
});

// Strength Checker with specific requirements
function checkStrength(p) {
    const bar = document.getElementById('strBar');
    const items = {
        len: p.length >= 8,
        up: /[A-Z]/.test(p),
        low: /[a-z]/.test(p),
        num: /[0-9]/.test(p),
        spec: /[^A-Za-z0-9]/.test(p)
    };

    let s = 0;
    Object.keys(items).forEach(id => {
        const el = document.querySelector(`.req-item[data-id="${id}"]`);
        if (el) {
            if (items[id]) {
                el.classList.add('met');
                el.querySelector('i').className = 'fas fa-check-circle';
                s++;
            } else {
                el.classList.remove('met');
                el.querySelector('i').className = 'fas fa-circle';
            }
        }
    });

    const colors = ['#ef4444', '#f87171', '#fbbf24', '#60a5fa', '#22c55e'];
    bar.style.width = (s * 20) + '%';
    bar.style.background = colors[s - 1] || '#334155';
    
    return s === 5;
}

// Global Instant Validation
const validateField = (id, condition) => {
    const el = document.getElementById(id);
    if (!el) return;
    if (condition) el.classList.remove('is-invalid');
    else el.classList.add('is-invalid');
};

const listeners = {
    regName: (v) => v.trim().length > 0,
    regPhone: (v) => /^\d{10}$/.test(v),
    regEmail: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v),
    regPass: (v) => checkStrength(v),
    regConfirm: (v) => v === document.getElementById('regPass').value,
    regPin: (v) => /^\d{6}$/.test(v),
    regAddr: (v) => v.trim().length > 0
};

Object.keys(listeners).forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        ['input', 'blur'].forEach(evt => {
            el.addEventListener(evt, () => validateField(id, listeners[id](el.value)));
        });
    }
});

// Prevent form submission if invalid
const authForm = document.querySelector('form');
if (authForm) {
    authForm.addEventListener('submit', (e) => {
        let isFormValid = true;
        const isInit = authForm.action.includes('sub_mode=init');
        
        if (isInit) {
            Object.keys(listeners).forEach(id => {
                const el = document.getElementById(id);
                if (el && !listeners[id](el.value)) {
                    validateField(id, false);
                    isFormValid = false;
                }
            });
        }

        if (!isFormValid) {
            e.preventDefault();
            alert("Please fix the errors in the form before submitting.");
        }
    });
}
</script>
</body>
</html>
