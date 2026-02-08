<?php
require_once 'config/database.php';
session_start();

$searchResults = [];
$searchQuery = '';
$searchType = 'sale_number';
$message = '';
$error = '';

// Handle search
if ($_POST && isset($_POST['search_query'])) {
    $searchQuery = trim($_POST['search_query']);
    $searchType = $_POST['search_type'] ?? 'sale_number';
    
    if (!empty($searchQuery)) {
        try {
            $pdo = getDBConnection();
            
            switch ($searchType) {
                case 'sale_number':
                    $stmt = $pdo->prepare("
                        SELECT s.*, 
                               DATE_FORMAT(s.sale_date, '%Y-%m-%d %h:%i %p') AS formatted_date,
                               COUNT(si.id) as item_count
                        FROM sales s 
                        LEFT JOIN sale_items si ON s.id = si.sale_id 
                        WHERE s.sale_number LIKE ? 
                        GROUP BY s.id 
                        ORDER BY s.sale_date DESC
                    ");
                    $stmt->execute(['%' . $searchQuery . '%']);
                    break;
                    
                case 'date':
                    $stmt = $pdo->prepare("
                        SELECT s.*, 
                               DATE_FORMAT(s.sale_date, '%Y-%m-%d %h:%i %p') AS formatted_date,
                               COUNT(si.id) as item_count
                        FROM sales s 
                        LEFT JOIN sale_items si ON s.id = si.sale_id 
                        WHERE DATE(s.sale_date) = ? 
                        GROUP BY s.id 
                        ORDER BY s.sale_date DESC
                    ");
                    $stmt->execute([$searchQuery]);
                    break;
                    
                case 'amount':
                    $stmt = $pdo->prepare("
                        SELECT s.*, 
                               DATE_FORMAT(s.sale_date, '%Y-%m-%d %h:%i %p') AS formatted_date,
                               COUNT(si.id) as item_count
                        FROM sales s 
                        LEFT JOIN sale_items si ON s.id = si.sale_id 
                        WHERE s.total_amount = ? 
                        GROUP BY s.id 
                        ORDER BY s.sale_date DESC
                    ");
                    $stmt->execute([$searchQuery]);
                    break;
                    
                case 'product':
                    $stmt = $pdo->prepare("
                        SELECT DISTINCT s.*, 
                               DATE_FORMAT(s.sale_date, '%Y-%m-%d %h:%i %p') AS formatted_date,
                               COUNT(si.id) as item_count
                        FROM sales s 
                        JOIN sale_items si ON s.id = si.sale_id 
                        JOIN products p ON si.product_id = p.id 
                        WHERE p.product_name LIKE ? 
                        GROUP BY s.id 
                        ORDER BY s.sale_date DESC
                    ");
                    $stmt->execute(['%' . $searchQuery . '%']);
                    break;
            }
            
            $searchResults = $stmt->fetchAll();
            
            if (empty($searchResults)) {
                $message = "No receipts found matching your search criteria.";
            }
            
        } catch (Exception $e) {
            $error = "Search failed: " . $e->getMessage();
        }
    } else {
        $error = "Please enter a search term.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt Search - Mining Equipment Management</title>
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
        
        .container {
            max-width: 1200px;
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
        
        .search-form {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            gap: 1rem;
            align-items: end;
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
        
        .form-group select,
        .form-group input {
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group select:focus,
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
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
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
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .search-stats {
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
        
        .quick-search {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .quick-search-item {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .quick-search-item:hover {
            transform: translateY(-5px);
        }
        
        .quick-search-item h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .quick-search-item p {
            color: #666;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h1>🧾 Receipt Search</h1>
                <div class="nav-menu">
                    <a href="index.php" class="nav-item">🏠 Dashboard</a>
                    <a href="pos.php" class="nav-item">💰 Point of Sale</a>
                    <a href="inventory.php" class="nav-item">📦 Inventory</a>
                    <a href="receipts.php" class="nav-item">🧾 Receipt Search</a>
                </div>
            </div>
            <div>
                <a href="index.php" style="background: linear-gradient(135deg, #6c757d, #5a6268); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    ← Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message warning"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Search Form -->
        <div class="card">
            <h2>🔍 Search Receipts</h2>
            <form method="post" class="search-form">
                <div class="form-group">
                    <label for="search_type">Search By:</label>
                    <select name="search_type" id="search_type">
                        <option value="sale_number" <?php echo $searchType === 'sale_number' ? 'selected' : ''; ?>>Sale Number</option>
                        <option value="date" <?php echo $searchType === 'date' ? 'selected' : ''; ?>>Date (YYYY-MM-DD)</option>
                        <option value="amount" <?php echo $searchType === 'amount' ? 'selected' : ''; ?>>Amount</option>
                        <option value="product" <?php echo $searchType === 'product' ? 'selected' : ''; ?>>Product Name</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="search_query">Search Term:</label>
                    <input type="text" name="search_query" id="search_query" 
                           value="<?php echo htmlspecialchars($searchQuery); ?>" 
                           placeholder="Enter search term..." required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">🔍 Search</button>
                </div>
            </form>
        </div>

        <!-- Quick Search Options -->
        <div class="card">
            <h2>⚡ Quick Search</h2>
            <div class="quick-search">
                <div class="quick-search-item">
                    <h3>📅 Today's Sales</h3>
                    <p>View all receipts from today</p>
                    <a href="?search_type=date&search_query=<?php echo date('Y-m-d'); ?>" class="btn">View Today</a>
                </div>
                
                <div class="quick-search-item">
                    <h3>📊 Recent Sales</h3>
                    <p>View last 10 sales</p>
                    <a href="?recent=1" class="btn">View Recent</a>
                </div>
                
                <div class="quick-search-item">
                    <h3>💰 High Value</h3>
                    <p>Sales over GHC 100</p>
                    <a href="?high_value=1" class="btn">View High Value</a>
                </div>
            </div>
        </div>

        <!-- Search Results -->
        <?php if (!empty($searchResults)): ?>
        <div class="card">
            <h2>📋 Search Results (<?php echo count($searchResults); ?> found)</h2>
            
            <div class="search-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($searchResults); ?></div>
                    <div class="stat-label">Receipts Found</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">GHC <?php echo number_format(array_sum(array_column($searchResults, 'total_amount')), 2); ?></div>
                    <div class="stat-label">Total Value</div>
                </div>
            </div>
            
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Sale Number</th>
                        <th>Date</th>
                        <th class="right">Amount</th>
                        <th class="center">Items</th>
                        <th>Staff</th>
                        <th class="center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($searchResults as $sale): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($sale['sale_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($sale['formatted_date']); ?></td>
                        <td class="right"><strong>GHC <?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                        <td class="center"><?php echo $sale['item_count']; ?></td>
                        <td><?php echo htmlspecialchars($sale['staff_name']); ?></td>
                        <td class="center">
                            <a href="receipt.php?id=<?php echo $sale['id']; ?>&from_search=1" class="btn btn-success" style="padding: 0.5rem 1rem; font-size: 0.9rem;">View Receipt</a>
                            <a href="receipt.php?id=<?php echo $sale['id']; ?>&from_search=1&print=1" class="btn btn-warning" style="padding: 0.5rem 1rem; font-size: 0.9rem;">Print</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Handle Quick Search Requests -->
        <?php
        if (isset($_GET['recent'])) {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->query("
                    SELECT s.*, 
                           DATE_FORMAT(s.sale_date, '%Y-%m-%d %h:%i %p') AS formatted_date,
                           COUNT(si.id) as item_count
                    FROM sales s 
                    LEFT JOIN sale_items si ON s.id = si.sale_id 
                    GROUP BY s.id 
                    ORDER BY s.sale_date DESC 
                    LIMIT 10
                ");
                $searchResults = $stmt->fetchAll();
                $searchQuery = 'Recent Sales';
            } catch (Exception $e) {
                $error = "Failed to load recent sales: " . $e->getMessage();
            }
        }
        
        if (isset($_GET['high_value'])) {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("
                    SELECT s.*, 
                           DATE_FORMAT(s.sale_date, '%Y-%m-%d %h:%i %p') AS formatted_date,
                           COUNT(si.id) as item_count
                    FROM sales s 
                    LEFT JOIN sale_items si ON s.id = si.sale_id 
                    WHERE s.total_amount >= 100
                    GROUP BY s.id 
                    ORDER BY s.total_amount DESC
                ");
                $stmt->execute();
                $searchResults = $stmt->fetchAll();
                $searchQuery = 'High Value Sales (GHC 100+)';
            } catch (Exception $e) {
                $error = "Failed to load high value sales: " . $e->getMessage();
            }
        }
        ?>
    </div>
</body>
</html>
