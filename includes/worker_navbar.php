<?php if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.'); ?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/navbar.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/theme.css">
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF']);
$is_worker_path = strpos($_SERVER['REQUEST_URI'], '/worker/') !== false;
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm border-bottom border-white border-opacity-10">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="<?php echo BASE_URL; ?>/worker/dashboard.php">
            <div class="bg-primary text-white rounded d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; transform: rotate(-5deg);">
                <i class="fas fa-briefcase"></i>
            </div>
            <span class="fs-4">Labour<span class="text-primary">Pro</span></span>
        </a>
        
        <button class="navbar-toggler shadow-none border-0" type="button" data-bs-toggle="collapse" data-bs-target="#workerNav">
            <i class="fas fa-bars text-white fs-4"></i>
        </button>
        
        <div class="collapse navbar-collapse" id="workerNav">
            <ul class="navbar-nav ms-auto align-items-center text-center">
                <!-- Overview -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/worker/dashboard.php">
                        <i class="fas fa-chart-line me-1"></i>Overview
                    </a>
                </li>

                <!-- My Jobs -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'my_jobs.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/worker/my_jobs.php">
                        <i class="fas fa-list-check me-1"></i>Job Panel
                    </a>
                </li>

                <!-- Profile -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'edit_profile.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/worker/edit_profile.php">
                        <i class="fas fa-user-gear me-1"></i>Pro Settings
                    </a>
                </li>

                <!-- Reviews -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'reviews.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/worker/reviews.php">
                        <i class="fas fa-star me-1"></i>My Reviews
                    </a>
                </li>

                <!-- Earnings (Placeholder/Future) -->
                <li class="nav-item d-none d-lg-block mx-2">
                    <div class="vr h-100 text-white-50"></div>
                </li>

                <!-- Worker Profile Dropdown -->
                <li class="nav-item dropdown ms-lg-2">
                    <a class="nav-link dropdown-toggle bg-primary bg-opacity-10 rounded-pill px-3 py-1 mt-2 mt-lg-0 border border-primary border-opacity-25 shadow-sm d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                        <?php 
                        $worker_img = BASE_URL . "/assets/img/default-worker.png"; // Fallback default
                        if(isset($_SESSION['worker_id'])) {
                            // Fetch latest image from DB
                            $stmt = $pdo->prepare("SELECT profile_image FROM workers WHERE id = ?");
                            $stmt->execute([$_SESSION['worker_id']]);
                            $db_img = $stmt->fetchColumn();
                            if($db_img && $db_img != 'default.png') {
                                // Check if it's a Cloudinary URL or local
                                if (strpos($db_img, 'http') === 0) {
                                     $worker_img = $db_img;
                                } else {
                                     $worker_img = BASE_URL . "/uploads/workers/" . $db_img;
                                }
                            }
                        }
                        ?>
                        <img src="<?php echo $worker_img; ?>" class="rounded-circle border border-2 border-primary" style="width: 24px; height: 24px; object-fit: cover;" alt="Me">
                        <span><?php echo htmlspecialchars($_SESSION['worker_name'] ?? 'Worker'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2">
                        <li>
                            <div class="px-3 py-2">
                                <span class="d-block small text-muted">Service Provider</span>
                                <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['worker_name'] ?? 'Pro User'); ?></span>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>/worker/dashboard.php"><i class="fas fa-tachometer-alt me-2 text-primary"></i>Command Center</a></li>
                        <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>/worker/my_jobs.php"><i class="fas fa-briefcase me-2 text-success"></i>My Bookings</a></li>
                        <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>/worker/reviews.php"><i class="fas fa-star me-2 text-warning"></i>My Reviews</a></li>
                        <li><a class="dropdown-item py-2" href="<?php echo BASE_URL; ?>/worker/edit_profile.php"><i class="fas fa-id-card me-2 text-info"></i>Manage Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="<?php echo BASE_URL; ?>/logout.php"><i class="fas fa-power-off me-2"></i>Logout</a></li>
                    </ul>
                </li>
                
                <!-- Theme Toggle -->
                <li class="nav-item ms-lg-3 mt-2 mt-lg-0 border-lg-start ps-lg-3 border-white border-opacity-25">
                    <button class="btn btn-link nav-link p-0 text-white-50 shadow-none border-0" id="theme-toggle" title="Toggle theme">
                        <i class="fas fa-sun" id="theme-icon"></i>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</nav>
<script>
    // Handle shrinking navbar on scroll
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 50) {
            navbar.style.padding = "0.5rem 0";
            navbar.classList.add('shadow-lg');
        } else {
            navbar.style.padding = "1rem 0";
            navbar.classList.remove('shadow-lg');
        }
    });
</script>
