<?php
session_start();
require_once 'config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['worker_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login to send feedback.']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $user_id = $_SESSION['user_id'] ?? null;
    $worker_id = $_SESSION['worker_id'] ?? null;
    $sender_type = $_POST['sender_type'] ?? 'guest';
    $role = 'guest';

    if ($sender_type === 'customer' && $user_id) {
        $role = 'user';
        $worker_id = null; // Clear worker_id if sent as customer
    } elseif ($sender_type === 'worker' && $worker_id) {
        $role = 'worker';
        $user_id = null; // Clear user_id if sent as worker
    }

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill all fields.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO feedbacks (user_id, worker_id, sender_role, name, email, subject, message) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $worker_id, $role, $name, $email, $subject, $message])) {
            echo json_encode(['status' => 'success', 'message' => 'Feedback submitted successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Something went wrong.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
