<?php
require_once '../config/security.php';
require_once '../config/db.php';
require_once '../includes/cloudinary_helper.php';
$cld = CloudinaryHelper::getInstance();

// Check Admin Login
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$worker_id = $_GET['id'];
$success_msg = $error_msg = "";

// Handle Status Update from this page
if (isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE workers SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $worker_id])) {
        $success_msg = "Worker status updated to " . ucfirst($new_status) . ".";
    } else {
        $error_msg = "Failed to update status.";
    }
}

// Handle Document Update Approval/Rejection (from this page)
if (isset($_POST['doc_action'])) {
    $action = $_POST['doc_action'];
    if ($action === 'approve') {
        $stmt = $pdo->prepare("SELECT profile_image, profile_image_public_id, aadhar_photo, aadhar_photo_public_id, pan_photo, pan_photo_public_id, signature_photo, signature_photo_public_id, pending_profile_image, pending_profile_image_public_id, pending_aadhar_photo, pending_aadhar_photo_public_id, pending_pan_photo, pending_pan_photo_public_id, pending_signature_photo, pending_signature_photo_public_id FROM workers WHERE id = ?");
        $stmt->execute([$worker_id]);
        $w = $stmt->fetch();
        
        $updates = [];
        $params = [];
        if ($w['pending_profile_image']) { 
            if ($w['profile_image'] && $w['profile_image'] != 'default.png') {
                $pdo->prepare("INSERT INTO worker_photo_history (worker_id, photo_type, photo_path, photo_public_id) VALUES (?, 'profile', ?, ?)")->execute([$worker_id, $w['profile_image'], $w['profile_image_public_id']]);
            }
            $updates[] = "profile_image = ?"; $params[] = $w['pending_profile_image']; 
            $updates[] = "profile_image_public_id = ?"; $params[] = $w['pending_profile_image_public_id']; 
        }
        if ($w['pending_aadhar_photo']) { 
             if ($w['aadhar_photo']) {
                $pdo->prepare("INSERT INTO worker_photo_history (worker_id, photo_type, photo_path, photo_public_id) VALUES (?, 'aadhar', ?, ?)")->execute([$worker_id, $w['aadhar_photo'], $w['aadhar_photo_public_id']]);
            }
            $updates[] = "aadhar_photo = ?"; $params[] = $w['pending_aadhar_photo']; 
            $updates[] = "aadhar_photo_public_id = ?"; $params[] = $w['pending_aadhar_photo_public_id']; 
        }
        if ($w['pending_pan_photo']) { 
             if ($w['pan_photo']) {
                $pdo->prepare("INSERT INTO worker_photo_history (worker_id, photo_type, photo_path, photo_public_id) VALUES (?, 'pan', ?, ?)")->execute([$worker_id, $w['pan_photo'], $w['pan_photo_public_id']]);
            }
            $updates[] = "pan_photo = ?"; $params[] = $w['pending_pan_photo']; 
            $updates[] = "pan_photo_public_id = ?"; $params[] = $w['pending_pan_photo_public_id']; 
        }
        if ($w['pending_signature_photo']) { 
             if ($w['signature_photo']) {
                $pdo->prepare("INSERT INTO worker_photo_history (worker_id, photo_type, photo_path, photo_public_id) VALUES (?, 'signature', ?, ?)")->execute([$worker_id, $w['signature_photo'], $w['signature_photo_public_id']]);
            }
            $updates[] = "signature_photo = ?"; $params[] = $w['pending_signature_photo']; 
            $updates[] = "signature_photo_public_id = ?"; $params[] = $w['pending_signature_photo_public_id']; 
        }
        
        if (!empty($updates)) {
            $query = "UPDATE workers SET " . implode(', ', $updates) . ", pending_profile_image = NULL, pending_profile_image_public_id = NULL, pending_aadhar_photo = NULL, pending_aadhar_photo_public_id = NULL, pending_pan_photo = NULL, pending_pan_photo_public_id = NULL, pending_signature_photo = NULL, pending_signature_photo_public_id = NULL, doc_update_status = 'approved' WHERE id = ?";
            $params[] = $worker_id;
            $pdo->prepare($query)->execute($params);
            $success_msg = "Documents updated and approved. Old photos moved to history.";
        }
    } else {
        $pdo->prepare("UPDATE workers SET pending_profile_image = NULL, pending_profile_image_public_id = NULL, pending_aadhar_photo = NULL, pending_aadhar_photo_public_id = NULL, pending_pan_photo = NULL, pending_pan_photo_public_id = NULL, pending_signature_photo = NULL, pending_signature_photo_public_id = NULL, doc_update_status = 'rejected' WHERE id = ?")->execute([$worker_id]);
        $success_msg = "Document update request rejected.";
    }
    
    // Refresh worker data
    $stmt = $pdo->prepare("SELECT w.*, c.name as category_name, c.icon as category_icon FROM workers w LEFT JOIN categories c ON w.service_category_id = c.id WHERE w.id = ?");
    $stmt->execute([$worker_id]);
    $worker = $stmt->fetch();
}

// Fetch Worker Details
$stmt = $pdo->prepare("SELECT w.*, c.name as category_name, c.icon as category_icon 
                      FROM workers w 
                      LEFT JOIN categories c ON w.service_category_id = c.id 
                      WHERE w.id = ?");
$stmt->execute([$worker_id]);
$worker = $stmt->fetch();

if (!$worker) {
    echo "Worker not found.";
    exit;
}

// Fetch Booking Summary for this worker
$bookings_count_stmt = $pdo->prepare("SELECT COUNT(*) as total, 
                                     SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                                     SUM(CASE WHEN status = 'completed' THEN amount_paid ELSE 0 END) as total_earned
                                     FROM bookings WHERE worker_id = ?");
$bookings_count_stmt->execute([$worker_id]);
$stats = $bookings_count_stmt->fetch();

// Fetch Completed Bookings (Income History)
$income_stmt = $pdo->prepare("SELECT b.*, u.name as customer_name 
                              FROM bookings b 
                              LEFT JOIN users u ON b.user_id = u.id 
                              WHERE b.worker_id = ? AND b.status = 'completed' 
                              ORDER BY b.service_date DESC, b.service_time DESC");
$income_stmt->execute([$worker_id]);
$income_history = $income_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Worker - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Admin Layout Styles from dashboard.php */
        .admin-sidebar { height: 100vh; background: #343a40; position: fixed; width: 250px; padding-top: 20px; z-index: 1000; }
        .admin-sidebar a { color: #cfd8dc; padding: 15px; display: block; text-decoration: none; border-left: 4px solid transparent; }
        .admin-sidebar a:hover, .admin-sidebar a.active { background: #495057; color: white; border-left-color: #ffc107; }
        .main-content { margin-left: 250px; padding: 30px; min-height: 100vh; transition: all 0.3s; }
        
        @media (max-width: 768px) {
            .admin-sidebar { 
                width: 100%; height: auto; position: relative; padding-top: 10px;
                display: flex; flex-direction: row; flex-wrap: wrap; justify-content: center;
            }
            .admin-sidebar h4 { width: 100%; margin-bottom: 10px !important; }
            .admin-sidebar a { padding: 10px 15px; border-left: none; border-bottom: 3px solid transparent; }
            .admin-sidebar a:hover, .admin-sidebar a.active { border-left-color: transparent; border-bottom-color: #ffc107; }
            .main-content { margin-left: 0; padding: 15px; }
        }

        :root { --primary-dark: #2c3e50; --accent: #f39c12; }
        /* Match Admin Background */
        body { font-family: 'Inter', sans-serif; background-color: #d5cc9dff; }
        
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: background-color 0.3s ease; }
        
        .worker-img { 
            width: 120px; height: 120px; object-fit: cover; 
            border-radius: 50%; 
            border: 4px solid #fff; /* Revert to white for Admin Theme */
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
            cursor: pointer;
            transition: transform 0.2s;
        }
        .worker-img:hover { transform: scale(1.05); }
        
        .status-badge { padding: 8px 16px; border-radius: 50px; font-size: 0.85rem; }
        
        .stat-card { 
            background-color: #fff; /* Revert to white for Admin Theme */
            padding: 20px; border-radius: 15px; text-align: center; 
            border: 1px solid rgba(0,0,0,0.05);
        }
        .stat-card i { font-size: 2rem; color: var(--accent); margin-bottom: 10px; }
        
        /* Enhanced label visibility - Default for Admin Theme */
        .text-muted.small.fw-bold.text-uppercase,
        label.text-muted {
            opacity: 0.9;
            font-weight: 600 !important;
            color: #2c3e50 !important;
        }

        /* Tabs - Default for Admin Theme */
        .nav-pills .nav-link {
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid #dee2e6;
            color: #6c757d;
            background-color: #f8f9fa;
        }
        
        .nav-pills .nav-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .nav-pills .nav-link.active {
            background-color: #0d6efd !important;
            color: white !important;
            border-color: #0d6efd !important;
            box-shadow: 0 4px 12px rgba(13,110,253,0.3);
        }

        /* Custom Tab Logic to bypass Bootstrap completely */
        .custom-tab-pane {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }
        .custom-tab-pane.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Admin Sidebar -->
    <div class="admin-sidebar">
        <h4 class="text-white text-center mb-4">Admin Panel</h4>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
        <a href="../logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
        <div class="mb-4 d-flex justify-content-end align-items-center">
            <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill"><i class="fas fa-arrow-left me-2"></i>Back to List</a>
        </div>

        <?php if($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-pill px-4 shadow-sm border-0 mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left Sidebar -->
            <div class="col-lg-4">
                <div class="card text-center p-4 mb-4">
                    <div class="mb-3 position-relative d-inline-block mx-auto">
                        <?php 
                            $worker_img = ($worker['profile_image'] && $worker['profile_image'] != 'default.png') 
                                ? $cld->getUrl($worker['profile_image'], ['width' => 240, 'height' => 240, 'crop' => 'fill', 'gravity' => 'face']) 
                                : "https://via.placeholder.com/120"; 
                            $full_profile_img = ($worker['profile_image'] && $worker['profile_image'] != 'default.png') 
                                ? $cld->getUrl($worker['profile_image_public_id'] ?: $worker['profile_image']) 
                                : $worker_img;
                        ?>
                        <img src="<?php echo $worker_img; ?>" data-full="<?php echo $full_profile_img; ?>" class="rounded-circle mb-3 worker-img" title="Click to enlarge">
                        <span class="position-absolute bottom-0 end-0 p-2 bg-<?php echo ($worker['is_available'] ? 'success' : 'secondary'); ?> border border-2 border-white rounded-circle"></span>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($worker['name']); ?></h3>
                    <div class="mb-1"><span class="badge bg-dark font-monospace opacity-75">ID: <?php echo htmlspecialchars($worker['worker_uid']); ?></span></div>
                    <p class="text-muted"><i class="fas <?php echo $worker['category_icon'] ? $worker['category_icon'] : 'fa-user'; ?> me-2"></i><?php echo htmlspecialchars($worker['category_name']); ?></p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <span class="status-badge bg-<?php echo ($worker['status'] == 'approved' ? 'success' : ($worker['status'] == 'pending' ? 'warning' : 'danger')); ?> text-white">
                            <?php echo ucfirst($worker['status']); ?>
                        </span>
                    </div>

                    <form method="POST" class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Update Account Status</label>
                        <div class="input-group">
                            <select name="status" class="form-select border-0 bg-transparent text-body">
                                <option value="pending" <?php echo $worker['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $worker['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $worker['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                            <button type="submit" name="update_status" class="btn btn-primary"><i class="fas fa-save"></i></button>
                        </div>
                    </form>
                    <hr>
                    <div class="text-start">
                        <label class="text-muted small fw-bold text-uppercase mb-2 d-block">Contact Info</label>
                        <p class="mb-1"><i class="fas fa-envelope me-2 text-primary"></i><?php echo htmlspecialchars($worker['email']); ?></p>
                        <p class="mb-0"><i class="fas fa-phone me-2 text-primary"></i><?php echo htmlspecialchars($worker['phone']); ?></p>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="stat-card">
                            <i class="fas fa-briefcase"></i>
                            <h4 class="fw-bold mb-0"><?php echo $stats['completed']; ?></h4>
                            <small class="text-muted text-uppercase" style="font-size: 0.65rem;">Jobs Done</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card">
                            <i class="fas fa-wallet"></i>
                            <h4 class="fw-bold mb-0">₹<?php echo number_format($stats['total_earned']); ?></h4>
                            <small class="text-muted text-uppercase" style="font-size: 0.65rem;">Earned</small>
                        </div>
                    </div>
                </div>

                <!-- Income History Widget -->
                <div class="card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                         <h5 class="fw-bold mb-0 text-primary"><i class="fas fa-receipt me-2"></i>Income</h5>
                    </div>
                    
                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="incomeSearch" class="form-control bg-light border-start-0" placeholder="Search customer, date...">
                    </div>

                    <div class="income-scroll-container" style="max-height: 400px; overflow-y: auto; overflow-x: hidden;">
                        <?php if($income_history): ?>
                            <div class="list-group list-group-flush" id="incomeList">
                                <?php foreach($income_history as $payment): ?>
                                <div class="list-group-item px-0 py-3 border-bottom income-item">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="fw-bold mb-0 text-truncate" style="max-width: 60%;"><?php echo htmlspecialchars($payment['customer_name']); ?></h6>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill">+₹<?php echo number_format($payment['amount_paid']); ?></span>
                                    </div>
                                    <div class="d-flex align-items-center text-muted small mb-1">
                                        <i class="fas fa-calendar-alt me-2" style="width: 12px;"></i>
                                        <span><?php echo date('d M Y', strtotime($payment['service_date'])); ?></span>
                                        <span class="mx-1">•</span>
                                        <span><?php echo date('h:i A', strtotime($payment['service_time'])); ?></span>
                                    </div>
                                    <div class="d-flex align-items-start text-muted small">
                                        <i class="fas fa-map-marker-alt me-2 mt-1" style="width: 12px;"></i>
                                        <span class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($payment['address']); ?>">
                                            <?php echo htmlspecialchars($payment['address']); ?>
                                        </span>
                                    </div>
                                    <!-- Hidden searchable text -->
                                    <span class="d-none search-data">
                                        <?php echo strtolower($payment['customer_name'] . ' ' . $payment['address'] . ' ' . date('d M Y', strtotime($payment['service_date']))); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div id="noIncomeMatch" class="text-center text-muted py-4 d-none">
                                <i class="fas fa-search mb-2"></i><br>No matches found
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-wallet fa-2x mb-2 opacity-25"></i>
                                <p class="small mb-0">No completed jobs yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-8">
                <?php if($worker['doc_update_status'] == 'pending'): ?>
                <div class="card mb-4 border-start border-4 border-warning bg-body-tertiary shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 fw-bold text-warning"><i class="fas fa-clock me-2"></i>Pending Document Update Request</h5>
                            <form method="POST" class="d-flex gap-2">
                                <button type="submit" name="doc_action" value="approve" class="btn btn-success btn-sm rounded-pill px-3">Approve All</button>
                                <button type="submit" name="doc_action" value="reject" class="btn btn-outline-danger btn-sm rounded-pill px-3">Reject All</button>
                            </form>
                        </div>
                        <div class="row g-3 text-center">
                            <?php if($worker['pending_profile_image']): ?>
                            <div class="col-md-6 col-lg-3">
                                <div class="p-2 border rounded bg-body small">
                                    <span class="d-block mb-1 fw-bold">New Photo</span>
                                    <img src="<?php echo $worker['pending_profile_image']; ?>" data-full="<?php echo $worker['pending_profile_image']; ?>" class="rounded-circle mb-2 shadow-sm" style="width: 50px; height: 50px; object-fit: cover; cursor: pointer;" title="Click to enlarge">
                                    <span class="d-block tiny text-muted">Click image to view</span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if($worker['pending_aadhar_photo']): ?>
                            <div class="col-md-6 col-lg-3">
                                <div class="p-2 border rounded bg-body small">
                                    <span class="d-block mb-1 fw-bold">New Aadhar</span>
                                    <i class="fas fa-id-card text-primary mb-2"></i><br>
                                    <a href="<?php echo $worker['pending_aadhar_photo']; ?>" target="_blank" class="tiny text-decoration-none">Review Document</a>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if($worker['pending_pan_photo']): ?>
                            <div class="col-md-6 col-lg-3">
                                <div class="p-2 border rounded bg-body small">
                                    <span class="d-block mb-1 fw-bold">New PAN</span>
                                    <i class="fas fa-id-card text-danger mb-2"></i><br>
                                    <a href="<?php echo $worker['pending_pan_photo']; ?>" target="_blank" class="tiny text-decoration-none">Review Document</a>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if($worker['pending_signature_photo']): ?>
                            <div class="col-md-6 col-lg-3">
                                <div class="p-2 border rounded bg-body small">
                                    <span class="d-block mb-1 fw-bold">New Signature</span>
                                    <i class="fas fa-file-signature text-secondary mb-2"></i><br>
                                    <a href="<?php echo $worker['pending_signature_photo']; ?>" target="_blank" class="tiny text-decoration-none">Review Document</a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- General Information Section -->
                <div class="card p-4 mb-4">
                    <h5 class="fw-bold mb-4 text-primary"><i class="fas fa-info-circle me-2"></i>General Information</h5>
                        <!-- General Info -->
                    <div id="details">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="text-muted small fw-bold text-uppercase">Aadhar Card Number</label>
                                    <p class="fs-5 fw-bold text-primary"><?php echo htmlspecialchars($worker['adhar_card']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="text-muted small fw-bold text-uppercase">Hourly Rate</label>
                                    <p class="fs-5">₹<?php echo number_format($worker['hourly_rate'], 2); ?>/hr</p>
                                </div>
                                <div class="col-12">
                                    <label class="text-muted small fw-bold text-uppercase">Service PIN Codes</label>
                                    <div class="mt-1">
                                        <?php 
                                            foreach(explode(',', $worker['pin_code']) as $pin) {
                                                echo '<span class="badge bg-body-tertiary text-body border me-1 mb-1">' . trim($pin) . '</span>';
                                            }
                                        ?>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="text-muted small fw-bold text-uppercase">Residential Address</label>
                                    <p><?php echo nl2br(htmlspecialchars($worker['address'])); ?></p>
                                </div>
                                <div class="col-12">
                                    <label class="text-muted small fw-bold text-uppercase">Bio / Skills</label>
                                    <p><?php echo nl2br(htmlspecialchars($worker['bio'] ?: 'No bio provided.')); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($worker['previous_work_images']): ?>
                            <hr class="my-4">
                            <label class="text-muted small fw-bold text-uppercase mb-3 d-block">Portfolio / Previous Work</label>
                            <div class="row g-2">
                                <?php 
                                $port_imgs = explode(',', $worker['previous_work_images'] ?? '');
                                $port_pids = explode(',', $worker['previous_work_public_ids'] ?? '');
                                
                                foreach($port_imgs as $index => $img_url): 
                                    if (empty(trim($img_url))) continue;
                                    
                                    // Use public ID for better Cloudinary transformations if available
                                    $pid = isset($port_pids[$index]) ? trim($port_pids[$index]) : trim($img_url);
                                    $thumb = $cld->getUrl($pid, ['width' => 400, 'height' => 300, 'crop' => 'fill']);
                                    $full_url = $cld->getUrl($pid); // Get high-res URL for lightbox
                                ?>
                                    <div class="col-md-4 col-lg-3">
                                        <img src="<?php echo $thumb; ?>" data-full="<?php echo $full_url; ?>" class="img-fluid rounded border shadow-sm w-100" style="height: 120px; object-fit: cover; transition: transform 0.2s; cursor: pointer;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'" onerror="this.src='https://via.placeholder.com/400x300?text=Image+Not+Found'" title="Click to enlarge">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <!-- Documents Section -->
                <div class="card p-4 mb-4">
                    <h5 class="fw-bold mb-4 text-primary"><i class="fas fa-file-alt me-2"></i>Documents</h5>
                    <div id="docs">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <div class="p-3 border rounded text-center h-100 d-flex flex-column justify-content-between">
                                        <div class="mb-3">
                                            <small class="text-muted d-block">Aadhar Card</small>
                                            <?php if ($worker['aadhar_photo']): ?>
                                                <?php if (strtolower(pathinfo($worker['aadhar_photo'], PATHINFO_EXTENSION)) == 'pdf'): ?>
                                                    <a href="<?php echo $worker['aadhar_photo']; ?>" target="_blank" class="btn btn-sm btn-outline-danger w-100 mt-1"><i class="fas fa-file-pdf me-1"></i> View PDF</a>
                                                <?php else: ?>
                                                    <?php 
                                                        $aadhar_full = $cld->getUrl($worker['aadhar_photo_public_id'] ?: $worker['aadhar_photo']);
                                                        $aadhar_thumb = $cld->getUrl($worker['aadhar_photo_public_id'] ?: $worker['aadhar_photo'], ['width' => 400]);
                                                    ?>
                                                    <img src="<?php echo $aadhar_thumb; ?>" data-full="<?php echo $aadhar_full; ?>" class="img-fluid rounded border mt-1 shadow-sm" style="max-height: 100px; cursor: pointer;" title="Click to enlarge">
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">Not provided</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 border rounded text-center h-100 d-flex flex-column justify-content-between">
                                        <div class="mb-3">
                                            <small class="text-muted d-block">PAN Card</small>
                                            <?php if ($worker['pan_photo']): ?>
                                                <?php if (strtolower(pathinfo($worker['pan_photo'], PATHINFO_EXTENSION)) == 'pdf'): ?>
                                                    <a href="<?php echo $worker['pan_photo']; ?>" target="_blank" class="btn btn-sm btn-outline-danger w-100 mt-1"><i class="fas fa-file-pdf me-1"></i> View PDF</a>
                                                <?php else: ?>
                                                    <?php 
                                                        $pan_full = $cld->getUrl($worker['pan_photo_public_id'] ?: $worker['pan_photo']);
                                                        $pan_thumb = $cld->getUrl($worker['pan_photo_public_id'] ?: $worker['pan_photo'], ['width' => 400]);
                                                    ?>
                                                    <img src="<?php echo $pan_thumb; ?>" data-full="<?php echo $pan_full; ?>" class="img-fluid rounded border mt-1 shadow-sm" style="max-height: 100px; cursor: pointer;" title="Click to enlarge">
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">Not provided</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 border rounded text-center h-100 d-flex flex-column justify-content-between">
                                        <div class="mb-3">
                                            <small class="text-muted d-block">Signature</small>
                                            <?php if ($worker['signature_photo']): ?>
                                                <?php 
                                                    $sig_full = $cld->getUrl($worker['signature_photo_public_id'] ?: $worker['signature_photo']);
                                                    $sig_thumb = $cld->getUrl($worker['signature_photo_public_id'] ?: $worker['signature_photo'], ['width' => 400]);
                                                ?>
                                                <img src="<?php echo $sig_thumb; ?>" data-full="<?php echo $sig_full; ?>" class="img-fluid rounded border mt-1 shadow-sm" style="max-height: 100px; cursor: pointer;" title="Click to enlarge">
                                            <?php else: ?>
                                                <span class="text-muted small">Not provided</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    </div>
                </div>

                <!-- Photo History Section -->
                <div class="card p-4">
                     <h5 class="fw-bold mb-4 text-primary"><i class="fas fa-history me-2"></i>Photo History</h5>
                     <div id="history">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Type</th>
                                            <th>Previous Version</th>
                                            <th>Archived On</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                            $history_stmt = $pdo->prepare("SELECT * FROM worker_photo_history WHERE worker_id = ? ORDER BY replaced_at DESC");
                                            $history_stmt->execute([$worker_id]);
                                            $history = $history_stmt->fetchAll();
                                            if ($history):
                                                foreach($history as $h):
                                        ?>
                                        <tr>
                                            <td><span class="badge bg-secondary text-uppercase"><?php echo $h['photo_type']; ?></span></td>
                                            <td>
                                                <?php if (strtolower(pathinfo($h['photo_path'], PATHINFO_EXTENSION)) == 'pdf'): ?>
                                                    <a href="<?php echo $h['photo_path']; ?>" target="_blank" class="text-decoration-none">
                                                        <i class="fas fa-file-pdf me-1"></i> PDF Document
                                                    </a>
                                                <?php else: ?>
                                                    <img src="<?php echo $cld->getUrl($h['photo_path'], ['width' => 100, 'height' => 100, 'crop' => 'thumb']); ?>" data-full="<?php echo $cld->getUrl($h['photo_public_id'] ?: $h['photo_path']); ?>" class="rounded border shadow-sm" style="width: 50px; height: 50px; object-fit: cover; cursor: pointer;" title="Click to enlarge">
                                                <?php endif; ?>
                                            </td>
                                            <td><small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($h['replaced_at'])); ?></small></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="3" class="text-center text-muted py-4">No history available yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        </div>
                     </div>
                </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set Active Link in Sidebar
        document.querySelectorAll('.admin-sidebar a').forEach(link => {
            if(link.getAttribute('href').includes('workers') || window.location.href.includes('view_worker')) {
                // Approximate logic to highlight Dashboard or Workers
            }
        });

        // Income Search Functionality
        document.getElementById('incomeSearch')?.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const items = document.querySelectorAll('.income-item');
            let hasVisible = false;

            items.forEach(item => {
                const text = item.querySelector('.search-data').textContent;
                if (text.includes(filter)) {
                    item.classList.remove('d-none');
                    hasVisible = true;
                } else {
                    item.classList.add('d-none');
                }
            });

            const noMatch = document.getElementById('noIncomeMatch');
            if (noMatch) {
                noMatch.classList.toggle('d-none', hasVisible);
            }
        });
    </script>
    <?php include '../includes/lightbox.php'; ?>
</body>
</html>
