<?php
if (!defined('EXECUTION_ALLOWED')) exit('Direct access not allowed.');

/**
 * Generates a unique alphanumeric ID (UID) for users or workers.
 * @param PDO $pdo The database connection instance.
 * @param string $type The type of UID to generate ('user' or 'worker').
 * @return string The unique ID.
 */
function generateUID($pdo, $type) {
    if ($type === 'user') {
        $length = 10;
        $table = 'users';
        $column = 'user_uid';
    } elseif ($type === 'worker') {
        $length = 8;
        $table = 'workers';
        $column = 'worker_uid';
    } else {
        throw new Exception("Invalid UID type specified.");
    }

    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    while (true) {
        $uid = '';
        for ($i = 0; $i < $length; $i++) {
            $uid .= $chars[rand(0, strlen($chars) - 1)];
        }

        // Check for uniqueness
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $column = ?");
        $stmt->execute([$uid]);
        if ($stmt->fetchColumn() == 0) {
            return $uid;
        }
    }
}
?>
