<?php
require_once '../config/security.php';
require_once '../config/db.php';
require_once '../includes/cloudinary_helper.php';

// Check Worker Login
if (!isset($_SESSION['worker_id'])) {
    header("Location: login.php");
    exit;
}

$worker_id = $_SESSION['worker_id'];
$success = "";
$error = "";

// Handle OTP Generation for Profile Update (AJAX endpoint)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_profile_otp'])) {
    require_once '../includes/mailer.php';
    
    $stmt = $pdo->prepare("SELECT email, name FROM workers WHERE id = ?");
    $stmt->execute([$worker_id]);
    $worker_data = $stmt->fetch();
    
    $otp = rand(100000, 999999);
    $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));
    
    $pdo->prepare("UPDATE workers SET otp = ?, otp_expires_at = ? WHERE id = ?")
        ->execute([$otp, $expiry, $worker_id]);
    
    $mail_result = sendOTPEmail($worker_data['email'], $otp, $worker_data['name']);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $mail_result['status'] ? 'success' : 'error',
        'message' => $mail_result['status'] ? 'OTP sent to ' . $worker_data['email'] : $mail_result['message']
    ]);
    exit;
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    $phone = trim($_POST['phone']);
    $hourly_rate = trim($_POST['hourly_rate']);
    $bio = trim($_POST['bio']);
    $address = trim($_POST['address']);
    $pin_codes_input = trim($_POST['pin_code']);
    $working_location = trim($_POST['working_location']);
    $otp_entered = trim($_POST['profile_otp'] ?? '');

    // Check if sensitive fields changed
    $stmt = $pdo->prepare("SELECT email, password FROM workers WHERE id = ?");
    $stmt->execute([$worker_id]);
    $current_worker = $stmt->fetch();

    $email_changed = ($email !== $current_worker['email']);
    $password_changed = !empty($new_password);

    if ($email_changed || $password_changed) {
        // Verify OTP
        $stmt_otp = $pdo->prepare("SELECT otp, otp_expires_at FROM workers WHERE id = ?");
        $stmt_otp->execute([$worker_id]);
        $otp_data = $stmt_otp->fetch();

        if (empty($otp_entered) || $otp_data['otp'] !== $otp_entered || strtotime($otp_data['otp_expires_at']) < time()) {
            $error = "Invalid or expired OTP. Please verify your identity to change email/password.";
        } else {
            // Clear OTP after successful verification
            $pdo->prepare("UPDATE workers SET otp = NULL WHERE id = ?")->execute([$worker_id]);
        }
        
        // Check email uniqueness if changed
        if ($email_changed) {
            $stmt_check = $pdo->prepare("SELECT id FROM workers WHERE email = ? AND id != ?");
            $stmt_check->execute([$email, $worker_id]);
            if ($stmt_check->fetch()) {
                $error = "This email is already registered by another worker.";
            }
        }
    }
    
    // Validate and process multiple PIN codes
    $pin_codes_array = array_map('trim', explode(',', $pin_codes_input));
    $valid_pin_codes = [];
    foreach ($pin_codes_array as $pin) {
        if (preg_match('/^\d{6}$/', $pin)) {
            $valid_pin_codes[] = $pin;
        }
    }
    $pin_code = implode(',', array_unique($valid_pin_codes));

    // Handle File Uploads (Required Admin Approval)
    $pending_profile_image_url = null;
    $pending_profile_image_public_id = null;
    $pending_aadhar_photo_url = null;
    $pending_aadhar_photo_public_id = null;
    $pending_pan_photo_url = null;
    $pending_pan_photo_public_id = null;
    $pending_signature_photo_url = null;
    $pending_signature_photo_public_id = null;
    $has_pending_docs = false;
    
    $cld = CloudinaryHelper::getInstance();

    // Profile Image
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $upload = $cld->uploadImage($_FILES['profile_image']['tmp_name'], CLD_FOLDER_WORKER_PROFILE, 'high-res');
        if ($upload) {
            $pending_profile_image_url = $upload['url'];
            $pending_profile_image_public_id = $upload['public_id'];
            $has_pending_docs = true;
        }
    }

    // Aadhar Photo
    if (isset($_FILES['aadhar_photo']) && $_FILES['aadhar_photo']['error'] == 0) {
        $upload = $cld->uploadImage($_FILES['aadhar_photo']['tmp_name'], CLD_FOLDER_WORKER_DOCS . 'aadhar/', 'high-res');
        if ($upload) {
            $pending_aadhar_photo_url = $upload['url'];
            $pending_aadhar_photo_public_id = $upload['public_id'];
            $has_pending_docs = true;
        }
    }

    // PAN Photo
    if (isset($_FILES['pan_photo']) && $_FILES['pan_photo']['error'] == 0) {
        $upload = $cld->uploadImage($_FILES['pan_photo']['tmp_name'], CLD_FOLDER_WORKER_DOCS . 'pan/', 'high-res');
        if ($upload) {
            $pending_pan_photo_url = $upload['url'];
            $pending_pan_photo_public_id = $upload['public_id'];
            $has_pending_docs = true;
        }
    }

    // Signature Photo (Required Admin Approval)
    if (isset($_FILES['signature_photo']) && $_FILES['signature_photo']['error'] == 0) {
        $upload = $cld->uploadImage($_FILES['signature_photo']['tmp_name'], CLD_FOLDER_WORKER_DOCS . 'signature/', 'high-res');
        if ($upload) {
            $pending_signature_photo_url = $upload['url'];
            $pending_signature_photo_public_id = $upload['public_id'];
            $has_pending_docs = true;
        }
    }

    // Previous Work Images (Append & Delete from Cloudinary)
    $stmt_curr = $pdo->prepare("SELECT previous_work_images, previous_work_public_ids FROM workers WHERE id = ?");
    $stmt_curr->execute([$worker_id]);
    $curr_row = $stmt_curr->fetch();
    $current_images_array = array_filter(explode(',', $curr_row['previous_work_images'] ?? ''));
    $current_public_ids_array = array_filter(explode(',', $curr_row['previous_work_public_ids'] ?? ''));

    // 2. Handle Deletions
    if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
        foreach ($_POST['delete_images'] as $del_img_url) {
            $key = array_search($del_img_url, $current_images_array);
            if ($key !== false) {
                // Delete from Cloudinary
                $cld->deleteImage($current_public_ids_array[$key]);
                // Remove from arrays
                unset($current_images_array[$key]);
                unset($current_public_ids_array[$key]);
            }
        }
        $current_images_array = array_values($current_images_array);
        $current_public_ids_array = array_values($current_public_ids_array);
    }

    // 3. Handle New Uploads (Append)
    $new_images_urls = [];
    $new_public_ids = [];
    if (isset($_FILES['previous_work_images'])) {
         $total_files = count($_FILES['previous_work_images']['name']);
         for ($i = 0; $i < $total_files; $i++) {
             if ($_FILES['previous_work_images']['error'][$i] == 0) {
                 $upload = $cld->uploadImage($_FILES['previous_work_images']['tmp_name'][$i], CLD_FOLDER_WORKER_PREV_WORK, 'high-res');
                 if ($upload) {
                     $new_images_urls[] = $upload['url'];
                     $new_public_ids[] = $upload['public_id'];
                 }
             }
         }
    }

    $final_images_array = array_merge($current_images_array, $new_images_urls);
    $final_public_ids_array = array_merge($current_public_ids_array, $new_public_ids);
    $previous_work_images_str = implode(',', $final_images_array);
    $previous_work_public_ids_str = implode(',', $final_public_ids_array);

    if (!preg_match('/^\d{10}$/', $phone)) {
        $error = "Phone number must be exactly 10 digits.";
    } elseif ($hourly_rate < 0) {
        $error = "Hourly rate cannot be negative.";
    }

    if (!$error) {
        // Prepare Query
        $sql = "UPDATE workers SET name = ?, email = ?, phone = ?, hourly_rate = ?, bio = ?, address = ?, pin_code = ?, working_location = ?";
        $params = [$name, $email, $phone, $hourly_rate, $bio, $address, $pin_code, $working_location];

        if ($password_changed) {
            $sql .= ", password = ?";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        }

        $sql .= ", previous_work_images = ?, previous_work_public_ids = ?";
        $params[] = $previous_work_images_str;
        $params[] = $previous_work_public_ids_str;

        if ($has_pending_docs) {
            $sql .= ", doc_update_status = 'pending'";
            if ($pending_profile_image_url) {
                $sql .= ", pending_profile_image = ?, pending_profile_image_public_id = ?";
                $params[] = $pending_profile_image_url;
                $params[] = $pending_profile_image_public_id;
            }
            if ($pending_aadhar_photo_url) {
                $sql .= ", pending_aadhar_photo = ?, pending_aadhar_photo_public_id = ?";
                $params[] = $pending_aadhar_photo_url;
                $params[] = $pending_aadhar_photo_public_id;
            }
            if ($pending_pan_photo_url) {
                $sql .= ", pending_pan_photo = ?, pending_pan_photo_public_id = ?";
                $params[] = $pending_pan_photo_url;
                $params[] = $pending_pan_photo_public_id;
            }
            if ($pending_signature_photo_url) {
                $sql .= ", pending_signature_photo = ?, pending_signature_photo_public_id = ?";
                $params[] = $pending_signature_photo_url;
                $params[] = $pending_signature_photo_public_id;
            }
        }

        $sql .= " WHERE id = ?";
        $params[] = $worker_id;

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
             $success = "Profile updated successfully! Critical changes will be live after admin approval.";
        } else {
            $error = "Failed to update database.";
        }
    }
}

// Fetch Current Data
$stmt = $pdo->prepare("SELECT * FROM workers WHERE id = ?");
$stmt->execute([$worker_id]);
$worker = $stmt->fetch();
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
</head>
<body class="bg-body">
    <?php include '../includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0">Edit Profile</h4>
                    </div>
                    <div class="card-body">
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form action="edit_profile.php" method="POST" enctype="multipart/form-data">
                            <div class="text-center mb-4">
                                <?php 
                                    $img_src = $worker['profile_image'] && $worker['profile_image'] != 'default.png' 
                                        ? $worker['profile_image'] 
                                        : "https://via.placeholder.com/150"; 
                                ?>
                                <img src="<?php echo $img_src; ?>" alt="Profile" class="profile-img-preview mb-3">
                                <div class="mb-3">
                                    <label for="profile_image" class="form-label">Change Profile Picture</label>
                                    <?php if($worker['pending_profile_image']): ?>
                                        <div class="mb-2"><span class="badge bg-info"><i class="fas fa-clock"></i> New Photo Pending Approval</span></div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="profile_image" name="profile_image">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($worker['name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($worker['phone']); ?>" pattern="\d{10}" maxlength="10" title="Phone number must be exactly 10 digits">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address <small class="text-danger font-monospace">*Sensitive</small></label>
                                    <input type="email" class="form-control sensitive-field" id="email" name="email" value="<?php echo htmlspecialchars($worker['email']); ?>" required data-original="<?php echo htmlspecialchars($worker['email']); ?>">
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

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="hourly_rate" class="form-label">Hourly Rate (₹)</label>
                                    <input type="number" class="form-control" id="hourly_rate" name="hourly_rate" value="<?php echo htmlspecialchars($worker['hourly_rate']); ?>" required min="0" step="0.01">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="working_location" class="form-label">Preferred Working Area</label>
                                    <input type="text" class="form-control" id="working_location" name="working_location" value="<?php echo htmlspecialchars($worker['working_location']); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="bio" class="form-label">Bio / Skills</label>
                                <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo htmlspecialchars($worker['bio']); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="p-3 border rounded bg-light">
                                        <label class="form-label d-block fw-bold mb-3"><i class="fas fa-id-card me-2 text-primary"></i>Aadhar Card Photo</label>
                                        <?php if($worker['aadhar_photo']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted d-block mb-1">Current Aadhar:</small>
                                                <img src="<?php echo $worker['aadhar_photo']; ?>" class="rounded shadow-sm" style="max-height: 100px;">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" name="aadhar_photo" accept=".jpg,.jpeg,.png,.webp">
                                        <?php if($worker['pending_aadhar_photo']): ?>
                                            <div class="mt-2 text-info small"><i class="fas fa-clock"></i> New Aadhar Pending Approval</div>
                                        <?php endif; ?>
                                        <small class="text-muted mt-2 d-block">Requires admin approval to update.</small>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="p-3 border rounded bg-light">
                                        <label class="form-label d-block fw-bold mb-3"><i class="fas fa-id-card me-2 text-primary"></i>PAN Card Photo</label>
                                        <?php if($worker['pan_photo']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted d-block mb-1">Current PAN:</small>
                                                <img src="<?php echo $worker['pan_photo']; ?>" class="rounded shadow-sm" style="max-height: 100px;">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" name="pan_photo" accept=".jpg,.jpeg,.png,.webp">
                                        <?php if($worker['pending_pan_photo']): ?>
                                            <div class="mt-2 text-info small"><i class="fas fa-clock"></i> New PAN Pending Approval</div>
                                        <?php endif; ?>
                                        <small class="text-muted mt-2 d-block">Requires admin approval to update.</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="p-3 border rounded bg-light">
                                        <label class="form-label d-block fw-bold mb-3"><i class="fas fa-file-signature me-2 text-primary"></i>Signature Photo</label>
                                        <?php if(isset($worker['signature_photo']) && $worker['signature_photo']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted d-block mb-1">Current Signature:</small>
                                                <img src="<?php echo $worker['signature_photo']; ?>" class="rounded shadow-sm border bg-white" style="max-height: 80px;">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" name="signature_photo" accept=".jpg,.jpeg,.png,.webp">
                                        <?php if($worker['pending_signature_photo']): ?>
                                            <div class="mt-2 text-info small"><i class="fas fa-clock"></i> New Signature Pending Approval</div>
                                        <?php endif; ?>
                                        <small class="text-muted mt-2 d-block">Requires admin approval to update.</small>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                     <div class="p-3 border rounded bg-light">
                                        <label class="form-label d-block fw-bold mb-3"><i class="fas fa-images me-2 text-primary"></i>Previous Work Images</label>
                                        <?php if(isset($worker['previous_work_images']) && $worker['previous_work_images']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted d-block mb-1">Current Images (Check to delete):</small>
                                                <div class="d-flex gap-3 flex-wrap">
                                                    <?php 
                                                        foreach(explode(',', $worker['previous_work_images']) as $img_url):
                                                            if(trim($img_url)):
                                                    ?>
                                                        <div class="position-relative text-center border rounded p-1" style="width: 80px;">
                                                            <img src="<?php echo trim($img_url); ?>" class="rounded shadow-sm mb-1" style="width: 100%; height: 60px; object-fit: cover;">
                                                            <div class="form-check d-flex justify-content-center">
                                                                <input class="form-check-input" type="checkbox" name="delete_images[]" value="<?php echo trim($img_url); ?>" id="del_<?php echo md5($img_url); ?>">
                                                            </div>
                                                            <label class="form-check-label small text-danger" for="del_<?php echo md5($img_url); ?>" style="font-size: 0.7rem;">Delete</label>
                                                        </div>
                                                    <?php 
                                                            endif;
                                                        endforeach; 
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" name="previous_work_images[]" multiple accept=".jpg,.jpeg,.png,.webp">
                                        <small class="text-muted mt-1 d-block">Uploading new images will append to your portfolio.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($worker['address']); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="pin_code" class="form-label">Service Area PIN Codes <small class="text-muted">(You can add multiple)</small></label>
                                <div id="pinCodeContainer">
                                    <?php 
                                    $existing_pins = $worker['pin_code'] ? explode(',', $worker['pin_code']) : [''];
                                    foreach ($existing_pins as $index => $pin): 
                                    ?>
                                    <div class="input-group mb-2 pin-code-group">
                                        <input type="text" class="form-control pin-code-input" name="pin_codes[]" maxlength="6" pattern="\d{6}" placeholder="e.g. 110001" value="<?php echo htmlspecialchars(trim($pin)); ?>">
                                        <?php if ($index === 0): ?>
                                        <button type="button" class="btn btn-outline-success btn-add-pin"><i class="fas fa-plus"></i></button>
                                        <?php else: ?>
                                        <button type="button" class="btn btn-outline-danger btn-remove-pin"><i class="fas fa-minus"></i></button>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <small class="text-muted">Enter 6-digit PIN codes for areas you serve</small>
                                <input type="hidden" name="pin_code" id="pin_code_hidden">
                            </div>
                            </div>

                            <div id="otpSection" class="mb-3 border p-3 rounded bg-light" style="display: none;">
                                <label for="profile_otp" class="form-label fw-bold"><i class="fas fa-shield-alt me-2 text-warning"></i>Identity Verification</label>
                                <p class="small text-muted mb-2">Changing email or password requires verification. An OTP will be sent to your current email.</p>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="profile_otp" name="profile_otp" placeholder="Enter 6-digit OTP">
                                    <button class="btn btn-warning" type="button" id="sendOtpBtn">Send OTP</button>
                                </div>
                                <div id="otpStatus" class="mt-2 small"></div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning btn-lg">Update Profile</button>
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
            const passInput = document.getElementById('new_password');
            const passReqs = document.getElementById('pass-reqs');
            
            // Password toggle
            const toggleBtn = document.getElementById('togglePassword');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    const type = passInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passInput.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            }

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
                    if (el) {
                        el.className = req.valid ? 'text-success' : 'text-danger';
                        el.querySelector('i').className = req.valid ? 'fas fa-check-circle' : 'fas fa-times-circle';
                    }
                });
            }

            // AJAX Send OTP
            if (sendOtpBtn) {
                sendOtpBtn.addEventListener('click', function() {
                    sendOtpBtn.disabled = true;
                    sendOtpBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';
                    
                    const formData = new FormData();
                    formData.append('send_profile_otp', '1');
                    
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
            }

            // PIN code handling
            const container = document.getElementById('pinCodeContainer');
            
            container.addEventListener('click', function(e) {
                if (e.target.closest('.btn-add-pin')) {
                    const newGroup = document.createElement('div');
                    newGroup.className = 'input-group mb-2 pin-code-group';
                    newGroup.innerHTML = `
                        <input type="text" class="form-control pin-code-input" name="pin_codes[]" maxlength="6" pattern="\\d{6}" placeholder="e.g. 110001">
                        <button type="button" class="btn btn-outline-danger btn-remove-pin"><i class="fas fa-minus"></i></button>
                    `;
                    container.appendChild(newGroup);
                }
                
                if (e.target.closest('.btn-remove-pin')) {
                    e.target.closest('.pin-code-group').remove();
                }
            });

            document.querySelector('form').addEventListener('submit', function(e) {
                const pinInputs = document.querySelectorAll('.pin-code-input');
                const pinCodes = [];
                pinInputs.forEach(input => {
                    const value = input.value.trim();
                    if (value && /^\d{6}$/.test(value)) pinCodes.push(value);
                });
                document.getElementById('pin_code_hidden').value = pinCodes.join(',');
            });
        });
    </script>
</body>
</html>
