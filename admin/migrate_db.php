require_once '../includes/security.php';
require_once '../config/db.php';

// Check Admin Login
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$columns = [
    'pending_updates' => "JSON DEFAULT NULL",
    'pending_profile_image' => "VARCHAR(255) DEFAULT NULL",
    'pending_profile_image_public_id' => "VARCHAR(255) DEFAULT NULL",
    'pending_aadhar_photo' => "VARCHAR(255) DEFAULT NULL", 
    'pending_aadhar_photo_public_id' => "VARCHAR(255) DEFAULT NULL",
    'pending_pan_photo' => "VARCHAR(255) DEFAULT NULL",
    'pending_pan_photo_public_id' => "VARCHAR(255) DEFAULT NULL",
    'pending_signature_photo' => "VARCHAR(255) DEFAULT NULL",
    'pending_signature_photo_public_id' => "VARCHAR(255) DEFAULT NULL",
    'doc_update_status' => "ENUM('pending', 'approved', 'rejected') DEFAULT 'approved'"
];

foreach ($columns as $col => $def) {
    $stmt = $pdo->query("SHOW COLUMNS FROM workers LIKE '$col'");
    if (!$stmt->fetch()) {
        try {
            $pdo->exec("ALTER TABLE workers ADD COLUMN $col $def");
            echo "Successfully added '$col'<br>";
        } catch (PDOException $e) {
            echo "Error adding '$col': " . $e->getMessage() . "<br>";
        }
    } else {
        echo "Column '$col' already exists<br>";
    }
}
echo "Create Table `worker_photo_history` if not exists...<br>";
$pdo->exec("CREATE TABLE IF NOT EXISTS worker_photo_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    worker_id INT NOT NULL,
    photo_type VARCHAR(50) NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    photo_public_id VARCHAR(255),
    replaced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE
)");

echo "Create Table `jobs` if not exists...<br>";
$pdo->exec("CREATE TABLE IF NOT EXISTS `jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `type` VARCHAR(50) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    `attempts` INT DEFAULT 0,
    `last_error` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

echo "Done.";
?>
