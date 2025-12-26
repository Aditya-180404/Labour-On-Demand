<?php
session_start();
require_once '../config/db.php';

// Check Worker Login
if (!isset($_SESSION['worker_id'])) {
    header("Location: login.php");
    exit;
}

$worker_id = $_SESSION['worker_id'];
$success = "";
$error = "";

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $hourly_rate = trim($_POST['hourly_rate']);
    $bio = trim($_POST['bio']);
    $address = trim($_POST['address']);
    $pin_codes_input = trim($_POST['pin_code']);
    $working_location = trim($_POST['working_location']);
    
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
    $pending_profile_image = null;
    $pending_aadhar_photo = null;
    $pending_pan_photo = null;
    $has_pending_docs = false;
    
    $worker_upload_dir = '../uploads/workers/';
    $doc_upload_dir = '../uploads/documents/';
    
    if (!is_dir($worker_upload_dir)) mkdir($worker_upload_dir, 0777, true);
    if (!is_dir($doc_upload_dir)) mkdir($doc_upload_dir, 0777, true);

    $allowed_img_ext = ['jpg', 'jpeg', 'png', 'gif'];
    $allowed_doc_ext = ['jpg', 'jpeg', 'png', 'pdf'];

    // Profile Image
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_img_ext)) {
            $pending_profile_image = "pending_worker_" . $worker_id . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['profile_image']['tmp_name'], $worker_upload_dir . $pending_profile_image);
            $has_pending_docs = true;
        } else {
            $error = "Invalid Profile Image type.";
        }
    }

    // Aadhar Photo
    if (isset($_FILES['aadhar_photo']) && $_FILES['aadhar_photo']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['aadhar_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_doc_ext)) {
            $pending_aadhar_photo = "pending_aadhar_" . $worker_id . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['aadhar_photo']['tmp_name'], $doc_upload_dir . $pending_aadhar_photo);
            $has_pending_docs = true;
        } else {
            $error = "Invalid Aadhar Photo type.";
        }
    }

    // PAN Photo
    if (isset($_FILES['pan_photo']) && $_FILES['pan_photo']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['pan_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_doc_ext)) {
            $pending_pan_photo = "pending_pan_" . $worker_id . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['pan_photo']['tmp_name'], $doc_upload_dir . $pending_pan_photo);
            $has_pending_docs = true;
        } else {
            $error = "Invalid PAN Photo type.";
        }
    }

    // Signature Photo (Direct Update)
    $signature_photo = null;
    if (isset($_FILES['signature_photo']) && $_FILES['signature_photo']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['signature_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_img_ext)) {
            $signature_photo = "sig_" . $worker_id . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['signature_photo']['tmp_name'], $doc_upload_dir . $signature_photo);
        }
    }

    // Previous Work Images (Append & Delete)
    $work_upload_dir = '../uploads/work_images/';
    if (!is_dir($work_upload_dir)) mkdir($work_upload_dir, 0777, true);

    // 1. Fetch CURRENT images from DB first
    $stmt_curr = $pdo->prepare("SELECT previous_work_images FROM workers WHERE id = ?");
    $stmt_curr->execute([$worker_id]);
    $curr_row = $stmt_curr->fetch();
    $current_images_str = $curr_row['previous_work_images'] ?? '';
    $current_images_array = array_filter(explode(',', $current_images_str)); // Array of existing images

    // 2. Handle Deletions
    if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
        foreach ($_POST['delete_images'] as $del_img) {
            // Remove from array
            $key = array_search($del_img, $current_images_array);
            if ($key !== false) {
                unset($current_images_array[$key]);
                // Optional: Delete file from server? 
                // formatted_img_path = $work_upload_dir . $del_img;
                // if(file_exists($formatted_img_path)) unlink($formatted_img_path);
            }
        }
    }

    // 3. Handle New Uploads (Append)
    $new_images_paths = [];
    if (isset($_FILES['previous_work_images'])) {
         $total_files = count($_FILES['previous_work_images']['name']);
         for ($i = 0; $i < $total_files; $i++) {
             if ($_FILES['previous_work_images']['error'][$i] == 0) {
                 $ext = strtolower(pathinfo($_FILES['previous_work_images']['name'][$i], PATHINFO_EXTENSION));
                 if (in_array($ext, $allowed_img_ext)) {
                     $new_name = "work_" . $worker_id . "_" . time() . "_" . $i . "." . $ext;
                     if (move_uploaded_file($_FILES['previous_work_images']['tmp_name'][$i], $work_upload_dir . $new_name)) {
                         $new_images_paths[] = $new_name;
                     }
                 }
             }
         }
    }

    // 4. Merge and Update
    $final_images_array = array_merge($current_images_array, $new_images_paths);
    $previous_work_images = implode(',', $final_images_array);


    if (!preg_match('/^\d{10}$/', $phone)) {
        $error = "Phone number must be exactly 10 digits.";
    } elseif ($hourly_rate < 0) {
        $error = "Hourly rate cannot be negative.";
    }

    if (!$error) {
        // Prepare Query for Direct Updates (Basic Info)
        $sql = "UPDATE workers SET name = ?, phone = ?, hourly_rate = ?, bio = ?, address = ?, pin_code = ?, working_location = ?";
        $params = [$name, $phone, $hourly_rate, $bio, $address, $pin_code, $working_location];

        if ($signature_photo) {
            $sql .= ", signature_photo = ?";
            $params[] = $signature_photo;
        }
        if ($previous_work_images) {
             $sql .= ", previous_work_images = ?";
             $params[] = $previous_work_images;
        }

        if ($has_pending_docs) {
            $sql .= ", doc_update_status = 'pending'";
            if ($pending_profile_image) {
                $sql .= ", pending_profile_image = ?";
                $params[] = $pending_profile_image;
            }
            if ($pending_aadhar_photo) {
                $sql .= ", pending_aadhar_photo = ?";
                $params[] = $pending_aadhar_photo;
            }
            if ($pending_pan_photo) {
                $sql .= ", pending_pan_photo = ?";
                $params[] = $pending_pan_photo;
            }
        }

        $sql .= " WHERE id = ?";
        $params[] = $worker_id;

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
             $success = "Profile basic info updated.";
             if ($has_pending_docs) {
                 $success .= " Your photo/document change requests have been sent for admin approval.";
             }
             $_SESSION['worker_name'] = $name;
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
    </style>
</head>
<body class="bg-body">
    <?php include '../includes/worker_navbar.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0">Edit Worker Profile</h4>
                    </div>
                    <div class="card-body">
                        <?php if($error): ?>
                            <div class="alert alert-danger px-4 py-3 rounded-3 shadow-sm mb-4"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if($success): ?>
                            <div class="alert alert-success px-4 py-3 rounded-3 shadow-sm mb-4"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
                        <?php endif; ?>

                        <?php if($worker['doc_update_status'] == 'pending'): ?>
                            <div class="alert alert-info px-4 py-3 rounded-3 shadow-sm mb-4">
                                <i class="fas fa-clock me-2"></i> <strong>Approval Pending:</strong> You have submitted some document/photo changes that are awaiting admin approval. 
                                <br><small>You can still update other profile details below.</small>
                            </div>
                        <?php endif; ?>

                        <form action="edit_profile.php" method="POST" enctype="multipart/form-data">
                            <div class="text-center mb-4">
                                <?php 
                                    $img_src = $worker['profile_image'] && $worker['profile_image'] != 'default.png' 
                                        ? "../uploads/workers/" . $worker['profile_image'] 
                                        : "https://via.placeholder.com/150"; 
                                ?>
                                <img src="<?php echo $img_src; ?>" alt="Profile" class="profile-img-preview mb-3">
                                <div class="mb-3">
                                    <label for="profile_image" class="form-label">Change Profile Picture</label>
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
                                                <?php if(strtolower(pathinfo($worker['aadhar_photo'], PATHINFO_EXTENSION)) == 'pdf'): ?>
                                                    <span class="badge bg-secondary"><i class="fas fa-file-pdf"></i> PDF Uploaded</span>
                                                <?php else: ?>
                                                    <img src="../uploads/documents/<?php echo $worker['aadhar_photo']; ?>" class="rounded shadow-sm" style="max-height: 100px;">
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" name="aadhar_photo" accept=".jpg,.jpeg,.png,.pdf">
                                        <small class="text-muted mt-2 d-block">Requires admin approval to update.</small>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="p-3 border rounded bg-light">
                                        <label class="form-label d-block fw-bold mb-3"><i class="fas fa-id-card me-2 text-primary"></i>PAN Card Photo</label>
                                        <?php if($worker['pan_photo']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted d-block mb-1">Current PAN:</small>
                                                <?php if(strtolower(pathinfo($worker['pan_photo'], PATHINFO_EXTENSION)) == 'pdf'): ?>
                                                    <span class="badge bg-secondary"><i class="fas fa-file-pdf"></i> PDF Uploaded</span>
                                                <?php else: ?>
                                                    <img src="../uploads/documents/<?php echo $worker['pan_photo']; ?>" class="rounded shadow-sm" style="max-height: 100px;">
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" name="pan_photo" accept=".jpg,.jpeg,.png,.pdf">
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
                                                <img src="../uploads/documents/<?php echo $worker['signature_photo']; ?>" class="rounded shadow-sm border bg-white" style="max-height: 80px;">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" name="signature_photo" accept=".jpg,.jpeg,.png">
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
                                                        foreach(explode(',', $worker['previous_work_images']) as $img):
                                                            if(trim($img)):
                                                    ?>
                                                        <div class="position-relative text-center border rounded p-1" style="width: 80px;">
                                                            <img src="../uploads/work_images/<?php echo trim($img); ?>" class="rounded shadow-sm mb-1" style="width: 100%; height: 60px; object-fit: cover;">
                                                            <div class="form-check d-flex justify-content-center">
                                                                <input class="form-check-input" type="checkbox" name="delete_images[]" value="<?php echo trim($img); ?>" id="del_<?php echo trim($img); ?>">
                                                            </div>
                                                            <label class="form-check-label small text-danger" for="del_<?php echo trim($img); ?>" style="font-size: 0.7rem;">Delete</label>
                                                        </div>
                                                    <?php 
                                                            endif;
                                                        endforeach; 
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" name="previous_work_images[]" multiple accept=".jpg,.jpeg,.png">
                                        <small class="text-muted mt-1 d-block">Uploading new images will replace existing ones.</small>
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

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning">Update Profile</button>
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
        // Dynamic PIN Code Management
        document.addEventListener('DOMContentLoaded', function() {
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
                
                document.getElementById('pin_code_hidden').value = pinCodes.join(',');
            });
        });
    </script>
    <?php include '../includes/worker_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/laubour/assets/js/theme.js"></script>
    <script>
        // Real-time Validation Helper (reused)
        function setupValidation(input, validateFn, errorMsg) {
             if (!input) return;
 
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
                 // Only show error if not empty (let HTML5 required handle empty)
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
             if(phoneInput) {
                 phoneInput.addEventListener('input', function() { this.value = this.value.replace(/\D/g, ''); });
             }
 
             // 2. Hourly Rate Validation
             const rateInput = document.getElementById('hourly_rate');
             setupValidation(rateInput, (val) => parseFloat(val) >= 0, 'Hourly rate cannot be negative.');
 
             // 3. Pin Code Validation (Dynamic)
             const container = document.getElementById('pinCodeContainer');
             
             const attachPinValidation = (input) => {
                  setupValidation(input, (val) => /^\d{6}$/.test(val), 'Pin code must be 6 digits.');
                  input.addEventListener('input', function() {
                      this.value = this.value.replace(/\D/g, '');
                  });
             };
 
             container.querySelectorAll('.pin-code-input').forEach(attachPinValidation);
 
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
