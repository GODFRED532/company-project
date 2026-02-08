<?php
require_once 'config/database.php';
session_start();

$message = '';
$error = '';
$products = [];

// Handle adding new product
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    try {
        $pdo = getDBConnection();
        
        $product_name = trim($_POST['product_name']);
        $container_unit = trim($_POST['container_unit']);
        $container_quantity = (int)$_POST['container_quantity'];
        $pieces_per_container = (int)$_POST['pieces_per_container'];
        $individual_unit = trim($_POST['individual_unit']);
        $model_size = trim($_POST['model_size']);
        $unit_price = (float)$_POST['unit_price'];
        $minimum_stock = (int)$_POST['minimum_stock'];
        $supplier_name = trim($_POST['supplier_name']);
        $supplier_contact = trim($_POST['supplier_contact']);
        $description = trim($_POST['description']);
        
        if (empty($product_name) || $unit_price <= 0) {
            throw new Exception('Product name and unit price are required.');
        }
        
        // Check for duplicate product (same name and model/size)
        $checkStmt = $pdo->prepare("
            SELECT id, product_name, model_size, container_quantity, loose_pieces 
            FROM products 
            WHERE LOWER(product_name) = LOWER(?) AND LOWER(COALESCE(model_size, '')) = LOWER(?)
        ");
        $checkStmt->execute([$product_name, $model_size ?: '']);
        $existingProduct = $checkStmt->fetch();
        
        if ($existingProduct) {
            // Product already exists - ask if user wants to add stock instead
            $existingId = $existingProduct['id'];
            $existingName = $existingProduct['product_name'];
            $existingModel = $existingProduct['model_size'] ?: 'No model';
            $existingContainers = (int)$existingProduct['container_quantity'];
            $existingLoose = (int)$existingProduct['loose_pieces'];
            
            // Calculate existing total pieces
            $existingPiecesPerContainer = max(1, (int)$pieces_per_container);
            $existingTotalPieces = ($existingContainers * $existingPiecesPerContainer) + $existingLoose;
            
            // Calculate new total pieces
            $newTotalPieces = ($container_quantity * $pieces_per_container) + 0; // No loose pieces for new stock
            
            $error = "Product '$existingName' ($existingModel) already exists!<br><br>
                     <strong>Current stock:</strong> $existingTotalPieces pieces<br>
                     <strong>You're trying to add:</strong> $newTotalPieces pieces<br><br>
                     <a href='inventory.php?merge_stock=$existingId&new_containers=$container_quantity&new_loose=0' 
                        class='btn btn-warning' style='margin-top:10px;'>
                        📦 Add Stock to Existing Product Instead
                     </a>";
        } else {
            // No duplicate found - proceed with normal insertion
            $stmt = $pdo->prepare("
                INSERT INTO products (
                    product_name, container_unit, container_quantity, pieces_per_container,
                    individual_unit, model_size, unit_price, minimum_stock,
                    supplier_name, supplier_contact, description
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $product_name, $container_unit, $container_quantity, $pieces_per_container,
                $individual_unit, $model_size, $unit_price, $minimum_stock,
                $supplier_name, $supplier_contact, $description
            ]);
            
            $message = "Product '$product_name' added successfully!";
        }
        
    } catch (Exception $e) {
        $error = "Failed to add product: " . $e->getMessage();
    }
}

// Handle merging stock when duplicate is detected
if (isset($_GET['merge_stock']) && isset($_GET['new_containers']) && isset($_GET['new_loose'])) {
    try {
        $pdo = getDBConnection();
        $productId = (int)$_GET['merge_stock'];
        $newContainers = (int)$_GET['new_containers'];
        $newLoose = (int)$_GET['new_loose'];
        
        // Get current stock
        $stmt = $pdo->prepare("SELECT container_quantity, loose_pieces, pieces_per_container, product_name FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if ($product) {
            $currentContainers = (int)$product['container_quantity'];
            $currentLoose = (int)$product['loose_pieces'];
            $piecesPerContainer = max(1, (int)$product['pieces_per_container']);
            
            // Calculate new totals
            $newContainerTotal = $currentContainers + $newContainers;
            $newLooseTotal = $currentLoose + $newLoose;
            
            // Update stock
            $updateStmt = $pdo->prepare("UPDATE products SET container_quantity = ?, loose_pieces = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->execute([$newContainerTotal, $newLooseTotal, $productId]);
            
            $message = "Stock successfully added to existing product '{$product['product_name']}'! " .
                      "Added: $newContainers containers + $newLoose pieces. " .
                      "New total: " . (($newContainerTotal * $piecesPerContainer) + $newLooseTotal) . " pieces.";
        }
        
    } catch (Exception $e) {
        $error = "Failed to merge stock: " . $e->getMessage();
    }
}

// Handle stock update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    try {
        $pdo = getDBConnection();
        $product_id = (int)$_POST['product_id'];
        $new_container_quantity = (int)$_POST['new_container_quantity'];
        $new_loose_pieces = (int)$_POST['new_loose_pieces'];
        
        $stmt = $pdo->prepare("UPDATE products SET container_quantity = ?, loose_pieces = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$new_container_quantity, $new_loose_pieces, $product_id]);
        
        $message = "Stock updated successfully!";
        
    } catch (Exception $e) {
        $error = "Failed to update stock: " . $e->getMessage();
    }
}

// Handle price update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_price') {
    try {
        $pdo = getDBConnection();
        $product_id = (int)$_POST['product_id'];
        $new_price = (float)$_POST['new_price'];
        
        if ($new_price <= 0) {
            throw new Exception('Price must be greater than 0.');
        }
        
        $stmt = $pdo->prepare("UPDATE products SET unit_price = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$new_price, $product_id]);
        
        $message = "Price updated successfully!";
        
    } catch (Exception $e) {
        $error = "Failed to update price: " . $e->getMessage();
    }
}

// Load products with stock calculations
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT 
            id, product_name, container_unit, container_quantity, 
            pieces_per_container, COALESCE(loose_pieces, 0) AS loose_pieces,
            individual_unit, model_size, unit_price, minimum_stock,
            supplier_name, supplier_contact, description
        FROM products 
        ORDER BY product_name ASC
    ");
    $products = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Failed to load products: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f6fa; color: #2c3e50; margin: 0; }
        .header { background: linear-gradient(135deg,#2c3e50,#3498db); color:#fff; padding:1rem 2rem; display:flex; align-items:center; justify-content:space-between; }
        .header a { color:#fff; text-decoration:none; margin-right:1rem; }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 1rem; }
        .card { background:#fff; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.08); padding:1.5rem; margin-bottom:2rem; }
        .title { font-size:1.5rem; margin-bottom:1rem; color:#2c3e50; }
        .message { padding:0.75rem 1rem; border-radius:8px; margin-bottom:1rem; }
        .success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .warning { background:#fff3cd; color:#856404; border:1px solid #ffeaa7; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .form-group { margin-bottom: 1rem; }
        label { display:block; font-weight:600; margin-bottom:0.25rem; }
        input, select, textarea { width:100%; padding:0.6rem 0.75rem; border:1px solid #dcdde1; border-radius:8px; }
        .btn { background: linear-gradient(135deg,#3498db,#2980b9); color:#fff; border:none; padding:0.7rem 1.5rem; border-radius:8px; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn:hover { background: linear-gradient(135deg,#2980b9,#1f4e79); }
        .btn-success { background: linear-gradient(135deg,#27ae60,#229954); }
        .btn-warning { background: linear-gradient(135deg,#f39c12,#e67e22); }
        .btn-danger { background: linear-gradient(135deg,#e74c3c,#c0392b); }
        table { width:100%; border-collapse: collapse; margin-top:1rem; }
        th, td { text-align:left; padding:0.75rem; border-bottom:1px solid #ecf0f1; }
        th { background:#f8f9fa; font-weight:600; }
        .right { text-align:right; }
        .center { text-align:center; }
        .low-stock { background:#fff5f5; }
        .out-of-stock { background:#ffeaea; color:#c0392b; font-weight:600; }
        .stock-ok { background:#f0fff4; }
        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
        .modal-content { background:#fff; margin:5% auto; padding:2rem; border-radius:10px; width:90%; max-width:600px; }
        .close { color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer; }
        .close:hover { color:#000; }
        .tabs { display:flex; border-bottom:2px solid #ecf0f1; margin-bottom:1rem; }
        .tab { padding:0.75rem 1.5rem; cursor:pointer; border-bottom:3px solid transparent; }
        .tab.active { border-bottom-color:#3498db; color:#3498db; font-weight:600; }
        .tab-content { display:none; }
        .tab-content.active { display:block; }
    </style>
</head>
<body>
    <div class="header">
        <div><a href="index.php">🏠 Dashboard</a> <a href="pos.php">💰 POS</a> <a href="products.php">🔧 Products</a></div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <div>📦 Inventory Management</div>
            <a href="index.php" style="background: linear-gradient(135deg, #6c757d, #5a6268); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                ← Back to Dashboard
            </a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="tabs">
            <div class="tab active" onclick="showTab('overview')">📊 Stock Overview</div>
            <div class="tab" onclick="showTab('add')">➕ Add Product</div>
            <div class="tab" onclick="showTab('update')">🔄 Update Stock</div>
            <div class="tab" onclick="showTab('price')">💰 Update Prices</div>
            <div class="tab" onclick="showTab('quickadd')">⚡ Quick Add Stock</div>
        </div>

        <!-- Stock Overview Tab -->
        <div id="overview" class="tab-content active">
            <div class="card">
                <div class="title">📦 Current Inventory</div>
                
                <?php if (empty($products)): ?>
                    <div class="message warning">No products found. Add some products to get started.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Model/Size</th>
                                <th class="right">Current Stock</th>
                                <th class="right">Unit Price</th>
                                <th class="right">Total Value</th>
                                <th class="center">Status</th>
                                <th class="center">Supplier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): 
                                $piecesPerContainer = max(1, (int)$product['pieces_per_container']);
                                $totalPieces = ((int)$product['container_quantity'] * $piecesPerContainer) + (int)$product['loose_pieces'];
                                $totalValue = $totalPieces * (float)$product['unit_price'];
                                
                                // Stock status
                                $stockStatus = 'ok';
                                $statusText = 'In Stock';
                                $rowClass = 'stock-ok';
                                
                                if ($totalPieces == 0) {
                                    $stockStatus = 'out';
                                    $statusText = 'Out of Stock';
                                    $rowClass = 'out-of-stock';
                                } elseif ($totalPieces <= (int)$product['minimum_stock']) {
                                    $stockStatus = 'low';
                                    $statusText = 'Low Stock';
                                    $rowClass = 'low-stock';
                                }
                                
                                // Format stock display
                                if ($piecesPerContainer == 1) {
                                    $stockDisplay = $totalPieces . ' ' . ($product['individual_unit'] ?: 'pieces');
                                } else {
                                    $containers = intdiv($totalPieces, $piecesPerContainer);
                                    $loose = $totalPieces % $piecesPerContainer;
                                    $stockDisplay = $containers . ' ' . ($product['container_unit'] ?: 'containers');
                                    if ($loose > 0) {
                                        $stockDisplay .= ' ' . $loose . ' pieces';
                                    }
                                }
                            ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td><strong><?php echo htmlspecialchars($product['product_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($product['model_size'] ?: '-'); ?></td>
                                    <td class="right"><?php echo $stockDisplay; ?></td>
                                    <td class="right">$<?php echo number_format((float)$product['unit_price'], 2); ?></td>
                                    <td class="right">$<?php echo number_format($totalValue, 2); ?></td>
                                    <td class="center">
                                        <span style="padding:0.25rem 0.5rem; border-radius:4px; font-size:0.8rem; font-weight:600;
                                            <?php if ($stockStatus === 'out'): ?>background:#ffeaea;color:#c0392b;
                                            <?php elseif ($stockStatus === 'low'): ?>background:#fff3cd;color:#856404;
                                            <?php else: ?>background:#d4edda;color:#155724;<?php endif; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td class="center"><?php echo htmlspecialchars($product['supplier_name'] ?: '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top:1rem; padding:1rem; background:#f8f9fa; border-radius:8px;">
                        <strong>Stock Summary:</strong>
                        <?php
                        $totalProducts = count($products);
                        $outOfStock = 0;
                        $lowStock = 0;
                        $totalValue = 0;
                        
                        foreach ($products as $product) {
                            $piecesPerContainer = max(1, (int)$product['pieces_per_container']);
                            $totalPieces = ((int)$product['container_quantity'] * $piecesPerContainer) + (int)$product['loose_pieces'];
                            $totalValue += $totalPieces * (float)$product['unit_price'];
                            
                            if ($totalPieces == 0) {
                                $outOfStock++;
                            } elseif ($totalPieces <= (int)$product['minimum_stock']) {
                                $lowStock++;
                            }
                        }
                        ?>
                        Total Products: <?php echo $totalProducts; ?> | 
                        Out of Stock: <span style="color:#c0392b;font-weight:600;"><?php echo $outOfStock; ?></span> | 
                        Low Stock: <span style="color:#f39c12;font-weight:600;"><?php echo $lowStock; ?></span> | 
                        Total Inventory Value: <strong>$<?php echo number_format($totalValue, 2); ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add Product Tab -->
        <div id="add" class="tab-content">
            <div class="card">
                <div class="title">➕ Add New Product</div>
                <form method="post">
                    <input type="hidden" name="action" value="add_product">
                    <div class="grid">
                        <div>
                            <div class="form-group">
                                <label for="product_name">Product Name *</label>
                                <input type="text" id="product_name" name="product_name" required placeholder="e.g., Can Spray">
                            </div>
                            
                            <div class="form-group">
                                <label for="container_unit">Container Unit</label>
                                <input type="text" id="container_unit" name="container_unit" placeholder="e.g., Box, Sack, Gallon">
                            </div>
                            
                            <div class="form-group">
                                <label for="container_quantity">Initial Container Quantity</label>
                                <input type="number" id="container_quantity" name="container_quantity" min="0" value="0">
                            </div>
                            
                            <div class="form-group">
                                <label for="pieces_per_container">Pieces per Container</label>
                                <input type="number" id="pieces_per_container" name="pieces_per_container" min="1" value="1">
                            </div>
                            
                            <div class="form-group">
                                <label for="individual_unit">Individual Unit (What you sell)</label>
                                <input type="text" id="individual_unit" name="individual_unit" placeholder="e.g., Piece, Gallon">
                            </div>
                        </div>
                        
                        <div>
                            <div class="form-group">
                                <label for="model_size">Model/Size</label>
                                <input type="text" id="model_size" name="model_size" placeholder="e.g., Large, Model X123">
                            </div>
                            
                            <div class="form-group">
                                <label for="unit_price">Unit Price *</label>
                                <input type="number" id="unit_price" name="unit_price" min="0" step="0.01" required placeholder="0.00">
                            </div>
                            
                            <div class="form-group">
                                <label for="minimum_stock">Minimum Stock Level</label>
                                <input type="number" id="minimum_stock" name="minimum_stock" min="0" value="5">
                            </div>
                            
                            <div class="form-group">
                                <label for="supplier_name">Supplier Name</label>
                                <input type="text" id="supplier_name" name="supplier_name" placeholder="e.g., Industrial Supplies Ltd">
                            </div>
                            
                            <div class="form-group">
                                <label for="supplier_contact">Supplier Contact</label>
                                <input type="text" id="supplier_contact" name="supplier_contact" placeholder="Phone, email, etc.">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3" placeholder="Additional product details..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-success">Add Product</button>
                </form>
            </div>
        </div>

        <!-- Update Stock Tab -->
        <div id="update" class="tab-content">
            <div class="card">
                <div class="title">🔄 Update Stock Levels</div>
                <?php if (empty($products)): ?>
                    <div class="message warning">No products available to update.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Current Stock</th>
                                <th>New Container Qty</th>
                                <th>New Loose Pieces</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): 
                                $piecesPerContainer = max(1, (int)$product['pieces_per_container']);
                                $totalPieces = ((int)$product['container_quantity'] * $piecesPerContainer) + (int)$product['loose_pieces'];
                                
                                if ($piecesPerContainer == 1) {
                                    $stockDisplay = $totalPieces . ' pieces';
                                } else {
                                    $containers = intdiv($totalPieces, $piecesPerContainer);
                                    $loose = $totalPieces % $piecesPerContainer;
                                    $stockDisplay = $containers . ' ' . ($product['container_unit'] ?: 'containers');
                                    if ($loose > 0) {
                                        $stockDisplay .= ' ' . $loose . ' pieces';
                                    }
                                }
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($product['product_name']); ?></strong></td>
                                    <td><?php echo $stockDisplay; ?></td>
                                    <td>
                                        <input type="number" id="new_container_<?php echo $product['id']; ?>" 
                                               min="0" value="<?php echo (int)$product['container_quantity']; ?>" 
                                               style="width:80px;">
                                    </td>
                                    <td>
                                        <input type="number" id="new_loose_<?php echo $product['id']; ?>" 
                                               min="0" value="<?php echo (int)$product['loose_pieces']; ?>" 
                                               style="width:80px;">
                                    </td>
                                    <td>
                                        <button class="btn btn-warning" onclick="updateStock(<?php echo $product['id']; ?>)">Update</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Update Prices Tab -->
        <div id="price" class="tab-content">
            <div class="card">
                <div class="title">💰 Update Product Prices</div>
                <p style="color: #666; margin-bottom: 1.5rem;">Update the unit prices for your products. Changes will affect all future sales.</p>
                
                <?php if (empty($products)): ?>
                    <div class="message warning">No products available to update prices.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Model/Size</th>
                                <th>Current Price</th>
                                <th>New Price</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($product['product_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($product['model_size'] ?: '-'); ?></td>
                                    <td class="right"><strong>$<?php echo number_format((float)$product['unit_price'], 2); ?></strong></td>
                                    <td>
                                        <input type="number" id="new_price_<?php echo $product['id']; ?>" 
                                               min="0.01" step="0.01" value="<?php echo number_format((float)$product['unit_price'], 2); ?>" 
                                               style="width:100px;">
                                    </td>
                                    <td>
                                        <button class="btn btn-warning" onclick="updatePrice(<?php echo $product['id']; ?>)">Update Price</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Add Stock Tab -->
        <div id="quickadd" class="tab-content">
            <div class="card">
                <div class="title">⚡ Quick Add Stock to Existing Products</div>
                <p style="color: #666; margin-bottom: 1.5rem;">Quickly add stock to existing products without going through the full product creation process.</p>
                
                <?php if (empty($products)): ?>
                    <div class="message warning">No products available to add stock to.</div>
                <?php else: ?>
                    <div class="grid">
                        <?php foreach ($products as $product): 
                            $piecesPerContainer = max(1, (int)$product['pieces_per_container']);
                            $totalPieces = ((int)$product['container_quantity'] * $piecesPerContainer) + (int)$product['loose_pieces'];
                            
                            if ($piecesPerContainer == 1) {
                                $stockDisplay = $totalPieces . ' pieces';
                            } else {
                                $containers = intdiv($totalPieces, $piecesPerContainer);
                                $loose = $totalPieces % $piecesPerContainer;
                                $stockDisplay = $containers . ' ' . ($product['container_unit'] ?: 'containers');
                                if ($loose > 0) {
                                    $stockDisplay .= ' ' . $loose . ' pieces';
                                }
                            }
                        ?>
                            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                                <h4 style="margin: 0 0 0.5rem 0; color: #2c3e50;">
                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                    <?php if ($product['model_size']): ?>
                                        <span style="color: #666; font-weight: normal;">(<?php echo htmlspecialchars($product['model_size']); ?>)</span>
                                    <?php endif; ?>
                                </h4>
                                <p style="margin: 0.25rem 0; color: #666; font-size: 0.9rem;">
                                    <strong>Current Stock:</strong> <?php echo $stockDisplay; ?>
                                </p>
                                <p style="margin: 0.25rem 0; color: #666; font-size: 0.9rem;">
                                    <strong>Unit Price:</strong> $<?php echo number_format((float)$product['unit_price'], 2); ?>
                                </p>
                                
                                <div style="margin-top: 0.75rem;">
                                    <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.8rem; color: #666; margin-bottom: 0.25rem; font-weight: 600;">
                                                📦 Containers to Add
                                            </label>
                                            <input type="number" id="quick_containers_<?php echo $product['id']; ?>" 
                                                   placeholder="0" min="0" value="0" 
                                                   style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.8rem; color: #666; margin-bottom: 0.25rem; font-weight: 600;">
                                                🔢 Loose Pieces to Add
                                            </label>
                                            <input type="number" id="quick_loose_<?php echo $product['id']; ?>" 
                                                   placeholder="0" min="0" value="0" 
                                                   style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                    </div>
                                    <div style="text-align: center;">
                                        <button class="btn btn-success" onclick="quickAddStock(<?php echo $product['id']; ?>)" 
                                                style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                                            ➕ Add Stock
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        function updateStock(productId) {
            const newContainerQty = document.getElementById('new_container_' + productId).value;
            const newLoosePieces = document.getElementById('new_loose_' + productId).value;
            
            if (confirm('Update stock for this product?')) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_stock">
                    <input type="hidden" name="product_id" value="${productId}">
                    <input type="hidden" name="new_container_quantity" value="${newContainerQty}">
                    <input type="hidden" name="new_loose_pieces" value="${newLoosePieces}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function updatePrice(productId) {
            const newPrice = document.getElementById('new_price_' + productId).value;
            
            if (newPrice <= 0) {
                alert('Price must be greater than 0.');
                return;
            }
            
            if (confirm(`Update price to $${newPrice}?`)) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_price">
                    <input type="hidden" name="product_id" value="${productId}">
                    <input type="hidden" name="new_price" value="${newPrice}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function quickAddStock(productId) {
            const containers = document.getElementById('quick_containers_' + productId).value;
            const loose = document.getElementById('quick_loose_' + productId).value;
            
            if (containers == 0 && loose == 0) {
                alert('Please enter at least some containers or loose pieces to add.');
                return;
            }
            
            if (confirm(`Add ${containers} containers and ${loose} loose pieces to this product?`)) {
                // Redirect to merge stock functionality
                window.location.href = `inventory.php?merge_stock=${productId}&new_containers=${containers}&new_loose=${loose}`;
            }
        }
    </script>
</body>
</html>