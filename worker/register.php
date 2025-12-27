<?php
require_once '../config/security.php';
require_once '../config/db.php';
require_once '../includes/mailer.php';
require_once '../includes/cloudinary_helper.php';

$otp_sent = false;
$error = "";
$success = "";

// Fetch categories for the dropdown
$stmt = $pdo->query("SELECT id, name FROM categories");
$categories = $stmt->fetchAll();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if POST data is lost because it exceeded post_max_size
    if (empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $max_size = ini_get('post_max_size');
        $error = "The uploaded file size is too large (Total: " . round($_SERVER['CONTENT_LENGTH'] / 1024 / 1024, 2) . "MB). The limit is approximately $max_size. Please use smaller images or contact support.";
    } else {
        require_once '../includes/captcha.php';

        // Validate CAPTCHA and CSRF
        $captcha_valid = isset($_POST['g-recaptcha-response']) && verifyCaptcha($_POST['g-recaptcha-response']);
        $csrf_valid = isset($_POST['csrf_token']) && verifyCSRF($_POST['csrf_token']);

        if (!$captcha_valid) {
            $debug_info = [
                'timestamp' => date('Y-m-d H:i:s'),
                'post_set' => isset($_POST['g-recaptcha-response']),
                'post_data_count' => count($_POST),
                'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'N/A'
            ];
            file_put_contents('c:/xampp/htdocs/laubour/register_debug.log', "CAPTCHA FAIL: " . print_r($debug_info, true), FILE_APPEND);
            $error = "CAPTCHA verification failed. Please try again.";
        }
        elseif (!$csrf_valid) {
            $csrf_debug = [
                'timestamp' => date('Y-m-d H:i:s'),
                'post_token' => $_POST['csrf_token'] ?? 'MISSING',
                'session_token' => $_SESSION['csrf_token'] ?? 'MISSING',
                'session_id' => session_id(),
                'post_keys' => array_keys($_POST)
            ];
            file_put_contents('c:/xampp/htdocs/laubour/csrf_debug.log', "CSRF FAIL: " . print_r($csrf_debug, true), FILE_APPEND);
            $error = "Security validation failed (CSRF mismatch). This can happen if the upload took too long or the session expired. Please try with fewer images or smaller files.";
        }
    // STEP 1: INITIAL SUBMISSION (UPLOAD FILES & SEND OTP)
    elseif (isset($_POST['register_init'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $phone = trim($_POST['phone']);
        $service_category_id = $_POST['service_category_id'];
        $bio = trim($_POST['bio']);
        $hourly_rate = $_POST['hourly_rate'];
        $pin_codes_input = trim($_POST['pin_code']);
        $address = trim($_POST['address']);
        $adhar_card = trim($_POST['adhar_card']);
        $working_location = trim($_POST['working_location']);
    
        // Validate and process multiple PIN codes
        $pin_codes_array = array_map('trim', explode(',', $pin_codes_input));
        $valid_pin_codes = [];
        foreach ($pin_codes_array as $pin) {
            if (preg_match('/^\d{6}$/', $pin)) {
                $valid_pin_codes[] = $pin;
            }
        }
        $pin_code = implode(',', array_unique($valid_pin_codes)); // Remove duplicates
    
        if (empty($name) || empty($email) || empty($password) || empty($service_category_id) || empty($pin_code) || empty($address) || empty($adhar_card)) {
            $error = "Please fill all required fields.";
        } else {
             // Check password strength
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
                $error = "Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character.";
            } elseif (!preg_match('/^\d{10}$/', $phone)) {
                $error = "Phone number must be exactly 10 digits.";
            } elseif ($hourly_rate < 0) {
                $error = "Hourly rate cannot be negative.";
            } else {
                 // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM workers WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->rowCount() > 0) {
                    $error = "Email already exists.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Handle ID Document & Profile Image Uploads (CLOUD STORAGE)
                    $profile_image_url = "default.png";
                    $profile_image_public_id = null;
                    $aadhar_photo_url = "";
                    $aadhar_photo_public_id = null;
                    $pan_photo_url = "";
                    $pan_photo_public_id = null;
                    $signature_photo_url = "";
                    $signature_photo_public_id = null;
                    $previous_work_urls = [];
                    $previous_work_public_ids = [];
                    
                    $cld = CloudinaryHelper::getInstance();

                    // Profile Image
                    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                        $upload = $cld->uploadImage($_FILES['profile_image']['tmp_name'], CLD_FOLDER_WORKER_PROFILE, 'high-res');
                        if ($upload) {
                            $profile_image_url = $upload['url'];
                            $profile_image_public_id = $upload['public_id'];
                        }
                    }
    
                    // Aadhar Photo
                    if (isset($_FILES['aadhar_photo']) && $_FILES['aadhar_photo']['error'] == 0) {
                        $upload = $cld->uploadImage($_FILES['aadhar_photo']['tmp_name'], CLD_FOLDER_WORKER_DOCS . 'aadhar/', 'high-res');
                        if ($upload) {
                            $aadhar_photo_url = $upload['url'];
                            $aadhar_photo_public_id = $upload['public_id'];
                        }
                    }
    
                    // PAN Photo
                    if (isset($_FILES['pan_photo']) && $_FILES['pan_photo']['error'] == 0) {
                        $upload = $cld->uploadImage($_FILES['pan_photo']['tmp_name'], CLD_FOLDER_WORKER_DOCS . 'pan/', 'high-res');
                        if ($upload) {
                            $pan_photo_url = $upload['url'];
                            $pan_photo_public_id = $upload['public_id'];
                        }
                    }
    
                    // Signature Photo
                    if (isset($_FILES['signature_photo']) && $_FILES['signature_photo']['error'] == 0) {
                        $upload = $cld->uploadImage($_FILES['signature_photo']['tmp_name'], CLD_FOLDER_WORKER_DOCS . 'signature/', 'high-res');
                        if ($upload) {
                            $signature_photo_url = $upload['url'];
                            $signature_photo_public_id = $upload['public_id'];
                        }
                    }
    
                    // Previous Work Images (Multiple)
                    if (isset($_FILES['previous_work_images'])) {
                        $total_files = count($_FILES['previous_work_images']['name']);
                        for ($i = 0; $i < $total_files; $i++) {
                            if ($_FILES['previous_work_images']['error'][$i] == 0) {
                                $upload = $cld->uploadImage($_FILES['previous_work_images']['tmp_name'][$i], CLD_FOLDER_WORKER_PREV_WORK, 'high-res');
                                if ($upload) {
                                    $previous_work_urls[] = $upload['url'];
                                    $previous_work_public_ids[] = $upload['public_id'];
                                }
                            }
                        }
                    }
                    $previous_work_images = implode(',', $previous_work_urls); 
                    $previous_work_ids = implode(',', $previous_work_public_ids);
    
                    if (empty($aadhar_photo_url) || empty($pan_photo_url) || $profile_image_url == "default.png" || empty($signature_photo_url)) {
                        $error = "Please upload all required photos and documents.";
                    } else {
                        // All good, save to session
                        $_SESSION['temp_worker'] = [
                            'name' => $name,
                            'profile_image' => $profile_image_url,
                            'profile_image_public_id' => $profile_image_public_id,
                            'email' => $email,
                            'password' => $hashed_password,
                            'phone' => $phone,
                            'service_category_id' => $service_category_id,
                            'bio' => $bio,
                            'hourly_rate' => $hourly_rate,
                            'pin_code' => $pin_code,
                            'address' => $address,
                            'adhar_card' => $adhar_card,
                            'aadhar_photo' => $aadhar_photo_url,
                            'aadhar_photo_public_id' => $aadhar_photo_public_id,
                            'pan_photo' => $pan_photo_url,
                            'pan_photo_public_id' => $pan_photo_public_id,
                            'signature_photo' => $signature_photo_url,
                            'signature_photo_public_id' => $signature_photo_public_id,
                            'previous_work_images' => $previous_work_images,
                            'previous_work_public_ids' => $previous_work_ids,
                            'working_location' => $working_location
                        ];
                        
                         // Generate OTP
                        $otp = rand(100000, 999999);
                        $_SESSION['register_worker_otp'] = $otp;
    
                        // Send OTP via Email
                        $mail_result = sendOTPEmail($email, $otp, $name);
                        
                        if ($mail_result['status']) {
                            $success = "OTP sent to $email. Please check your inbox.";
                             $otp_sent = true;
                        } else {
                            $error = "Error sending email: " . $mail_result['message'];
                            $otp_sent = false;
                        }
                    }
                }
            }
        }
    }

    // STEP 2: VERIFY OTP AND CREATE WORKER
    elseif (isset($_POST['verify_otp'])) {
        $entered_otp = trim($_POST['otp']);
        
        if (!isset($_SESSION['register_worker_otp']) || !isset($_SESSION['temp_worker'])) {
             $error = "Session expired. Please register again.";
             $otp_sent = false;
        } elseif ($entered_otp == $_SESSION['register_worker_otp']) {
            $w = $_SESSION['temp_worker'];
            
            $sql = "INSERT INTO workers (name, profile_image, profile_image_public_id, email, password, phone, service_category_id, bio, hourly_rate, status, pin_code, address, adhar_card, aadhar_photo, aadhar_photo_public_id, pan_photo, pan_photo_public_id, signature_photo, signature_photo_public_id, previous_work_images, previous_work_public_ids, working_location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$w['name'], $w['profile_image'], $w['profile_image_public_id'], $w['email'], $w['password'], $w['phone'], $w['service_category_id'], $w['bio'], $w['hourly_rate'], $w['pin_code'], $w['address'], $w['adhar_card'], $w['aadhar_photo'], $w['aadhar_photo_public_id'], $w['pan_photo'], $w['pan_photo_public_id'], $w['signature_photo'], $w['signature_photo_public_id'], $w['previous_work_images'], $w['previous_work_public_ids'], $w['working_location']])) {
                $success = "Registration successful! Your account is pending approval from admin.";
                $otp_sent = false; // Show success message with login link
                
                // Clear session
                unset($_SESSION['temp_worker']);
                unset($_SESSION['register_worker_otp']);
            } else {
                 $error = "Something went wrong. Please try again.";
            }
        } else {
             $error = "Invalid OTP. Please try again.";
             $otp_sent = true;
             $success = "Please enter the OTP sent to your email.";
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
    <title>Worker Registration - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/worker_register.css">
    <script src="../assets/js/theme.js"></script>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        .password-container { position: relative; }
        .toggle-password { position: absolute; right: 10px; top: 38px; cursor: pointer; color: #6c757d; }
        
        .password-requirements { list-style: none; padding: 0; margin-bottom: 0; font-size: 0.85rem; text-align: left; }
        .password-requirements li { margin-bottom: 3px; transition: color 0.3s ease; }
        .password-requirements li.invalid { color: #dc3545; } /* Red */
        .password-requirements li.valid { color: #198754; font-weight: bold; } /* Green */
        .password-requirements li i { margin-right: 5px; }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5 px-sm-3 px-1">
        <div class="row justify-content-center g-0">
            <div class="col-lg-8 col-md-10">
                <div class="card shadow border-0 overflow-hidden">
                    <div class="card-header bg-warning text-dark text-center">
                        <h3>Worker Registration</h3>
                        <p class="mb-0">Join us and grow your business</p>
                    </div>
                    <div class="card-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if(isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?> <a href="login.php" class="alert-link">Login here</a></div>
                        <?php endif; ?>
                        
                        <?php if(empty($otp_sent) || $otp_sent == false): ?>
                        <form method="POST" action="register.php" enctype="multipart/form-data" id="registerForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <!-- Personal Details -->
                            <h4 class="mb-3">Personal Information</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="profile_image" class="form-label">Profile Photo * <small class="text-muted">(JPG, PNG)</small></label>
                                    <input type="file" class="form-control" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" required placeholder="OTP will be sent here" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="text" class="form-control" id="phone" name="phone" required pattern="\d{10}" maxlength="10" title="Phone number must be exactly 10 digits" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3 text-start">
                                    <label for="adhar_card" class="form-label">Adhaar Card No. *</label>
                                    <input type="text" class="form-control" id="adhar_card" name="adhar_card" required maxlength="12" placeholder="12-digit number" value="<?php echo isset($_POST['adhar_card']) ? htmlspecialchars($_POST['adhar_card']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="aadhar_photo" class="form-label">Aadhar Card Photo * <small class="text-muted">(JPG, PNG, PDF)</small></label>
                                    <input type="file" class="form-control" id="aadhar_photo" name="aadhar_photo" required accept=".jpg,.jpeg,.png,.pdf">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="pan_photo" class="form-label">PAN Card Photo * <small class="text-muted">(JPG, PNG, PDF)</small></label>
                                    <input type="file" class="form-control" id="pan_photo" name="pan_photo" required accept=".jpg,.jpeg,.png,.pdf">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="signature_photo" class="form-label">Signature Photo * <small class="text-muted">(JPG, PNG)</small></label>
                                    <input type="file" class="form-control" id="signature_photo" name="signature_photo" required accept=".jpg,.jpeg,.png">
                                </div>
                                <div class="col-md-6 mb-3">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Previous Work Pictures * <small class="text-muted">(You can add multiple)</small></label>
                                    <div id="workImagesContainer">
                                        <div class="input-group mb-2 work-image-group">
                                            <input type="file" class="form-control work-image-input" name="previous_work_images[]" required accept=".jpg,.jpeg,.png">
                                            <button type="button" class="btn btn-outline-success btn-add-work-image"><i class="fas fa-plus"></i></button>
                                        </div>
                                    </div>
                                    <small class="text-muted">Upload pictures of your past work to show to customers</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="pin_code" class="form-label">Service Area PIN Codes * <small class="text-muted">(You can add multiple)</small></label>
                                    <div id="pinCodeContainer">
                                        <div class="input-group mb-2 pin-code-group">
                                            <input type="text" class="form-control pin-code-input" name="pin_codes[]" required maxlength="6" pattern="\d{6}" placeholder="e.g. 110001" value="<?php echo isset($_POST['pin_codes'][0]) ? htmlspecialchars($_POST['pin_codes'][0]) : ''; ?>">
                                            <button type="button" class="btn btn-outline-success btn-add-pin"><i class="fas fa-plus"></i></button>
                                        </div>
                                    </div>
                                    <small class="text-muted">Enter 6-digit PIN codes for areas you serve</small>
                                    <input type="hidden" name="pin_code" id="pin_code_hidden">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="working_location" class="form-label">Preferred Working Area</label>
                                    <input type="text" class="form-control" id="working_location" name="working_location" placeholder="e.g. South Delhi" value="<?php echo isset($_POST['working_location']) ? htmlspecialchars($_POST['working_location']) : ''; ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Full Residential Address *</label>
                                <textarea class="form-control" id="address" name="address" rows="2" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="service_category_id" class="form-label">Service Category *</label>
                                    <select class="form-select" id="service_category_id" name="service_category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['service_category_id']) && $_POST['service_category_id'] == $cat['id']) ? 'selected' : ''; ?>><?php echo $cat['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="hourly_rate" class="form-label">Hourly Rate (₹)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="hourly_rate" name="hourly_rate" placeholder="e.g. 100.00" value="<?php echo isset($_POST['hourly_rate']) ? htmlspecialchars($_POST['hourly_rate']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                     <div class="password-container mb-3">
                                         <label for="password" class="form-label">Password *</label>
                                         <input type="password" class="form-control" id="password" name="password" required>
                                         <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                                         <ul class="password-requirements mt-2">
                                             <li id="req-length" class="invalid"><i class="fas fa-times-circle"></i> At least 8 characters</li>
                                             <li id="req-lower" class="invalid"><i class="fas fa-times-circle"></i> At least one lowercase letter</li>
                                             <li id="req-upper" class="invalid"><i class="fas fa-times-circle"></i> At least one uppercase letter</li>
                                             <li id="req-number" class="invalid"><i class="fas fa-times-circle"></i> At least one number</li>
                                             <li id="req-special" class="invalid"><i class="fas fa-times-circle"></i> At least one special character</li>
                                         </ul>
                                     </div>
                                     <div class="password-container">
                                         <label for="confirm_password" class="form-label">Confirm Password *</label>
                                         <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                         <i class="fas fa-eye toggle-password" id="toggleConfirmPassword"></i>
                                         <div id="passwordMatchMsg" class="small mt-2"></div>
                                     </div>
                                 </div>
                                 <div class="col-md-6 mb-3">
                                      <label for="bio" class="form-label">Short Bio / Experience</label>
                                      <textarea class="form-control" id="bio" name="bio" rows="4"><?php echo isset($_POST['bio']) ? htmlspecialchars($_POST['bio']) : ''; ?></textarea>
                                 </div>
                            </div>



                            <div class="mb-3">
                                <div class="g-recaptcha" data-sitekey="6LfwHzgsAAAAAI0kyJ7g6V_S6uE0FFb4zDWpypmD"></div>
                            </div>
                            <button type="submit" name="register_init" class="btn btn-warning w-100">Register as Worker</button>
                        </form>
                        <?php else: ?>
                            <form action="register.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <div class="text-center mb-4">
                                    <i class="fas fa-envelope-open-text fa-3x text-warning mb-3"></i>
                                    <h4>Verify your Email</h4>
                                    <p class="text-muted">Enter the 6-digit code sent to your email.</p>
                                </div>
                                <div class="mb-3">
                                    <input type="text" class="form-control text-center text-tracking-widest" style="letter-spacing: 5px; font-size: 1.5rem;" name="otp" placeholder="XXXXXX" required maxlength="6">
                                </div>
                                <div class="mb-3">
                                    <div class="g-recaptcha" data-sitekey="6LfwHzgsAAAAAI0kyJ7g6V_S6uE0FFb4zDWpypmD"></div>
                                </div>
                                <button type="submit" name="verify_otp" class="btn btn-dark w-100">Verify & Complete Registration</button>
                                <a href="register.php" class="btn btn-link w-100 mt-2 text-dark">Cancel / Try Again</a>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center">
                        <small>Already have an account? <a href="login.php">Login here</a></small> <br>
                        <small>Looking for a worker? <a href="../customer/register.php">Register as Customer</a></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const passwordMatchMsg = document.getElementById('passwordMatchMsg');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        toggleConfirmPassword.addEventListener('click', function () {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        function checkMatch() {
            if (confirmPasswordInput.value === "") {
                passwordMatchMsg.innerHTML = "";
            } else if (passwordInput.value === confirmPasswordInput.value) {
                passwordMatchMsg.innerHTML = "<i class='fas fa-check-circle text-success'></i> Passwords match";
                passwordMatchMsg.className = "small mt-2 text-success";
            } else {
                passwordMatchMsg.innerHTML = "<i class='fas fa-times-circle text-danger'></i> Passwords do not match";
                passwordMatchMsg.className = "small mt-2 text-danger";
            }
        }

        passwordInput.addEventListener('input', checkMatch);
        confirmPasswordInput.addEventListener('input', checkMatch);

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

    <script>
        // Dynamic PIN Code Management
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('pinCodeContainer');
            
            // Add PIN code field
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
                
                // Remove PIN code field
                if (e.target.closest('.btn-remove-pin')) {
                    e.target.closest('.pin-code-group').remove();
                }
            });

            // Before form submit, combine all PIN codes
            document.querySelector('form').addEventListener('submit', function(e) {
                const pinInputs = document.querySelectorAll('.pin-code-input');
                const pinCodes = [];
                
                pinInputs.forEach(input => {
                    const value = input.value.trim();
                    if (value && /^\d{6}$/.test(value)) {
                        pinCodes.push(value);
                    }
                });
                
                if (pinCodes.length === 0) {
                    e.preventDefault();
                    alert('Please enter at least one valid 6-digit PIN code');
                    return false;
                }
                
                // CAPTCHA check
                if (typeof grecaptcha !== 'undefined') {
                    var response = grecaptcha.getResponse();
                    if (response.length === 0) {
                        e.preventDefault();
                        alert("Please check the CAPTCHA box to verify you are not a robot.");
                        return false;
                    }
                }

                document.getElementById('pin_code_hidden').value = pinCodes.join(',');
            });
        });

        // Dynamic Work Images Management
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('workImagesContainer');
            
            // Add Work Image field
            container.addEventListener('click', function(e) {
                if (e.target.closest('.btn-add-work-image')) {
                    const newGroup = document.createElement('div');
                    newGroup.className = 'input-group mb-2 work-image-group';
                    newGroup.innerHTML = `
                        <input type="file" class="form-control work-image-input" name="previous_work_images[]" accept=".jpg,.jpeg,.png">
                        <button type="button" class="btn btn-outline-danger btn-remove-work-image"><i class="fas fa-minus"></i></button>
                    `;
                    container.appendChild(newGroup);
                }
                
                // Remove Work Image field
                if (e.target.closest('.btn-remove-work-image')) {
                    e.target.closest('.work-image-group').remove();
                }
            });
        });
    </script>
    <script>
        // Real-time Validation Helper
        function setupValidation(input, validateFn, errorMsg) {
            if (!input) return;

            // Check if error message div already exists
            let errorDiv = input.parentNode.querySelector('.invalid-feedback-custom');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback invalid-feedback-custom';
                errorDiv.style.display = 'none';
                errorDiv.style.color = '#dc3545';
                errorDiv.style.fontSize = '0.875em';
                errorDiv.style.marginTop = '0.25rem';
                input.parentNode.appendChild(errorDiv);
            }

            const validate = () => {
                const isValid = validateFn(input.value);
                if (!isValid && input.value !== '') {
                    input.classList.add('is-invalid');
                    errorDiv.innerText = errorMsg;
                    errorDiv.style.display = 'block';
                } else {
                    input.classList.remove('is-invalid');
                    errorDiv.style.display = 'none';
                }
            };

            input.addEventListener('input', validate);
            input.addEventListener('blur', validate);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // 1. Phone Validation
            const phoneInput = document.getElementById('phone');
            setupValidation(phoneInput, (val) => /^\d{10}$/.test(val), 'Phone number must be exactly 10 digits.');
            
            // Restrict phone input to numbers only
            phoneInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '');
            });

            // 2. Hourly Rate Validation
            const rateInput = document.getElementById('hourly_rate');
            setupValidation(rateInput, (val) => parseFloat(val) >= 0, 'Hourly rate cannot be negative.');

            // 3. Adhar Validation
            const adharInput = document.getElementById('adhar_card');
            setupValidation(adharInput, (val) => /^\d{12}$/.test(val), 'Aadhar must be exactly 12 digits.');
            
            // Restrict adhar to numbers
            adharInput.addEventListener('input', function(e) {
                 this.value = this.value.replace(/\D/g, '');
            });

            // 4. Pin Code Validation (Dynamic)
            const container = document.getElementById('pinCodeContainer');
            
            // Function to attach validation to a specific pin input
            const attachPinValidation = (input) => {
                 setupValidation(input, (val) => /^\d{6}$/.test(val), 'Pin code must be 6 digits.');
                 input.addEventListener('input', function() {
                     this.value = this.value.replace(/\D/g, '');
                 });
            };

            // Attach to initial inputs
            container.querySelectorAll('.pin-code-input').forEach(attachPinValidation);

            // Observer for new inputs
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === 1 && node.classList.contains('pin-code-group')) {
                            const input = node.querySelector('.pin-code-input');
                            if(input) attachPinValidation(input);
                        }
                    });
                });
            });

            observer.observe(container, { childList: true });
        });
    </script>
</body>
</html>
