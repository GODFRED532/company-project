<?php
require_once 'config/database.php';
session_start();

$salesHistory = [];
$analytics = [];
$message = '';
$error = '';

// Handle analytics calculation
try {
    $pdo = getDBConnection();
    
    // Get date range for analytics (default to current month)
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    
    // Get sales history with detailed information
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            DATE_FORMAT(s.sale_date, '%Y-%m-%d %h:%i %p') AS formatted_date,
            COUNT(si.id) as item_count
        FROM sales s 
        LEFT JOIN sale_items si ON s.id = si.sale_id 
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
        GROUP BY s.id 
        ORDER BY s.sale_date DESC
        LIMIT 100
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $salesHistory = $stmt->fetchAll();
    
    // Calculate analytics
    $analyticsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sales,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(AVG(total_amount), 0) as average_sale,
            COALESCE(MAX(total_amount), 0) as highest_sale,
            COALESCE(MIN(total_amount), 0) as lowest_sale
        FROM sales s 
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
    ");
    $analyticsStmt->execute([$dateFrom, $dateTo]);
    $analytics = $analyticsStmt->fetch();
    
} catch (Exception $e) {
    $error = "Failed to load sales history: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - Mining Equipment Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            color: #2c3e50;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 300;
            margin-bottom: 1rem;
        }
        
        .nav-menu {
            display: flex;
            gap: 1rem;
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
        
        .back-btn {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .card h2 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        
        .form-group input {
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 1rem;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #2980b9, #1f4e79);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60, #229954);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }
        
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
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
        
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
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
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .results-table th,
        .results-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .results-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .results-table tr:hover {
            background: #f8f9fa;
        }
        
        .right {
            text-align: right;
        }
        
        .center {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div>
                <h1>📊 Sales History & Analytics</h1>
                <div class="nav-menu">
                    <a href="index.php" class="nav-item">🏠 Dashboard</a>
                    <a href="pos.php" class="nav-item">💰 Point of Sale</a>
                    <a href="inventory.php" class="nav-item">📦 Inventory</a>
                    <a href="receipts.php" class="nav-item">🧾 Receipt Search</a>
                    <a href="sales_history.php" class="nav-item">📊 Sales History</a>
                </div>
            </div>
            <div>
                <a href="index.php" class="back-btn">← Back to Dashboard</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card">
            <h2>🔍 Filter Sales History</h2>
            <form method="get" class="filters-grid">
                <div class="form-group">
                    <label for="date_from">From Date:</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_to">To Date:</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">🔍 Apply Filters</button>
                </div>
            </form>
            
            <div style="margin-top: 1rem;">
                <a href="sales_history.php" class="btn btn-warning">🔄 Clear Filters</a>
                <a href="test_sales_history.php" class="btn btn-success">🔧 Test Database</a>
            </div>
        </div>

        <!-- Analytics Overview -->
        <div class="card">
            <h2>📈 Sales Analytics</h2>
            <div class="analytics-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($analytics['total_sales'] ?? 0); ?></div>
                    <div class="stat-label">Total Sales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">$<?php echo number_format($analytics['total_revenue'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">$<?php echo number_format($analytics['average_sale'] ?? 0, 2); ?></div>
                    <div class="stat-label">Average Sale</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">$<?php echo number_format($analytics['highest_sale'] ?? 0, 2); ?></div>
                    <div class="stat-label">Highest Sale</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">$<?php echo number_format($analytics['lowest_sale'] ?? 0, 2); ?></div>
                    <div class="stat-label">Lowest Sale</div>
                </div>
            </div>
        </div>

        <!-- Sales History -->
        <div class="card">
            <h2>📋 Sales History (<?php echo count($salesHistory); ?> records)</h2>
            
            <?php if (empty($salesHistory)): ?>
                <div class="message warning">
                    No sales found for the selected date range. 
                    <br><br>
                    <strong>Possible reasons:</strong>
                    <ul style="margin-top: 1rem;">
                        <li>No sales have been made yet</li>
                        <li>Date range doesn't include any sales</li>
                        <li>Database connection issue</li>
                    </ul>
                    <br>
                    <a href="pos.php" class="btn btn-success">💰 Make a Sale</a>
                    <a href="test_sales_history.php" class="btn btn-warning">🔧 Test Database</a>
                </div>
            <?php else: ?>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Sale Number</th>
                            <th>Date & Time</th>
                            <th>Customer</th>
                            <th class="right">Amount</th>
                            <th class="center">Items</th>
                            <th>Staff</th>
                            <th class="center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salesHistory as $sale): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($sale['sale_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($sale['formatted_date']); ?></td>
                            <td><?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in Customer'); ?></td>
                            <td class="right"><strong>$<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                            <td class="center"><?php echo $sale['item_count']; ?></td>
                            <td><?php echo htmlspecialchars($sale['staff_name']); ?></td>
                            <td class="center">
                                <a href="receipt.php?id=<?php echo $sale['id']; ?>&from_search=1" class="btn btn-success" style="padding: 0.5rem 1rem; font-size: 0.9rem;">View</a>
                                <a href="receipt.php?id=<?php echo $sale['id']; ?>&from_search=1&print=1" class="btn btn-warning" style="padding: 0.5rem 1rem; font-size: 0.9rem;">Print</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Set default date range if not specified
        document.addEventListener('DOMContentLoaded', function() {
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');
            
            if (!dateFrom.value) {
                dateFrom.value = '<?php echo date('Y-m-01'); ?>';
            }
            if (!dateTo.value) {
                dateTo.value = '<?php echo date('Y-m-d'); ?>';
            }
        });
    </script>
</body>
</html>