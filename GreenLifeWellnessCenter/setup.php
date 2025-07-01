<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - GreenLife Wellness Center</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .btn {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #218838;
        }
        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .credentials {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        pre {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>GreenLife Wellness Center - Database Setup</h1>
        
        <?php
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        
        $message = '';
        $error = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Database configuration
                $host = $_POST['host'] ?? 'localhost';
                $username = $_POST['username'] ?? 'root';
                $password = $_POST['password'] ?? '';
                $database = $_POST['database'] ?? 'greenlife_wellness';
                
                // Create connection without selecting database first
                $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                
                // Read and execute SQL file
                $sqlFile = __DIR__ . '/sql/create_database.sql';
                if (!file_exists($sqlFile)) {
                    throw new Exception("SQL file not found: $sqlFile");
                }
                
                $sql = file_get_contents($sqlFile);
                
                // Split SQL into individual statements
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                
                $executedCount = 0;
                foreach ($statements as $statement) {
                    if (!empty($statement) && !preg_match('/^--/', $statement)) {
                        $pdo->exec($statement);
                        $executedCount++;
                    }
                }
                
                // Update db_connect.php with new credentials
                $dbConnectFile = __DIR__ . '/includes/db_connect.php';
                $dbConnectContent = "<?php
/**
 * Database Connection using PDO
 * GreenLife Wellness Center
 */

// Database configuration
define('DB_HOST', '$host');
define('DB_NAME', '$database');
define('DB_USER', '$username');
define('DB_PASS', '$password');

try {
    // Create PDO connection with error handling
    \$pdo = new PDO(
        \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8mb4\",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException \$e) {
    die(\"Database connection failed: \" . \$e->getMessage());
}

// Function to get database connection
function getDBConnection() {
    global \$pdo;
    return \$pdo;
}
?>";
                
                file_put_contents($dbConnectFile, $dbConnectContent);
                
                $message = "Database setup completed successfully! Executed $executedCount SQL statements.";
                
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
        ?>
        
        <?php if ($message): ?>
            <div class="success"><?php echo htmlspecialchars($message); ?></div>
            <div class="credentials">
                <h3>Default Login Credentials:</h3>
                <ul>
                    <li><strong>Admin:</strong> admin@greenlife.com / password</li>
                    <li><strong>Therapists:</strong> samara@greenlife.com, nimal@greenlife.com, priya@greenlife.com, kamal@greenlife.com / password</li>
                    <li><strong>Clients:</strong> john@example.com, jane@example.com / password</li>
                </ul>
                <p><a href="index.php" class="btn">Go to Main Site</a> <a href="admin/admin_dashboard.php" class="btn">Go to Admin Panel</a></p>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="info">
            <h3>Database Setup Instructions:</h3>
            <p>This script will create the database structure and insert sample data for the GreenLife Wellness Center system.</p>
            <p><strong>Requirements:</strong></p>
            <ul>
                <li>MySQL/MariaDB server running</li>
                <li>PHP with PDO MySQL extension</li>
                <li>Database user with CREATE, INSERT, UPDATE, DELETE privileges</li>
            </ul>
        </div>
        
        <form method="POST">
            <h3>Database Configuration:</h3>
            <p>
                <label>Host:</label><br>
                <input type="text" name="host" value="localhost" style="width: 100%; padding: 8px; margin: 5px 0;">
            </p>
            <p>
                <label>Username:</label><br>
                <input type="text" name="username" value="root" style="width: 100%; padding: 8px; margin: 5px 0;">
            </p>
            <p>
                <label>Password:</label><br>
                <input type="password" name="password" value="" style="width: 100%; padding: 8px; margin: 5px 0;">
            </p>
            <p>
                <label>Database Name:</label><br>
                <input type="text" name="database" value="greenlife_wellness" style="width: 100%; padding: 8px; margin: 5px 0;">
            </p>
            <p>
                <button type="submit" class="btn">Setup Database</button>
            </p>
        </form>
        
        <div class="info">
            <h3>What This Setup Does:</h3>
            <ul>
                <li>Creates the greenlife_wellness database</li>
                <li>Creates all required tables with proper relationships</li>
                <li>Inserts sample data including admin user, therapists, services, and clients</li>
                <li>Creates database indexes for optimal performance</li>
                <li>Updates database connection configuration</li>
            </ul>
        </div>
    </div>
</body>
</html>