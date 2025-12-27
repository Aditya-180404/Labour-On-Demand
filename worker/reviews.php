<?php
require_once '../config/security.php';
require_once '../config/db.php';

// Check if worker is logged in
if (!isset($_SESSION['worker_id'])) {
    header("Location: login.php");
    exit;
}

$worker_id = $_SESSION['worker_id'];

// Fetch Worker Details
$stmt = $pdo->prepare("SELECT name FROM workers WHERE id = ?");
$stmt->execute([$worker_id]);
$worker = $stmt->fetch();

// Fetch Rating Summary
$rating_stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE worker_id = ?");
$rating_stmt->execute([$worker_id]);
$rating_summary = $rating_stmt->fetch();
$avg_rating = round($rating_summary['avg_rating'], 1);
$total_reviews = $rating_summary['total_reviews'];

// Fetch All Reviews
$reviews_stmt = $pdo->prepare("
    SELECT r.*, u.name as user_name 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.worker_id = ? 
    ORDER BY r.created_at DESC
");
$reviews_stmt->execute([$worker_id]);
$reviews = $reviews_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reviews - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .page-header { background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%); color: white; padding: 3rem 0; margin-bottom: 2rem; border-radius: 0 0 30px 30px; }
        .review-card { border: none; border-radius: 15px; transition: transform 0.2s; background-color: var(--bs-body-bg); border: 1px solid var(--bs-border-color); }
        .review-card:hover { transform: translateY(-3px); }
        .rating-stars { color: #ffc107; }
    </style>
</head>
<body class="bg-body">

    <?php include '../includes/worker_navbar.php'; ?>

    <div class="page-header text-center">
        <div class="container">
            <h2 class="fw-bold mb-2">My Reviews & Ratings</h2>
            <div class="d-flex justify-content-center align-items-center gap-3 mt-3">
                <div class="bg-white bg-opacity-25 rounded-pill px-4 py-2 border border-white border-opacity-25">
                    <span class="h4 mb-0 fw-bold"><?php echo $avg_rating ?: '0.0'; ?></span>
                    <i class="fas fa-star text-warning ms-1"></i>
                    <small class="ms-1 opacity-75">Average</small>
                </div>
                <div class="bg-white bg-opacity-25 rounded-pill px-4 py-2 border border-white border-opacity-25">
                    <span class="h4 mb-0 fw-bold"><?php echo $total_reviews; ?></span>
                    <small class="ms-1 opacity-75">Total Reviews</small>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if(count($reviews) > 0): ?>
                    <?php foreach($reviews as $review): ?>
                        <div class="card review-card mb-3 shadow-sm">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($review['user_name']); ?></h6>
                                        <div class="rating-stars">
                                            <?php for($i=1; $i<=5; $i++) echo $i <= $review['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></small>
                                </div>
                                <p class="mb-0 text-muted fst-italic">"<?php echo nl2br(htmlspecialchars($review['comment'] ?: 'No comment provided.')); ?>"</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="bg-body-secondary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="far fa-comment-dots text-muted fa-2x"></i>
                        </div>
                        <h5 class="text-muted">No reviews yet.</h5>
                        <p class="text-muted small">Quality service leads to great reviews!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../includes/worker_footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/theme.js"></script>
</body>
</html>
