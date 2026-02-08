<?php
require_once 'config/database.php';

$message = '';
$error = '';

if ($_POST) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Read and execute the SQL setup file
        $sql = file_get_contents('database_setup.sql');
        
        // Split by semicolon and execute each statement
        $statements = explode(';', $sql);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        $message = "Database setup completed successfully! You can now use the system.";
        
    } catch (Exception $e) {
        $error = "Setup failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Mining Equipment Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .setup-container {
            background: white;
            border-radius: 15px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 90%;
            text-align: center;
        }
        
        .setup-container h1 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 2.5rem;
        }
        
        .setup-container p {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            font-weight: 500;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 1rem;
            cursor: pointer;
            margin: 0.5rem;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #2980b9, #1f4e79);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60, #229954);
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #229954, #1e8449);
        }
        
        .setup-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
            text-align: left;
        }
        
        .setup-info h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .setup-info ul {
            list-style: none;
            padding: 0;
        }
        
        .setup-info li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .setup-info li:before {
            content: "✓ ";
            color: #27ae60;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1>🏭 Database Setup</h1>
        <p>Set up the database for your Mining Equipment Management System</p>
        
        <?php if ($message): ?>
            <div class="message success">
                <?php echo $message; ?>
            </div>
            <a href="index.php" class="btn btn-success">Go to Dashboard</a>
        <?php elseif ($error): ?>
            <div class="message error">
                <?php echo $error; ?>
            </div>
            <form method="post">
                <button type="submit" class="btn">Try Again</button>
            </form>
        <?php else: ?>
            <div class="setup-info">
                <h3>This setup will create:</h3>
                <ul>
                    <li>Database: mining_equipment_db</li>
                    <li>Products table with flexible fields</li>
                    <li>Sales and Sale Items tables</li>
                    <li>Sample data for testing</li>
                </ul>
            </div>
            
            <form method="post">
                <button type="submit" class="btn">Setup Database</button>
            </form>
            
            <div style="margin-top: 2rem;">
                <a href="index.php" class="btn">Back to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

