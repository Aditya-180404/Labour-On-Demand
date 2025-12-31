<?php
/**
 * Labour On Demand - Unified Worker Authentication
 * Handles Worker Login, Multi-step Registration, and Password Recovery.
 */

require_once '../includes/security.php';
require_once '../config/db.php';
require_once '../includes/mailer.php';
require_once '../includes/cloudinary_helper.php';
require_once '../includes/utils.php';

// --- DATA FETCHING ---
$stmt_cats = $pdo->query("SELECT id, name FROM categories");
$categories = $stmt_cats->fetchAll();

$error = "";
$success = "";
$mode = $_GET['mode'] ?? 'login';
$sub_mode = $_POST['sub_mode'] ?? ($_GET['sub_mode'] ?? 'init');

if (isset($_SESSION['worker_id'])) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isBotDetected()) die("Bot detected.");

    if (!isset($_POST['csrf_token']) || !verifyCSRF($_POST['csrf_token'])) {
        $error = "Security mismatch. Refresh page.";
    } elseif (!isset($_POST['g-recaptcha-response']) || !verifyCaptcha($_POST['g-recaptcha-response'])) {
        $error = "CAPTCHA failed.";
    } else {
        $action = $_POST['action'] ?? '';

        // LOGIN
        if ($action === 'login') {
            if (!checkLoginRateLimit($pdo)) {
                $error = "Locked: Too many attempts.";
            } else {
                $email = trim($_POST['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Invalid email format.";
                } else {
                    $stmt = $pdo->prepare("SELECT id, name, password, status FROM workers WHERE email = ?");
                    $stmt->execute([$email]);
                    $worker = $stmt->fetch();

                    if ($worker && password_verify($_POST['password'], $worker['password'])) {
                        if ($worker['status'] !== 'approved') {
                            $error = "Account is " . htmlspecialchars($worker['status']) . ". Wait for approval.";
                        } else {
                            if (isset($_SESSION['login_attempts'])) unset($_SESSION['login_attempts']);
                            session_regenerate_id(true);
                            $_SESSION['worker_id'] = $worker['id'];
                            $_SESSION['worker_name'] = $worker['name'];
                            rotateCSRF(); // Security Enhancement
                            header("Location: dashboard.php");
                            exit;
                        }
                    } else {
                        incrementLoginAttempts($pdo);
                        $error = "Invalid credentials.";
                    }
                }
            }
        }

        // REGISTER STEP 1
        elseif ($action === 'register_init') {
            $pass = $_POST['password'];
            $conf = $_POST['confirm_password'];
            $pins_str = trim($_POST['pin_code']);
            $phone = trim($_POST['phone']);
            $email = trim($_POST['email']);

            // Validate Pins
            $pins_arr = explode(',', $pins_str);
            $valid_pins = true;
            foreach($pins_arr as $p) if(!ctype_digit(trim($p))) $valid_pins = false;

            if ($pass !== $conf) {
                $error = "Passwords to not match.";
            } elseif ($_POST['hourly_rate'] <= 0) {
                $error = "Hourly rate must be greater than zero.";
            } elseif (empty($pins_str) || !$valid_pins) {
                $error = "Invalid PINs (Numbers only).";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid Email.";
            } elseif (!preg_match('/^\d{10}$/', $phone)) {
                $error = "Phone must be 10 digits.";
            } elseif (!preg_match('/^\d{12}$/', trim($_POST['adhar_card']))) {
                $error = "Aadhar must be 12 digits.";
            } else {
                // Check Email
                $stmt = $pdo->prepare("SELECT id FROM workers WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "Email already registered.";
                } else {
                    $_SESSION['w_reg'] = [
                        'name' => trim($_POST['name']), 'email' => $email,
                        'pass' => password_hash($pass, PASSWORD_DEFAULT),
                        'phone' => $phone, 'cat_id' => $_POST['service_category_id'],
                        'bio' => trim($_POST['bio']), 'rate' => $_POST['hourly_rate'],
                        'pins' => $pins_str, 'address' => trim($_POST['address']),
                        'adhar_no' => trim($_POST['adhar_card']),
                        'loc' => trim($_POST['working_location'])
                    ];
                    $mode = 'register'; $sub_mode = 'docs';
                }
            }
        }

        // REGISTER STEP 2 (Docs)
        elseif ($action === 'register_docs') {
            if (!isset($_SESSION['w_reg'])) { $error = "Session timeout."; $mode = 'register'; $sub_mode = 'init'; }
            else {
                $cld = CloudinaryHelper::getInstance();
                $uploads = [
                    'profile_image' => ['folder' => CLD_FOLDER_WORKER_PROFILE],
                    'aadhar_photo' => ['folder' => CLD_FOLDER_WORKER_DOCS . 'aadhar/'],
                    'pan_photo' => ['folder' => CLD_FOLDER_WORKER_DOCS . 'pan/'],
                    'signature_photo' => ['folder' => CLD_FOLDER_WORKER_DOCS . 'signature/']
                ];
                
                foreach ($uploads as $field => $cfg) {
                    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === 0) {
                        if (Validator::isMaliciousFile($_FILES[$field]['name'])) {
                            $error = "Malicious file detected in $field."; break;
                        }
                        $up = $cld->uploadImage($_FILES[$field]['tmp_name'], $cfg['folder']);
                        if ($up) {
                            $_SESSION['w_reg'][$field] = $up['url'];
                            $_SESSION['w_reg'][$field.'_id'] = $up['public_id'];
                        } else { $error="Upload failed for $field"; break; }
                    } else { $error="$field missing."; break; }
                }

                if (empty($error) && isset($_FILES['prev_work'])) {
                    $urls = []; $ids = [];
                    foreach ($_FILES['prev_work']['name'] as $i => $name) {
                        if ($_FILES['prev_work']['error'][$i] === 0) {
                            if (Validator::isMaliciousFile($name)) { $error="Malicious portfolio file."; break; }
                            $up = $cld->uploadImage($_FILES['prev_work']['tmp_name'][$i], CLD_FOLDER_WORKER_PREV_WORK);
                            if ($up) { $urls[] = $up['url']; $ids[] = $up['public_id']; }
                        }
                    }
                    if(empty($error)) {
                        $_SESSION['w_reg']['prev_work'] = implode(',', $urls);
                        $_SESSION['w_reg']['prev_work_ids'] = implode(',', $ids);
                    }
                }

                if (empty($error)) {
                    $otp = random_int(100000, 999999);
                    $uid = generateUID($pdo, 'worker');
                    $_SESSION['w_reg']['uid'] = $uid;
                    $_SESSION['w_reg_otp'] = hash_hmac('sha256', (string)$otp, OTP_SECRET_KEY);
                    $_SESSION['w_reg_otp_expiry'] = time() + 300;
                    sendOTPEmail($_SESSION['w_reg']['email'], $otp, $_SESSION['w_reg']['name'], $uid);
                    $mode = 'register'; $sub_mode = 'verify';
                    $success = "Verification OTP sent.";
                }
            }
        }

        // REGISTER STEP 3
        elseif ($action === 'register_verify') {
            $otp = trim($_POST['otp']);
            if (!ctype_digit($otp)) $error = "Invalid OTP.";
            elseif (!isset($_SESSION['w_reg_otp']) || time() > $_SESSION['w_reg_otp_expiry']) $error="Expired OTP.";
            elseif (hash_equals($_SESSION['w_reg_otp'], hash_hmac('sha256', $otp, OTP_SECRET_KEY))) {
                $w = $_SESSION['w_reg'];
                $stmt = $pdo->prepare("INSERT INTO workers (name, profile_image, profile_image_public_id, email, password, phone, service_category_id, bio, hourly_rate, pin_code, address, adhar_card, aadhar_photo, aadhar_photo_public_id, pan_photo, pan_photo_public_id, signature_photo, signature_photo_public_id, previous_work_images, previous_work_public_ids, working_location, worker_uid, status, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0)");
                $params = [
                    $w['name'], $w['profile_image'], $w['profile_image_id'], $w['email'], $w['pass'], $w['phone'], $w['cat_id'], $w['bio'], $w['rate'], $w['pins'], $w['address'], $w['adhar_no'], $w['aadhar_photo'], $w['aadhar_photo_id'], $w['pan_photo'], $w['pan_photo_id'], $w['signature_photo'], $w['signature_photo_id'], $w['prev_work']??'', $w['prev_work_ids']??'', $w['loc'], $w['uid']
                ];
                if ($stmt->execute($params)) {
                    rotateCSRF(); // Security Enhancement
                    $success = "Registration Pending Approval."; unset($_SESSION['w_reg'], $_SESSION['w_reg_otp']); $mode='login';
                } else $error="DB Error.";
            } else {
                incrementLoginAttempts($pdo);
                $error="Wrong OTP.";
            }
        }

        // FORGOT
        elseif ($action === 'forgot_init') {
            if (!checkLoginRateLimit($pdo)) {
                $error = "Too many attempts. Locked for 15 minutes.";
            } else {
                $email = trim($_POST['email']);
                $stmt = $pdo->prepare("SELECT id, name FROM workers WHERE email = ?");
                $stmt->execute([$email]);
                $worker = $stmt->fetch();
                if ($worker) {
                    $otp = random_int(100000, 999999);
                    $expiry = date('Y-m-d H:i:s', time() + 300);

                    // DB Store
                    $upd = $pdo->prepare("UPDATE workers SET otp = ?, otp_expires_at = ? WHERE email = ?");
                    if ($upd->execute([$otp, $expiry, $email])) {
                        $_SESSION['w_forgot_email'] = $email;
                        $res = sendOTPEmail($email, $otp, $worker['name']);
                        if ($res['status']) {
                            $mode='forgot'; $sub_mode='verify'; $success="Reset code sent to $email.";
                        } else {
                            $error = "Failed to send email. Try again.";
                        }
                    } else {
                        $error = "Database error. Try again.";
                    }
                } else {
                    incrementLoginAttempts($pdo);
                    $error="Email not found.";
                }
            }
        }
        elseif ($action === 'forgot_verify') {
             $otp = trim($_POST['otp']);
             $email = $_SESSION['w_forgot_email'] ?? '';

             if (empty($email)) {
                 $error = "Session timeout. Restart.";
                 $mode='forgot'; $sub_mode='init';
             } else {
                 $stmt = $pdo->prepare("SELECT otp, otp_expires_at FROM workers WHERE email = ?");
                 $stmt->execute([$email]);
                 $row = $stmt->fetch();

                 if (!$row || empty($row['otp']) || strtotime($row['otp_expires_at']) < time()) {
                     $error = "Code expired. Request a new one.";
                     $mode='forgot'; $sub_mode='init';
                 } elseif ($row['otp'] === $otp) {
                     $mode='forgot'; $sub_mode='reset';
                     $success = "Code verified. Set new password.";
                     $pdo->prepare("UPDATE workers SET otp = NULL WHERE email = ?")->execute([$email]);
                 } else {
                     incrementLoginAttempts($pdo);
                     $error="Invalid code.";
                 }
             }
        }
        elseif ($action === 'forgot_reset') {
            $pass = $_POST['password'];
            $conf = $_POST['confirm_password'];
            if($pass !== $conf) { $error="Passwords mismatch."; $mode='forgot'; $sub_mode='reset'; }
            elseif(strlen($pass) < 8) { $error="Weak password."; $mode='forgot'; $sub_mode='reset'; }
            else {
                $h = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE workers SET password = ?, otp = NULL, otp_expires_at = NULL WHERE email = ?")->execute([$h, $_SESSION['w_forgot_email']]);
                unset($_SESSION['w_forgot_email']);
                $success="Password reset successful! Please login."; $mode='login'; $sub_mode='init';
            }
        }
        elseif ($action === 'resend_otp') {
            $type = $_POST['otp_type'] ?? '';
            if ($type === 'register' && isset($_SESSION['w_reg'])) {
                $otp = random_int(100000, 999999);
                $_SESSION['w_reg_otp'] = hash_hmac('sha256', (string)$otp, OTP_SECRET_KEY);
                $_SESSION['w_reg_otp_expiry'] = time() + 300;
                sendOTPEmail($_SESSION['w_reg']['email'], $otp, $_SESSION['w_reg']['name'], $_SESSION['w_reg']['uid']);
                $success = "New verification OTP sent.";
                $mode = 'register'; $sub_mode = 'verify';
            } elseif ($type === 'forgot' && isset($_SESSION['w_forgot_email'])) {
                if (!checkLoginRateLimit($pdo)) {
                    $error = "Too many attempts. Please wait.";
                } else {
                    $email = $_SESSION['w_forgot_email'];
                    $stmt = $pdo->prepare("SELECT name FROM workers WHERE email = ?");
                    $stmt->execute([$email]);
                    $wn = $stmt->fetchColumn(); $otp = random_int(100000, 999999);
                    $expiry = date('Y-m-d H:i:s', time() + 300);
                    $upd = $pdo->prepare("UPDATE workers SET otp = ?, otp_expires_at = ? WHERE email = ?");
                    if ($upd->execute([$otp, $expiry, $email])) {
                        sendOTPEmail($email, $otp, $wn);
                        $success = "New reset code sent.";
                    }
                }
                $mode = 'forgot'; $sub_mode = 'verify';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Worker Portal - Labour On Demand</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script src="../assets/js/image_compressor.js"></script>
    <style>
        :root { --primary: #f59e0b; --bg: #0f172a; --card: rgba(30, 41, 59, 0.85); --text: #f8fafc; }
        body { font-family: system-ui, sans-serif; background: linear-gradient(135deg, #0f172a, #1e1b4b); color: var(--text); padding: 20px; min-height: 100vh; display: flex; justify-content: center; align-items: center; }
        .auth-card { background: var(--card); border-radius: 20px; width: 100%; max-width: 650px; border: 1px solid rgba(245, 158, 11, 0.2); overflow: hidden; backdrop-filter: blur(10px); }
        .auth-header { background: linear-gradient(135deg, #f59e0b, #d97706); padding: 25px; text-align: center; color: #0f172a; }
        .auth-body { padding: 30px; }
        .form-control, .form-select { background: rgba(15,23,42,0.6); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding-right: 40px; }
        .form-control:focus { background: rgba(15,23,42,0.8); border-color: var(--primary); color: #fff; box-shadow: none; }
        .btn-auth { background: var(--primary); color: #0f172a; border: none; padding: 12px; width: 100%; font-weight: 700; border-radius: 10px; }
        .btn-auth:hover { background: #fbbf24; }
        .pass-wrapper { position: relative; }
        .toggle-pass { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8; z-index: 10; }
        .strength-meter { height: 4px; background: #334155; margin-top: 5px; border-radius: 2px; }
        .strength-fill { height: 100%; width: 0%; transition: 0.3s; }
        .tag-container { display: flex; flex-wrap: wrap; gap: 5px; padding: 8px; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; background: rgba(15,23,42,0.6); }
        .tag { background: rgba(245,158,11,0.2); color: var(--primary); padding: 2px 8px; border-radius: 15px; font-size: 0.8em; display: flex; align-items: center; gap: 5px; }
        .tag-input { background: transparent; border: none; color: white; flex: 1; outline: none; min-width: 100px; }
        a { color: var(--primary); text-decoration: none; }
        .is-invalid { border-color: #ef4444 !important; }
        .invalid-feedback { color: #ef4444; font-size: 0.8em; margin-top: 4px; display: none; }
        .is-invalid + .invalid-feedback { display: block; }
        .req-list { list-style: none; padding: 0; margin-top: 5px; font-size: 0.75em; display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
        .req-item { color: #94a3b8; display: flex; align-items: center; gap: 5px; }
        .req-item.met { color: #22c55e; }
        .req-item i { font-size: 0.8em; }
        
        .gallery-container { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        .gallery-item { width: 80px; height: 80px; border-radius: 10px; overflow: hidden; position: relative; border: 1px solid rgba(255,255,255,0.1); }
        .gallery-item img { width: 100%; height: 100%; object-fit: cover; }
        .gallery-item .remove-btn { position: absolute; top: 2px; right: 2px; background: rgba(0,0,0,0.5); color: #ef4444; border: none; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .add-gallery-btn { width: 80px; height: 80px; border-radius: 10px; border: 2px dashed rgba(245,158,11,0.5); display: flex; align-items: center; justify-content: center; font-size: 24px; color: var(--primary); cursor: pointer; transition: 0.3s; }
        .add-gallery-btn:hover { background: rgba(245,158,11,0.1); border-color: var(--primary); }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-header"><h3 class="fw-bold m-0"><?php echo $mode=='register'?'Worker Registration':($mode=='forgot'?'Reset Password':'Worker Login'); ?></h3></div>
    <div class="auth-body">
        <?php if($error): ?><div class="alert alert-danger py-2"><?php echo $error; ?></div><?php endif; ?>
        <?php if($success): ?><div class="alert alert-success py-2"><?php echo $success; ?></div><?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" action="?mode=<?php echo $mode; ?>&sub_mode=<?php echo $sub_mode; ?>">
        <?php echo csrf_input(); ?>
        <?php renderHoneypot(); ?>

        <?php if($mode === 'login'): ?>
            <div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" required></div>
            <div class="mb-3">
                <div class="d-flex justify-content-between"><label>Password</label><a href="?mode=forgot" class="small">Forgot?</a></div>
                <div class="pass-wrapper"><input type="password" name="password" class="form-control" required><i class="fas fa-eye toggle-pass"></i></div>
            </div>
            <input type="hidden" name="action" value="login">

        <?php elseif($mode === 'register'): ?>
            <?php if($sub_mode === 'init'): ?>
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label>Name</label>
                        <input type="text" name="name" id="regName" class="form-control" required>
                        <div class="invalid-feedback">Please enter your full name.</div>
                    </div>
                    <div class="col">
                        <label>Phone</label>
                        <input type="text" name="phone" id="regPhone" class="form-control" required pattern="\d{10}">
                        <div class="invalid-feedback">Enter a valid 10-digit number.</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" id="regEmail" class="form-control" required>
                    <div class="invalid-feedback">Enter a valid email address.</div>
                </div>
                <div class="row g-2 mb-3">
                     <div class="col">
                         <label>Password</label>
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
                     <div class="col">
                         <label>Confirm</label>
                         <div class="pass-wrapper">
                             <input type="password" name="confirm_password" id="regConfirm" class="form-control" required>
                             <i class="fas fa-eye toggle-pass"></i>
                         </div>
                         <div class="invalid-feedback">Passwords do not match.</div>
                     </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label>Category</label>
                        <select name="service_category_id" id="regCat" class="form-select" required>
                            <option value="">Select</option><?php foreach($categories as $c) echo "<option value='{$c['id']}'>{$c['name']}</option>"; ?>
                        </select>
                        <div class="invalid-feedback">Please select a category.</div>
                    </div>
                    <div class="col">
                        <label>Rate (₹)</label>
                        <input type="number" name="hourly_rate" id="regRate" class="form-control" required min="1" step="0.01">
                        <div class="invalid-feedback">Rate must be at least 1.</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label>Service Area PINs (Add multiple)</label>
                    <div class="tag-container" id="pin-wrapper">
                        <input type="text" class="tag-input" id="pin-input">
                        <button type="button" class="btn btn-sm btn-warning rounded-pill" onclick="addPin()">+</button>
                    </div>
                    <input type="hidden" name="pin_code" id="pin_code_hidden" required>
                    <div id="pin-error" class="invalid-feedback">Please add at least one service area PIN.</div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col">
                        <label>Aadhar No.</label>
                        <input type="text" name="adhar_card" id="regAadhar" class="form-control" required pattern="\d{12}">
                        <div class="invalid-feedback">Must be a 12-digit number.</div>
                    </div>
                    <div class="col">
                        <label>City</label>
                        <input type="text" name="working_location" id="regCity" class="form-control" required>
                        <div class="invalid-feedback">Please specify your city.</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label>Address</label>
                    <input type="text" name="address" id="regAddress" class="form-control" required>
                    <div class="invalid-feedback">Please enter your full address.</div>
                </div>
                <div class="mb-3"><label>Bio</label><textarea name="bio" id="regBio" class="form-control" rows="2"></textarea></div>
                <input type="hidden" name="action" value="register_init">
            <?php elseif($sub_mode === 'docs'): ?>
                <div class="alert alert-info py-1 small">Securely upload documents</div>
                <div class="row g-2 mb-2">
                    <div class="col"><label>Profile Pic</label><input type="file" name="profile_image" class="form-control" required></div>
                    <div class="col"><label>Signature</label><input type="file" name="signature_photo" class="form-control" required></div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col"><label>Aadhar Img</label><input type="file" name="aadhar_photo" class="form-control" required></div>
                    <div class="col"><label>PAN Img</label><input type="file" name="pan_photo" class="form-control" required></div>
                </div>
                <div class="mb-3">
                    <label>Portfolio Gallery (Add multiple)</label>
                    <div class="gallery-container" id="portfolioGallery">
                        <div class="add-gallery-btn" onclick="document.getElementById('portfolioInput').click()"><i class="fas fa-plus"></i></div>
                    </div>
                    <input type="file" id="portfolioInput" class="d-none" multiple accept="image/*" onchange="handlePortfolio(this)">
                    <div id="portfolioInputsContainer"></div> <!-- Hidden for server submission -->
                    <div class="mt-1 small text-white-50">Upload previous work photos to showcase your skills.</div>
                </div>
                <input type="hidden" name="action" value="register_docs">
            <?php else: ?>
                <div class="text-center mb-3">
                    <label>OTP Code</label>
                    <input type="text" name="otp" class="form-control text-center fs-3 letter-spacing-2" maxlength="6" pattern="\d{6}" inputmode="numeric" title="6-digit numeric code" required>
                    <div class="mt-2 small text-white-50">Expires in: <span id="timer" class="text-warning fw-bold">05:00</span></div>
                    <div id="resend-container" class="mt-2 d-none">
                        <button type="submit" name="action" value="resend_otp" class="btn btn-link btn-sm text-warning p-0 text-decoration-none">Resend Code</button>
                        <input type="hidden" name="otp_type" value="register">
                    </div>
                </div>
                <input type="hidden" name="action" value="register_verify">
            <?php endif; ?>

        <?php elseif($mode === 'forgot'): ?>
             <?php if($sub_mode === 'init'): ?><div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" required></div><input type="hidden" name="action" value="forgot_init">
             <?php elseif($sub_mode === 'verify'): ?>
                <div class="mb-3 text-center">
                    <label>Enter Reset Code</label>
                    <input type="text" name="otp" class="form-control text-center fs-3 letter-spacing-2" maxlength="6" pattern="\d{6}" inputmode="numeric" title="6-digit numeric code" required>
                    <div class="mt-2 small text-white-50">Expires in: <span id="timer" class="text-warning fw-bold">05:00</span></div>
                    <div id="resend-container" class="mt-2 d-none">
                        <button type="submit" name="action" value="resend_otp" class="btn btn-link btn-sm text-warning p-0 text-decoration-none">Resend Code</button>
                        <input type="hidden" name="otp_type" value="forgot">
                    </div>
                </div>
                <input type="hidden" name="action" value="forgot_verify">
             <?php else: ?>
                <div class="mb-3"><label>New Password</label><div class="pass-wrapper"><input type="password" name="password" id="regPass" class="form-control" required onkeyup="checkStrength(this.value)"><i class="fas fa-eye toggle-pass"></i></div><div class="strength-meter"><div class="strength-fill" id="strBar"></div><span class="small text-white-50" id="strText">Enter 8+ characters</span></div></div>
                <div class="mb-3"><label>Confirm New Password</label><div class="pass-wrapper"><input type="password" name="confirm_password" class="form-control" required><i class="fas fa-eye toggle-pass"></i></div></div>
                <input type="hidden" name="action" value="forgot_reset">
             <?php endif; ?>
        <?php endif; ?>
        
        <?php echo '<div class="g-recaptcha my-3 d-flex justify-content-center" data-sitekey="6LfUczssAAAAAJAyN5ozYXwMRzPfmfnzex9NRLdu"></div>'; ?>
        <button class="btn-auth"><?php echo $sub_mode==='init' && $mode==='login' ? 'Login' : 'Submit'; ?></button>
        </form>
        <div class="text-center mt-3">
            <?php if($mode === 'login'): ?>
                <div class="mt-2"><a href="?mode=register" class="small text-white-50 text-decoration-none">New worker? <strong>Register now</strong></a></div>
                <div class="mt-1"><a href="../customer/auth.php" class="small text-warning opacity-75">Are you a Customer? <strong>Login here</strong></a></div>
            <?php else: ?>
                <a href="?mode=login" class="btn btn-warning btn-sm rounded-pill px-4 mt-2">Back to Login</a>
                <div class="mt-2"><a href="../customer/auth.php?mode=register" class="small text-white-50 opacity-75">Looking for help? <strong>Register as Customer</strong></a></div>
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

// Portfolio Gallery Logic
let portfolioFiles = [];
function handlePortfolio(input) {
    const container = document.getElementById('portfolioGallery');
    const addButton = container.querySelector('.add-gallery-btn');
    
    Array.from(input.files).forEach(file => {
        if (!file.type.startsWith('image/')) return;
        
        const id = Math.random().toString(36).substr(2, 9);
        portfolioFiles.push({ id, file });
        
        const reader = new FileReader();
        reader.onload = (e) => {
            const item = document.createElement('div');
            item.className = 'gallery-item';
            item.id = `gallery-${id}`;
            item.innerHTML = `
                <img src="${e.target.result}">
                <button type="button" class="remove-btn" onclick="removePortfolio('${id}')"><i class="fas fa-times"></i></button>
            `;
            container.insertBefore(item, addButton);
        };
        reader.readAsDataURL(file);
    });
    input.value = '';
}

function removePortfolio(id) {
    portfolioFiles = portfolioFiles.filter(f => f.id !== id);
    const el = document.getElementById(`gallery-${id}`);
    if(el) el.remove();
}

function syncPortfolioForSubmit() {
    const container = document.getElementById('portfolioInputsContainer');
    if(!container) return;
    container.innerHTML = '';
    
    const dataTransfer = new DataTransfer();
    portfolioFiles.forEach(f => dataTransfer.items.add(f.file));
    
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'file';
    hiddenInput.name = 'prev_work[]';
    hiddenInput.multiple = true;
    hiddenInput.files = dataTransfer.files;
    hiddenInput.style.display = 'none';
    container.appendChild(hiddenInput);
}

// PINs Logic
const pins = []; const pIn = document.getElementById('pin-input'); const pHid = document.getElementById('pin_code_hidden'); const pWrap = document.getElementById('pin-wrapper');
if(pIn){
    function rPins(){ 
        pWrap.querySelectorAll('.tag').forEach(t=>t.remove()); 
        pins.forEach((p,i)=>{ 
            let t=document.createElement('span'); t.className='tag'; t.innerHTML=`${p} <i class="fas fa-times" onclick="remP(${i})"></i>`; pWrap.insertBefore(t,pIn); 
        }); 
        pHid.value=pins.join(','); 
        if(pins.length > 0) {
            const err = document.getElementById('pin-error');
            if(err) err.style.display = 'none';
        }
    }
    function addPin(){ const v=pIn.value.trim(); if(v && /^\d+$/.test(v) && !pins.includes(v)){ pins.push(v); pIn.value=''; rPins(); } }
    window.remP = i => { pins.splice(i,1); rPins(); };
    pIn.addEventListener('keydown', e=>{ if(e.key==='Enter'){e.preventDefault(); addPin();} });
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
    regCat: (v) => v !== "",
    regRate: (v) => parseFloat(v) >= 1,
    regAadhar: (v) => /^\d{12}$/.test(v),
    regCity: (v) => v.trim().length > 0,
    regAddress: (v) => v.trim().length > 0
};

Object.keys(listeners).forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        ['input', 'blur'].forEach(evt => {
            el.addEventListener(evt, () => validateField(id, listeners[id](el.value)));
        });
    }
});

const authForm = document.querySelector('form');
if (authForm) {
    authForm.addEventListener('submit', (e) => {
        if (document.getElementById('portfolioGallery')) {
            syncPortfolioForSubmit();
        }

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

            const pinsInput = document.getElementById('pin_code_hidden');
            if (pinsInput && pinsInput.hasAttribute('required') && !pinsInput.value) {
                const err = document.getElementById('pin-error');
                if(err) err.style.display = 'block';
                isFormValid = false;
            }
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
