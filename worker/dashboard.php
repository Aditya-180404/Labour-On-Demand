<?php
require_once '../config/security.php';
require_once '../config/db.php';
require_once '../includes/cloudinary_helper.php';
$cld = CloudinaryHelper::getInstance();

// Check if worker is logged in
if (!isset($_SESSION['worker_id'])) {
    header("Location: login.php");
    exit;
}

$worker_id = $_SESSION['worker_id'];
$success_msg = $error_msg = "";

// Handle Availability Toggle
if (isset($_POST['toggle_availability'])) {
    $new_status = $_POST['is_available'] ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE workers SET is_available = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $worker_id])) {
        $success_msg = "Availability status updated.";
    } else {
        $error_msg = "Failed to update availability.";
    }
}

// Handle Worker GPS Location Update
if (isset($_POST['update_location']) && isset($_POST['latitude']) && isset($_POST['longitude'])) {
    $lat = $_POST['latitude'];
    $lng = $_POST['longitude'];
    $stmt = $pdo->prepare("UPDATE workers SET latitude = ?, longitude = ? WHERE id = ?");
    if ($stmt->execute([$lat, $lng, $worker_id])) {
        $success_msg = "Current location updated successfully.";
    } else {
        $error_msg = "Failed to update location.";
    }
}

// Fetch Worker Details
$stmt = $pdo->prepare("SELECT * FROM workers WHERE id = ?");
$stmt->execute([$worker_id]);
$worker = $stmt->fetch();

// Fetch Quick Stats
$count_stmt = $pdo->prepare("SELECT 
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as upcoming_count,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM bookings WHERE worker_id = ?");
$count_stmt->execute([$worker_id]);
$stats = $count_stmt->fetch();

// Fetch Rating Stats
$rating_stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE worker_id = ?");
$rating_stmt->execute([$worker_id]);
$rating_stats = $rating_stmt->fetch();
$avg_rating = round($rating_stats['avg_rating'], 1);
$total_reviews = $rating_stats['total_reviews'];

// Fetch next upcoming job
$next_job_stmt = $pdo->prepare("
    SELECT b.*, u.name as user_name 
    FROM bookings b 
    JOIN users u ON b.user_id = u.id 
    WHERE b.worker_id = ? AND b.status = 'accepted'
    ORDER BY b.service_date ASC, b.service_time ASC 
    LIMIT 1
");
$next_job_stmt->execute([$worker_id]);
$next_job = $next_job_stmt->fetch();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Overview - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .dashboard-header { 
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); 
            color: white; 
            padding: 3.5rem 0; 
            border-radius: 0 0 40px 40px;
            margin-bottom: 2rem;
        }
        .stat-card { border: none; border-radius: 20px; text-align: center; padding: 1.5rem; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .control-card { border: none; border-radius: 20px; overflow: hidden; }
        .worker-avatar { width: 100px; height: 100px; object-fit: cover; border: 4px solid rgba(255,255,255,0.2); }
    </style>
</head>
<body>

    <?php 
    $path_prefix = '../';
    include '../includes/worker_navbar.php'; 
    ?>

    <div class="dashboard-header text-center">
        <div class="container">
            <?php 
                $img_src = $worker['profile_image'] && $worker['profile_image'] != 'default.png' 
                    ? $cld->getUrl($worker['profile_image'], ['width' => 200, 'height' => 200, 'crop' => 'fill', 'gravity' => 'face']) 
                    : "https://via.placeholder.com/150"; 
            ?>
            <img src="<?php echo $img_src; ?>" class="rounded-circle mb-3 worker-avatar" alt="Profile">
            <h2 class="fw-bold mb-1">Welcome, <?php echo htmlspecialchars($worker['name']); ?></h2>
            <div class="mb-3"><span class="badge bg-dark font-monospace opacity-75">Worker ID: <?php echo htmlspecialchars($worker['worker_uid']); ?></span></div>
            <p class="opacity-75 mb-3"><?php echo htmlspecialchars($worker['bio'] ?: ''); ?></p>
            <div class="d-flex justify-content-center gap-2">
                <a href="edit_profile.php" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm">Edit Profile</a>
                <a href="my_jobs.php" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm text-primary">View Job Panel</a>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <?php if($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-pill px-4"><?php echo $success_msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left: Controls -->
            <div class="col-lg-4">
                <!-- Availability -->
                <div class="card control-card shadow-sm mb-4">
                    <div class="card-body p-4 text-center">
                        <h6 class="text-muted text-uppercase small fw-bold mb-3">Service Status</h6>
                        <div class="d-inline-block p-3 rounded-4 <?php echo $worker['is_available'] ? 'bg-success bg-opacity-10' : 'bg-body-secondary'; ?> mb-3">
                            <i class="fas <?php echo $worker['is_available'] ? 'fa-toggle-on text-success' : 'fa-toggle-off text-muted'; ?> fa-3x"></i>
                        </div>
                        <form method="POST">
                            <div class="form-check form-switch d-flex justify-content-center mb-0">
                                <input class="form-check-input h4 cursor-pointer" type="checkbox" name="is_available" value="1" onchange="this.form.submit();" <?php echo $worker['is_available'] ? 'checked' : ''; ?>>
                                <input type="hidden" name="toggle_availability" value="1">
                                <label class="ms-2 fw-bold text-<?php echo $worker['is_available'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $worker['is_available'] ? 'You are Online' : 'You are Offline'; ?>
                                </label>
                            </div>
                        </form>
                        <p class="small text-muted mt-2 mb-0">Toggle to start/stop receiving new job requests</p>
                    </div>
                </div>

                <!-- GPS Location -->
                <div class="card control-card shadow-sm">
                    <div class="card-body p-4 text-center">
                        <h6 class="text-muted text-uppercase small fw-bold mb-3">Share Location</h6>
                        <form method="POST" id="locationForm">
                            <input type="hidden" name="latitude" id="workerLat">
                            <input type="hidden" name="longitude" id="workerLng">
                            <input type="hidden" name="update_location" value="1">
                            <button type="button" class="btn btn-primary rounded-pill w-100 py-2 fw-bold" onclick="shareWorkerLocation()">
                                <i class="fas fa-location-arrow me-2"></i>Update My GPS
                            </button>
                        </form>
                        <div id="workerLocationStatus" class="small text-muted mt-2">
                             <?php if($worker['latitude']): ?>
                                <span class="text-success"><i class="fas fa-check-circle me-1"></i>Location Shared</span>
                            <?php else: ?>
                                <i class="fas fa-info-circle me-1"></i>Help users find you nearby
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Stats & Next Job -->
            <div class="col-lg-8">
                <!-- Stats Row -->
                <div class="row g-3 mb-4 text-center">
                    <div class="col-4">
                        <div class="card stat-card shadow-sm border-bottom border-4 border-warning">
                            <h3 class="fw-bold mb-0"><?php echo (int)$stats['pending_count']; ?></h3>
                            <small class="text-muted">Requests</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card stat-card shadow-sm border-bottom border-4 border-primary">
                            <h3 class="fw-bold mb-0"><?php echo (int)$stats['upcoming_count']; ?></h3>
                            <small class="text-muted">Upcoming</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card stat-card shadow-sm border-bottom border-4 border-success">
                            <h3 class="fw-bold mb-0"><?php echo (int)$stats['completed_count']; ?></h3>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                </div>

                <!-- Rating Stats Row -->
                <div class="row g-3 mb-4 text-center">
                    <div class="col-6">
                        <div class="card stat-card shadow-sm border-bottom border-4 border-info">
                            <h3 class="fw-bold mb-0 text-warning"><?php echo $avg_rating ?: '0.0'; ?> <i class="fas fa-star small"></i></h3>
                            <small class="text-muted">Avg Rating</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card stat-card shadow-sm border-bottom border-4 border-info">
                            <h3 class="fw-bold mb-0"><?php echo $total_reviews; ?></h3>
                            <small class="text-muted">Total Reviews</small>
                        </div>
                    </div>
                </div>

                <!-- Next Appointment -->
                <div class="card control-card shadow-sm">
                    <div class="card-header bg-transparent border-bottom-0 pt-4 px-4">
                        <h5 class="fw-bold mb-0">Next Appointment</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if($next_job): ?>
                             <div class="bg-body-tertiary rounded-4 p-4 border border-info border-opacity-25">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="badge bg-danger rounded-pill mb-2 px-3"><?php echo date('h:i A', strtotime($next_job['service_time'])); ?> - Today</div>
                                        <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($next_job['user_name']); ?></h4>
                                        <p class="text-muted mb-0"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($next_job['address']); ?></p>
                                    </div>
                                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                        <a href="my_jobs.php" class="btn btn-outline-primary rounded-pill px-4 btn-sm">Manage Job</a>
                                    </div>
                                </div>
                             </div>
                        <?php else: ?>
                             <div class="text-center py-5">
                                <div class="bg-body-secondary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                    <i class="fas fa-calendar-check text-muted fa-2x"></i>
                                </div>
                                <h6 class="text-muted mb-3">No jobs scheduled yet.</h6>
                                <p class="small text-muted mb-0">New requests will appear in your <a href="my_jobs.php" class="text-decoration-none fw-bold">Job Panel</a></p>
                             </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ratings / Reviews Quick Link -->
                <div class="card control-card shadow-sm mt-4 bg-info text-white">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 opacity-75">Customer Feedback</h6>
                            <h5 class="mb-0 fw-bold">See what customers are saying</h5>
                        </div>
                        <a href="reviews.php" class="btn btn-light rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-star text-info"></i>
                        </a>
                    </div>
                </div>

                <!-- Earnings / Quick Link -->
                <div class="card control-card shadow-sm mt-4 bg-primary text-white">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 opacity-75">Work History</h6>
                            <h5 class="mb-0 fw-bold">Review your performance & earnings</h5>
                        </div>
                        <a href="my_jobs.php" class="btn btn-light rounded-circle" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-arrow-right text-primary"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function shareWorkerLocation() {
            const status = document.getElementById('workerLocationStatus');
            status.innerHTML = "<i class='fas fa-spinner fa-spin'></i> Acquiring GPS...";

            if (!navigator.geolocation) {
                status.innerHTML = "Not supported by browser";
            } else {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        document.getElementById('workerLat').value = position.coords.latitude;
                        document.getElementById('workerLng').value = position.coords.longitude;
                        document.getElementById('locationForm').submit();
                    },
                    (error) => {
                        status.innerHTML = "Error: " + error.message;
                    }
                );
            }
        }
    </script>
    <?php include '../includes/worker_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $path_prefix; ?>assets/js/theme.js"></script>
</body>
</html>
