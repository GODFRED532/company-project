<?php
require_once 'config/database.php';

$message = '';
$error = '';

if ($_POST) {
    try {
        $pdo = getDBConnection();
        
        // Check if customer_name column exists
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS cnt 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'sales' 
            AND COLUMN_NAME = 'customer_name'
        ");
        $stmt->execute();
        $exists = (int)$stmt->fetch()['cnt'] > 0;
        
        if (!$exists) {
            // Add customer fields to sales table
            $pdo->exec("ALTER TABLE sales ADD COLUMN customer_name VARCHAR(255) AFTER staff_name");
            $pdo->exec("ALTER TABLE sales ADD COLUMN customer_address TEXT AFTER customer_name");
            $pdo->exec("ALTER TABLE sales ADD COLUMN lpo_number VARCHAR(100) AFTER customer_address");
            
            $message = "✅ Customer fields added successfully! The sales table now includes customer_name, customer_address, and lpo_number columns.";
        } else {
            $message = "✅ Database is already up to date. Customer fields already exist.";
        }
        
    } catch (Exception $e) {
        $error = "❌ Update failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Customer Fields</title>
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
        
        .update-container {
            background: white;
            border-radius: 15px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 700px;
            width: 90%;
            text-align: center;
        }
        
        .update-container h1 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 2.5rem;
        }
        
        .update-container p {
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
        
        .update-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
            text-align: left;
        }
        
        .update-info h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .update-info ul {
            list-style: none;
            padding: 0;
        }
        
        .update-info li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .update-info li:before {
            content: "✓ ";
            color: #27ae60;
            font-weight: bold;
        }
        
        .sql-code {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 1rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            text-align: left;
            margin: 1rem 0;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="update-container">
        <h1>👤 Add Customer Fields</h1>
        <p>Add customer information fields to the sales table</p>
        
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
            <div class="update-info">
                <h3>This update will add:</h3>
                <ul>
                    <li>customer_name field to store customer names</li>
                    <li>customer_address field to store customer addresses</li>
                    <li>lpo_number field to store L.P.O numbers</li>
                    <li>Enable proper customer information storage</li>
                </ul>
            </div>
            
            <div class="sql-code">
                <strong>SQL Queries that will be executed:</strong><br>
                ALTER TABLE sales ADD COLUMN customer_name VARCHAR(255) AFTER staff_name;<br>
                ALTER TABLE sales ADD COLUMN customer_address TEXT AFTER customer_name;<br>
                ALTER TABLE sales ADD COLUMN lpo_number VARCHAR(100) AFTER customer_address;
            </div>
            
            <form method="post">
                <button type="submit" class="btn">Add Customer Fields</button>
            </form>
            
            <div style="margin-top: 2rem;">
                <a href="index.php" class="btn">Back to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
