<?php
require_once 'config/database.php';

try {
    $pdo = getDBConnection();
    
    // Clear all products (sample data)
    $stmt = $pdo->prepare("DELETE FROM products");
    $stmt->execute();
    
    // Clear all sales and sale items
    $stmt = $pdo->prepare("DELETE FROM sale_items");
    $stmt->execute();
    
    $stmt = $pdo->prepare("DELETE FROM sales");
    $stmt->execute();
    
    echo "✅ Sample data cleared successfully!<br>";
    echo "Your database is now clean and ready for your own products.<br>";
    echo "<a href='inventory.php'>Go to Inventory Management</a>";
    
} catch (Exception $e) {
    echo "❌ Error clearing data: " . $e->getMessage();
}
?>




