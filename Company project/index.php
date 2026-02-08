<?php
require_once 'config/database.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mining Equipment Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 300;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .nav-menu {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .nav-item {
            background: rgba(255,255,255,0.2);
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .nav-item:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-2px);
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }
        
        .card p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        
        .btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            cursor: pointer;
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
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #e67e22, #d35400);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏭 Mining Equipment Management System</h1>
        <div class="nav-menu">
            <a href="index.php" class="nav-item">🏠 Dashboard</a>
            <a href="pos.php" class="nav-item">💰 Point of Sale</a>
            <a href="inventory.php" class="nav-item">📦 Inventory</a>
            <a href="products.php" class="nav-item">🔧 Manage Products</a>
            <a href="sales_history.php" class="nav-item">📊 Sales History</a>
            <a href="receipts.php" class="nav-item">🧾 Receipt Search</a>
        </div>
    </div>

    <div class="container">
        <h2 style="color: #2c3e50; margin-bottom: 2rem;">Dashboard Overview</h2>
        
        <div class="stats">
            <?php
            try {
                $pdo = getDBConnection();
                
                // Get total products
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
                $totalProducts = $stmt->fetch()['total'];
                
                // Get total sales today
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM sales WHERE DATE(sale_date) = CURDATE()");
                $todaySales = $stmt->fetch()['total'];
                
                // Get low stock items
                $stmt = $pdo->query("
                    SELECT COUNT(*) as total FROM products 
                    WHERE (container_quantity * pieces_per_container) <= minimum_stock
                ");
                $lowStock = $stmt->fetch()['total'];
                
                // Get total revenue today
                $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(sale_date) = CURDATE()");
                $todayRevenue = $stmt->fetch()['total'];
                
            } catch (Exception $e) {
                $totalProducts = 0;
                $todaySales = 0;
                $lowStock = 0;
                $todayRevenue = 0;
            }
            ?>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalProducts; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $todaySales; ?></div>
                <div class="stat-label">Sales Today</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $lowStock; ?></div>
                <div class="stat-label">Low Stock Items</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($todayRevenue, 2); ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
        </div>

        <div class="dashboard">
            <div class="card">
                <h3>💰 Point of Sale</h3>
                <p>Process customer purchases quickly and efficiently. Add multiple items to one transaction and generate receipts.</p>
                <a href="pos.php" class="btn">Start New Sale</a>
            </div>
            
            <div class="card">
                <h3>📦 Inventory Management</h3>
                <p>View current stock levels, check item quantities, and monitor low stock alerts for timely restocking.</p>
                <a href="inventory.php" class="btn btn-success">View Inventory</a>
            </div>
            
            <div class="card">
                <h3>🔧 Product Management</h3>
                <p>Add new products, update prices, manage supplier information, and set minimum stock levels.</p>
                <a href="products.php" class="btn btn-warning">Manage Products</a>
            </div>
            
            <div class="card">
                <h3>📊 Sales History</h3>
                <p>View detailed sales reports, track daily performance, and analyze sales trends over time.</p>
                <a href="sales_history.php" class="btn">View Sales</a>
            </div>
            
            <div class="card">
                <h3>🧾 Receipt Search</h3>
                <p>Search and reprint customer receipts by sale number, date, items, or amount for customer service.</p>
                <a href="receipts.php" class="btn">Search Receipts</a>
            </div>
            
            <div class="card">
                <h3>⚙️ System Setup</h3>
                <p>Run the database setup script to create tables and insert sample data for testing the system.</p>
                <a href="setup.php" class="btn btn-warning">Setup Database</a>
            </div>
        </div>
    </div>
</body>
</html>

