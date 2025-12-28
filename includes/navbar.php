<?php if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.'); ?>
<link rel="stylesheet" href="<?php echo $path_prefix; ?>assets/css/navbar.css">
<link rel="stylesheet" href="<?php echo $path_prefix; ?>assets/css/theme.css">
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF']);
$is_worker_path = strpos($_SERVER['REQUEST_URI'], '/worker/') !== false;
$is_admin_path = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="<?php echo $path_prefix; ?>index.php">
            <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                <i class="fas fa-hard-hat"></i>
            </div>
            <span>Labour<span class="text-warning">On</span>Demand</span>
        </a>
        
        <button class="navbar-toggler shadow-none border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <i class="fas fa-bars text-white fs-4"></i>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center text-center">
                <!-- Home Link -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'index.php' && !$is_worker_path && !$is_admin_path) ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>index.php">
                        <i class="fas fa-home me-1"></i>Home
                    </a>
                </li>

                <?php if(isset($_SESSION['user_id'])): ?>
                    <!-- User Links -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'services.php' ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>customer/services.php">
                            <i class="fas fa-th-list me-1"></i>Services
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'workers.php' ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>customer/workers.php">
                            <i class="fas fa-users-cog me-1"></i>Find Workers
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'dashboard.php' && strpos($_SERVER['REQUEST_URI'], '/customer/') !== false) ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>customer/dashboard.php">
                            <i class="fas fa-th-large me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'my_bookings.php' ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>customer/my_bookings.php">
                            <i class="fas fa-calendar-check me-1"></i>My Bookings
                        </a>
                    </li>
                <?php elseif(isset($_SESSION['worker_id'])): ?>
                    <!-- Worker Links -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'dashboard.php' && $is_worker_path) ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>worker/dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'my_jobs.php' && $is_worker_path) ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>worker/my_jobs.php">
                            <i class="fas fa-briefcase me-1"></i>My Jobs
                        </a>
                    </li>
                <?php elseif(isset($_SESSION['admin_logged_in'])): ?>
                    <!-- Admin Links -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $is_admin_path ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>admin/dashboard.php">
                            <i class="fas fa-shield-alt me-1"></i>Admin Panel
                        </a>
                    </li>
                <?php else: ?>
                    <!-- Guest Links -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'services.php' ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>customer/services.php">
                            <i class="fas fa-th-list me-1"></i>Services
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'workers.php' ? 'active' : ''; ?>" href="<?php echo $path_prefix; ?>customer/workers.php">
                            <i class="fas fa-users me-1"></i>Workers
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown ms-lg-3">
                        <a class="nav-link dropdown-toggle bg-white bg-opacity-10 rounded-pill px-3 py-1 mt-2 mt-lg-0 border border-white border-opacity-25 shadow-sm d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                            <?php 
                            // Fetch profile image if not in session or just use a default
                            $user_img = $path_prefix . "assets/img/default-user.png";
                            if(isset($_SESSION['user_id'])) {
                                $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                $u_img = $stmt->fetchColumn();
                                if($u_img && $u_img != 'default.png') {
                                    // Check if it's a Cloudinary URL or local
                                    if (strpos($u_img, 'http') === 0) {
                                         $user_img = $u_img;
                                    } else {
                                         $user_img = $path_prefix . "uploads/users/" . $u_img;
                                    }
                                }
                            }
                            ?>
                            <img src="<?php echo $user_img; ?>" class="rounded-circle border border-2 border-white border-opacity-50" style="width: 24px; height: 24px; object-fit: cover;" alt="Me">
                            <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4">
                            <li><a class="dropdown-item py-2" href="<?php echo $path_prefix; ?>customer/dashboard.php"><i class="fas fa-columns me-2 text-primary"></i>Dashboard</a></li>
                            <li><a class="dropdown-item py-2" href="<?php echo $path_prefix; ?>customer/my_bookings.php"><i class="fas fa-calendar-check me-2 text-success"></i>My Bookings</a></li>
                            <li><a class="dropdown-item py-2" href="<?php echo $path_prefix; ?>customer/edit_profile.php"><i class="fas fa-user-edit me-2 text-info"></i>Edit Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2 text-danger" href="<?php echo $path_prefix; ?>logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php elseif(isset($_SESSION['worker_id'])): ?>
                    <li class="nav-item dropdown ms-lg-3">
                        <a class="nav-link dropdown-toggle bg-white bg-opacity-10 rounded-pill px-3 py-1 mt-2 mt-lg-0 border border-white border-opacity-25 shadow-sm" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-hard-hat me-1 text-warning"></i> <?php echo htmlspecialchars($_SESSION['worker_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4">
                            <li><a class="dropdown-item py-2" href="<?php echo $path_prefix; ?>worker/dashboard.php"><i class="fas fa-tachometer-alt me-2 text-primary"></i>Overview</a></li>
                            <li><a class="dropdown-item py-2" href="<?php echo $path_prefix; ?>worker/my_jobs.php"><i class="fas fa-briefcase me-2 text-success"></i>My Jobs</a></li>
                            <li><a class="dropdown-item py-2" href="<?php echo $path_prefix; ?>worker/edit_profile.php"><i class="fas fa-user-edit me-2 text-info"></i>Edit Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2 text-danger" href="<?php echo $path_prefix; ?>logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                <?php elseif(isset($_SESSION['admin_logged_in'])): ?>
                     <li class="nav-item ms-lg-3"><a class="nav-link btn btn-danger btn-sm text-white fw-bold px-3 py-1 rounded-pill mt-2 mt-lg-0 shadow-sm" href="<?php echo $path_prefix; ?>logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item ms-lg-3">
                        <a class="nav-link btn btn-light text-primary fw-bold px-4 py-1 rounded-pill mt-2 mt-lg-0 shadow-sm border-0" href="<?php echo $path_prefix; ?>customer/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                <?php endif; ?>
                
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

<script src="<?php echo $path_prefix; ?>assets/js/theme.js"></script>
<script>
    // Handle shrinking navbar on scroll
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 50) {
            navbar.classList.add('sticky-nav');
        } else {
            navbar.classList.remove('sticky-nav');
        }
    });
</script>
