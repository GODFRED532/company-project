<?php
require_once 'config/database.php';

$message = '';
$error = '';

try {
    $pdo = getDBConnection();
    
    // Check sale_items table structure
    $stmt = $pdo->query("DESCRIBE sale_items");
    $saleItemsColumns = $stmt->fetchAll();
    
    // Check products table structure
    $stmt = $pdo->query("DESCRIBE products");
    $productsColumns = $stmt->fetchAll();
    
    // Check if there are any sales
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sales");
    $salesCount = $stmt->fetch()['total'];
    
    // Check if there are any sale_items
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sale_items");
    $saleItemsCount = $stmt->fetch()['total'];
    
    $message = "✅ Database structure is correct!";
    
} catch (Exception $e) {
    $error = "❌ Database check failed: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Check</title>
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
        
        .check-container {
            background: white;
            border-radius: 15px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 800px;
            width: 90%;
            text-align: center;
        }
        
        .check-container h1 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 2.5rem;
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
        
        .table-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
            text-align: left;
        }
        
        .table-info h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .column-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .column-item {
            background: white;
            padding: 0.5rem;
            border-radius: 4px;
            border-left: 3px solid #3498db;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #3498db;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="check-container">
        <h1>🔍 Database Check</h1>
        <p>Checking your database structure and data</p>
        
        <?php if ($message): ?>
            <div class="message success">
                <?php echo $message; ?>
            </div>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $salesCount; ?></div>
                    <div class="stat-label">Total Sales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $saleItemsCount; ?></div>
                    <div class="stat-label">Sale Items</div>
                </div>
            </div>
            
            <div class="table-info">
                <h3>📋 Sale Items Table Structure:</h3>
                <div class="column-list">
                    <?php foreach ($saleItemsColumns as $column): ?>
                        <div class="column-item">
                            <strong><?php echo htmlspecialchars($column['Field']); ?></strong><br>
                            <small><?php echo htmlspecialchars($column['Type']); ?> 
                            <?php if ($column['Null'] === 'NO'): ?>(Required)<?php endif; ?>
                            <?php if ($column['Default']): ?>Default: <?php echo htmlspecialchars($column['Default']); ?><?php endif; ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="table-info">
                <h3>📦 Products Table Structure:</h3>
                <div class="column-list">
                    <?php foreach ($productsColumns as $column): ?>
                        <div class="column-item">
                            <strong><?php echo htmlspecialchars($column['Field']); ?></strong><br>
                            <small><?php echo htmlspecialchars($column['Type']); ?> 
                            <?php if ($column['Null'] === 'NO'): ?>(Required)<?php endif; ?>
                            <?php if ($column['Default']): ?>Default: <?php echo htmlspecialchars($column['Default']); ?><?php endif; ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <a href="pos.php" class="btn btn-success">Test POS System</a>
            <a href="index.php" class="btn">Back to Dashboard</a>
            
        <?php elseif ($error): ?>
            <div class="message error">
                <?php echo $error; ?>
            </div>
            <a href="index.php" class="btn">Back to Dashboard</a>
        <?php endif; ?>
    </div>
</body>
</html>
