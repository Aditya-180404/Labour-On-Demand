<?php
require_once '../config/security.php';
require_once '../config/db.php';
require_once '../includes/cloudinary_helper.php';
$cld = CloudinaryHelper::getInstance();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch Stats
$stats = [
    'pending' => 0,
    'total' => 0,
    'completed' => 0
];

$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM bookings WHERE user_id = ? GROUP BY status");
$stmt->execute([$user_id]);
while($row = $stmt->fetch()) {
    if($row['status'] == 'pending') $stats['pending'] = $row['count'];
    if($row['status'] == 'completed') $stats['completed'] = $row['count'];
    $stats['total'] += $row['count'];
}

// Fetch 3 most recent upcoming bookings for a preview
$upcoming_stmt = $pdo->prepare("
    SELECT b.*, w.name as worker_name, c.name as category_name 
    FROM bookings b 
    JOIN workers w ON b.worker_id = w.id 
    LEFT JOIN categories c ON w.service_category_id = c.id
    WHERE b.user_id = ? AND (b.status = 'pending' OR b.status = 'accepted') 
    ORDER BY b.service_date ASC, b.service_time ASC 
    LIMIT 3
");
$upcoming_stmt->execute([$user_id]);
$upcoming_previews = $upcoming_stmt->fetchAll();

// Fetch user info
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section { 
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); 
            color: white; 
            padding: 3rem 0; 
            border-radius: 0 0 40px 40px;
            margin-bottom: 2rem;
        }
        .stat-card {
            border: none;
            border-radius: 20px;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
        .feature-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            display: flex;
            align-items: center;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            transition: transform 0.2s;
        }
        .feature-card:hover { transform: scale(1.02); cursor: pointer; }
        .feature-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.5rem;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>

    <?php 
    $path_prefix = '../';
    include '../includes/navbar.php'; 
    ?>

    <div class="hero-section text-center">
        <div class="container">
            <div class="mb-4 d-flex justify-content-center">
                <?php 
                    $user_img = ($user['profile_image'] && $user['profile_image'] != 'default.png') 
                        ? $cld->getUrl($user['profile_image'], ['width' => 200, 'height' => 200, 'crop' => 'fill', 'gravity' => 'face']) 
                        : $path_prefix . "assets/img/default-user.png";
                ?>
                <div class="position-relative">
                    <img src="<?php echo $user_img; ?>" alt="Profile" class="rounded-circle border border-4 border-white border-opacity-25 shadow-lg" style="width: 100px; height: 100px; object-fit: cover;">
                    <div class="position-absolute bottom-0 end-0 bg-warning rounded-circle d-flex align-items-center justify-content-center shadow" style="width: 30px; height: 30px; border: 3px solid #1e3c72;">
                        <i class="fas fa-check text-dark" style="font-size: 10px;"></i>
                    </div>
                </div>
            </div>
            <h1 class="fw-bold mb-2">Hello, <?php echo htmlspecialchars($user['name']); ?>!</h1>
            <p class="opacity-75 mb-4">What help do you need today?</p>
            <div class="d-flex justify-content-center gap-2">
                <a href="workers.php" class="btn btn-warning rounded-pill px-4 fw-bold">Book a Worker</a>
                <a href="edit_profile.php" class="btn btn-outline-light rounded-pill px-4">Edit Profile</a>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Stats Row -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card stat-card p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase small fw-bold">Active Bookings</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $stats['pending']; ?></h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                            <i class="fas fa-calendar-alt fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase small fw-bold">Completed Jobs</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $stats['completed']; ?></h2>
                        </div>
                        <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                            <i class="fas fa-check-double fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase small fw-bold">Total Requests</h6>
                            <h2 class="mb-0 fw-bold"><?php echo $stats['total']; ?></h2>
                        </div>
                        <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                            <i class="fas fa-history fa-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Quick Actions -->
            <div class="col-lg-6">
                <h5 class="mb-4 fw-bold">Quick Actions</h5>
                
                <div class="feature-card bg-body-tertiary" onclick="location.href='my_bookings.php'">
                    <div class="feature-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 fw-bold">View My Bookings</h6>
                        <small class="text-muted">Track all your active and past service requests</small>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>

                <div class="feature-card bg-body-tertiary" onclick="location.href='workers.php'">
                    <div class="feature-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-search-location"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 fw-bold">Find Local Workers</h6>
                        <small class="text-muted">Search workers by category and location</small>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>

                <div class="feature-card bg-body-tertiary" onclick="location.href='services.php'">
                    <div class="feature-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-th-large"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 fw-bold">Explore Services</h6>
                        <small class="text-muted">Browse available service categories</small>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>
            </div>

            <!-- Recent Preview -->
            <div class="col-lg-6 mt-4 mt-lg-0">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0 fw-bold">Upcoming Services</h5>
                    <a href="my_bookings.php" class="small text-primary text-decoration-none fw-bold">View All</a>
                </div>

                <?php if(count($upcoming_previews) > 0): ?>
                    <?php foreach($upcoming_previews as $up): ?>
                        <div class="card mb-3 border-0 shadow-sm rounded-4">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center">
                                    <div class="bg-body-secondary rounded p-2 text-center me-3" style="min-width: 60px;">
                                        <div class="small fw-bold text-danger"><?php echo date('M d', strtotime($up['service_date'])); ?></div>
                                        <div class="small text-muted"><?php echo date('h:i A', strtotime($up['service_time'])); ?></div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($up['category_name']); ?></h6>
                                        <small class="text-muted">with <?php echo htmlspecialchars($up['worker_name']); ?></small>
                                    </div>
                                    <span class="badge <?php echo $up['status'] == 'accepted' ? 'bg-success' : 'bg-warning text-dark'; ?> rounded-pill">
                                        <?php echo ucfirst($up['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5 bg-body-tertiary rounded-4 shadow-sm">
                        <i class="fas fa-tasks text-muted fa-3x mb-3"></i>
                        <p class="text-muted mb-0">No upcoming tasks scheduled.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
