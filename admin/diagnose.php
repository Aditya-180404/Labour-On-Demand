<?php
/**
 * Labour On Demand - Admin Dashboard Diagnostic Tool
 * 
 * Usage: Upload this file to your 'admin/' folder and visit it in your browser.
 * Example: https://yourdomain.com/admin/diagnose.php
 */

// 1. Enable Full Error Reporting & Admin Session Check
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    die("<h1>403 Forbidden</h1><p>Diagnostic tools are restricted to authorized administrators.</p>");
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<html><head><title>Admin Diagnostics</title><style>body{font-family:monospace; background:#f4f4f4; padding:20px;} h1{color:#333;} .success{color:green; font-weight:bold;} .fail{color:red; font-weight:bold;} .warning{color:orange; font-weight:bold;}</style></head><body>";
echo "<h1>Admin Dashboard Diagnostics</h1>";
echo "<pre>";

// 2. Check Execution Context
define('EXECUTION_ALLOWED', true);
$path_prefix = '../';

// 3. Test Dependencies
function test_require($path, $label) {
    echo "Testing include: $label ($path)... ";
    if (file_exists($path)) {
        try {
            require_once $path;
            echo "<span class='success'>SUCCESS</span>\n";
        } catch (Throwable $e) {
            echo "<span class='fail'>CRASHED during include: " . $e->getMessage() . "</span>\n";
            return false;
        }
    } else {
        echo "<span class='fail'>FAILED (File not found)</span>\n";
        return false;
    }
    return true;
}

$deps = [
    '../includes/security.php' => 'Security Module',
    '../config/db.php' => 'Database Config',
    '../includes/mailer.php' => 'Mailer Module',
    '../includes/cloudinary_helper.php' => 'Cloudinary Helper'
];

foreach ($deps as $path => $label) {
    if (!test_require($path, $label)) break;
}

// 4. Test Composer Autoload specifically
echo "\nTesting Composer Autoload... ";
if (file_exists('../vendor/autoload.php')) {
    echo "<span class='success'>FOUND</span>\n";
} else {
    echo "<span class='fail'>MISSING! (Run 'composer install' or upload 'vendor' folder)</span>\n";
}

// 5. Test Database Connection and Schema
global $pdo;
if (isset($pdo)) {
    echo "\nDatabase Connection: <span class='success'>ACTIVE</span>\n";
    
    $tables_to_check = ['users', 'workers', 'bookings', 'jobs', 'feedbacks', 'security_incidents', 'worker_photo_history'];
    foreach ($tables_to_check as $table) {
        try {
            $stmt = $pdo->query("DESCRIBE `$table` ");
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "Table `$table`: <span class='success'>EXISTS</span> (Columns: " . implode(', ', $cols) . ")\n";
            
            // Check specific high-risk columns
            if ($table === 'users' && !in_array('user_uid', $cols)) echo "  <span class='fail'>CRITICAL: Column 'user_uid' missing from 'users'!</span>\n";
            if ($table === 'workers' && !in_array('worker_uid', $cols)) echo "  <span class='fail'>CRITICAL: Column 'worker_uid' missing from 'workers'!</span>\n";
            if ($table === 'workers' && !in_array('doc_update_status', $cols)) echo "  <span class='fail'>CRITICAL: Column 'doc_update_status' missing from 'workers'!</span>\n";
            if ($table === 'bookings' && !in_array('work_proof_images', $cols)) echo "  <span class='warning'>WARNING: Column 'work_proof_images' missing from 'bookings'.</span>\n";
            
        } catch (PDOException $e) {
            echo "Table `$table`: <span class='fail'>MISSING or ERROR</span> (" . $e->getMessage() . ")\n";
        }
    }
} else {
    echo "\nDatabase Connection: <span class='fail'>NOT FOUND (PDO instance missing)</span>\n";
}

// 6. Test Cloudinary initialization
echo "\nTesting CloudinaryHelper... ";
try {
    if (class_exists('CloudinaryHelper')) {
        $cld = CloudinaryHelper::getInstance();
        echo "<span class='success'>SUCCESS (Object initialized)</span>\n";
    } else {
        echo "<span class='fail'>FAILED (Class not found)</span>\n";
    }
} catch (Throwable $e) {
    echo "<span class='fail'>FAILED (" . $e->getMessage() . ")</span>\n";
}

echo "</pre>";
echo "<hr><p>If all items above are green but the dashboard is still white, check the browser Network tab for 403 or 500 errors and ensure all files are fully uploaded.</p>";
echo "</body></html>";
?>
