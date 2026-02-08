<?php
// Test script to check database connection and table structure
require_once 'config/database.php';

echo "<h2>Database Connection Test</h2>";

try {
    $pdo = getDBConnection();
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    
    // Check if tables exist
    $tables = ['products', 'sales', 'sale_items'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Table '$table' exists</p>";
            
            // Check columns for products table
            if ($table === 'products') {
                $stmt = $pdo->query("DESCRIBE products");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo "<p>Products table columns: " . implode(', ', $columns) . "</p>";
                
                if (!in_array('loose_pieces', $columns)) {
                    echo "<p style='color: orange;'>⚠️ Missing 'loose_pieces' column in products table</p>";
                    try {
                        $pdo->exec("ALTER TABLE products ADD COLUMN loose_pieces INT NOT NULL DEFAULT 0");
                        echo "<p style='color: green;'>✅ Added 'loose_pieces' column</p>";
                    } catch (Exception $e) {
                        echo "<p style='color: red;'>❌ Failed to add 'loose_pieces' column: " . $e->getMessage() . "</p>";
                    }
                }
            }
        } else {
            echo "<p style='color: red;'>❌ Table '$table' does not exist</p>";
        }
    }
    
    // Test sales history query
    echo "<h3>Testing Sales History Query</h3>";
    try {
        $stmt = $pdo->query("
            SELECT 
                s.*,
                DATE_FORMAT(s.sale_date, '%Y-%m-%d %h:%i %p') AS formatted_date,
                COUNT(si.id) as item_count
            FROM sales s 
            LEFT JOIN sale_items si ON s.id = si.sale_id 
            GROUP BY s.id 
            ORDER BY s.sale_date DESC
            LIMIT 5
        ");
        $results = $stmt->fetchAll();
        echo "<p style='color: green;'>✅ Sales history query successful! Found " . count($results) . " sales records.</p>";
        
        if (count($results) > 0) {
            echo "<h4>Sample Sales Data:</h4>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>Sale Number</th><th>Date</th><th>Amount</th><th>Customer</th><th>Staff</th></tr>";
            foreach ($results as $sale) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($sale['sale_number']) . "</td>";
                echo "<td>" . htmlspecialchars($sale['formatted_date']) . "</td>";
                echo "<td>$" . number_format($sale['total_amount'], 2) . "</td>";
                echo "<td>" . htmlspecialchars($sale['customer_name'] ?: 'Walk-in') . "</td>";
                echo "<td>" . htmlspecialchars($sale['staff_name']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>⚠️ No sales data found. Make some sales first!</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Sales history query failed: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='sales_history.php'>Try Sales History Page</a></p>";
echo "<p><a href='index.php'>Back to Dashboard</a></p>";
?>
