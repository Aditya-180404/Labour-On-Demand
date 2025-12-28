<?php
$path_prefix = '../';
require_once '../config/security.php';
require_once '../config/db.php';
require_once '../includes/mailer.php';
require_once '../includes/cloudinary_helper.php';
$cld = CloudinaryHelper::getInstance();

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
    
    // Fetch worker details first for email
    $stmt = $pdo->prepare("SELECT name, email FROM workers WHERE id = ?");
    $stmt->execute([$worker_id]);
    $worker = $stmt->fetch();

    $stmt = $pdo->prepare("UPDATE workers SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $worker_id])) {
        $success_msg = "Worker status updated to " . ucfirst($new_status) . ".";
        
        // Send Notification Email
        if ($worker) {
            $subject = "Account Status Update - Labour On Demand";
            $message = "";
            if ($action === 'approve') {
                $message = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                        <h2 style='color: #28a745;'>Account Approved!</h2>
                        <p>Hello <strong>{$worker['name']}</strong>,</p>
                        <p>Congratulations! Your worker profile has been approved by the admin.</p>
                        <p>You can now login to your account and start accepting bookings.</p>
                        <a href='http://" . $_SERVER['HTTP_HOST'] . $path_prefix . "worker/login.php' style='display: inline-block; padding: 10px 20px; color: white; background-color: #28a745; text-decoration: none; border-radius: 5px;'>Login Now</a>
                        <br><br>
                        <p>Regards,<br>Team Labour On Demand</p>
                    </div>";
            } else {
                $message = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                        <h2 style='color: #dc3545;'>Account Status Update</h2>
                        <p>Hello <strong>{$worker['name']}</strong>,</p>
                        <p>We regret to inform you that your worker profile application has been rejected.</p>
                        <p>Please contact support for more information or try registering again with correct details.</p>
                        <br>
                        <p>Regards,<br>Team Labour On Demand</p>
                    </div>";
            }
            sendEmail($worker['email'], $worker['name'], $subject, $message);
        }

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
        $stmt = $pdo->prepare("SELECT profile_image, profile_image_public_id, aadhar_photo, aadhar_photo_public_id, pan_photo, pan_photo_public_id, signature_photo, signature_photo_public_id, pending_profile_image, pending_profile_image_public_id, pending_aadhar_photo, pending_aadhar_photo_public_id, pending_pan_photo, pending_pan_photo_public_id, pending_signature_photo, pending_signature_photo_public_id FROM workers WHERE id = ?");
        $stmt->execute([$worker_id]);
        $w = $stmt->fetch();
        
        if ($w) {
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
                $stmt = $pdo->prepare($query);
                if ($stmt->execute($params)) {
                    $success_msg = "Worker documents updated and approved. Old photos moved to history.";
                } else {
                    $error_msg = "Failed to update documents.";
                }
            }
        }
    } else {
        // Reject update - clear pending docs
        $stmt = $pdo->prepare("UPDATE workers SET pending_profile_image = NULL, pending_profile_image_public_id = NULL, pending_aadhar_photo = NULL, pending_aadhar_photo_public_id = NULL, pending_pan_photo = NULL, pending_pan_photo_public_id = NULL, pending_signature_photo = NULL, pending_signature_photo_public_id = NULL, doc_update_status = 'rejected' WHERE id = ?");
        if ($stmt->execute([$worker_id])) {
            $success_msg = "Document update request rejected.";
        } else {
            $error_msg = "Failed to reject update.";
        }
    }
}

// Search Logic
$worker_q = $_GET['worker_q'] ?? '';
$user_q = $_GET['user_q'] ?? '';
$feedback_q = $_GET['feedback_q'] ?? '';
$booking_q = $_GET['booking_q'] ?? '';
$booking_status = $_GET['booking_status'] ?? '';
$history_q = $_GET['history_q'] ?? '';

// Fetch Pending Workers
$pending_stmt = $pdo->query("SELECT w.*, c.name as category_name FROM workers w LEFT JOIN categories c ON w.service_category_id = c.id WHERE w.status = 'pending'");
$pending_workers = $pending_stmt->fetchAll();

// Fetch Document Update Requests
$doc_updates_stmt = $pdo->query("SELECT w.*, c.name as category_name FROM workers w LEFT JOIN categories c ON w.service_category_id = c.id WHERE w.doc_update_status = 'pending'");
$doc_updates = $doc_updates_stmt->fetchAll();

// Fetch All Workers with Search
if (!empty($worker_q)) {
    $all_workers_stmt = $pdo->prepare("SELECT * FROM workers WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY created_at DESC");
    $all_workers_stmt->execute(["%$worker_q%", "%$worker_q%", "%$worker_q%"]);
} else {
    $all_workers_stmt = $pdo->query("SELECT * FROM workers ORDER BY created_at DESC");
}
$all_workers = $all_workers_stmt->fetchAll();

// Fetch All Users with Search
if (!empty($user_q)) {
    $users_stmt = $pdo->prepare("SELECT * FROM users WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY created_at DESC");
    $users_stmt->execute(["%$user_q%", "%$user_q%", "%$user_q%"]);
} else {
    $users_stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
}
$users = $users_stmt->fetchAll();

// Fetch All Bookings with search & filter
$booking_sql = "SELECT b.*, u.name as user_name, u.pin_code as user_pin, w.name as worker_name 
                FROM bookings b 
                JOIN users u ON b.user_id = u.id 
                JOIN workers w ON b.worker_id = w.id WHERE 1=1";
$booking_params = [];

if (!empty($booking_q)) {
    $booking_sql .= " AND (u.name LIKE ? OR w.name LIKE ? OR b.address LIKE ? OR b.service_date LIKE ?)";
    $booking_params = array_merge($booking_params, ["%$booking_q%", "%$booking_q%", "%$booking_q%", "%$booking_q%"]);
}

if (!empty($booking_status)) {
    $booking_sql .= " AND b.status = ?";
    $booking_params[] = $booking_status;
}

$booking_sql .= " ORDER BY b.created_at DESC";
$bookings_stmt = $pdo->prepare($booking_sql);
$bookings_stmt->execute($booking_params);
$bookings = $bookings_stmt->fetchAll();

// Fetch Document History with search
if (!empty($history_q)) {
    $history_stmt = $pdo->prepare("SELECT h.*, w.name as worker_name FROM worker_photo_history h JOIN workers w ON h.worker_id = w.id WHERE w.name LIKE ? OR h.photo_type LIKE ? ORDER BY h.replaced_at DESC");
    $history_stmt->execute(["%$history_q%", "%$history_q%"]);
} else {
    $history_stmt = $pdo->query("SELECT h.*, w.name as worker_name FROM worker_photo_history h JOIN workers w ON h.worker_id = w.id ORDER BY h.replaced_at DESC");
}
$doc_history = $history_stmt->fetchAll();

// Fetch Feedbacks with Search
if (!empty($feedback_q)) {
    $feedbacks_stmt = $pdo->prepare("SELECT * FROM feedbacks WHERE name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ? ORDER BY created_at DESC");
    $feedbacks_stmt->execute(["%$feedback_q%", "%$feedback_q%", "%$feedback_q%", "%$feedback_q%"]);
} else {
    $feedbacks_stmt = $pdo->query("SELECT * FROM feedbacks ORDER BY created_at DESC");
}
$feedbacks = $feedbacks_stmt->fetchAll();

// Helper function to render feedback rows
// Helper function to render feedback rows
function renderFeedbackTable($feedbacks, $showRole = true, $context = 'default') {
    if (count($feedbacks) > 0) {
        foreach ($feedbacks as $fb) {
            $modalId = "replyModal" . $fb['id'] . "_" . $context;
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
                        <?php if($fb['admin_reply']): ?>
                            <div class="mt-2 p-2 bg-light rounded border-start border-primary border-4">
                                <small class="fw-bold d-block text-primary"><i class="fas fa-reply me-1"></i> Admin Reply:</small>
                                <small class="text-dark"><?php echo htmlspecialchars($fb['admin_reply']); ?></small>
                                <div class="text-end mt-1">
                                    <small class="text-muted" style="font-size: 0.7rem;"><?php echo date('M d, H:i', strtotime($fb['replied_at'])); ?></small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <span class="badge bg-<?php echo $fb['status'] == 'replied' ? 'success' : 'info'; ?> rounded-pill">
                        <?php echo ucfirst($fb['status']); ?>
                    </span>
                </td>
                <td>
                    <div class="d-flex gap-2">
                        <?php if($fb['status'] == 'pending' || $fb['status'] == 'read'): ?>
                            <button type="button" class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>">
                                <i class="fas fa-reply me-1"></i> Reply
                            </button>

                            <!-- Reply Modal -->
                            <div class="modal fade" id="<?php echo $modalId; ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reply to <?php echo htmlspecialchars($fb['name']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="feedback_id" value="<?php echo $fb['id']; ?>">
                                                <input type="hidden" name="reply_email" value="<?php echo htmlspecialchars($fb['email']); ?>">
                                                <input type="hidden" name="reply_name" value="<?php echo htmlspecialchars($fb['name']); ?>">
                                                <input type="hidden" name="reply_subject" value="<?php echo htmlspecialchars($fb['subject']); ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label text-muted small">To:</label>
                                                    <input type="text" class="form-control-plaintext fw-bold" value="<?php echo htmlspecialchars($fb['name']); ?> < <?php echo htmlspecialchars($fb['email']); ?> >" readonly>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Message:</label>
                                                    <textarea name="reply_message" class="form-control" rows="5" required placeholder="Type your reply here..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" name="send_feedback_reply" value="1" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> Send Reply</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <button class="btn btn-success btn-sm rounded-pill px-3" disabled>
                                <i class="fas fa-check me-1"></i> Replied
                            </button>
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

// Handle Feedback Reply (Email + Status Update)
if (isset($_POST['send_feedback_reply']) && isset($_POST['feedback_id'])) {
    $fid = $_POST['feedback_id'];
    $to_email = $_POST['reply_email'];
    $to_name = $_POST['reply_name'];
    $original_subject = $_POST['reply_subject'];
    $reply_message = $_POST['reply_message'];

    $subject = "Re: $original_subject - Labour On Demand";
    $body = "
        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #0d6efd;'>Response from Support</h2>
            <p>Hello <strong>$to_name</strong>,</p>
            <p>Thank you for contacting us regarding '<em>$original_subject</em>'.</p>
            <div style='background: #f8f9fa; padding: 15px; border-left: 4px solid #0d6efd; margin: 15px 0;'>
                <strong>Our Reply:</strong><br>
                " . nl2br(htmlspecialchars($reply_message)) . "
            </div>
            <p>If you have further questions, feel free to reply to this email.</p>
            <br>
            <p>Regards,<br>Admin Team<br>Labour On Demand</p>
        </div>";
    
    // Send Email
    $email_result = sendEmail($to_email, $to_name, $subject, $body);

    if ($email_result['status']) {
        // Update DB
        $stmt = $pdo->prepare("UPDATE feedbacks SET status = 'replied', admin_reply = ?, replied_at = NOW() WHERE id = ?");
        if ($stmt->execute([$reply_message, $fid])) {
            $success_msg = "Reply sent successfully to $to_email and stored securely.";
        } else {
            $error_msg = "Email sent, but failed to update storage in database.";
        }
    } else {
        $error_msg = "Failed to send email: " . $email_result['message'];
    }

    // Refresh data
    $feedbacks_stmt = $pdo->query("SELECT * FROM feedbacks ORDER BY created_at DESC");
    $feedbacks = $feedbacks_stmt->fetchAll();
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
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: #2c3136;
            --sidebar-hover: #3e444a;
            --primary-accent: #ffc107;
        }

        .admin-sidebar { 
            height: 100vh; 
            background: var(--sidebar-bg); 
            position: fixed; 
            width: var(--sidebar-width); 
            padding-top: 20px; 
            z-index: 1050;
            transition: all 0.3s ease;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
        }

        .admin-sidebar a { 
            color: #cfd8dc; 
            padding: 12px 20px; 
            display: block; 
            text-decoration: none; 
            border-left: 4px solid transparent; 
            transition: all 0.2s;
        }

        .admin-sidebar a:hover, .admin-sidebar a.active { 
            background: var(--sidebar-hover); 
            color: white; 
            border-left-color: var(--primary-accent); 
        }

        .main-content { 
            margin-left: var(--sidebar-width); 
            padding: 25px; 
            min-height: 100vh; 
            transition: all 0.3s ease; 
        }

        /* Mobile Top Nav */
        .mobile-header {
            display: none;
            background: var(--sidebar-bg);
            color: white;
            padding: 10px 20px;
            position: sticky;
            top: 0;
            z-index: 1040;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        @media (max-width: 991.98px) {
            .admin-sidebar { 
                left: -260px;
            }
            .admin-sidebar.show {
                left: 0;
            }
            .main-content { 
                margin-left: 0; 
                padding: 15px; 
            }
            .mobile-header {
                display: flex;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1045;
            }
            .sidebar-overlay.show {
                display: block;
            }
        }
        .table thead th { background-color: #f8f9fa; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; white-space: nowrap; }
        .card { border: none; border-radius: 12px; }
        .nav-tabs .nav-link { color: #6c757d; border: none; border-bottom: 2px solid transparent; }
        .nav-tabs .nav-link.active { color: var(--sidebar-bg); background: transparent; border-bottom-color: var(--primary-accent); font-weight: bold; }
    </style>
</head>
<body style="background-color: #ede7c9;">

    <div class="mobile-header">
        <h5 class="mb-0">Admin Panel</h5>
        <button class="btn btn-outline-light btn-sm" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="admin-sidebar" id="adminSidebar">
        <h4 class="text-white text-center mb-4 d-none d-lg-block">Admin Panel</h4>
        <div class="px-3 mb-3 d-lg-none text-center">
            <h5 class="text-white">Admin Panel</h5>
            <hr class="text-white-50">
        </div>
        
        <!-- Dashboard Sections (Visible in Hamburger Menu on Mobile) -->
        <div class="sidebar-nav-links">
            <a href="#" class="active" data-section="dashboard"><i class="fas fa-tachometer-alt me-2"></i>Dashboard Home</a>
            
            <!-- Mobile-Only Tab Links -->
            <div class="d-lg-none">
                <hr class="text-white-50 mx-3 my-2">
                <small class="text-white-50 px-3 mb-2 d-block">Manage Data</small>
                <a href="#" data-bs-toggle="tab" data-bs-target="#doc-updates-tab"><i class="fas fa-file-upload me-2"></i>Document Updates</a>
                <a href="#" data-bs-toggle="tab" data-bs-target="#workers-tab"><i class="fas fa-users-cog me-2"></i>All Workers</a>
                <a href="#" data-bs-toggle="tab" data-bs-target="#users-tab"><i class="fas fa-users me-2"></i>All Customers</a>
                <a href="#" data-bs-toggle="tab" data-bs-target="#bookings-tab"><i class="fas fa-calendar-alt me-2"></i>All Bookings</a>
                <a href="#" data-bs-toggle="tab" data-bs-target="#feedback-tab"><i class="fas fa-comments me-2"></i>Feedback</a>
                <a href="#" data-bs-toggle="tab" data-bs-target="#history-tab"><i class="fas fa-history me-2"></i>Document History</a>
                <hr class="text-white-50 mx-3 my-2">
            </div>

            <a href="<?php echo $path_prefix; ?>index.php"><i class="fas fa-home me-2"></i>View Site</a>
            <a href="<?php echo $path_prefix; ?>logout.php" class="text-danger mt-auto"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Stats -->
            <div class="row g-3 mb-4">
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
            <ul class="nav nav-tabs mb-3 d-none d-lg-flex" id="adminTabs" role="tablist">
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
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#history-tab" type="button">Document History</button>
                </li>
            </ul>

            <div class="tab-content">
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
                                                                        <img src="<?php echo $cld->getUrl($du['pending_profile_image'], ['width' => 120, 'height' => 120, 'crop' => 'fill']); ?>" class="rounded shadow-sm mb-1" style="height: 60px; width: 60px; object-fit: cover;">
                                                                        <a href="<?php echo $du['pending_profile_image']; ?>" target="_blank" class="d-block small text-decoration-none">Full App</a>
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
                                                                            <img src="<?php echo $cld->getUrl($du['pending_aadhar_photo'], ['width' => 120, 'height' => 120, 'crop' => 'fill']); ?>" class="rounded shadow-sm mb-1" style="height: 60px; width: 60px; object-fit: cover;">
                                                                        <?php endif; ?>
                                                                        <a href="<?php echo $du['pending_aadhar_photo']; ?>" target="_blank" class="d-block small text-decoration-none">View Doc</a>
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
                                                                            <img src="<?php echo $cld->getUrl($du['pending_pan_photo'], ['width' => 120, 'height' => 120, 'crop' => 'fill']); ?>" class="rounded shadow-sm mb-1" style="height: 60px; width: 60px; object-fit: cover;">
                                                                        <?php endif; ?>
                                                                        <a href="<?php echo $du['pending_pan_photo']; ?>" target="_blank" class="d-block small text-decoration-none">View Doc</a>
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
                        <div class="card-header bg-white py-3">
                            <form method="GET" class="row g-2 align-items-center">
                                <div class="col-md-6">
                                    <h5 class="mb-0 text-primary fw-bold">All Workers</h5>
                                </div>
                                <div class="col-md-4">
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="worker_q" class="form-control rounded-start-pill" placeholder="Search by name, email, phone..." value="<?php echo htmlspecialchars($worker_q); ?>">
                                        <button class="btn btn-primary rounded-end-pill px-3" type="submit"><i class="fas fa-search"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <?php if(!empty($worker_q)): ?>
                                        <a href="dashboard.php" class="btn btn-link btn-sm text-decoration-none">Clear</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
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
                        <div class="card-header bg-white py-3">
                            <form method="GET" class="row g-2 align-items-center">
                                <div class="col-md-6">
                                    <h5 class="mb-0 text-primary fw-bold">Registered Customers</h5>
                                </div>
                                <div class="col-md-4">
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="user_q" class="form-control rounded-start-pill" placeholder="Search by name, email, phone..." value="<?php echo htmlspecialchars($user_q); ?>">
                                        <button class="btn btn-primary rounded-end-pill px-3" type="submit"><i class="fas fa-search"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <?php if(!empty($user_q)): ?>
                                        <a href="dashboard.php" class="btn btn-link btn-sm text-decoration-none">Clear</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
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
                        <div class="card-header bg-white py-3">
                            <form method="GET" class="row g-2 align-items-center">
                                <div class="col-md-3">
                                    <h5 class="mb-0 text-primary fw-bold">All Bookings</h5>
                                </div>
                                <div class="col-md-4">
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="booking_q" class="form-control rounded-start-pill" placeholder="User, Worker, Date, Location..." value="<?php echo htmlspecialchars($booking_q); ?>">
                                        <button class="btn btn-primary rounded-end-pill px-3" type="submit"><i class="fas fa-search"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <select name="booking_status" class="form-select form-select-sm rounded-pill" onchange="this.form.submit()">
                                        <option value="">All Statuses</option>
                                        <option value="pending" <?php echo $booking_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="accepted" <?php echo $booking_status == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                        <option value="completed" <?php echo $booking_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="rejected" <?php echo $booking_status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        <option value="cancelled" <?php echo $booking_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <?php if(!empty($booking_q) || !empty($booking_status)): ?>
                                        <a href="dashboard.php" class="btn btn-link btn-sm text-decoration-none">Clear</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
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
                                                        
                                                        <?php if(!empty($b['work_proof_images'])): ?>
                                                            <br>
                                                            <button type="button" class="btn btn-sm btn-outline-info rounded-pill py-0 px-2 mt-1" style="font-size: 0.75em;" data-bs-toggle="modal" data-bs-target="#proofModal<?php echo $b['id']; ?>">
                                                                <i class="fas fa-images me-1"></i>View Proof
                                                            </button>
                                                            
                                                            <!-- Proof Modal -->
                                                            <div class="modal fade" id="proofModal<?php echo $b['id']; ?>" tabindex="-1">
                                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title">Work Proof - Booking #<?php echo $b['id']; ?></h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <div class="row g-2">
                                                                                <?php 
                                                                                    $images = explode(',', $b['work_proof_images']);
                                                                                    foreach($images as $img): 
                                                                                ?>
                                                                                    <div class="col-md-4 col-6">
                                                                                        <a href="<?php echo trim($img); ?>" target="_blank">
                                                                                            <img src="<?php echo trim($img); ?>" class="img-fluid rounded border shadow-sm w-100" style="height: 150px; object-fit: cover;">
                                                                                        </a>
                                                                                    </div>
                                                                                <?php endforeach; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
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
                        <div class="card-header bg-white py-3">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <h5 class="mb-0 text-primary fw-bold">User Feedback</h5>
                                </div>
                                <div class="col-md-4">
                                    <form method="GET" class="input-group input-group-sm">
                                        <input type="text" name="feedback_q" class="form-control rounded-start-pill" placeholder="Search name, msg, subject..." value="<?php echo htmlspecialchars($feedback_q); ?>">
                                        <button class="btn btn-primary rounded-end-pill px-3" type="submit"><i class="fas fa-search"></i></button>
                                        <?php if(!empty($feedback_q)): ?>
                                            <a href="dashboard.php" class="btn btn-outline-secondary ms-2 rounded-pill px-2" title="Clear"><i class="fas fa-times"></i></a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                                <div class="col-md-4 text-end">
                                    <ul class="nav nav-pills small d-inline-flex" id="feedbackSubTabs" role="tablist">
                                        <li class="nav-item">
                                            <button class="nav-link active py-1 px-3" data-bs-toggle="tab" data-bs-target="#fb-all" type="button">All (<?php echo count($feedbacks); ?>)</button>
                                        </li>
                                        <li class="nav-item ms-2">
                                            <button class="nav-link py-1 px-3" data-bs-toggle="tab" data-bs-target="#fb-users" type="button">Guests (<?php echo count(array_filter($feedbacks, fn($f) => in_array($f['sender_role'], ['user', 'guest']))); ?>)</button>
                                        </li>
                                        <li class="nav-item ms-2">
                                            <button class="nav-link py-1 px-3" data-bs-toggle="tab" data-bs-target="#fb-workers" type="button">Workers (<?php echo count(array_filter($feedbacks, fn($f) => $f['sender_role'] == 'worker')); ?>)</button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
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
                </div> <!-- End Feedback Tab -->

                <!-- Document History Tab -->
                <div class="tab-pane fade" id="history-tab">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white py-3">
                            <form method="GET" class="row g-2 align-items-center">
                                <div class="col-md-6">
                                    <h5 class="mb-0 text-primary fw-bold">Global Document Update History</h5>
                                </div>
                                <div class="col-md-4">
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="history_q" class="form-control rounded-start-pill" placeholder="Search by name or document type..." value="<?php echo htmlspecialchars($history_q); ?>">
                                        <button class="btn btn-primary rounded-end-pill px-3" type="submit"><i class="fas fa-search"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <?php if(!empty($history_q)): ?>
                                        <a href="dashboard.php" class="btn btn-link btn-sm text-decoration-none">Clear</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Worker</th>
                                            <th>Doc Type</th>
                                            <th>Archived Preview</th>
                                            <th>Action Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(count($doc_history) > 0): ?>
                                            <?php foreach($doc_history as $h): ?>
                                                <tr>
                                                    <td>
                                                        <small class="text-muted d-block"><?php echo date('M d, Y', strtotime($h['replaced_at'])); ?></small>
                                                        <small class="text-muted" style="font-size: 0.75rem;"><?php echo date('h:i A', strtotime($h['replaced_at'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <a href="view_worker.php?id=<?php echo $h['worker_id']; ?>" class="text-decoration-none fw-bold">
                                                            <?php echo htmlspecialchars($h['worker_name']); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info text-dark">
                                                            <?php echo strtoupper($h['photo_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if(strtolower(pathinfo($h['photo_path'], PATHINFO_EXTENSION)) == 'pdf'): ?>
                                                            <a href="<?php echo $h['photo_path']; ?>" target="_blank" class="btn btn-sm btn-outline-danger">
                                                                <i class="fas fa-file-pdf me-1"></i>View PDF
                                                            </a>
                                                        <?php else: ?>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <?php 
                                                                    // Use public_id for optimized preview if available
                                                                    $preview_url = !empty($h['photo_public_id']) 
                                                                        ? $cld->getUrl($h['photo_public_id'], ['width' => 100, 'height' => 100, 'crop' => 'thumb']) 
                                                                        : $h['photo_path'];
                                                                ?>
                                                                <img src="<?php echo $preview_url; ?>" class="rounded border shadow-sm" style="height: 40px; width: 40px; object-fit: cover;">
                                                                <a href="<?php echo $h['photo_path']; ?>" target="_blank" class="small text-decoration-none">Full View</a>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary rounded-pill">Replaced</span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">No historical document updates found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div> <!-- End Document History Tab -->
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

            // Sidebar Toggle Control
            const toggleBtn = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');

            if (toggleBtn && sidebar && overlay) {
                const toggleSidebar = () => {
                    sidebar.classList.toggle('show');
                    overlay.classList.toggle('show');
                    document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
                };

                toggleBtn.addEventListener('click', toggleSidebar);
                overlay.addEventListener('click', toggleSidebar);

                // Auto-close on link click (mobile)
                sidebar.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', (e) => {
                        // Handle active state for tab links in sidebar
                        if (link.hasAttribute('data-bs-toggle')) {
                            sidebar.querySelectorAll('a').forEach(l => l.classList.remove('active'));
                            link.classList.add('active');
                            
                            // Trigger the actual bootstrap tab
                            const targetId = link.getAttribute('data-bs-target');
                            const targetTabBtn = document.querySelector(`.nav-tabs button[data-bs-target="${targetId}"]`);
                            if (targetTabBtn) {
                                bootstrap.Tab.getOrCreateInstance(targetTabBtn).show();
                            }

                            // Scroll to main content on mobile for better visibility
                            if (window.innerWidth < 992) {
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                            }
                        }

                        if (window.innerWidth < 992) toggleSidebar();
                    });
                });

                // Sync sidebar active state with tab clicks
                const dashboardTabs = document.querySelectorAll('button[data-bs-toggle="tab"]');
                dashboardTabs.forEach(tab => {
                    tab.addEventListener('shown.bs.tab', function (e) {
                        const targetId = e.target.getAttribute('data-bs-target');
                        sidebar.querySelectorAll('a[data-bs-toggle="tab"]').forEach(link => {
                            if (link.getAttribute('data-bs-target') === targetId) {
                                sidebar.querySelectorAll('a').forEach(l => l.classList.remove('active'));
                                link.classList.add('active');
                            }
                        });
                    });
                });
            }
        });
    </script>
    <?php include '../includes/lightbox.php'; ?>
</body>
</html>
