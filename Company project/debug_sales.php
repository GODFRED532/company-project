<?php
// Debug version of sales history to identify issues
echo "<h1>Sales History Debug</h1>";

// Test 1: Check if config file exists
echo "<h2>1. Testing config file...</h2>";
if (file_exists('config/database.php')) {
    echo "✅ config/database.php exists<br>";
} else {
    echo "❌ config/database.php NOT found<br>";
    exit;
}

// Test 2: Test database connection
echo "<h2>2. Testing database connection...</h2>";
try {
    require_once 'config/database.php';
    $pdo = getDBConnection();
    echo "✅ Database connection successful<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    exit;
}

// Test 3: Check if tables exist
echo "<h2>3. Testing database tables...</h2>";
$tables = ['products', 'sales', 'sale_items'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Table '$table' exists<br>";
        } else {
            echo "❌ Table '$table' does NOT exist<br>";
        }
    } catch (Exception $e) {
        echo "❌ Error checking table '$table': " . $e->getMessage() . "<br>";
    }
}

// Test 4: Check if there's any sales data
echo "<h2>4. Testing sales data...</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sales");
    $result = $stmt->fetch();
    echo "✅ Found " . $result['count'] . " sales records<br>";
    
    if ($result['count'] > 0) {
        $stmt = $pdo->query("SELECT * FROM sales ORDER BY sale_date DESC LIMIT 3");
        $sales = $stmt->fetchAll();
        echo "<h3>Recent Sales:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Sale Number</th><th>Date</th><th>Amount</th><th>Customer</th></tr>";
        foreach ($sales as $sale) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($sale['sale_number']) . "</td>";
            echo "<td>" . htmlspecialchars($sale['sale_date']) . "</td>";
            echo "<td>$" . number_format($sale['total_amount'], 2) . "</td>";
            echo "<td>" . htmlspecialchars($sale['customer_name'] ?: 'Walk-in') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "⚠️ No sales data found. Make some sales first!<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking sales data: " . $e->getMessage() . "<br>";
}

// Test 5: Test the actual sales history query
echo "<h2>5. Testing sales history query...</h2>";
try {
    $dateFrom = date('Y-m-01');
    $dateTo = date('Y-m-d');
    
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
        LIMIT 5
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $results = $stmt->fetchAll();
    
    echo "✅ Sales history query successful! Found " . count($results) . " records.<br>";
    
    if (count($results) > 0) {
        echo "<h3>Sales History Results:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Sale Number</th><th>Date</th><th>Amount</th><th>Items</th><th>Customer</th></tr>";
        foreach ($results as $sale) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($sale['sale_number']) . "</td>";
            echo "<td>" . htmlspecialchars($sale['formatted_date']) . "</td>";
            echo "<td>$" . number_format($sale['total_amount'], 2) . "</td>";
            echo "<td>" . $sale['item_count'] . "</td>";
            echo "<td>" . htmlspecialchars($sale['customer_name'] ?: 'Walk-in') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "❌ Sales history query failed: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h2>Next Steps:</h2>";
echo "<p><a href='sales_history.php'>Try Sales History Page</a></p>";
echo "<p><a href='pos.php'>Make a Sale</a></p>";
echo "<p><a href='index.php'>Back to Dashboard</a></p>";
?>
