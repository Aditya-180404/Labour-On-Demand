<?php
session_start();
require_once '../config/db.php';

// Check Admin Login
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$success_msg = $error_msg = "";

// Handle Worker Approval/Rejection (New Registration)
if (isset($_POST['action']) && isset($_POST['worker_id'])) {
    $action = $_POST['action']; // 'approve' or 'reject'
    $worker_id = $_POST['worker_id'];
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $stmt = $pdo->prepare("UPDATE workers SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $worker_id])) {
        $success_msg = "Worker status updated to " . ucfirst($new_status) . ".";
    } else {
        $error_msg = "Failed to update status.";
    }
}

// Handle Document Update Approval/Rejection
if (isset($_POST['doc_action']) && isset($_POST['worker_id'])) {
    $action = $_POST['doc_action']; // 'approve' or 'reject'
    $worker_id = $_POST['worker_id'];
    
    if ($action === 'approve') {
        // Fetch pending docs
        $stmt = $pdo->prepare("SELECT profile_image, aadhar_photo, pan_photo, pending_profile_image, pending_aadhar_photo, pending_pan_photo FROM workers WHERE id = ?");
        $stmt->execute([$worker_id]);
        $w = $stmt->fetch();
        
        if ($w) {
            $updates = [];
            $params = [];
            
            if ($w['pending_profile_image']) {
                $updates[] = "profile_image = ?";
                $params[] = $w['pending_profile_image'];
            }
            if ($w['pending_aadhar_photo']) {
                $updates[] = "aadhar_photo = ?";
                $params[] = $w['pending_aadhar_photo'];
            }
            if ($w['pending_pan_photo']) {
                $updates[] = "pan_photo = ?";
                $params[] = $w['pending_pan_photo'];
            }
            
            if (!empty($updates)) {
                $query = "UPDATE workers SET " . implode(', ', $updates) . ", pending_profile_image = NULL, pending_aadhar_photo = NULL, pending_pan_photo = NULL, doc_update_status = 'approved' WHERE id = ?";
                $params[] = $worker_id;
                $stmt = $pdo->prepare($query);
                if ($stmt->execute($params)) {
                    $success_msg = "Worker documents updated and approved.";
                } else {
                    $error_msg = "Failed to update documents.";
                }
            }
        }
    } else {
        // Reject update - clear pending docs
        $stmt = $pdo->prepare("UPDATE workers SET pending_profile_image = NULL, pending_aadhar_photo = NULL, pending_pan_photo = NULL, doc_update_status = 'rejected' WHERE id = ?");
        if ($stmt->execute([$worker_id])) {
            $success_msg = "Document update request rejected.";
        } else {
            $error_msg = "Failed to reject update.";
        }
    }
}

// Fetch Pending Workers
$pending_stmt = $pdo->query("SELECT w.*, c.name as category_name FROM workers w LEFT JOIN categories c ON w.service_category_id = c.id WHERE w.status = 'pending'");
$pending_workers = $pending_stmt->fetchAll();

// Fetch Document Update Requests
$doc_updates_stmt = $pdo->query("SELECT w.*, c.name as category_name FROM workers w LEFT JOIN categories c ON w.service_category_id = c.id WHERE w.doc_update_status = 'pending'");
$doc_updates = $doc_updates_stmt->fetchAll();

// Fetch All Workers
$all_workers_stmt = $pdo->query("SELECT * FROM workers ORDER BY created_at DESC");
$all_workers = $all_workers_stmt->fetchAll();

// Fetch All Users
$users_stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $users_stmt->fetchAll();

// Fetch All Bookings
$bookings_stmt = $pdo->query("SELECT b.*, u.name as user_name, u.pin_code as user_pin, w.name as worker_name 
                              FROM bookings b 
                              JOIN users u ON b.user_id = u.id 
                              JOIN workers w ON b.worker_id = w.id 
                              ORDER BY b.created_at DESC");
$bookings = $bookings_stmt->fetchAll();

// Fetch Feedbacks
$feedbacks_stmt = $pdo->query("SELECT * FROM feedbacks ORDER BY created_at DESC");
$feedbacks = $feedbacks_stmt->fetchAll();

// Helper function to render feedback rows
function renderFeedbackTable($feedbacks, $showRole = true) {
    if (count($feedbacks) > 0) {
        foreach ($feedbacks as $fb) {
            ?>
            <tr>
                <td><small class="text-muted"><?php echo date('M d, Y', strtotime($fb['created_at'])); ?></small></td>
                <td>
                    <strong><?php echo htmlspecialchars($fb['name']); ?></strong>
                </td>
                <?php if($showRole): ?>
                <td>
                    <?php 
                        $role_color = match($fb['sender_role']) {
                            'worker' => 'warning text-dark',
                            'user' => 'primary',
                            default => 'secondary'
                        };
                        $role_icon = match($fb['sender_role']) {
                            'worker' => 'hard-hat',
                            'user' => 'user',
                            default => 'ghost'
                        };
                    ?>
                    <span class="badge bg-<?php echo $role_color; ?> rounded-pill">
                        <i class="fas fa-<?php echo $role_icon; ?> me-1"></i><?php echo ucfirst($fb['sender_role']); ?>
                    </span>
                </td>
                <?php endif; ?>
                <td><small><?php echo htmlspecialchars($fb['email']); ?></small></td>
                <td>
                    <div class="text-wrap" style="max-width: 250px;">
                        <span class="badge bg-light text-dark border mb-1"><?php echo htmlspecialchars($fb['subject']); ?></span><br>
                        <small class="text-muted"><?php echo htmlspecialchars($fb['message']); ?></small>
                    </div>
                </td>
                <td>
                    <span class="badge bg-<?php echo $fb['status'] == 'replied' ? 'success' : 'info'; ?> rounded-pill">
                        <?php echo ucfirst($fb['status']); ?>
                    </span>
                </td>
                <td>
                    <div class="d-flex gap-2">
                        <a href="mailto:<?php echo $fb['email']; ?>?subject=Re: <?php echo rawurlencode($fb['subject']); ?>&body=Hello <?php echo rawurlencode($fb['name']); ?>,%0D%0A%0D%0A" class="btn btn-primary btn-sm rounded-pill px-3" title="Reply Email">
                            <i class="fas fa-reply me-1"></i> Reply
                        </a>
                        <?php if($fb['status'] == 'pending'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="feedback_id" value="<?php echo $fb['id']; ?>">
                                <input type="hidden" name="handle_feedback" value="mark_replied">
                                <button type="submit" class="btn btn-outline-success btn-sm rounded-pill px-3" title="Mark as Resolved">
                                    <i class="fas fa-check me-1"></i> Done
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php
        }
    } else {
        echo "<tr><td colspan='7' class='text-center py-4 text-muted'>No feedback found.</td></tr>";
    }
}

// Handle Feedback Status Update
if (isset($_POST['handle_feedback']) && $_POST['handle_feedback'] == 'mark_replied' && isset($_POST['feedback_id'])) {
    $fid = $_POST['feedback_id'];
    $stmt = $pdo->prepare("UPDATE feedbacks SET status = 'replied' WHERE id = ?");
    if ($stmt->execute([$fid])) {
        $success_msg = "Feedback marked as replied.";
        // Refresh data
        $feedbacks_stmt = $pdo->query("SELECT * FROM feedbacks ORDER BY created_at DESC");
        $feedbacks = $feedbacks_stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-sidebar { height: 100vh; background: #343a40; position: fixed; width: 250px; padding-top: 20px; z-index: 1000; }
        .admin-sidebar a { color: #cfd8dc; padding: 15px; display: block; text-decoration: none; border-left: 4px solid transparent; }
        .admin-sidebar a:hover, .admin-sidebar a.active { background: #495057; color: white; border-left-color: #ffc107; }
        .main-content { margin-left: 250px; padding: 30px; min-height: 100vh; transition: all 0.3s; }
        
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
            .main-content { margin-left: 0; padding: 15px; }
        }
        .table thead th { background-color: #f8f9fa; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; }
    </style>
</head>
<body style="background-color: #d5cc9dff;">

    <div class="admin-sidebar">
        <h4 class="text-white text-center mb-4">Admin Panel</h4>
        <a href="#" class="active"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
        <!-- <a href="../index.php"><i class="fas fa-home me-2"></i>View Site</a> -->
        <a href="../logout.php" class="text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-danger text-white border-0 shadow">
                        <div class="card-body">
                            <h5 class="card-title">Doc Updates</h5>
                            <h2><?php echo count($doc_updates); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark border-0 shadow">
                        <div class="card-body">
                            <h5 class="card-title">Pending Jobs</h5>
                            <h2><?php echo count($pending_workers); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white border-0 shadow">
                        <div class="card-body">
                            <h5 class="card-title">Total Workers</h5>
                            <h2><?php echo count($all_workers); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white border-0 shadow">
                        <div class="card-body">
                            <h5 class="card-title">Customers</h5>
                            <h2><?php echo count($users); ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <?php if($success_msg): ?>
                <div class="alert alert-success alert-dismissible fade show"><?php echo $success_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <!-- Pending Approvals -->
            <?php if(count($pending_workers) > 0): ?>
                <div class="card mb-4 shadow-sm border-warning border-3">
                    <div class="card-header bg-white">
                        <h5 class="text-warning-emphasis mb-0"><i class="fas fa-exclamation-circle me-2"></i>Pending Worker Requests</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Rate</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($pending_workers as $worker): ?>
                                        <tr>
                                             <td>
                                                 <a href="view_worker.php?id=<?php echo $worker['id']; ?>" class="text-decoration-none fw-bold">
                                                     <?php echo htmlspecialchars($worker['name']); ?>
                                                 </a>
                                             </td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($worker['category_name']); ?></span></td>
                                            <td><?php echo htmlspecialchars($worker['email']); ?></td>
                                            <td><?php echo htmlspecialchars($worker['phone']); ?></td>
                                            <td>₹<?php echo $worker['hourly_rate']; ?></td>
                                            <td>
                                                <form method="POST" class="d-flex gap-2 align-items-center">
                                                    <input type="hidden" name="worker_id" value="<?php echo $worker['id']; ?>">
                                                    <a href="view_worker.php?id=<?php echo $worker['id']; ?>" class="btn btn-outline-primary btn-sm rounded-pill" title="View Profile">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                                                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- All Users & Workers Tabs -->
            <ul class="nav nav-tabs mb-3" id="adminTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#doc-updates-tab" type="button">Document Updates <?php if(count($doc_updates)>0): ?><span class="badge bg-danger rounded-pill"><?php echo count($doc_updates); ?></span><?php endif; ?></button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#workers-tab" type="button">All Workers</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#users-tab" type="button">All Customers</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#bookings-tab" type="button">All Bookings</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#feedback-tab" type="button">Feedback <?php if(count(array_filter($feedbacks, fn($f) => $f['status'] == 'pending')) > 0): ?><span class="badge bg-warning text-dark rounded-pill"><?php echo count(array_filter($feedbacks, fn($f) => $f['status'] == 'pending')); ?></span><?php endif; ?></button>
                </li>
            </ul>

            <div class="tab-content">
<?php
// Fetch All Bookings (already fetched above, this is redundant but keeping for compatibility)
$bookings_stmt = $pdo->query("SELECT b.*, u.name as user_name, u.pin_code as user_pin, w.name as worker_name 
                              FROM bookings b 
                              JOIN users u ON b.user_id = u.id 
                              JOIN workers w ON b.worker_id = w.id 
                              ORDER BY b.created_at DESC");
$bookings = $bookings_stmt->fetchAll();
?>
                <div class="tab-pane fade show active" id="doc-updates-tab">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <?php if(count($doc_updates) == 0): ?>
                                <p class="text-center text-muted py-4">No pending document update requests.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead class="table-light text-uppercase small fw-bold">
                                            <tr>
                                                <th>Worker</th>
                                                <th>Requested Changes</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($doc_updates as $du): ?>
                                                <tr>
                                                    <td style="width: 200px;">
                                                        <a href="view_worker.php?id=<?php echo $du['id']; ?>" class="text-decoration-none fw-bold">
                                                            <?php echo htmlspecialchars($du['name']); ?>
                                                        </a>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($du['category_name']); ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="row g-2">
                                                            <?php if($du['pending_profile_image']): ?>
                                                                <div class="col-auto">
                                                                    <div class="p-2 border rounded text-center" style="width: 120px;">
                                                                        <small class="d-block mb-1 text-primary">New Profile</small>
                                                                        <img src="../uploads/workers/<?php echo $du['pending_profile_image']; ?>" class="rounded shadow-sm mb-1" style="height: 60px; width: 60px; object-fit: cover;">
                                                                        <a href="../uploads/workers/<?php echo $du['pending_profile_image']; ?>" target="_blank" class="d-block small text-decoration-none">Full App</a>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if($du['pending_aadhar_photo']): ?>
                                                                <div class="col-auto">
                                                                    <div class="p-2 border rounded text-center" style="width: 120px;">
                                                                        <small class="d-block mb-1 text-primary">New Aadhar</small>
                                                                        <?php if(strtolower(pathinfo($du['pending_aadhar_photo'], PATHINFO_EXTENSION)) == 'pdf'): ?>
                                                                            <div class="mb-1"><i class="fas fa-file-pdf fa-2x text-danger"></i></div>
                                                                        <?php else: ?>
                                                                            <img src="../uploads/documents/<?php echo $du['pending_aadhar_photo']; ?>" class="rounded shadow-sm mb-1" style="height: 60px; width: 60px; object-fit: cover;">
                                                                        <?php endif; ?>
                                                                        <a href="../uploads/documents/<?php echo $du['pending_aadhar_photo']; ?>" target="_blank" class="d-block small text-decoration-none">View Doc</a>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if($du['pending_pan_photo']): ?>
                                                                <div class="col-auto">
                                                                    <div class="p-2 border rounded text-center" style="width: 120px;">
                                                                        <small class="d-block mb-1 text-primary">New PAN</small>
                                                                        <?php if(strtolower(pathinfo($du['pending_pan_photo'], PATHINFO_EXTENSION)) == 'pdf'): ?>
                                                                            <div class="mb-1"><i class="fas fa-file-pdf fa-2x text-danger"></i></div>
                                                                        <?php else: ?>
                                                                            <img src="../uploads/documents/<?php echo $du['pending_pan_photo']; ?>" class="rounded shadow-sm mb-1" style="height: 60px; width: 60px; object-fit: cover;">
                                                                        <?php endif; ?>
                                                                        <a href="../uploads/documents/<?php echo $du['pending_pan_photo']; ?>" target="_blank" class="d-block small text-decoration-none">View Doc</a>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td style="width: 200px;">
                                                        <form method="POST" class="d-flex gap-2">
                                                            <input type="hidden" name="worker_id" value="<?php echo $du['id']; ?>">
                                                            <button type="submit" name="doc_action" value="approve" class="btn btn-success btn-sm px-3 rounded-pill">Approve</button>
                                                            <button type="submit" name="doc_action" value="reject" class="btn btn-outline-danger btn-sm px-3 rounded-pill">Reject</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Workers Tab -->
                <div class="tab-pane fade" id="workers-tab">
                    <div class="card shadow-sm">
                        <div class="card-body p-0">
                             <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>PIN Codes</th>
                                            <th>Available</th>
                                            <th>Status</th>
                                            <th>Joined</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($all_workers as $w): ?>
                                            <tr>
                                                <td>#<?php echo $w['id']; ?></td>
                                                 <td>
                                                     <a href="view_worker.php?id=<?php echo $w['id']; ?>" class="text-decoration-none fw-bold">
                                                         <?php echo htmlspecialchars($w['name']); ?>
                                                     </a>
                                                 </td>
                                                <td><?php echo htmlspecialchars($w['email']); ?></td>
                                                <td><?php echo htmlspecialchars($w['phone']); ?></td>
                                                <td>
                                                    <?php if($w['pin_code']): ?>
                                                        <span class="badge bg-primary"><?php echo htmlspecialchars($w['pin_code']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if($w['is_available']): ?>
                                                        <span class="badge bg-success"><i class="fas fa-check-circle"></i> Online</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"><i class="fas fa-times-circle"></i> Offline</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $status_color = 'secondary';
                                                        switch($w['status']) {
                                                            case 'approved': $status_color = 'success'; break;
                                                            case 'pending': $status_color = 'warning'; break;
                                                            case 'rejected': $status_color = 'danger'; break;
                                                        }
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_color; ?>">
                                                        <?php echo ucfirst($w['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($w['created_at'])); ?></td>
                                                <td>
                                                     <a href="view_worker.php?id=<?php echo $w['id']; ?>" class="btn btn-outline-primary btn-sm rounded-pill">
                                                         <i class="fas fa-external-link-alt me-1"></i> View
                                                     </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Tab -->
                <div class="tab-pane fade" id="users-tab">
                    <div class="card shadow-sm">
                         <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>PIN Code</th>
                                            <th>Joined</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($users as $u): ?>
                                            <tr>
                                                <td>#<?php echo $u['id']; ?></td>
                                                <td><?php echo htmlspecialchars($u['name']); ?></td>
                                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                                <td><?php echo htmlspecialchars($u['phone']); ?></td>
                                                <td>
                                                    <?php if($u['pin_code']): ?>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($u['pin_code']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bookings Tab -->
                <div class="tab-pane fade" id="bookings-tab">
                    <div class="card shadow-sm">
                         <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>User</th>
                                            <th>User PIN</th>
                                            <th>Worker</th>
                                            <th>Date & Time</th>
                                            <th>Status</th>
                                            <th>Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($bookings as $b): ?>
                                            <tr>
                                                <td>#<?php echo $b['id']; ?></td>
                                                <td><?php echo htmlspecialchars($b['user_name']); ?></td>
                                                <td>
                                                    <?php if($b['user_pin']): ?>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($b['user_pin']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                 <td>
                                                     <a href="view_worker.php?id=<?php echo $b['worker_id']; ?>" class="text-decoration-none">
                                                         <?php echo htmlspecialchars($b['worker_name']); ?>
                                                     </a>
                                                 </td>
                                                <td>
                                                    <div class="fw-bold"><?php echo date('M d, Y', strtotime($b['service_date'])); ?></div>
                                                    <small class="text-muted">
                                                        <?php echo date('h:i A', strtotime($b['service_time'])); ?> 
                                                        <?php if($b['service_end_time']): ?>
                                                            - <?php echo date('h:i A', strtotime($b['service_end_time'])); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                     <?php 
                                                        $status_color = 'secondary';
                                                        switch($b['status']) {
                                                            case 'accepted': $status_color = 'success'; break;
                                                            case 'pending': $status_color = 'warning'; break;
                                                            case 'rejected': case 'cancelled': $status_color = 'danger'; break;
                                                            case 'completed': $status_color = 'info'; break;
                                                        }
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_color; ?>">
                                                        <?php echo ucfirst($b['status']); ?>
                                                    </span>
                                                    <?php if($b['status'] == 'completed'): ?>
                                                        <br><small class="text-muted" style="font-size: 0.7em;">Paid: ₹<?php echo $b['amount_paid']; ?></small>
                                                        <?php if($b['completion_time']): ?>
                                                            <br><small class="text-muted" style="font-size: 0.7em;">Ended: <?php echo date('h:i A', strtotime($b['completion_time'])); ?></small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo htmlspecialchars(substr($b['address'], 0, 30)) . (strlen($b['address']) > 30 ? '...' : ''); ?></small>
                                                    <?php if($b['booking_latitude'] !== null && $b['booking_longitude'] !== null): ?>
                                                        <br><a href="https://www.google.com/maps/search/?api=1&query=<?php echo $b['booking_latitude']; ?>,<?php echo $b['booking_longitude']; ?>" target="_blank" class="small text-success text-decoration-none fw-bold"><i class="fas fa-map-marked-alt me-1"></i>View Map</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feedback Tab -->
                <div class="tab-pane fade" id="feedback-tab">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 text-primary fw-bold">User Feedback</h5>
                            <ul class="nav nav-pills small" id="feedbackSubTabs" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active py-1 px-3" data-bs-toggle="tab" data-bs-target="#fb-all" type="button">All (<?php echo count($feedbacks); ?>)</button>
                                </li>
                                <li class="nav-item ms-2">
                                    <button class="nav-link py-1 px-3" data-bs-toggle="tab" data-bs-target="#fb-users" type="button">Customers & Guests (<?php echo count(array_filter($feedbacks, fn($f) => in_array($f['sender_role'], ['user', 'guest']))); ?>)</button>
                                </li>
                                <li class="nav-item ms-2">
                                    <button class="nav-link py-1 px-3" data-bs-toggle="tab" data-bs-target="#fb-workers" type="button">Workers (<?php echo count(array_filter($feedbacks, fn($f) => $f['sender_role'] == 'worker')); ?>)</button>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body p-0">
                            <div class="tab-content">
                                <!-- All Feedbacks -->
                                <div class="tab-pane fade show active" id="fb-all">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Sender</th>
                                                    <th>Role</th>
                                                    <th>Email</th>
                                                    <th>Subject / Message</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php renderFeedbackTable($feedbacks); ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- Customer Feedbacks -->
                                <div class="tab-pane fade" id="fb-users">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Sender</th>
                                                    <th>Type</th>
                                                    <th>Email</th>
                                                    <th>Subject / Message</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php renderFeedbackTable(array_filter($feedbacks, fn($f) => in_array($f['sender_role'], ['user', 'guest'])), true); ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- Worker Feedbacks -->
                                <div class="tab-pane fade" id="fb-workers">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Sender</th>
                                                    <th>Email</th>
                                                    <th>Subject / Message</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php renderFeedbackTable(array_filter($feedbacks, fn($f) => $f['sender_role'] == 'worker'), false); ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div> <!-- End Feedback Tab -->
            </div> <!-- End Tab Content -->
        </div> <!-- End container-fluid -->
    </div> <!-- End main-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab persistence logic
        document.addEventListener('DOMContentLoaded', function() {
            // Save active tab on click
            const tabs = document.querySelectorAll('button[data-bs-toggle="tab"]');
            tabs.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(e) {
                    const targetId = e.target.getAttribute('data-bs-target');
                    localStorage.setItem('adminActiveTab', targetId);
                });
            });

            // Restore active tab
            const activeTabId = localStorage.getItem('adminActiveTab');
            if (activeTabId) {
                const activeTab = document.querySelector(`button[data-bs-target="${activeTabId}"]`);
                if (activeTab) {
                    const tabTrigger = new bootstrap.Tab(activeTab);
                    tabTrigger.show();
                }
            }
        });
    </script>
</body>
</html>
