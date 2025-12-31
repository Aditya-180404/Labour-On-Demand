<?php
require_once __DIR__ . '/../config/db.php';

echo "Starting Database Optimization for Scalability...\n";

try {
    // 1. Add Columns to Workers Table
    echo "1. Checking/Adding columns to workers table...\n";
    
    // Check if column exists first to avoid duplicate errors
    $check = $pdo->query("SHOW COLUMNS FROM workers LIKE 'avg_rating'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE workers ADD COLUMN avg_rating DECIMAL(3,2) DEFAULT 0.00");
        echo "   - Added 'avg_rating' column.\n";
    } else {
        echo "   - 'avg_rating' column already exists.\n";
    }

    $check = $pdo->query("SHOW COLUMNS FROM workers LIKE 'total_reviews'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE workers ADD COLUMN total_reviews INT DEFAULT 0");
        echo "   - Added 'total_reviews' column.\n";
    } else {
        echo "   - 'total_reviews' column already exists.\n";
    }

    // 2. Add Indexes (Using try-catch for individual indexes as MySQL doesn't have IF NOT EXISTS for indexes in older versions reliably)
    echo "2. Applying Indexes...\n";
    
    $indexes = [
        "CREATE INDEX idx_status_rating ON workers(status, avg_rating)",
        "CREATE INDEX idx_category ON workers(service_category_id)",
        "CREATE INDEX idx_worker_booking ON bookings(worker_id, service_date, service_time)",
        "CREATE INDEX idx_user_booking ON bookings(user_id, status)",
        "CREATE INDEX idx_worker_pin ON workers(pin_code(6))" // Prefix index for text column
    ];

    foreach ($indexes as $sql) {
        try {
            $pdo->exec($sql);
            echo "   - Executed: $sql\n";
        } catch (PDOException $e) {
            // 1061 = Duplicate key name
            if ($e->getCode() == '42000' && strpos($e->getMessage(), 'Duplicate key name') !== false) {
                 echo "   - Index already exists (Skipped).\n";
            } else {
                echo "   - Error creating index: " . $e->getMessage() . "\n";
            }
        }
    }

    // 3. Populate Data (Backfill)
    echo "3. Backfilling Rating Data...\n";
    
    // Get all stats
    $stats = $pdo->query("
        SELECT worker_id, COUNT(*) as cnt, AVG(rating) as avg_r 
        FROM reviews 
        GROUP BY worker_id
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "   - Found stats for " . count($stats) . " workers.\n";

    $updateStmt = $pdo->prepare("UPDATE workers SET avg_rating = ?, total_reviews = ? WHERE id = ?");
    
    $pdo->beginTransaction();
    foreach ($stats as $row) {
        $updateStmt->execute([
            round($row['avg_r'], 2),
            $row['cnt'],
            $row['worker_id']
        ]);
    }
    $pdo->commit();
    echo "   - Data population complete.\n";

    echo "\nSUCCESS! Database optimized for scalability.\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
}
?>
