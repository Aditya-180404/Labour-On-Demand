<?php
session_start();
require_once '../config/db.php';
require_once '../includes/mailer.php';

// Check if ID is provided
if (!isset($_GET['id'])) {
    header("Location: workers.php");
    exit;
}

$worker_id = $_GET['id'];

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

// Fetch Rating Summary
$rating_stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE worker_id = ?");
$rating_stmt->execute([$worker_id]);
$rating_summary = $rating_stmt->fetch();
$avg_rating = round($rating_summary['avg_rating'], 1);
$total_reviews = $rating_summary['total_reviews'];

// Fetch Detailed Reviews
$reviews_stmt = $pdo->prepare("SELECT r.*, u.name as user_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.worker_id = ? ORDER BY r.created_at DESC");
$reviews_stmt->execute([$worker_id]);
$reviews = $reviews_stmt->fetchAll();

// User Context
$is_admin = isset($_SESSION['admin_logged_in']);
$is_user = isset($_SESSION['user_id']);
$user_id = $is_user ? $_SESSION['user_id'] : null;

// Restricted access for users (only see approved workers)
if (!$is_admin && $worker['status'] !== 'approved') {
    echo "This worker profile is not available.";
    exit;
}

// Fetch User Address Details if logged in
$user_address = "";
if ($is_user) {
    $u_stmt = $pdo->prepare("SELECT address_details FROM users WHERE id = ?");
    $u_stmt->execute([$user_id]);
    $u_data = $u_stmt->fetch();
    if ($u_data) $user_address = $u_data['address_details'];
}

// Fetch already booked slots for today and selected date (via AJAX or page refresh)
$selected_date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
$booked_slots_stmt = $pdo->prepare("SELECT service_time, service_end_time FROM bookings WHERE worker_id = ? AND service_date = ? AND status NOT IN ('cancelled', 'rejected', 'completed') ORDER BY service_time ASC");
$booked_slots_stmt->execute([$worker_id, $selected_date]);
$booked_slots = $booked_slots_stmt->fetchAll();

// Handle Booking Submission
$booking_msg = "";
$booking_error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_worker'])) {
    if (!$is_user) {
        header("Location: login.php");
        exit;
    }

    $date = $_POST['date'];
    $time = $_POST['time'];
    $address = trim($_POST['address']);
    $lat = (isset($_POST['latitude']) && $_POST['latitude'] !== "") ? $_POST['latitude'] : null;
    $lng = (isset($_POST['longitude']) && $_POST['longitude'] !== "") ? $_POST['longitude'] : null;

    if (empty($date) || empty($time) || empty($address)) {
        $booking_error = "Please fill all booking details.";
    } else {
        // Calculate end time (1 hour by default)
        $start_timestamp = strtotime("$date $time");
        $end_timestamp = $start_timestamp + 3600; // +1 hour
        $end_time = date('H:i:s', $end_timestamp);
        
        // Check for overlaps
        $overlap_stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM bookings 
            WHERE worker_id = ? 
            AND service_date = ? 
            AND status NOT IN ('cancelled', 'rejected', 'completed') 
            AND (
                (service_time < ?) -- existing start < requested end
                AND 
                (service_end_time > ?) -- existing end > requested start
            )
        ");
        $overlap_stmt->execute([$worker_id, $date, $end_time, $time]);
        $has_overlap = $overlap_stmt->fetchColumn() > 0;

        if ($has_overlap) {
            $booking_error = "This worker is already booked during the selected time slot. Please choose another time.";
        } else {
            $sql = "INSERT INTO bookings (user_id, worker_id, service_date, service_time, service_end_time, address, booking_latitude, booking_longitude, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$user_id, $worker_id, $date, $time, $end_time, $address, $lat, $lng])) {
                $booking_msg = "Booking request sent successfully! Duration: " . date('h:i A', $start_timestamp) . " to " . date('h:i A', $end_timestamp) . ". Please wait for the worker to accept.";
                // Refresh booked slots for the message
                $booked_slots_stmt->execute([$worker_id, $date]);
                $booked_slots = $booked_slots_stmt->fetchAll();

                // SEND EMAIL TO WORKER
                // Fetch user name
                $u_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $u_stmt->execute([$user_id]);
                $u_name = $u_stmt->fetchColumn(); 

                $subject = "New Booking Request - Labour On Demand";
                $message = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                        <h2 style='color: #fd7e14;'>New Job Request!</h2>
                        <p>Hello <strong>{$worker['name']}</strong>,</p>
                        <p>You have received a new booking request from customer <strong>$u_name</strong>.</p>
                        <ul>
                            <li><strong>Date:</strong> $date</li>
                            <li><strong>Time:</strong> $time</li>
                            <li><strong>Location:</strong> $address</li>
                        </ul>
                        <p>Please login to your dashboard to <strong>Accept</strong> or <strong>Reject</strong> this job.</p>
                        <a href='http://localhost/laubour/worker/dashboard.php' style='display: inline-block; padding: 10px 20px; color: white; background-color: #fd7e14; text-decoration: none; border-radius: 5px;'>View Dashboard</a>
                        <br><br>
                        <p>Regards,<br>Team Labour On Demand</p>
                    </div>";
                
                sendEmail($worker['email'], $worker['name'], $subject, $message);


            } else {
                $booking_error = "Failed to book worker.";
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
    <title><?php echo htmlspecialchars($worker['name']); ?> - Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <style>
        .profile-container { margin-top: 30px; margin-bottom: 50px; }
        .profile-card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; }
        .profile-header { background: linear-gradient(135deg, #f1c40f 0%, #f39c12 100%); padding: 40px 20px; color: #fff; text-align: center; }
        .profile-img { width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 5px solid rgba(255,255,255,0.5); margin-bottom: 15px; }
        .availability-badge { font-size: 0.9rem; padding: 5px 15px; border-radius: 20px; }
        .section-title { border-bottom: 2px solid #f1c40f; display: inline-block; padding-bottom: 5px; margin-bottom: 20px; font-weight: bold; }
        .booking-card { background-color: var(--bs-tertiary-bg); border-radius: 15px; padding: 25px; position: sticky; top: 20px; border: 1px solid var(--bs-border-color); }
        
        @media (max-width: 768px) {
            .profile-container { margin-top: 20px; }
            .profile-header { padding: 30px 15px; }
            .profile-img { width: 120px; height: 120px; }
            .booking-card { margin-top: 30px; position: static; }
        }
    </style>
</head>
<body class="bg-body">

    <?php include '../includes/navbar.php'; ?>

    <div class="container profile-container">
        <?php if($booking_msg): ?>
            <div class="alert alert-success alert-dismissible fade show"><?php echo $booking_msg; ?> <a href="my_bookings.php">View Bookings</a><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <script>
                setTimeout(function() {
                    window.location.href = 'my_bookings.php';
                }, 10000); // 10 seconds redirect
            </script>
        <?php endif; ?>
        <?php if($booking_error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?php echo $booking_error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="mb-4">
            <a href="workers.php" class="text-decoration-none text-muted">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card profile-card mb-4">
                    <div class="profile-header">
                        <?php 
                            $img_src = $worker['profile_image'] && $worker['profile_image'] != 'default.png' 
                                ? "../uploads/workers/" . $worker['profile_image'] 
                                : "https://via.placeholder.com/150"; 
                        ?>
                        <img src="<?php echo $img_src; ?>" alt="Profile" class="profile-img">
                        <h2 class="mb-1"><?php echo htmlspecialchars($worker['name']); ?></h2>
                        <div class="mb-2">
                            <span class="badge bg-body-tertiary text-body shadow-sm">
                                <i class="fas <?php echo $worker['category_icon'] ? $worker['category_icon'] : 'fa-user'; ?> me-2 text-warning"></i>
                                <?php echo htmlspecialchars($worker['category_name']); ?>
                            </span>
                        </div>
                        <div class="mt-2">
                            <?php if ($worker['is_available']): ?>
                                <span class="availability-badge bg-success text-white shadow-sm"><i class="fas fa-check-circle me-1"></i> Available Now</span>
                            <?php else: ?>
                                <span class="availability-badge bg-secondary text-white shadow-sm"><i class="fas fa-times-circle me-1"></i> Busy/Offline</span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-3 text-warning">
                            <?php 
                            $stars = floor($avg_rating);
                            for($i=1; $i<=5; $i++) {
                                if($i <= $stars) echo '<i class="fas fa-star"></i>';
                                elseif($i == $stars + 1 && $avg_rating - $stars >= 0.5) echo '<i class="fas fa-star-half-alt"></i>';
                                else echo '<i class="far fa-star"></i>';
                            }
                            ?>
                            <span class="text-white ms-2 small">(<?php echo $avg_rating ?: '0'; ?> / 5 from <?php echo $total_reviews; ?> reviews)</span>
                        </div>
                        <?php if ($is_admin): ?>
                            <div class="mt-3">
                                <span class="badge bg-<?php echo ($worker['status'] == 'approved' ? 'success' : ($worker['status'] == 'pending' ? 'warning' : 'danger')); ?>">
                                    Admin Status: <?php echo ucfirst($worker['status']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-4">
                        <div class="row mb-4">
                            <div class="col-sm-6 mb-3 mb-sm-0">
                                <h5 class="section-title">Experience & Bio</h5>
                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($worker['bio'] ? $worker['bio'] : 'No bio provided.')); ?></p>
                            </div>
                            <div class="col-sm-6">
                                <h5 class="section-title">Work Details</h5>
                                <ul class="list-unstyled">
                                    <li class="mb-2"><strong><i class="fas fa-coins text-warning me-2"></i>Hourly Rate:</strong> ₹<?php echo number_format($worker['hourly_rate'], 0); ?> / hour</li>
                                    <li class="mb-2"><strong><i class="fas fa-map-marker-alt text-warning me-2"></i>Service Areas:</strong> 
                                        <?php 
                                            $pins = explode(',', $worker['pin_code']);
                                            foreach($pins as $pin) echo '<span class="badge bg-body-secondary text-body border me-1">'.trim($pin).'</span>';
                                        ?>
                                    </li>
                                    <li class="mb-2"><strong><i class="fas fa-home text-warning me-2"></i>Preferred Location:</strong> <?php echo htmlspecialchars($worker['working_location'] ? $worker['working_location'] : 'Open to any'); ?></li>
                                </ul>
                            </div>
                        </div>


                    </div>
                </div>

                <!-- Reviews Section -->
                <div class="card profile-card mb-4 border-0">
                    <div class="card-body p-4">
                        <h5 class="section-title">Customer Reviews</h5>
                        <?php if (count($reviews) > 0): ?>
                            <div class="review-list">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="review-item mb-4 pb-3 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="fw-bold"><?php echo htmlspecialchars($review['user_name']); ?></div>
                                            <div class="text-warning small">
                                                <?php for($i=1; $i<=5; $i++) echo $i <= $review['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                                            </div>
                                        </div>
                                        <p class="text-muted small mb-1"><?php echo nl2br(htmlspecialchars($review['comment'] ?: 'No comment provided.')); ?></p>
                                        <small class="text-muted opacity-75" style="font-size: 0.75rem;">
                                            <i class="far fa-clock me-1"></i><?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-4">No reviews yet for this worker.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <?php if ($is_user && $worker['status'] == 'approved'): ?>
                <div class="booking-card shadow-sm border-0">
                    <h4 class="mb-4 fw-bold">Book this Worker</h4>
                    <form method="POST" id="bookingForm">
                        <div class="mb-3">
                            <label class="form-label">Select Date</label>
                            <input type="date" name="date" class="form-control rounded-pill px-3" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Time</label>
                            <input type="time" name="time" class="form-control rounded-pill px-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Service Location</label>
                            <div class="d-flex flex-column gap-2 mb-2">
                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" onclick="useRegisteredAddress()">
                                    <i class="fas fa-home me-1"></i> Use Registered Address
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm rounded-pill" onclick="useCurrentLocation()">
                                    <i class="fas fa-location-arrow me-1"></i> Send Current GPS Location
                                </button>
                            </div>
                            <textarea name="address" id="addressField" class="form-control rounded-3" rows="3" required placeholder="Enter full address..."></textarea>
                            <input type="hidden" name="latitude" id="latField">
                            <input type="hidden" name="longitude" id="lngField">
                            <div id="locationStatus" class="small mt-1 text-muted"></div>
                        </div>

                        <?php if(!empty($booked_slots) && empty($booking_msg)): ?>
                        <div class="mb-3">
                            <label class="form-label small text-danger fw-bold"><i class="fas fa-calendar-times me-1"></i> Occupied Slots (<?php echo date('M d', strtotime($selected_date)); ?>)</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach($booked_slots as $slot): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill small py-1 px-2">
                                        <?php echo date('h:i A', strtotime($slot['service_time'])); ?> - <?php echo date('h:i A', strtotime($slot['service_end_time'])); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-warning py-2 small border-0 mb-4">
                            <i class="fas fa-info-circle me-1"></i> Pay directly to worker after service.
                        </div>
                        <button type="submit" name="book_worker" class="btn btn-warning w-100 rounded-pill py-2 fw-bold shadow-sm">Confirm Booking</button>
                    </form>
                </div>
                <?php elseif (!$is_user && $worker['status'] == 'approved'): ?>
                <div class="card border-0 shadow-sm p-4 text-center">
                    <h5 class="mb-3">Want to book this worker?</h5>
                    <p class="text-muted small">Please login as a customer to book services.</p>
                    <a href="login.php" class="btn btn-primary rounded-pill w-100">Login Now</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function useRegisteredAddress() {
            const registeredAddress = <?php echo json_encode($user_address); ?>;
            document.getElementById('addressField').value = registeredAddress;
            document.getElementById('latField').value = "";
            document.getElementById('lngField').value = "";
            document.getElementById('locationStatus').innerHTML = "<i class='fas fa-check text-success'></i> Using registered address";
        }

        function useCurrentLocation() {
            const status = document.getElementById('locationStatus');
            status.innerHTML = "<i class='fas fa-spinner fa-spin'></i> Getting location...";

            if (!navigator.geolocation) {
                status.innerHTML = "<i class='fas fa-exclamation-triangle text-danger'></i> Geolocation not supported by your browser";
            } else {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        document.getElementById('latField').value = lat;
                        document.getElementById('lngField').value = lng;
                        
                        // Optionally fetch address from lat/lng using reverse geocoding
                        // For now, just set a placeholder and let user refine
                        if (document.getElementById('addressField').value === "") {
                            document.getElementById('addressField').value = "GPS Location Shared (" + lat.toFixed(6) + ", " + lng.toFixed(6) + ")";
                        }
                        status.innerHTML = "<i class='fas fa-check text-success'></i> GPS Coordinates captured!";
                    },
                    (error) => {
                        status.innerHTML = "<i class='fas fa-exclamation-triangle text-danger'></i> Error: " + error.message;
                    }
                );
            }
        }
    </script>
    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
