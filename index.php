<?php
session_start();
require_once 'config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Labour On Demand</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>
    
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <header class="hero-section text-center text-white d-flex align-items-center justify-content-center">
        <div class="container">
            <h1 class="display-3 fw-bold mb-4">Find Local Workers in Minutes</h1>
            <p class="lead mb-5">Plumbers, Electricians, Cleaners, and more at your doorstep.</p>
            <div class="search-box bg-body-tertiary p-3 rounded-pill shadow mx-auto" style="border: none;">
                <form action="customer/workers.php" method="GET" class="d-flex">
                    <input type="text" name="search" class="form-control bg-transparent border-0 me-2" placeholder="What service do you need? (e.g. Plumber)">
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Search</button>
                </form>
            </div>
        </div>
    </header>

    <!-- Categories Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold">Popular Services</h2>
            <div class="row g-4">
                <?php
                $stm = $pdo->query("SELECT * FROM categories LIMIT 6");
                while($cat = $stm->fetch()):
                ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="customer/workers.php?category=<?php echo $cat['id']; ?>" class="text-decoration-none">
                        <div class="card category-card text-center h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <i class="fas <?php echo $cat['icon']; ?> fa-3x mb-3 text-primary"></i>
                                <h5 class="card-title text-body"><?php echo $cat['name']; ?></h5>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endwhile; ?>
            </div>
            <div class="text-center mt-5">
                <a href="customer/services.php" class="btn btn-outline-primary rounded-pill px-4">View All Services</a>
            </div>
        </div>
    </section>

    <!-- Mechanics / How it Works -->
    <section class="py-5 bg-body-tertiary">
        <div class="container">
            <h2 class="text-center mb-5 fw-bold">How It Works</h2>
            <div class="row text-center">
                <div class="col-md-4 mb-4">
                    <div class="step-card p-4">
                        <div class="step-icon bg-body shadow-sm rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                            <i class="fas fa-search fa-2x text-primary"></i>
                        </div>
                        <h4>1. Search</h4>
                        <p class="text-muted">Choose from a wide range of services or search for a specific worker near you.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="step-card p-4">
                        <div class="step-icon bg-body shadow-sm rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                            <i class="fas fa-calendar-check fa-2x text-primary"></i>
                        </div>
                        <h4>2. Book</h4>
                        <p class="text-muted">Select a suitable time and date. Book your worker instantly.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="step-card p-4">
                        <div class="step-icon bg-body shadow-sm rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                            <i class="fas fa-smile fa-2x text-primary"></i>
                        </div>
                        <h4>3. Relax</h4>
                        <p class="text-muted">The worker comes to your doorstep. Rate them after the job is done.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
