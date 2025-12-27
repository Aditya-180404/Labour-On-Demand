<?php
require_once '../config/security.php';
require_once '../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Services - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/navbar.css">
    <link rel="stylesheet" href="../assets/css/services.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container py-5">
        <h2 class="text-center mb-5 fw-bold">Our Services</h2>
        <div class="row g-4">
            <?php
            $stmt = $pdo->query("SELECT * FROM categories");
            while($cat = $stmt->fetch()):
            ?>
            <div class="col-md-3 col-6">
                <a href="workers.php?category=<?php echo $cat['id']; ?>" class="text-decoration-none">
                    <div class="card service-card h-100 text-center shadow-sm border-0 rounded-4">
                        <div class="card-body py-4">
                            <div class="icon-wrapper mb-3 mx-auto bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                                <i class="fas <?php echo $cat['icon']; ?> fa-2x text-primary"></i>
                            </div>
                            <h5 class="card-title text-body fw-bold"><?php echo $cat['name']; ?></h5>
                            <p class="card-text text-muted small mb-0">Find <?php echo $cat['name']; ?>s near you</p>
                        </div>
                    </div>
                </a>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
