<?php
require_once '../config/security.php';
require_once '../config/db.php';
require_once '../includes/mailer.php';
require_once '../includes/cloudinary_helper.php';

// Check if worker is logged in
if (!isset($_SESSION['worker_id'])) {
    header("Location: login.php");
    exit;
}

$worker_id = $_SESSION['worker_id'];
$success_msg = $error_msg = "";

// Handle Booking Actions (Accept/Reject)
if (isset($_POST['booking_action']) && isset($_POST['booking_id'])) {
    $status = $_POST['booking_action']; // 'accepted' or 'rejected'
    $booking_id = $_POST['booking_id'];
    
    // Verify booking belongs to this worker and is pending
    $verify_stmt = $pdo->prepare("SELECT id, user_id, service_date, service_time FROM bookings WHERE id = ? AND worker_id = ? AND status = 'pending'");
    $verify_stmt->execute([$booking_id, $worker_id]);
    $booking_data = $verify_stmt->fetch();
    
    if ($booking_data) {
        $update_stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        if ($update_stmt->execute([$status, $booking_id])) {
            $success_msg = "Booking " . ucfirst($status) . ".";

            // SEND EMAIL TO USER (Acceptance)
            if ($status === 'accepted') {
                $u_stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                $u_stmt->execute([$booking_data['user_id']]);
                $user = $u_stmt->fetch();

                if ($user) {
                    $subject = "Booking Accepted! - Labour On Demand";
                    $message = "
                        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                            <h2 style='color: #28a745;'>Booking Confirmed!</h2>
                            <p>Hello <strong>{$user['name']}</strong>,</p>
                            <p>Great news! The worker has accepted your booking request.</p>
                            <p>They will arrive on <strong>" . date('M d, Y', strtotime($booking_data['service_date'])) . "</strong> at <strong>" . date('h:i A', strtotime($booking_data['service_time'])) . "</strong>.</p>
                            <br>
                            <p>Thank you for choosing Labour On Demand.</p>
                        </div>";
                    sendEmail($user['email'], $user['name'], $subject, $message);
                }
            }

        } else {
            $error_msg = "Failed to update booking.";
        }
    } else {
        $error_msg = "Invalid booking.";
    }
}

// Handle Booking Completion - Step 1: Upload Proof & Send OTP
if (isset($_POST['initiate_completion']) && isset($_POST['booking_id']) && isset($_POST['amount_paid'])) {
    $booking_id = $_POST['booking_id'];
    $amount_paid = $_POST['amount_paid'];
    
    if ($amount_paid < 0) {
        $error_msg = "Amount received cannot be negative.";
    } else {
        // Verify booking
        $stmt = $pdo->prepare("SELECT b.id, b.user_id, u.name as user_name, u.email as user_email, w.name as worker_name, w.worker_uid, c.name as category_name
                               FROM bookings b 
                               JOIN users u ON b.user_id = u.id 
                               JOIN workers w ON b.worker_id = w.id 
                               JOIN categories c ON w.service_category_id = c.id
                               WHERE b.id = ? AND b.worker_id = ? AND b.status = 'accepted'");
        $stmt->execute([$booking_id, $worker_id]);
        $booking_data = $stmt->fetch();
        
        if ($booking_data) {
            // Handle File Uploads (CLOUD STORAGE)
            $proof_images_urls = [];
            $proof_images_ids = [];
            $cld = CloudinaryHelper::getInstance();
            
            if (isset($_FILES['work_proof'])) {
                $total_files = count($_FILES['work_proof']['name']);
                for ($i = 0; $i < $total_files; $i++) {
                    if ($_FILES['work_proof']['error'][$i] == 0) {
                        $upload = $cld->uploadImage($_FILES['work_proof']['tmp_name'][$i], CLD_FOLDER_WORKER_WORK_DONE, 'standard');
                        if ($upload) {
                            $proof_images_urls[] = $upload['url'];
                            $proof_images_ids[] = $upload['public_id'];
                        }
                    }
                }
            }
            
            $proof_images_str = implode(',', $proof_images_urls);
            $proof_ids_str = implode(',', $proof_images_ids);
            
            // Generate OTP
            $otp = rand(100000, 999999);
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Update Booking with Temp Data
            $update_stmt = $pdo->prepare("UPDATE bookings SET amount_paid = ?, work_proof_images = ?, work_done_public_ids = ?, completion_otp = ?, completion_otp_expires_at = ? WHERE id = ?");
            if ($update_stmt->execute([$amount_paid, $proof_images_str, $proof_ids_str, $otp, $expires_at, $booking_id])) {
                
                // Send OTP Email
                $mail_result = sendBookingCompletionOTP($booking_data['user_email'], $otp, $booking_data['user_name'], $booking_data['worker_name'], $booking_data['category_name'], $booking_data['worker_uid'], $amount_paid);
                
                if ($mail_result['status']) {
                    $success_msg = "Proof uploaded. OTP sent to customer for verification.";
                } else {
                    $error_msg = "Proof uploaded but failed to send OTP email: " . $mail_result['message'];
                }
            } else {
                $error_msg = "Failed to update booking details.";
            }
        } else {
            $error_msg = "Invalid booking or already completed.";
        }
    }
}

// Handle Booking Completion - Step 2: Verify OTP
if (isset($_POST['verify_completion']) && isset($_POST['booking_id']) && isset($_POST['otp'])) {
    $booking_id = $_POST['booking_id'];
    $entered_otp = trim($_POST['otp']);
    
    // Verify booking and OTP
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND worker_id = ? AND status = 'accepted'");
    $stmt->execute([$booking_id, $worker_id]);
    $booking = $stmt->fetch();
    
    if ($booking) {
        if ($booking['completion_otp'] == $entered_otp) {
            
            // Validate Expiry (Optional, if you want strictly 10 mins)
            // if (strtotime($booking['completion_otp_expires_at']) < time()) { ... }
            
            $completion_time = date('Y-m-d H:i:s');
            
            // Mark Completed
            $update_stmt = $pdo->prepare("UPDATE bookings SET status = 'completed', completion_time = ?, completion_otp = NULL, completion_otp_expires_at = NULL WHERE id = ?");
            if ($update_stmt->execute([$completion_time, $booking_id])) {
                 $success_msg = "Job successfully marked as completed!";
            } else {
                 $error_msg = "Database error verifying OTP.";
            }
        } else {
            $error_msg = "Invalid OTP. Please ask the customer for the correct code.";
        }
    } else {
        $error_msg = "Invalid booking.";
    }
}

// Handle Booking Extension
if (isset($_POST['extend_booking']) && isset($_POST['booking_id']) && isset($_POST['extension_minutes'])) {
    $booking_id = $_POST['booking_id'];
    $extension_minutes = intval($_POST['extension_minutes']);
    
    // Fetch current booking details
    $stmt = $pdo->prepare("SELECT service_date, service_end_time FROM bookings WHERE id = ? AND worker_id = ?");
    $stmt->execute([$booking_id, $worker_id]);
    $booking = $stmt->fetch();
    
    if ($booking) {
        $current_end = $booking['service_end_time'];
        $new_end = date('H:i:s', strtotime("$current_end +$extension_minutes minutes"));
        
        // Check for overlaps with OTHER bookings
        $overlap_stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM bookings 
            WHERE worker_id = ? 
            AND service_date = ? 
            AND id != ?
            AND status NOT IN ('cancelled', 'rejected') 
            AND (
                (service_time < ?) -- existing start < requested end
                AND 
                (service_end_time > ?) -- existing end > requested start
            )
        ");
        $overlap_stmt->execute([$worker_id, $booking['service_date'], $booking_id, $new_end, $current_end]);
        $has_overlap = $overlap_stmt->fetchColumn() > 0;
        
        if ($has_overlap) {
            $error_msg = "Cannot extend. You have another booking that starts before the new end time.";
        } else {
            $update_stmt = $pdo->prepare("UPDATE bookings SET service_end_time = ? WHERE id = ?");
            if ($update_stmt->execute([$new_end, $booking_id])) {
                $success_msg = "Job extended to " . date('h:i A', strtotime($new_end));
            } else {
                $error_msg = "Failed to extend job.";
            }
        }
    }
}

// Fetch Bookings
// Pending
$pending_stmt = $pdo->prepare("
    SELECT b.*, u.name as user_name, u.phone as user_phone, u.email as user_email, u.user_uid 
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    WHERE b.worker_id = ? AND b.status = 'pending' 
    ORDER BY b.service_date ASC, b.service_time ASC
");
$pending_stmt->execute([$worker_id]);
$pending_bookings = $pending_stmt->fetchAll();

// Upcoming (Accepted)
$upcoming_stmt = $pdo->prepare("
    SELECT b.*, u.name as user_name, u.phone as user_phone, u.user_uid 
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    WHERE b.worker_id = ? AND b.status = 'accepted'
    ORDER BY b.service_date ASC, b.service_time ASC
");
$upcoming_stmt->execute([$worker_id]);
$upcoming_bookings = $upcoming_stmt->fetchAll();

// Past/Completed/Rejected
$history_stmt = $pdo->prepare("
    SELECT b.*, u.name as user_name, u.user_uid 
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    WHERE b.worker_id = ? AND (b.status = 'completed' OR b.status = 'rejected' OR b.status = 'cancelled')
    ORDER BY b.service_date DESC, b.service_time DESC 
");
$history_stmt->execute([$worker_id]);
$history_bookings = $history_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Jobs - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .page-header { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 2.5rem 0; margin-bottom: 2rem; }
        .card { border: none; border-radius: 15px; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05); margin-bottom: 1.5rem; }
        .btn-action { border-radius: 20px; padding: 0.3em 1em; font-size: 0.9em; }
    </style>
</head>
<body>

    <?php 
    $path_prefix = '../';
    include '../includes/worker_navbar.php'; 
    ?>

    <div class="page-header">
        <div class="container text-center text-md-start">
            <h2 class="fw-bold"><i class="fas fa-briefcase me-2"></i>My Job Panel</h2>
            <p class="mb-0 opacity-75">Manage your service requests and upcoming work</p>
        </div>
    </div>

    <div class="container mb-5">
        <?php if($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show"><?php echo $success_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?php echo $error_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="row">
            <!-- Main Content Area -->
            <div class="col-lg-8">
                
                <!-- Pending Requests -->
                <div class="card border-warning border-start border-5 shadow-sm">
                    <div class="card-header bg-transparent border-bottom-0 pt-3">
                        <h5 class="card-title mb-0 text-warning"><i class="fas fa-bell me-2"></i>New Requests</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($pending_bookings) > 0): ?>
                            <?php foreach($pending_bookings as $booking): ?>
                                <div class="card mb-3 border bg-body-tertiary">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($booking['user_name']); ?></h5>
                                                <div class="mb-2"><span class="badge bg-light text-dark border font-monospace small">Customer ID: <?php echo htmlspecialchars($booking['user_uid']); ?></span></div>
                                                <p class="mb-1 text-muted small"><i class="fas fa-map-marker-alt me-1 text-danger"></i><?php echo htmlspecialchars($booking['address']); ?></p>
                                                <p class="text-muted small mb-2"><i class="fas fa-hashtag me-1"></i>Booking: <strong>#BK-<?php echo $booking['id']; ?></strong></p>
                                                <div class="d-flex gap-3 text-sm flex-wrap small fw-bold">
                                                    <span><i class="far fa-calendar me-1"></i><?php echo date('D, M d', strtotime($booking['service_date'])); ?></span>
                                                    <span><i class="far fa-clock me-1"></i><?php echo date('h:i A', strtotime($booking['service_time'])); ?></span>
                                                    <?php if($booking['booking_latitude'] !== null && $booking['booking_longitude'] !== null): ?>
                                                        <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $booking['booking_latitude']; ?>,<?php echo $booking['booking_longitude']; ?>" target="_blank" class="text-success text-decoration-none"><i class="fas fa-map-marked-alt me-1"></i>Map</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                                <form method="POST" class="d-flex gap-2 justify-content-md-end">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <button type="submit" name="booking_action" value="accepted" class="btn btn-success btn-sm btn-action shadow-sm"><i class="fas fa-check me-1"></i>Accept</button>
                                                    <button type="submit" name="booking_action" value="rejected" class="btn btn-outline-danger btn-sm btn-action"><i class="fas fa-times me-1"></i>Reject</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted opacity-50">
                                <i class="far fa-calendar-check fa-3x mb-2"></i>
                                <p>No new requests at the moment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Jobs -->
                <div class="card mt-4 border-primary border-start border-5 shadow-sm">
                    <div class="card-header bg-transparent border-bottom-0 pt-3">
                        <h5 class="card-title mb-0 text-primary"><i class="fas fa-calendar-alt me-2"></i>Upcoming Jobs</h5>
                    </div>
                    <div class="card-body">
                         <?php if (count($upcoming_bookings) > 0): ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead class="table-secondary border-0">
                                        <tr>
                                            <th>Schedule</th>
                                            <th>Customer</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($upcoming_bookings as $booking): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold small text-danger"><?php echo date('M d', strtotime($booking['service_date'])); ?></div>
                                                    <div class="small">
                                                        <span class="text-muted"><?php echo date('h:i A', strtotime($booking['service_time'])); ?></span>
                                                        <span class="text-secondary mx-1">→</span>
                                                        <span class="text-primary fw-bold"><?php echo date('h:i A', strtotime($booking['service_end_time'])); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="fw-bold mb-1"><?php echo htmlspecialchars($booking['user_name']); ?></div>
                                                    <div class="small mb-1 font-monospace text-muted">ID: <?php echo htmlspecialchars($booking['user_uid']); ?></div>
                                                    <div class="small mb-1 text-muted"><span class="badge bg-light text-dark border">#BK-<?php echo $booking['id']; ?></span></div>
                                                    <div class="small mb-1 text-muted"><i class="fas fa-location-arrow me-1"></i><?php echo htmlspecialchars(substr($booking['address'],0,30)); ?>...</div>
                                                    <a href="tel:<?php echo htmlspecialchars($booking['user_phone']); ?>" class="btn btn-light btn-sm rounded-pill py-0 px-2 small text-primary border"><i class="fas fa-phone-alt me-1"></i>Call</a>
                                                </td>
                                                <td>
                                                    <?php if(!empty($booking['completion_otp'])): ?>
                                                        <!-- OTP Sent State -->
                                                        <button type="button" class="btn btn-warning btn-sm rounded-pill px-3 shadow-sm mb-1" data-bs-toggle="modal" data-bs-target="#verifyModal<?php echo $booking['id']; ?>">
                                                            <i class="fas fa-key me-1"></i>Verify OTP
                                                        </button>
                                                    <?php else: ?>
                                                        <!-- Initial State -->
                                                        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm mb-1" data-bs-toggle="modal" data-bs-target="#completeModal<?php echo $booking['id']; ?>">
                                                            Mark Done
                                                        </button>
                                                    <?php endif; ?>

                                                    <button type="button" class="btn btn-outline-warning btn-sm rounded-pill px-3 shadow-sm mb-1" data-bs-toggle="modal" data-bs-target="#extendModal<?php echo $booking['id']; ?>">
                                                        <i class="fas fa-clock me-1"></i>Extend
                                                    </button>
                                                    
                                                    <!-- Extend Modal -->
                                                    <div class="modal fade" id="extendModal<?php echo $booking['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog modal-dialog-centered modal-sm">
                                                            <div class="modal-content border-0 shadow rounded-4">
                                                                 <div class="modal-header border-0 pb-0">
                                                                    <h5 class="modal-title fw-bold">Extend Job</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST">
                                                                    <div class="modal-body p-4 text-center">
                                                                        <p class="small text-muted mb-4">How much longer will this take?</p>
                                                                        <div class="d-grid gap-2">
                                                                            <button type="submit" name="extension_minutes" value="30" class="btn btn-outline-primary rounded-pill">Add 30 Mins</button>
                                                                            <button type="submit" name="extension_minutes" value="60" class="btn btn-outline-primary rounded-pill">Add 1 Hour</button>
                                                                            <button type="submit" name="extension_minutes" value="120" class="btn btn-outline-primary rounded-pill">Add 2 Hours</button>
                                                                        </div>
                                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                        <input type="hidden" name="extend_booking" value="1">
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Step 1: Upload & Request Modal -->
                                                    <div class="modal fade" id="completeModal<?php echo $booking['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content border-0 shadow rounded-4">
                                                                <div class="modal-header border-0 pb-0">
                                                                    <h5 class="modal-title fw-bold">Job Completion Proof</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST" enctype="multipart/form-data" class="completion-form">
                                                                    <div class="modal-body p-4">
                                                                        <p class="small text-muted mb-3">Upload photos of the work done and enter the amount received. An OTP will be sent to the customer.</p>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-bold text-muted">Amount Received (₹)</label>
                                                                            <div class="input-group">
                                                                                <span class="input-group-text bg-body-secondary border-0 text-body">₹</span>
                                                                                <input type="number" name="amount_paid" class="form-control bg-body-secondary border-0 text-body" required placeholder="0.00" min="0" step="0.01">
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-bold text-muted">Work Proof Photos</label>
                                                                            <input type="file" name="work_proof[]" class="form-control" multiple accept="image/*" required>
                                                                            <div class="form-text">You can select multiple images.</div>
                                                                        </div>

                                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                        <input type="hidden" name="initiate_completion" value="1">
                                                                        <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">Upload & Send OTP</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Step 2: Verify OTP Modal -->
                                                    <div class="modal fade" id="verifyModal<?php echo $booking['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content border-0 shadow rounded-4">
                                                                <div class="modal-header border-0 pb-0">
                                                                    <h5 class="modal-title fw-bold">Verify OTP</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST">
                                                                    <div class="modal-body p-4 text-center">
                                                                        <p class="text-muted small mb-4">Enter the 6-digit code sent to <strong><?php echo htmlspecialchars($booking['user_name']); ?></strong> to complete this job.</p>
                                                                        
                                                                        <div class="mb-4">
                                                                            <input type="text" name="otp" class="form-control text-center fs-2 letter-spacing-2" placeholder="XXXXXX" maxlength="6" required style="letter-spacing: 5px;">
                                                                        </div>

                                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                        <input type="hidden" name="verify_completion" value="1">
                                                                        <button type="submit" class="btn btn-success w-100 rounded-pill py-2 fw-bold">Verify & Finish</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                             <div class="text-center py-4 text-muted">
                                <p>Relax! No upcoming jobs today.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- History Sidebar -->
            <div class="col-lg-4">
                <h5 class="mb-3 fw-bold text-muted">Recent History</h5>
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php if (count($history_bookings) > 0): ?>
                                <?php foreach($history_bookings as $booking): ?>
                                    <li class="list-group-item py-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div>
                                                <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($booking['user_name']); ?></h6>
                                                <small class="text-muted font-monospace" style="font-size: 0.75rem;">ID: <?php echo htmlspecialchars($booking['user_uid']); ?> | #BK-<?php echo $booking['id']; ?></small>
                                            </div>
                                            <span class="badge bg-<?php 
                                                echo match($booking['status']) {
                                                    'completed' => 'success',
                                                    'cancelled' => 'warning',
                                                    'rejected' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?> rounded-pill small"><?php echo ucfirst($booking['status']); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted"><i class="far fa-calendar me-1"></i><?php echo date('M d', strtotime($booking['service_date'])); ?></small>
                                            <?php if($booking['amount_paid']): ?>
                                                <small class="text-success fw-bold">₹<?php echo $booking['amount_paid']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item text-center text-muted py-4">No job history.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/worker_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $path_prefix; ?>assets/js/theme.js"></script>
    <script src="<?php echo $path_prefix; ?>assets/js/image_compressor.js"></script>
    <script>
        // Real-time Validation for Job Completion
        document.addEventListener('DOMContentLoaded', function() {
            const amountInputs = document.querySelectorAll('input[name="amount_paid"]');
            
            amountInputs.forEach(input => {
                // Create error div
                let errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.style.display = 'none';
                errorDiv.style.color = '#dc3545';
                errorDiv.style.fontSize = '0.875em';
                errorDiv.innerText = 'Amount cannot be negative.';
                
                // Append after parent input-group
                input.closest('.input-group').parentNode.appendChild(errorDiv);

                const validate = () => {
                    if (parseFloat(input.value) < 0) {
                        input.classList.add('is-invalid');
                        errorDiv.style.display = 'block';
                    } else {
                        input.classList.remove('is-invalid');
                        errorDiv.style.display = 'none';
                    }
                };

                input.addEventListener('input', validate);
                input.addEventListener('blur', validate);
            });

            // --- IMAGE COMPRESSION FOR JOB COMPLETION ---
            ImageCompressor.attach('.completion-form', 'Optimizing proof photos...');
        });
    </script>
</body>
</html>
