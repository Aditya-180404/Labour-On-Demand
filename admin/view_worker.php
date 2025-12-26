<?php
session_start();
require_once '../config/db.php';

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
        $stmt = $pdo->prepare("SELECT profile_image, aadhar_photo, pan_photo, pending_profile_image, pending_aadhar_photo, pending_pan_photo FROM workers WHERE id = ?");
        $stmt->execute([$worker_id]);
        $w = $stmt->fetch();
        
        $updates = [];
        $params = [];
        if ($w['pending_profile_image']) { $updates[] = "profile_image = ?"; $params[] = $w['pending_profile_image']; }
        if ($w['pending_aadhar_photo']) { $updates[] = "aadhar_photo = ?"; $params[] = $w['pending_aadhar_photo']; }
        if ($w['pending_pan_photo']) { $updates[] = "pan_photo = ?"; $params[] = $w['pending_pan_photo']; }
        
        if (!empty($updates)) {
            $query = "UPDATE workers SET " . implode(', ', $updates) . ", pending_profile_image = NULL, pending_aadhar_photo = NULL, pending_pan_photo = NULL, doc_update_status = 'approved' WHERE id = ?";
            $params[] = $worker_id;
            $pdo->prepare($query)->execute($params);
            $success_msg = "Documents updated and approved.";
        }
    } else {
        $pdo->prepare("UPDATE workers SET pending_profile_image = NULL, pending_aadhar_photo = NULL, pending_pan_photo = NULL, doc_update_status = 'rejected' WHERE id = ?")->execute([$worker_id]);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: View Worker - <?php echo htmlspecialchars($worker['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-sidebar { height: 100vh; background: #343a40; position: fixed; width: 250px; padding-top: 20px; z-index: 1000; }
        .admin-sidebar a { color: #cfd8dc; padding: 15px; display: block; text-decoration: none; border-left: 4px solid transparent; }
        .admin-sidebar a:hover, .admin-sidebar a.active { background: #495057; color: white; border-left-color: #ffc107; }
        .main-content { margin-left: 250px; padding: 40px; background-color: #f4f7f6; min-height: 100vh; transition: all 0.3s; }
        .worker-header { background: #fff; padding: 30px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .worker-img { width: 120px; height: 120px; object-fit: cover; border-radius: 15px; border: 3px solid #f1c40f; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .status-badge { font-size: 0.9rem; padding: 5px 15px; border-radius: 20px; }
        
        @media (max-width: 768px) {
            .admin-sidebar { 
                width: 100%; 
                height: auto; 
                position: relative; 
                padding-top: 10px;
                display: flex;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
            }
            .admin-sidebar h4 { width: 100%; margin-bottom: 10px !important; }
            .admin-sidebar a { padding: 10px 15px; border-left: none; border-bottom: 3px solid transparent; }
            .admin-sidebar a:hover, .admin-sidebar a.active { border-left-color: transparent; border-bottom-color: #ffc107; }
            .main-content { margin-left: 0; padding: 20px 15px; }
            .worker-header { text-align: center; justify-content: center !important; }
            .worker-header > div:first-child { flex-direction: column; text-align: center; }
            .worker-header .text-md-end { text-align: center !important; }
        }
    </style>
</head>
<body>

    <div class="admin-sidebar">
        <h4 class="text-white text-center mb-4">Admin Panel</h4>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
        <a href="../logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>

    <div class="main-content">
        <div class="container-fluid">
            <?php if($success_msg): ?>
                <div class="alert alert-success alert-dismissible fade show"><?php echo $success_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if($error_msg): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?php echo $error_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="mb-4">
                <a href="dashboard.php" class="text-decoration-none text-muted"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            </div>

            <div class="worker-header d-flex align-items-center justify-content-between flex-wrap gap-4">
                <div class="d-flex align-items-center gap-4">
                    <?php 
                        $img_src = $worker['profile_image'] && $worker['profile_image'] != 'default.png' 
                            ? "../uploads/workers/" . $worker['profile_image'] 
                            : "https://via.placeholder.com/150"; 
                    ?>
                    <img src="<?php echo $img_src; ?>" alt="Profile" class="worker-img">
                    <div>
                        <h2 class="mb-1 fw-bold"><?php echo htmlspecialchars($worker['name']); ?></h2>
                        <div class="mb-2">
                            <span class="badge bg-warning text-dark me-2">
                                <i class="fas <?php echo $worker['category_icon'] ? $worker['category_icon'] : 'fa-user'; ?> me-1"></i>
                                <?php echo htmlspecialchars($worker['category_name']); ?>
                            </span>
                            <span class="badge bg-<?php echo ($worker['is_available'] ? 'success' : 'secondary'); ?>">
                                <?php echo $worker['is_available'] ? 'Online' : 'Offline'; ?>
                            </span>
                        </div>
                        <p class="text-muted mb-0"><i class="far fa-calendar-alt me-1"></i> Joined: <?php echo date('M d, Y', strtotime($worker['created_at'])); ?></p>
                    </div>
                </div>
                
                <div class="text-md-end">
                    <p class="mb-2">Account Status: 
                        <span class="status-badge bg-<?php echo ($worker['status'] == 'approved' ? 'success' : ($worker['status'] == 'pending' ? 'warning' : 'danger')); ?> text-white fw-bold">
                            <?php echo ucfirst($worker['status']); ?>
                        </span>
                    </p>
                    <form method="POST" class="d-flex gap-2 justify-content-md-end mt-3">
                        <select name="status" class="form-select form-select-sm" style="width: auto;">
                            <option value="pending" <?php echo $worker['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $worker['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $worker['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                        <button type="submit" name="update_status" class="btn btn-dark btn-sm rounded-pill px-4">Update Status</button>
                    </form>
                </div>
            </div>

            <?php if($worker['doc_update_status'] == 'pending'): ?>
            <div class="card mb-4 border-danger border-2 shadow">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-signature me-2"></i>Pending Document/Photo Update Request</h5>
                    <form method="POST" class="d-flex gap-2">
                        <button type="submit" name="doc_action" value="approve" class="btn btn-success btn-sm px-4">Approve All</button>
                        <button type="submit" name="doc_action" value="reject" class="btn btn-light btn-sm px-4">Reject All</button>
                    </form>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-4">The worker has requested the following changes. Compare old vs new before approving.</p>
                    <div class="row text-center g-4">
                        <?php if($worker['pending_profile_image']): ?>
                        <div class="col-md-4">
                            <div class="p-3 border rounded bg-light h-100">
                                <label class="fw-bold d-block mb-3 text-uppercase small text-primary">Profile Photo</label>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="d-block text-muted mb-1">Current</small>
                                        <img src="../uploads/workers/<?php echo $worker['profile_image']; ?>" class="rounded-circle shadow-sm" style="width: 80px; height: 80px; object-fit: cover;">
                                    </div>
                                    <div class="col-6 border-start">
                                        <small class="d-block text-danger mb-1 fw-bold">NEW</small>
                                        <img src="../uploads/workers/<?php echo $worker['pending_profile_image']; ?>" class="rounded-circle shadow border border-danger" style="width: 80px; height: 80px; object-fit: cover;">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if($worker['pending_aadhar_photo']): ?>
                        <div class="col-md-4">
                            <div class="p-3 border rounded bg-light h-100">
                                <label class="fw-bold d-block mb-3 text-uppercase small text-primary">Aadhar Card</label>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="d-block text-muted mb-1">Current</small>
                                        <?php if(strtolower(pathinfo($worker['aadhar_photo'], PATHINFO_EXTENSION)) == 'pdf'): ?>
                                            <i class="fas fa-file-pdf fa-2x text-muted"></i>
                                        <?php else: ?>
                                            <img src="../uploads/documents/<?php echo $worker['aadhar_photo']; ?>" class="rounded shadow-sm" style="width: 100%; max-height: 80px; object-fit: cover;">
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-6 border-start">
                                        <small class="d-block text-danger mb-1 fw-bold">NEW</small>
                                        <?php if(strtolower(pathinfo($worker['pending_aadhar_photo'], PATHINFO_EXTENSION)) == 'pdf'): ?>
                                            <i class="fas fa-file-pdf fa-2x text-danger"></i>
                                            <br><a href="../uploads/documents/<?php echo $worker['pending_aadhar_photo']; ?>" target="_blank" class="small">Open PDF</a>
                                        <?php else: ?>
                                            <img src="../uploads/documents/<?php echo $worker['pending_aadhar_photo']; ?>" class="rounded shadow border border-danger" style="width: 100%; max-height: 80px; object-fit: cover;">
                                            <br><a href="../uploads/documents/<?php echo $worker['pending_aadhar_photo']; ?>" target="_blank" class="small">Full View</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if($worker['pending_pan_photo']): ?>
                        <div class="col-md-4">
                            <div class="p-3 border rounded bg-light h-100">
                                <label class="fw-bold d-block mb-3 text-uppercase small text-primary">PAN Card</label>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="d-block text-muted mb-1">Current</small>
                                        <?php if(strtolower(pathinfo($worker['pan_photo'], PATHINFO_EXTENSION)) == 'pdf'): ?>
                                            <i class="fas fa-file-pdf fa-2x text-muted"></i>
                                        <?php else: ?>
                                            <img src="../uploads/documents/<?php echo $worker['pan_photo']; ?>" class="rounded shadow-sm" style="width: 100%; max-height: 80px; object-fit: cover;">
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-6 border-start">
                                        <small class="d-block text-danger mb-1 fw-bold">NEW</small>
                                        <?php if(strtolower(pathinfo($worker['pending_pan_photo'], PATHINFO_EXTENSION)) == 'pdf'): ?>
                                            <i class="fas fa-file-pdf fa-2x text-danger"></i>
                                            <br><a href="../uploads/documents/<?php echo $worker['pending_pan_photo']; ?>" target="_blank" class="small">Open PDF</a>
                                        <?php else: ?>
                                            <img src="../uploads/documents/<?php echo $worker['pending_pan_photo']; ?>" class="rounded shadow border border-danger" style="width: 100%; max-height: 80px; object-fit: cover;">
                                            <br><a href="../uploads/documents/<?php echo $worker['pending_pan_photo']; ?>" target="_blank" class="small">Full View</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-info-circle text-warning me-2"></i>Personal & Contact Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="text-muted small fw-bold text-uppercase">Email Address</label>
                                    <p class="mb-0 fs-5"><?php echo htmlspecialchars($worker['email']); ?></p>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="text-muted small fw-bold text-uppercase">Phone Number</label>
                                    <p class="mb-0 fs-5"><?php echo htmlspecialchars($worker['phone']); ?></p>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="text-muted small fw-bold text-uppercase">Aadhar Card Number</label>
                                    <p class="mb-0 fs-5 fw-bold text-primary"><?php echo htmlspecialchars($worker['adhar_card']); ?></p>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="text-muted small fw-bold text-uppercase">Hourly Rate</label>
                                    <p class="mb-0 fs-5">₹<?php echo number_format($worker['hourly_rate'], 2); ?></p>
                                </div>
                                <div class="col-12 mb-4">
                                    <label class="text-muted small fw-bold text-uppercase">Residential Address</label>
                                    <p class="mb-0 fs-6"><?php echo nl2br(htmlspecialchars($worker['address'])); ?></p>
                                </div>
                                <div class="col-12">
                                    <label class="text-muted small fw-bold text-uppercase">Bio / Skills</label>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($worker['bio'] ? $worker['bio'] : 'No bio provided.')); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-map-marked-alt text-warning me-2"></i>Service Areas & Location</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <label class="text-muted small fw-bold text-uppercase mb-2 d-block">Serving PIN Codes</label>
                                <?php 
                                    $pins = explode(',', $worker['pin_code']);
                                    foreach($pins as $pin) {
                                        echo '<span class="badge bg-light text-dark border p-2 me-2 mb-2">' . trim($pin) . '</span>';
                                    }
                                ?>
                            </div>
                            <div class="mb-4">
                                <label class="text-muted small fw-bold text-uppercase mb-2 d-block">Preferred Working Area</label>
                                <p><?php echo htmlspecialchars($worker['working_location'] ? $worker['working_location'] : 'Not specified'); ?></p>
                            </div>

                            <div class="mb-4">
                                <label class="text-muted small fw-bold text-uppercase mb-3 d-block"><i class="fas fa-id-card me-2"></i>ID Verification Documents</label>
                                <div class="row g-3">
                                    <div class="col-md-6 text-center">
                                        <div class="p-2 border rounded bg-light mb-2">
                                            <p class="small fw-bold mb-2">Aadhar Card Photo</p>
                                            <?php if($worker['aadhar_photo']): ?>
                                                <?php $ext = strtolower(pathinfo($worker['aadhar_photo'], PATHINFO_EXTENSION)); ?>
                                                <?php if($ext == 'pdf'): ?>
                                                    <a href="../uploads/documents/<?php echo $worker['aadhar_photo']; ?>" target="_blank" class="btn btn-outline-dark btn-sm"><i class="fas fa-file-pdf me-1"></i> View Aadhar PDF</a>
                                                <?php else: ?>
                                                    <a href="../uploads/documents/<?php echo $worker['aadhar_photo']; ?>" target="_blank">
                                                        <img src="../uploads/documents/<?php echo $worker['aadhar_photo']; ?>" class="img-fluid rounded shadow-sm border" style="max-height: 200px;" alt="Aadhar Card">
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted italic">Not uploaded</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-center">
                                        <div class="p-2 border rounded bg-light mb-2">
                                            <p class="small fw-bold mb-2">PAN Card Photo</p>
                                            <?php if($worker['pan_photo']): ?>
                                                <?php $ext = strtolower(pathinfo($worker['pan_photo'], PATHINFO_EXTENSION)); ?>
                                                <?php if($ext == 'pdf'): ?>
                                                    <a href="../uploads/documents/<?php echo $worker['pan_photo']; ?>" target="_blank" class="btn btn-outline-dark btn-sm"><i class="fas fa-file-pdf me-1"></i> View PAN PDF</a>
                                                <?php else: ?>
                                                    <a href="../uploads/documents/<?php echo $worker['pan_photo']; ?>" target="_blank">
                                                        <img src="../uploads/documents/<?php echo $worker['pan_photo']; ?>" class="img-fluid rounded shadow-sm border" style="max-height: 200px;" alt="PAN Card">
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted italic">Not uploaded</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="col-md-6 text-center">
                                        <div class="p-2 border rounded bg-light mb-2">
                                            <p class="small fw-bold mb-2">Signature Photo</p>
                                            <?php if(isset($worker['signature_photo']) && $worker['signature_photo']): ?>
                                                <a href="../uploads/documents/<?php echo $worker['signature_photo']; ?>" target="_blank">
                                                    <img src="../uploads/documents/<?php echo $worker['signature_photo']; ?>" class="img-fluid rounded shadow-sm border" style="max-height: 150px;" alt="Signature">
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted italic">Not uploaded</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if(isset($worker['previous_work_images']) && $worker['previous_work_images']): ?>
                            <div class="mb-4">
                                <label class="text-muted small fw-bold text-uppercase mb-3 d-block"><i class="fas fa-images me-2"></i>Previous Work Pictures</label>
                                <div class="row g-2">
                                    <?php 
                                        $work_imgs = explode(',', $worker['previous_work_images']);
                                        foreach($work_imgs as $img):
                                            if(trim($img)):
                                    ?>
                                    <div class="col-md-4 col-sm-6">
                                        <a href="../uploads/work_images/<?php echo trim($img); ?>" target="_blank">
                                            <img src="../uploads/work_images/<?php echo trim($img); ?>" class="img-fluid rounded shadow-sm border w-100" style="height: 150px; object-fit: cover;" alt="Work Image">
                                        </a>
                                    </div>
                                    <?php 
                                            endif;
                                        endforeach; 
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($worker['latitude'] && $worker['longitude']): ?>
                            <div>
                                <label class="text-muted small fw-bold text-uppercase mb-2 d-block">Last Shared GPS Location</label>
                                <div class="alert alert-light border d-inline-block">
                                    <i class="fas fa-location-arrow text-primary me-2"></i>
                                    Lat: <?php echo $worker['latitude']; ?>, Lng: <?php echo $worker['longitude']; ?>
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $worker['latitude']; ?>,<?php echo $worker['longitude']; ?>" target="_blank" class="ms-3 btn btn-outline-primary btn-sm">
                                        View on Google Maps
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-4 text-center p-4" style="background: linear-gradient(135deg, #f1c40f 0%, #f39c12 100%); color: white;">
                        <h5 class="mb-3 fw-bold">Performance Summary</h5>
                        <div class="row mt-4">
                            <div class="col-6 mb-3 border-end border-white border-opacity-25">
                                <h3 class="fw-bold mb-0"><?php echo $stats['total']; ?></h3>
                                <p class="small mb-0">Total Bookings</p>
                            </div>
                            <div class="col-6 mb-3">
                                <h3 class="fw-bold mb-0"><?php echo $stats['completed']; ?></h3>
                                <p class="small mb-0">Jobs Done</p>
                            </div>
                            <div class="col-12 mt-2 pt-3 border-top border-white border-opacity-25">
                                <h2 class="fw-bold mb-0">₹<?php echo number_format($stats['total_earned'] ? $stats['total_earned'] : 0, 0); ?></h2>
                                <p class="small mb-0">Total Earnings Shared</p>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold">Recent Bookings</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php
                                $recent_bookings_stmt = $pdo->prepare("SELECT b.*, u.name as user_name FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.worker_id = ? ORDER BY b.created_at DESC LIMIT 5");
                                $recent_bookings_stmt->execute([$worker_id]);
                                $recent = $recent_bookings_stmt->fetchAll();
                                
                                if($recent):
                                    foreach($recent as $rb):
                                ?>
                                    <div class="list-group-item px-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold"><?php echo htmlspecialchars($rb['user_name']); ?></span>
                                            <span class="badge bg-<?php echo ($rb['status'] == 'completed' ? 'success' : ($rb['status'] == 'pending' ? 'warning' : 'danger')); ?> small" style="font-size: 0.7rem;">
                                                <?php echo ucfirst($rb['status']); ?>
                                            </span>
                                        </div>
                                        <div class="small text-muted">
                                            <i class="far fa-calendar-alt me-1"></i> <?php echo date('M d, Y', strtotime($rb['service_date'])); ?>
                                        </div>
                                    </div>
                                <?php 
                                    endforeach;
                                else:
                                    echo '<div class="p-3 text-center text-muted">No bookings yet.</div>';
                                endif;
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
