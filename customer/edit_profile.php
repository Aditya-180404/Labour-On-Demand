<?php
session_start();
require_once '../config/db.php';

// Check User Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = "";
$error = "";

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $pin_code = trim($_POST['pin_code']);
    $location = trim($_POST['location']);

    // Handle File Upload
    $profile_image = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $upload_dir = '../uploads/users/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $filename = "user_" . $user_id . "_" . time() . "." . $ext;
        $target_file = $upload_dir . $filename;
        
        $allowiv = array('jpg', 'jpeg', 'png', 'gif');
        if (in_array(strtolower($ext), $allowiv)) {
             if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                 $profile_image = $filename;
             } else {
                 $error = "Failed to upload image.";
             }
        } else {
            $error = "Invalid file type. Only JPG, PNG, GIF allowed.";
        }
    }

    if (!$error) {
        // Prepare Query
        $sql = "UPDATE users SET name = ?, phone = ?, address_details = ?, pin_code = ?, location = ?";
        $params = [$name, $phone, $address, $pin_code, $location];

        if ($profile_image) {
            $sql .= ", profile_image = ?";
            $params[] = $profile_image;
        }

        $sql .= " WHERE id = ?";
        $params[] = $user_id;

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
             $success = "Profile updated successfully!";
             $_SESSION['user_name'] = $name; // Update session name
        } else {
            $error = "Failed to update database.";
        }
    }
}

// Fetch Current Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Labour On Demand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-img-preview { width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 3px solid var(--bs-border-color); }
        .card { border: none; border-radius: 20px; overflow: hidden; }
        .card-header { border-bottom: none; }
    </style>
</head>
<body class="bg-body">
    <?php include '../includes/navbar.php'; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Edit Profile</h4>
                    </div>
                    <div class="card-body">
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form action="edit_profile.php" method="POST" enctype="multipart/form-data">
                            <div class="text-center mb-4">
                                <?php 
                                    $img_src = $user['profile_image'] && $user['profile_image'] != 'default.png' 
                                        ? "../uploads/users/" . $user['profile_image'] 
                                        : "https://via.placeholder.com/150"; 
                                ?>
                                <img src="<?php echo $img_src; ?>" alt="Profile" class="profile-img-preview mb-3">
                                <div class="mb-3">
                                    <label for="profile_image" class="form-label">Change Profile Picture</label>
                                    <input type="file" class="form-control" id="profile_image" name="profile_image">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Detailed Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($user['address_details']); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="pin_code" class="form-label">PIN Code</label>
                                    <input type="text" class="form-control" id="pin_code" name="pin_code" value="<?php echo htmlspecialchars($user['pin_code']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="location" class="form-label">Area / Location</label>
                                    <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($user['location']); ?>">
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
