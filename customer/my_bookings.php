<?php
require_once '../config/security.php';
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = $error_msg = "";

// Handle Cancellation
if (isset($_POST['cancel_booking']) && isset($_POST['booking_id'])) {
    $booking_id = $_POST['booking_id'];
    
    // Verify booking belongs to user and is pending
    $verify_stmt = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND user_id = ? AND status = 'pending'");
    $verify_stmt->execute([$booking_id, $user_id]);
    
    if ($verify_stmt->rowCount() > 0) {
        $update_stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
        if ($update_stmt->execute([$booking_id])) {
            $success_msg = "Booking cancelled successfully.";
        } else {
            $error_msg = "Failed to cancel booking.";
        }
    } else {
        $error_msg = "Cannot cancel this booking.";
    }
}

// Fetch Active Bookings (Pending, Accepted)
$active_stmt = $pdo->prepare("
    SELECT b.*, w.name as worker_name, w.phone as worker_phone, w.worker_uid, c.name as category_name 
    FROM bookings b 
    JOIN workers w ON b.worker_id = w.id 
    LEFT JOIN categories c ON w.service_category_id = c.id
    WHERE b.user_id = ? AND (b.status = 'pending' OR b.status = 'accepted') 
    ORDER BY b.service_date ASC
    ");
$active_stmt->execute([$user_id]);
$active_bookings = $active_stmt->fetchAll();

// Fetch History (Completed, Rejected, Cancelled)
$history_stmt = $pdo->prepare("
    SELECT b.*, w.name as worker_name, w.worker_uid, c.name as category_name, r.id as review_id 
    FROM bookings b 
    JOIN workers w ON b.worker_id = w.id 
    LEFT JOIN categories c ON w.service_category_id = c.id
    LEFT JOIN reviews r ON b.id = r.booking_id
    WHERE b.user_id = ? AND (b.status = 'completed' OR b.status = 'rejected' OR b.status = 'cancelled') 
    ORDER BY b.service_date DESC 
    ");
$history_stmt->execute([$user_id]);
$history_bookings = $history_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .page-header { background-color: var(--bs-tertiary-bg); padding: 2rem 0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 2rem; border-bottom: 1px solid var(--bs-border-color); }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .booking-card { transition: transform 0.2s; }
        .booking-card:hover { transform: translateY(-3px); }
        
        /* Star Rating Styles */
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
            margin-right: 5px;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #ffc107;
        }
    </style>
</head>
<body>

    <?php 
    $path_prefix = '../';
    include '../includes/navbar.php'; 
    ?>

    <div class="page-header">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <h3><i class="fas fa-calendar-check me-2 text-primary"></i>My Bookings</h3>
                <p class="text-muted mb-0">Track and manage your service appointments</p>
            </div>
            <a href="workers.php" class="btn btn-primary rounded-pill"><i class="fas fa-plus me-2"></i>New Booking</a>
        </div>
    </div>

    <div class="container mb-5">
        <?php if($success_msg || isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show"><?php echo $success_msg ?: htmlspecialchars($_GET['success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if($error_msg || isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?php echo $error_msg ?: htmlspecialchars($_GET['error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="row">
            <!-- Active Bookings -->
            <div class="col-lg-8">
                <h5 class="mb-4">Active & Upcoming</h5>
                <?php if(count($active_bookings) > 0): ?>
                    <?php foreach($active_bookings as $booking): ?>
                        <div class="card booking-card border-start border-4 border-<?php echo $booking['status'] == 'accepted' ? 'success' : 'warning'; ?>">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-2 text-center mb-3 mb-md-0">
                                        <div class="bg-body-secondary rounded p-2">
                                            <div class="fw-bold text-danger"><?php echo date('M', strtotime($booking['service_date'])); ?></div>
                                            <div class="h4 mb-0"><?php echo date('d', strtotime($booking['service_date'])); ?></div>
                                            <small><?php echo date('D', strtotime($booking['service_date'])); ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-7">
                                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($booking['category_name']); ?> Service <small class="text-muted ms-2">#BK-<?php echo $booking['id']; ?></small></h5>
                                        <p class="mb-1 text-muted">
                                            <i class="fas fa-user-hard-hat me-2 text-primary"></i><?php echo htmlspecialchars($booking['worker_name']); ?> 
                                            <span class="badge bg-light text-dark border font-monospace ms-2">ID: <?php echo htmlspecialchars($booking['worker_uid']); ?></span>
                                        </p>
                                        <p class="mb-0 small"><i class="far fa-clock me-2"></i><?php echo date('h:i A', strtotime($booking['service_time'])); ?> at <span class="text-truncate d-inline-block align-bottom" style="max-width: 250px;"><?php echo htmlspecialchars($booking['address']); ?></span></p>
                                    </div>
                                    <div class="col-md-3 text-md-end mt-3 mt-md-0">
                                        <?php if($booking['status'] == 'pending'): ?>
                                            <span class="badge bg-warning text-dark mb-2 d-block">Pending Approval</span>
                                            <form method="POST">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <button type="submit" name="cancel_booking" value="1" class="btn btn-outline-danger btn-sm w-100 rounded-pill" onclick="return confirm('Are you sure you want to cancel?')">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-success mb-2 d-block">Confirmed</span>
                                            <a href="tel:<?php echo htmlspecialchars($booking['worker_phone']); ?>" class="btn btn-primary btn-sm w-100 rounded-pill"><i class="fas fa-phone me-1"></i> Call Worker</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No active bookings found.</p>
                            <a href="workers.php" class="btn btn-outline-primary rounded-pill">Find Workers Now</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- History Sidebar -->
            <div class="col-lg-4">
                <h5 class="mb-4 text-muted">Booking History</h5>
                <div class="card">
                    <ul class="list-group list-group-flush">
                        <?php if(count($history_bookings) > 0): ?>
                            <?php foreach($history_bookings as $booking): ?>
                                <li class="list-group-item py-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($booking['category_name']); ?> <small class="text-muted ms-1">#BK-<?php echo $booking['id']; ?></small></h6>
                                            <small class="text-muted d-block mb-1"><i class="far fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($booking['service_date'])); ?></small>
                                            <small class="text-muted d-block"><i class="fas fa-hard-hat me-1"></i><?php echo htmlspecialchars($booking['worker_name']); ?></small>
                                            <small class="text-muted font-monospace" style="font-size: 0.75rem;">Worker ID: <?php echo htmlspecialchars($booking['worker_uid']); ?></small>
                                        </div>
                                        <span class="badge bg-<?php 
                                            echo match($booking['status']) {
                                                'completed' => 'success',
                                                'cancelled' => 'warning',
                                                'rejected' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?> rounded-pill"><?php echo ucfirst($booking['status']); ?></span>
                                    </div>
                                    <?php if($booking['status'] == 'completed'): ?>
                                        <div class="mt-2 text-end">
                                            <?php if($booking['work_proof_images']): ?>
                                                <div class="d-flex gap-1 justify-content-end mb-2">
                                                    <?php foreach(explode(',', $booking['work_proof_images']) as $proof): ?>
                                                        <a href="<?php echo trim($proof); ?>" target="_blank">
                                                            <img src="<?php echo trim($proof); ?>" class="rounded border" style="width: 40px; height: 40px; object-fit: cover;" alt="Proof">
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if($booking['review_id']): ?>
                                                <span class="badge bg-light text-success border"><i class="fas fa-check-circle me-1"></i>Rated</span>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-warning btn-sm rounded-pill px-3" 
                                                        onclick="openReviewModal(<?php echo $booking['id']; ?>, '<?php echo addslashes(htmlspecialchars($booking['worker_name'])); ?>')">
                                                    Rate Worker
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-center text-muted py-4">No past bookings.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-warning text-dark border-0">
                <h5 class="modal-title fw-bold">Rate Your Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="submit_review.php" method="POST">
                <div class="modal-body p-4">
                    <p class="text-muted mb-4">How was your experience with <span id="modalWorkerName" class="fw-bold text-dark"></span>?</p>
                    <input type="hidden" name="booking_id" id="modalBookingId">
                    
                    <div class="mb-4">
                        <label class="form-label d-block text-center mb-3">Your Rating</label>
                        <div class="star-rating d-flex justify-content-center">
                            <input type="radio" id="star5" name="rating" value="5" required/><label for="star5" title="5 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star4" name="rating" value="4"/><label for="star4" title="4 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star3" name="rating" value="3"/><label for="star3" title="3 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star2" name="rating" value="2"/><label for="star2" title="2 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star1" name="rating" value="1"/><label for="star1" title="1 star"><i class="fas fa-star"></i></label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Write a Comment (Optional)</label>
                        <textarea name="comment" class="form-control rounded-3" rows="3" placeholder="Tell us about the service..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_review" class="btn btn-warning rounded-pill px-4 fw-bold">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openReviewModal(bookingId, workerName) {
            document.getElementById('modalBookingId').value = bookingId;
            document.getElementById('modalWorkerName').innerText = workerName;
            var myModal = new bootstrap.Modal(document.getElementById('reviewModal'));
            myModal.show();
        }
    </script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
