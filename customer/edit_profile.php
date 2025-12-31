<?php
require_once '../includes/security.php';
require_once '../config/db.php';
require_once '../includes/cloudinary_helper.php';

// Check User Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = "";
$error = "";

// Handle OTP Generation for Profile Update (AJAX endpoint)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_profile_otp'])) {
    // CSRF Protection for AJAX
    if (!isset($_POST['csrf_token']) || !verifyCSRF($_POST['csrf_token'])) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Security Validation Failed (CSRF).']);
        exit;
    }

    require_once '../includes/mailer.php';
    
    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
    
    $otp = rand(100000, 999999);
    $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));
    
    // Store Hashed OTP
    $hashed_otp = hash_hmac('sha256', (string)$otp, OTP_SECRET_KEY);
    $pdo->prepare("UPDATE users SET otp = ?, otp_expires_at = ? WHERE id = ?")
        ->execute([$hashed_otp, $expiry, $user_id]);
    
    $mail_result = sendOTPEmail($user_data['email'], $otp, $user_data['name']);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $mail_result['status'] ? 'success' : 'error',
        'message' => $mail_result['status'] ? 'OTP sent to ' . $user_data['email'] : $mail_result['message']
    ]);
    exit;
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['send_profile_otp'])) {
    // 1. Bot Detection
    if (isBotDetected()) {
        die("Security validation failed: Bot detected.");
    }
    // 2. CSRF Protection
    if (!isset($_POST['csrf_token']) || !verifyCSRF($_POST['csrf_token'])) {
        die("Security Validation Failed (CSRF). Please refresh the page.");
    }

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $pin_code = trim($_POST['pin_code']);
    $location = trim($_POST['location']);
    $otp_entered = trim($_POST['profile_otp'] ?? '');

    // Check if sensitive fields changed
    $stmt = $pdo->prepare("SELECT email, password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch();

    $email_changed = ($email !== $current_user['email']);
    $password_changed = !empty($new_password);

    if ($email_changed || $password_changed) {
        // Verify OTP
        $stmt_otp = $pdo->prepare("SELECT otp, otp_expires_at FROM users WHERE id = ?");
        $stmt_otp->execute([$user_id]);
        $otp_data = $stmt_otp->fetch();

        $entered_hash = hash_hmac('sha256', (string)$otp_entered, OTP_SECRET_KEY);
        if (empty($otp_entered) || !hash_equals($otp_data['otp'] ?? '', $entered_hash) || strtotime($otp_data['otp_expires_at']) < time()) {
            $error = "Invalid or expired OTP. Please verify your identity to change email/password.";
        } else {
            // Clear OTP after successful verification
            $pdo->prepare("UPDATE users SET otp = NULL WHERE id = ?")->execute([$user_id]);
        }
        
        // Check email uniqueness if changed
        if ($email_changed) {
            $stmt_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt_check->execute([$email, $user_id]);
            if ($stmt_check->fetch()) {
                $error = "This email is already registered by another user.";
            }
        }
    }

    // Validate Inputs
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match('/^\d{10}$/', $phone)) {
        $error = "Phone number must be 10 digits.";
    } elseif (!preg_match('/^\d{6}$/', $pin_code)) {
        $error = "PIN Code must be 6 digits.";
    } elseif ($password_changed && strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters.";
    }

    // Handle File Upload
    $profile_image_url = null;
    $profile_image_public_id = null;

    if (!$error && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        if (Validator::isMaliciousFile($_FILES['profile_image']['name'])) { // Fixed Validator call
            $error = "Security Warning: Potential code file detected in profile image. Please upload a valid image (JPG/PNG/WEBP).";
        } else {
            $allowed_img_ext = array('jpg', 'jpeg', 'png', 'webp');
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed_img_ext)) {
                if ($_FILES['profile_image']['size'] > 10 * 1024 * 1024) {
                    $error = "File size exceeds 10MB limit.";
                } else {
                    $cld = CloudinaryHelper::getInstance();
                    
                    // Fetch old public_id to delete
                    $stmt = $pdo->prepare("SELECT profile_image_public_id FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $old_data = $stmt->fetch();
                    
                    $upload = $cld->uploadImage($_FILES['profile_image']['tmp_name'], CLD_FOLDER_USERS, 'standard');
                    
                    if ($upload) {
                        $profile_image_url = $upload['url'];
                        $profile_image_public_id = $upload['public_id'];
                        
                        // Delete old image if it exists
                        if ($old_data && $old_data['profile_image_public_id']) {
                            $cld->deleteImage($old_data['profile_image_public_id']);
                        }
                    } else {
                        $error = "Failed to upload to cloud storage.";
                    }
                }
            } else {
                $error = "Invalid file type. Only JPG, PNG, WEBP allowed.";
            }
        }
    }

    if (!$error) {
        // Prepare Query
        $sql = "UPDATE users SET name = ?, email = ?, phone = ?, address_details = ?, pin_code = ?, location = ?";
        $params = [$name, $email, $phone, $address, $pin_code, $location];

        if ($password_changed) {
            $sql .= ", password = ?";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        }

        if ($profile_image_url) {
            $sql .= ", profile_image = ?, profile_image_public_id = ?";
            $params[] = $profile_image_url;
            $params[] = $profile_image_public_id;
        }

        $sql .= " WHERE id = ?";
        $params[] = $user_id;

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
             $success = "Profile updated successfully!";
             $_SESSION['user_name'] = $name; // Update session name
        } else {
            $error = "Failed to update database.";
        }
    }
}

// Fetch Current Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-img-preview { width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 3px solid var(--bs-border-color); }
        .card { border: none; border-radius: 20px; overflow: hidden; }
        .card-header { border-bottom: none; }
    </style>
    <script src="../assets/js/theme.js"></script>
    <script src="../assets/js/image_compressor.js"></script>
</head>
<body class="bg-body">
    <?php 
    $path_prefix = '../';
    include '../includes/navbar.php'; 
    ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Edit Profile</h4>
                    </div>
                    <div class="card-body">
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form action="edit_profile.php" method="POST" enctype="multipart/form-data" id="profileForm">
                            <?php echo csrf_input(); ?>
                            <?php renderHoneypot(); ?>
                            <div id="sizeWarning"></div>
                            <div class="text-center mb-4">
                                <?php 
                                    $img_src = $user['profile_image'] && $user['profile_image'] != 'default.png' 
                                        ? $user['profile_image'] 
                                        : "https://via.placeholder.com/150"; 
                                ?>
                                <img src="<?php echo $img_src; ?>" alt="Profile" class="profile-img-preview mb-3">
                                <div class="mb-3">
                                    <h5 class="text-muted mb-1">User ID: <span class="text-primary fw-bold font-monospace"><?php echo htmlspecialchars($user['user_uid'] ?? 'Generating...'); ?></span></h5>
                                    <label for="profile_image" class="form-label">Change Profile Picture</label>
                                    <input type="file" class="form-control" id="profile_image" name="profile_image">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address <small class="text-danger font-monospace">*Sensitive</small></label>
                                    <input type="email" class="form-control sensitive-field" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required data-original="<?php echo htmlspecialchars($user['email']); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label">New Password <small class="text-danger font-monospace">*Sensitive</small></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control sensitive-field" id="new_password" name="new_password" placeholder="Leave blank to keep current">
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="fas fa-eye"></i></button>
                                    </div>
                                    <ul class="password-requirements mt-2 small" id="pass-reqs" style="display:none;">
                                        <li id="req-length" class="text-muted"><i class="fas fa-circle"></i> 8+ chars</li>
                                        <li id="req-upper" class="text-muted"><i class="fas fa-circle"></i> Uppercase</li>
                                        <li id="req-special" class="text-muted"><i class="fas fa-circle"></i> Special</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Detailed Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($user['address_details']); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="pin_code" class="form-label">PIN Code</label>
                                    <input type="text" class="form-control" id="pin_code" name="pin_code" value="<?php echo htmlspecialchars($user['pin_code']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="location" class="form-label">Area / Location</label>
                                    <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($user['location']); ?>">
                                </div>
                            </div>

                            <div id="otpSection" class="mb-3 border p-3 rounded bg-light" style="display: none;">
                                <label for="profile_otp" class="form-label fw-bold"><i class="fas fa-shield-alt me-2 text-primary"></i>Identity Verification</label>
                                <p class="small text-muted mb-2">Changing email or password requires verification. An OTP will be sent to your current email.</p>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="profile_otp" name="profile_otp" placeholder="Enter 6-digit OTP">
                                    <button class="btn btn-primary" type="button" id="sendOtpBtn">Send OTP</button>
                                </div>
                                <div id="otpStatus" class="mt-2 small"></div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" id="updateBtn" class="btn btn-primary">Update Profile</button>
                                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const sensitiveFields = document.querySelectorAll('.sensitive-field');
            const otpSection = document.getElementById('otpSection');
            const sendOtpBtn = document.getElementById('sendOtpBtn');
            const updateBtn = document.getElementById('updateBtn');
            const passInput = document.getElementById('new_password');
            const passReqs = document.getElementById('pass-reqs');
            const profileImageInput = document.getElementById('profile_image');

            // File Size Warning
            profileImageInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                    const warningDiv = document.getElementById('sizeWarning');
                    
                    if (sizeMB > 30) {
                        warningDiv.className = 'alert alert-danger mb-3 small';
                        warningDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> File size is <b>${sizeMB}MB</b>. Infinity Free might block requests over 30MB. Please use files under 10MB.`;
                    } else if (sizeMB > 0) {
                        warningDiv.className = 'alert alert-info mb-3 small';
                        warningDiv.innerHTML = `<i class="fas fa-magic"></i> <strong>Pro Tip:</strong> Any resolution is allowed. We will automatically resize your photo to <b>1024x1024px</b> and compress it to under <b>350KB</b>! <br> Current size: <b>${sizeMB}MB</b>.`;
                    }
                }
            });

            // --- IMAGE COMPRESSION ---
            ImageCompressor.attach('profileForm', 'Optimizing your profile photo...');
            
            // Password toggle
            document.getElementById('togglePassword').addEventListener('click', function() {
                const type = passInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });

            // Detect changes
            function checkChanges() {
                let changed = false;
                sensitiveFields.forEach(field => {
                    if (field.id === 'email') {
                        if (field.value !== field.dataset.original) changed = true;
                    } else if (field.id === 'new_password') {
                        if (field.value.length > 0) changed = true;
                    }
                });

                if (changed) {
                    otpSection.style.display = 'block';
                    document.getElementById('profile_otp').required = true;
                } else {
                    otpSection.style.display = 'none';
                    document.getElementById('profile_otp').required = false;
                }
            }

            sensitiveFields.forEach(field => {
                field.addEventListener('input', () => {
                    checkChanges();
                    if (field.id === 'new_password') {
                        passReqs.style.display = field.value.length > 0 ? 'block' : 'none';
                        updateRequirements(field.value);
                    }
                });
            });

            function updateRequirements(val) {
                const reqs = [
                    { id: 'req-length', valid: val.length >= 8 },
                    { id: 'req-upper', valid: /[A-Z]/.test(val) },
                    { id: 'req-special', valid: /[@$!%*?&]/.test(val) }
                ];
                reqs.forEach(req => {
                    const el = document.getElementById(req.id);
                    el.className = req.valid ? 'text-success' : 'text-danger';
                    el.querySelector('i').className = req.valid ? 'fas fa-check-circle' : 'fas fa-times-circle';
                });
            }

            // AJAX Send OTP
            sendOtpBtn.addEventListener('click', function() {
                sendOtpBtn.disabled = true;
                sendOtpBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';
                
                const formData = new FormData();
                formData.append('send_profile_otp', '1');
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                
                fetch('edit_profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    const status = document.getElementById('otpStatus');
                    status.className = 'mt-2 small ' + (data.status === 'success' ? 'text-success' : 'text-danger');
                    status.innerText = data.message;
                    
                    if(data.status === 'success') {
                        let timeLeft = 30;
                        const timer = setInterval(() => {
                            if(timeLeft <= 0) {
                                clearInterval(timer);
                                sendOtpBtn.disabled = false;
                                sendOtpBtn.innerText = 'Resend OTP';
                            } else {
                                sendOtpBtn.innerText = `Wait ${timeLeft}s`;
                                timeLeft--;
                            }
                        }, 1000);
                    } else {
                        sendOtpBtn.disabled = false;
                        sendOtpBtn.innerText = 'Retry Sending';
                    }
                })
                .catch(err => {
                    console.error(err);
                    sendOtpBtn.disabled = false;
                    sendOtpBtn.innerText = 'Error - Retry';
                });
            });
        });
    </script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
