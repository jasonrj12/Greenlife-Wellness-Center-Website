<?php
/**
 * Database Connection using PDO
 * GreenLife Wellness Center
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'greenlife_wellness');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    // Create PDO connection with error handling
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to get database connection
function getDBConnection() {
    global $pdo;
    return $pdo;
}
?>