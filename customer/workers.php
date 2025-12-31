<?php
require_once '../includes/security.php';
require_once '../config/db.php';
require_once '../includes/cloudinary_helper.php';
$cld = CloudinaryHelper::getInstance();

// Enforce Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle Booking Submission
$booking_msg = "";
$booking_error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_worker'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verifyCSRF($_POST['csrf_token'])) {
        $_SESSION['toast_error'] = "Security validation failed. Please try again.";
        header("Location: workers.php");
        exit;
    }

    $worker_id = $_POST['worker_id'];
    $user_id = $_SESSION['user_id'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $address = trim($_POST['address']);

    if (empty($date) || empty($time) || empty($address)) {
        $booking_error = "Please fill all booking details.";
    } else {
        // Basic Conflict Check (Optional - can be improved)
        $stmt = $pdo->prepare("SELECT id FROM bookings WHERE worker_id = ? AND service_date = ? AND service_time = ? AND status IN ('pending', 'accepted')");
        $stmt->execute([$worker_id, $date, $time]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['toast_error'] = "Worker is not available at this time.";
            header("Location: workers.php");
            exit;
        } else {
            $sql = "INSERT INTO bookings (user_id, worker_id, service_date, service_time, address, status) VALUES (?, ?, ?, ?, ?, 'pending')";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$user_id, $worker_id, $date, $time, $address])) {
                $_SESSION['toast_success'] = "Booking request sent successfully! Check your dashboard.";
                header("Location: workers.php");
                exit;
            } else {
                $_SESSION['toast_error'] = "Failed to book worker.";
                header("Location: workers.php");
                exit;
            }
        }
    }
}

// Build Query Logic
$base_filters = "w.status = 'approved'";
$base_params = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $base_filters .= " AND (w.name LIKE ? OR c.name LIKE ?)";
    $base_params[] = $search;
    $base_params[] = $search;
}

if (isset($_GET['category']) && !empty($_GET['category'])) {
    $base_filters .= " AND w.service_category_id = ?";
    $base_params[] = $_GET['category'];
}

// Get user's PIN code
$user_pin = null;
if (isset($_SESSION['user_id'])) {
    $user_stmt = $pdo->prepare("SELECT pin_code FROM users WHERE id = ?");
    $user_stmt->execute([$_SESSION['user_id']]);
    $user_data = $user_stmt->fetch();
    $user_pin = ($user_data && !empty($user_data['pin_code'])) ? intval($user_data['pin_code']) : null;
}

$location_status = "global"; // default
$final_filters = $base_filters;
$final_params = $base_params;

if ($user_pin) {
    // 1. Try Exact Match
    $exact_params = array_merge([$user_pin], $base_params);
    $exact_stmt = $pdo->prepare("SELECT COUNT(*) FROM workers w LEFT JOIN categories c ON w.service_category_id = c.id WHERE $base_filters AND FIND_IN_SET(?, w.pin_code)");
    $exact_stmt->execute($exact_params);
    
    if ($exact_stmt->fetchColumn() > 0) {
        $final_filters .= " AND FIND_IN_SET(?, w.pin_code)";
        $final_params = array_merge($base_params, [$user_pin]);
        $location_status = "exact";
    } else {
        // 2. Try nearby (+/- 5)
        $min_pin = $user_pin - 5;
        $max_pin = $user_pin + 5;
        
        // Since pin_code in workers is comma-separated TEXT, we need a complex check or simplify.
        // For robustness, we check if ANY of the comma-separated PINs fall in range.
        // Simplified SQL approach for TEXT comma-separated range check:
        $range_stmt = $pdo->prepare("SELECT COUNT(*) FROM workers w LEFT JOIN categories c ON w.service_category_id = c.id WHERE $base_filters AND EXISTS (
            SELECT 1 FROM (SELECT ? as p UNION SELECT ? UNION SELECT ? UNION SELECT ? UNION SELECT ? UNION SELECT ? UNION SELECT ? UNION SELECT ? UNION SELECT ? UNION SELECT ? UNION SELECT ?) as ranges
            WHERE FIND_IN_SET(ranges.p, w.pin_code)
        )");
        
        $range_vals = range($user_pin - 5, $user_pin + 5);
        $range_stmt->execute(array_merge($range_vals, $base_params));
        
        if ($range_stmt->fetchColumn() > 0) {
            $final_filters .= " AND EXISTS (
                SELECT 1 FROM (SELECT ? as p UNION SELECT ? UNION SELECT ? UNION SELECT ? UNION SELECT ? UNION SELECT ? UNION SELECT ? UNION SELECT ? UNION SELECT ? UNION SELECT ? UNION SELECT ?) as ranges
                WHERE FIND_IN_SET(ranges.p, w.pin_code)
            )";
            $final_params = array_merge($base_params, $range_vals);
            $location_status = "nearby";
        } else {
            $location_status = "none_nearby";
        }
    }
}

// Final Query assembly
$query = "SELECT w.*, c.name as category_name, c.icon as category_icon 
          FROM workers w 
          LEFT JOIN categories c ON w.service_category_id = c.id 
          WHERE $final_filters";

// Handle Sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
switch ($sort) {
    case 'price_low': $query .= " ORDER BY w.hourly_rate ASC"; break;
    case 'price_high': $query .= " ORDER BY w.hourly_rate DESC"; break;
    case 'rating': $query .= " ORDER BY w.avg_rating DESC, w.total_reviews DESC"; break; 
    case 'newest': default: $query .= " ORDER BY w.created_at DESC"; break;
}

$stmt = $pdo->prepare($query);
$stmt->execute($final_params);
$workers = $stmt->fetchAll();

// Fetch Categories for Filter
$cat_stmt = $pdo->query("SELECT * FROM categories");
$categories = $cat_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Workers - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <style>
        .worker-card { transition: all 0.3s ease; border: none; overflow: hidden; background-color: var(--bs-body-bg); border: 1px solid var(--bs-border-color); }
        .worker-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .rating-star { color: #ffc107; }
        .filter-section { background-color: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); border-radius: 15px; padding: 20px; }
        @media (max-width: 991px) {
            .filter-section { margin-bottom: 30px; }
        }
    </style>
</head>
<body>

    <?php 
    $path_prefix = '../';
    include '../includes/navbar.php'; 
    ?>

    <div class="container py-5">

        <div class="row">
            <!-- Sidebar / Filter -->
            <div class="col-lg-3 mb-4 reveal-fade">
                <div class="filter-section shadow-sm">
                    <h5 class="mb-3">Filter Workers</h5>
                    <form action="workers.php" method="GET">
                        <div class="mb-3">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Name or Service..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_GET['category']) && $_GET['category'] == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo $cat['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-select">
                                <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                                <option value="price_low" <?php echo ($sort == 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo ($sort == 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="rating" <?php echo ($sort == 'rating') ? 'selected' : ''; ?>>Highest Rated</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Apply Filters & Sort</button>
                        <a href="workers.php" class="btn btn-outline-secondary w-100 mt-2">Reset</a>
                    </form>
                </div>
            </div>

            <!-- Worker List -->
            <div class="col-lg-9 reveal-fade" data-delay="100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">Available Workers (<?php echo count($workers); ?>)</h4>
                    <?php if($location_status === 'exact'): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3">
                            <i class="fas fa-map-marker-alt me-1"></i> Exact Matches in <?php echo $user_pin; ?>
                        </span>
                    <?php elseif($location_status === 'nearby'): ?>
                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-3">
                            <i class="fas fa-map-marked-alt me-1"></i> Showing Nearby Area (+/- 5 range)
                        </span>
                    <?php elseif($location_status === 'none_nearby'): ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3">
                            <i class="fas fa-globe-asia me-1"></i> No local workers found. Showing all.
                        </span>
                    <?php endif; ?>
                </div>
                <?php if(count($workers) > 0): ?>
                    <div class="row g-4">
                        <?php foreach($workers as $worker): ?>
                            <div class="col-md-6 col-xl-4 reveal-fade" data-delay="200">
                                        <div class="card worker-card h-100 shadow-sm rounded-4">
                                        <div class="card-body text-center">
                                            <div class="mb-3">
                                                <div class="rounded-circle bg-body-secondary d-inline-flex align-items-center justify-content-center border overflow-hidden" style="width: 80px; height: 80px;">
                                                    <?php 
                                                        $img_src = $worker['profile_image'] && $worker['profile_image'] != 'default.png' 
                                                            ? $worker['profile_image'] 
                                                            : null;
                                                        
                                                        if ($img_src):
                                                            $thumb_url = $cld->getUrl($img_src, ['width' => 160, 'height' => 160, 'crop' => 'fill', 'gravity' => 'face']);
                                                    ?>
                                                        <img src="<?php echo $thumb_url; ?>" alt="Worker" class="w-100 h-100 object-fit-cover">
                                                    <?php else: ?>
                                                        <i class="fas <?php echo $worker['category_icon'] ? $worker['category_icon'] : 'fa-user'; ?> fa-2x text-secondary"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($worker['name']); ?></h5>
                                            <div class="mb-2">
                                                <span class="badge bg-info text-dark"><?php echo htmlspecialchars($worker['category_name']); ?></span>
                                                <?php if ($worker['is_available']): ?>
                                                    <span class="badge bg-success shadow-sm ms-1"><i class="fas fa-check-circle me-1"></i> Available</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary shadow-sm ms-1 opacity-75"><i class="fas fa-clock me-1"></i> Busy</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="mb-2 text-warning small">
                                                <?php 
                                                $avg = round($worker['avg_rating'], 1);
                                                $rev_count = $worker['total_reviews'];
                                                
                                                $stars = floor($avg);
                                                for($i=1; $i<=5; $i++) {
                                                    if($i <= $stars) echo '<i class="fas fa-star"></i>';
                                                    elseif($i == $stars + 1 && $avg - $stars >= 0.5) echo '<i class="fas fa-star-half-alt"></i>';
                                                    else echo '<i class="far fa-star"></i>';
                                                }
                                                ?>
                                                <span class="fw-bold text-dark ms-1"><?php echo $avg > 0 ? $avg : '0'; ?></span>
                                                <span class="text-muted ms-1 small">(<?php echo $rev_count; ?>)</span>
                                            </div>

                                            <p class="text-primary fw-bold mb-2">₹<?php echo number_format($worker['hourly_rate'], 0); ?> / hr</p>
                                            <p class="small text-muted mb-3 text-truncate"><?php echo $worker['bio'] ? htmlspecialchars($worker['bio']) : 'No bio available'; ?></p>
                                            
                                            <div class="mt-auto">
                                                <a href="worker_profile.php?id=<?php echo $worker['id']; ?>" class="btn btn-outline-primary btn-sm w-100 rounded-pill">View Profile & Book</a>
                                            </div>
                                        </div>
                                    </div>
                            </div>

                            <!-- Booking Modal for this Worker -->
                            <div class="modal fade" id="bookModal<?php echo $worker['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title">Book <?php echo htmlspecialchars($worker['name']); ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="worker_id" value="<?php echo $worker['id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Select Date</label>
                                                    <input type="date" name="date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Select Time</label>
                                                    <input type="time" name="time" class="form-control" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Service Address</label>
                                                    <textarea name="address" class="form-control" rows="3" required placeholder="Enter full address..."></textarea>
                                                </div>
                                                <div class="alert alert-info py-2 small">
                                                    <i class="fas fa-info-circle me-1"></i> Rate: ₹<?php echo number_format($worker['hourly_rate'], 0); ?>/hr. Payment to be settled with worker directly.
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" name="book_worker" class="btn btn-primary">Confirm Booking</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5>No workers found matching your criteria.</h5>
                        <p class="text-muted">Try adjusting your filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
