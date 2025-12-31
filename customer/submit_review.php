<?php
require_once '../includes/security.php';
require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_review'])) {
    $user_id = $_SESSION['user_id'];
    
    // CSRF & Bot Protection
    if (isBotDetected()) {
        header("Location: my_bookings.php?error=Security check failed: Bot detected.");
        exit;
    }
    if (!isset($_POST['csrf_token']) || !verifyCSRF($_POST['csrf_token'])) {
        header("Location: my_bookings.php?error=Security check failed. Please try again.");
        exit;
    }

    $booking_id = $_POST['booking_id'];
    $rating = $_POST['rating'];
    $comment = trim($_POST['comment']);

    // Validation
    if (empty($booking_id) || empty($rating) || $rating < 1 || $rating > 5) {
        header("Location: my_bookings.php?error=Invalid review data.");
        exit;
    }

    try {
        // Verify booking belongs to user and is completed
        $check_stmt = $pdo->prepare("SELECT worker_id FROM bookings WHERE id = ? AND user_id = ? AND status = 'completed'");
        $check_stmt->execute([$booking_id, $user_id]);
        $booking = $check_stmt->fetch();

        if (!$booking) {
            header("Location: my_bookings.php?error=Booking not found or not completed.");
            exit;
        }

        $worker_id = $booking['worker_id'];

        // Check if review already exists for this booking
        $exists_stmt = $pdo->prepare("SELECT id FROM reviews WHERE booking_id = ?");
        $exists_stmt->execute([$booking_id]);
        if ($exists_stmt->rowCount() > 0) {
            header("Location: my_bookings.php?error=You have already reviewed this booking.");
            exit;
        }

        // Insert Review
        $insert_stmt = $pdo->prepare("INSERT INTO reviews (booking_id, user_id, worker_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
        if ($insert_stmt->execute([$booking_id, $user_id, $worker_id, $rating, $comment])) {
            
            // OPTIMIZATION: Update Worker Stats (Denormalization)
            // recalculate average: (old_avg * old_count + new_rating) / new_count
            // Since we update total_reviews first, we use (total_reviews - 1) to get old count
            $update_stats = $pdo->prepare("
                UPDATE workers 
                SET total_reviews = total_reviews + 1, 
                    avg_rating = ((avg_rating * (total_reviews - 1)) + ?) / total_reviews 
                WHERE id = ?
            ");
            $update_stats->execute([$rating, $worker_id]);

            header("Location: my_bookings.php?success=Review submitted successfully.");
        } else {
            header("Location: my_bookings.php?error=Failed to submit review.");
        }
    } catch (PDOException $e) {
        logSecurityIncident('database_error', 'medium', 'Review submission failed: ' . $e->getMessage());
        header("Location: my_bookings.php?error=An internal error occurred.");
    }
    exit;
} else {
    header("Location: my_bookings.php");
    exit;
}
?>
