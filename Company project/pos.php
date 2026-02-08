<?php
require_once 'config/database.php';
session_start();

// Ensure products table supports loose pieces for mixed container accounting
try {
    $pdo = getDBConnection();
    // Try to add column if it doesn't exist (ignore if it already exists)
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS loose_pieces INT NOT NULL DEFAULT 0");
} catch (Exception $e) {
    // Older MySQL may not support IF NOT EXISTS; fallback: check information_schema
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'loose_pieces'");
        $stmt->execute();
        $exists = (int)$stmt->fetch()['cnt'] > 0;
        if (!$exists) {
            $pdo->exec("ALTER TABLE products ADD COLUMN loose_pieces INT NOT NULL DEFAULT 0");
        }
    } catch (Exception $ignored) {
        // If this also fails, continue without breaking the page
    }
}

$error = '';
$success = '';
$createdSaleId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['items'])) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        // Parse items
        $items = json_decode($_POST['items'], true);
        if (!is_array($items) || count($items) === 0) {
            throw new Exception('No items provided.');
        }

        // Validate and compute total
        $totalAmount = 0.00;
        $preparedItems = [];

        foreach ($items as $line) {
            $productId = (int)($line['product_id'] ?? 0);
            $quantity = (int)($line['quantity'] ?? 0); // quantity in individual units (pieces)
            if ($productId <= 0 || $quantity <= 0) {
                throw new Exception('Invalid item entry.');
            }

            // Load product
            $stmt = $pdo->prepare("SELECT id, product_name, unit_price, container_unit, container_quantity, pieces_per_container, COALESCE(loose_pieces, 0) AS loose_pieces FROM products WHERE id = ? FOR UPDATE");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            if (!$product) {
                throw new Exception('Product not found.');
            }

            $piecesPerContainer = max(1, (int)$product['pieces_per_container']);
            $totalPiecesAvailable = ((int)$product['container_quantity'] * $piecesPerContainer) + (int)$product['loose_pieces'];
            if ($quantity > $totalPiecesAvailable) {
                throw new Exception($product['product_name'] . ' has only ' . $totalPiecesAvailable . ' available.');
            }

            $unitPrice = (float)$product['unit_price'];
            $lineTotal = $unitPrice * $quantity;
            $totalAmount += $lineTotal;

            // Calculate actual pieces to deduct from inventory
            $unitType = trim($line['unit_type'] ?? 'pieces');
            $piecesToDeduct = $quantity; // Default: quantity is in pieces
            
            // If selling by container, convert to pieces
            if (stripos($unitType, 'box') !== false || stripos($unitType, 'container') !== false || 
                stripos($unitType, 'sack') !== false || stripos($unitType, 'bundle') !== false) {
                $piecesToDeduct = $quantity * $piecesPerContainer;
            }
            
            $preparedItems[] = [
                'product_id' => $product['id'],
                'quantity' => $quantity, // Display quantity (what customer sees)
                'pieces_deducted' => $piecesToDeduct, // Actual pieces to deduct from inventory
                'unit_type' => $unitType,
                'unit_price' => $unitPrice,
                'total_price' => $lineTotal,
                'pieces_per_container' => $piecesPerContainer
            ];
        }

        // Get customer information from form
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerAddress = trim($_POST['customer_address'] ?? '');
        $lpoNumber = trim($_POST['lpo_number'] ?? '');
        $staffName = trim($_POST['staff_name'] ?? 'Staff');

        // Create sale with customer information
        $saleNumber = generateSaleNumber();
        $stmt = $pdo->prepare("INSERT INTO sales (sale_number, total_amount, staff_name, customer_name, customer_address, lpo_number) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$saleNumber, $totalAmount, $staffName, $customerName, $customerAddress, $lpoNumber]);
        $saleId = (int)$pdo->lastInsertId();

        // Insert sale items and update stock
        $stmtInsertItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity_sold, unit_type, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtSelectForUpdate = $pdo->prepare("SELECT id, container_quantity, pieces_per_container, COALESCE(loose_pieces,0) AS loose_pieces FROM products WHERE id = ? FOR UPDATE");
        $stmtUpdateStock = $pdo->prepare("UPDATE products SET container_quantity = ?, loose_pieces = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");

        foreach ($preparedItems as $pi) {
            $stmtInsertItem->execute([$saleId, $pi['product_id'], $pi['quantity'], $pi['unit_type'], $pi['unit_price'], $pi['total_price']]);

            // Recompute inventory using actual pieces deducted
            $stmtSelectForUpdate->execute([$pi['product_id']]);
            $p = $stmtSelectForUpdate->fetch();
            $ppc = max(1, (int)$p['pieces_per_container']);
            $currentTotalPieces = ((int)$p['container_quantity'] * $ppc) + (int)$p['loose_pieces'];
            $newTotalPieces = $currentTotalPieces - (int)$pi['pieces_deducted']; // Use pieces_deducted instead of quantity
            if ($newTotalPieces < 0) { $newTotalPieces = 0; }
            $newContainers = intdiv($newTotalPieces, $ppc);
            $newLoose = $newTotalPieces % $ppc;
            $stmtUpdateStock->execute([$newContainers, $newLoose, $pi['product_id']]);
        }

        $pdo->commit();
        $success = 'Sale completed. Sale Number: ' . htmlspecialchars($saleNumber);
        $createdSaleId = $saleId;
        
        // Redirect to receipt with auto-print
        header("Location: receipt.php?id=" . $saleId . "&print=1");
        exit;
    } catch (Exception $ex) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $ex->getMessage();
    }
}

// Load products for selection
$products = [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT id, product_name, unit_price, container_unit, container_quantity, pieces_per_container, COALESCE(loose_pieces,0) AS loose_pieces, individual_unit FROM products ORDER BY product_name ASC");
    $products = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $error ?: 'Failed to load products.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point of Sale</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f6fa; color: #2c3e50; margin: 0; }
        .header { background: linear-gradient(135deg,#2c3e50,#3498db); color:#fff; padding:1rem 2rem; display:flex; align-items:center; justify-content:space-between; }
        .header a { color:#fff; text-decoration:none; margin-right:1rem; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .row { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; }
        .card { background:#fff; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.08); padding:1.25rem; }
        .title { font-size:1.25rem; margin-bottom:1rem; }
        .field { margin-bottom: 0.75rem; }
        label { display:block; font-weight:600; margin-bottom:0.25rem; }
        select, input { width:100%; padding:0.6rem 0.75rem; border:1px solid #dcdde1; border-radius:8px; }
        .btn { background: linear-gradient(135deg,#3498db,#2980b9); color:#fff; border:none; padding:0.7rem 1rem; border-radius:8px; cursor:pointer; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-secondary { background: #7f8c8d; }
        table { width:100%; border-collapse: collapse; }
        th, td { text-align:left; padding:0.6rem; border-bottom:1px solid #ecf0f1; }
        .right { text-align:right; }
        .message { padding:0.75rem 1rem; border-radius:8px; margin-bottom:1rem; }
        .success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
        .inline { display:flex; gap:0.5rem; align-items:center; }
        .muted { color:#7f8c8d; font-size:0.9rem; }
    </style>
</head>
<body>
    <div class="header">
        <div><a href="index.php">🏠 Dashboard</a></div>
        <div style="display: flex; align-items: center; gap: 15px;">
        <div>💰 Point of Sale</div>
            <a href="index.php" style="background: linear-gradient(135deg, #6c757d, #5a6268); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                ← Back to Dashboard
            </a>
        </div>
    </div>
    <div class="container">
        <?php if ($success): ?>
            <div class="message success"><?php echo $success; ?>
                <?php if ($createdSaleId): ?>
                    <div class="muted">You can reprint this later from Sales History.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="row" style="display: flex; gap: 2rem;">
            <div class="card" style="width: 400px; flex-shrink: 0;">
                <div class="title" style="font-size: 1.5rem; margin-bottom: 1.5rem;">⚡ Quick Add Items</div>
                <div class="field" style="margin-bottom: 1.5rem;">
                    <label for="product" style="font-size: 16px; font-weight: bold; margin-bottom: 8px; display: block;">Product</label>
                    <select id="product" style="width: 100%; padding: 15px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; background: white;">
                        <option value="">-- Select a product --</option>
                        <?php foreach ($products as $p): 
                            $ppc = max(1, (int)$p['pieces_per_container']);
                            $totalPieces = ((int)$p['container_quantity'] * $ppc) + (int)$p['loose_pieces'];
                            $containers = intdiv($totalPieces, $ppc);
                            $loose = $totalPieces % $ppc;
                            $stockLabel = $ppc === 1
                                ? ($containers . ' ' . ($p['individual_unit'] ?: 'pieces'))
                                : ($containers . ' ' . ($p['container_unit'] ?: 'containers') . ($loose ? (' ' . $loose . ' pieces') : ''));
                        ?>
                            <option value="<?php echo (int)$p['id']; ?>"
                                data-unit_price="<?php echo (float)$p['unit_price']; ?>"
                                data-stock_total="<?php echo (int)$totalPieces; ?>"
                                data-stock_label="<?php echo htmlspecialchars($stockLabel); ?>"
                                data-individual_unit="<?php echo htmlspecialchars($p['individual_unit'] ?: 'Piece'); ?>"
                                data-pieces_per_container="<?php echo $ppc; ?>">
                                <?php echo htmlspecialchars($p['product_name']); ?> - $<?php echo number_format((float)$p['unit_price'], 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field inline" style="display: flex; gap: 15px; align-items: end; margin-bottom: 1.5rem;">
                    <div style="flex:1;">
                        <label for="quantity" style="font-size: 16px; font-weight: bold; margin-bottom: 8px; display: block;">Qty</label>
                        <input type="number" id="quantity" min="1" value="1" style="width: 100%; font-size: 20px; padding: 15px; border: 2px solid #ddd; border-radius: 8px; text-align: center; font-weight: bold;">
                    </div>
                    <div style="flex:1;">
                        <label for="unit_type" style="font-size: 16px; font-weight: bold; margin-bottom: 8px; display: block;">Unit</label>
                        <input type="text" id="unit_type" placeholder="pieces" style="width: 100%; font-size: 18px; padding: 15px; border: 2px solid #ddd; border-radius: 8px;" />
                    </div>
                    <div>
                        <button class="btn" id="addItemBtn" style="padding: 15px 25px; font-size: 18px; font-weight: bold; background: linear-gradient(135deg, #3498db, #2980b9);">➕ Add</button>
                    </div>
                </div>
                <div class="muted" id="stockInfo" style="font-size: 14px; padding: 10px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #3498db;">Select a product to view stock.</div>
            </div>

            <div class="card" style="flex: 1; min-width: 600px;">
                <div class="title" style="font-size: 1.5rem; margin-bottom: 1.5rem;">🧾 Receipt Preview</div>
                <div id="receiptPreview" style="background: white; border: 2px solid #ddd; border-radius: 8px; padding: 30px; min-height: 500px; font-family: 'Arial', sans-serif; font-size: 16px; max-width: 100%; overflow-x: auto;">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <div style="font-size: 24px; font-weight: bold; color: #0066cc;">STEVE SANTOS VENTURES</div>
                        <div style="font-size: 12px; color: #0066cc;">Dealers In: General Spare Parts, Fan Belts, Bolt & Nuts And Small Scale Products</div>
                        <div style="font-size: 12px; color: #0066cc;">TEL: 0244-478001 / 0243-315287 / 0557-885800</div>
                        <div style="font-size: 12px; color: #0066cc;">OPP. SHELL FILLING STATION TARKWA, W/R</div>
                    </div>
                    
                    <div style="border-top: 2px dashed #333; margin: 15px 0;"></div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 25px; gap: 30px;">
                        <div style="flex: 2;">
                            <div style="font-weight: bold; margin-bottom: 10px; font-size: 18px;">NAME:</div>
                            <input type="text" id="customerName" placeholder="Enter customer name" 
                                   style="width: 100%; border: 2px solid #ddd; border-radius: 8px; background: white; padding: 15px; font-family: 'Arial', sans-serif; font-size: 18px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <div style="font-weight: bold; margin-bottom: 10px; font-size: 18px;">ADDRESS:</div>
                            <input type="text" id="customerAddress" placeholder="Enter customer address" 
                                   style="width: 100%; border: 2px solid #ddd; border-radius: 8px; background: white; padding: 15px; font-family: 'Arial', sans-serif; font-size: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: bold; margin-bottom: 10px; font-size: 18px;">L.P.O No:</div>
                            <input type="text" id="lpoNumber" placeholder="Enter L.P.O number" 
                                   style="width: 100%; border: 2px solid #ddd; border-radius: 8px; background: white; padding: 15px; font-family: 'Arial', sans-serif; font-size: 18px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <div style="font-weight: bold; margin-bottom: 10px; font-size: 18px;">Staff:</div>
                            <input type="text" id="staffName" value="Staff" 
                                   style="width: 100%; border: 2px solid #ddd; border-radius: 8px; background: white; padding: 15px; font-family: 'Arial', sans-serif; font-size: 18px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        </div>
                    </div>
                    
                    <div style="border-top: 2px dashed #333; margin: 15px 0;"></div>
                    
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="width:15%; padding: 12px 8px; border-bottom: 2px solid #333; font-weight: bold; font-size: 16px;">QTY.</th>
                                <th style="width:40%; padding: 12px 8px; border-bottom: 2px solid #333; font-weight: bold; font-size: 16px;">DESCRIPTION</th>
                                <th style="width:20%; padding: 12px 8px; border-bottom: 2px solid #333; text-align: right; font-weight: bold; font-size: 16px;">UNIT PRICE</th>
                                <th style="width:25%; padding: 12px 8px; border-bottom: 2px solid #333; text-align: right; font-weight: bold; font-size: 16px;">AMOUNT GHC</th>
                        </tr>
                        </thead>
                        <tbody id="receiptItems">
                        <tr>
                                <td colspan="4" style="text-align: center; padding: 30px; color: #666; font-style: italic; font-size: 16px;">Add items to see receipt preview</td>
                        </tr>
                    </tbody>
                </table>

                    <div style="border-top: 2px dashed #333; margin: 15px 0;"></div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
                        <div style="flex: 1; margin-right: 30px;">
                            <div style="font-size: 14px; margin-bottom: 20px; color: #666;">Goods sold are not returnable</div>
                            <div style="display: flex; justify-content: space-between; margin-top: 30px;">
                                <div style="text-align: center; font-size: 14px; font-weight: bold;">
                                    <div>Customer's Sign.</div>
                                    <div style="border-bottom: 1px solid #333; height: 30px; margin-top: 5px;"></div>
                                </div>
                                <div style="text-align: center; font-size: 14px; font-weight: bold;">
                                    <div>Manager's Sign.</div>
                                    <div style="border-bottom: 1px solid #333; height: 30px; margin-top: 5px;"></div>
                                </div>
                            </div>
                        </div>
                        <div style="width: 200px; text-align: right;">
                            <div style="font-weight: bold; font-size: 18px; margin-bottom: 5px;">TOTAL GHC</div>
                            <div style="font-weight: bold; font-size: 24px; color: #0066cc;" id="receiptTotal">GHC 0.00</div>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <div style="font-weight: bold; font-size: 16px;">THANK YOU</div>
                        <div style="font-size: 18px;">🤝</div>
                    </div>
                </div>
                
                <div style="margin-top: 30px; text-align: center;">
                    <form id="checkoutForm" method="post" style="display: inline-block;">
                    <input type="hidden" name="items" id="itemsField" />
                        <input type="hidden" name="customer_name" id="customerNameField" />
                        <input type="hidden" name="customer_address" id="customerAddressField" />
                        <input type="hidden" name="lpo_number" id="lpoNumberField" />
                        <input type="hidden" name="staff_name" id="staffNameField" />
                        <button type="submit" class="btn" id="checkoutBtn" disabled style="padding: 20px 40px; font-size: 20px; font-weight: bold; background: linear-gradient(135deg, #27ae60, #229954); border-radius: 12px; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">🖨️ Complete Sale & Print</button>
                </form>
            </div>
        </div>
        </div>
    </div>

    <script>
        const productSelect = document.getElementById('product');
        const quantityInput = document.getElementById('quantity');
        const unitTypeInput = document.getElementById('unit_type');
        const addItemBtn = document.getElementById('addItemBtn');
        const stockInfo = document.getElementById('stockInfo');
        const receiptItems = document.getElementById('receiptItems');
        const receiptTotal = document.getElementById('receiptTotal');
        const itemsField = document.getElementById('itemsField');
        const checkoutBtn = document.getElementById('checkoutBtn');
        const customerName = document.getElementById('customerName');
        const customerAddress = document.getElementById('customerAddress');
        const lpoNumber = document.getElementById('lpoNumber');
        const staffName = document.getElementById('staffName');
        const customerNameField = document.getElementById('customerNameField');
        const customerAddressField = document.getElementById('customerAddressField');
        const lpoNumberField = document.getElementById('lpoNumberField');
        const staffNameField = document.getElementById('staffNameField');

        const cart = [];

        // Update hidden fields when customer information changes
        function updateCustomerFields() {
            customerNameField.value = customerName.value;
            customerAddressField.value = customerAddress.value;
            lpoNumberField.value = lpoNumber.value;
            staffNameField.value = staffName.value;
        }

        // Add event listeners to customer fields
        customerName.addEventListener('input', updateCustomerFields);
        customerAddress.addEventListener('input', updateCustomerFields);
        lpoNumber.addEventListener('input', updateCustomerFields);
        staffName.addEventListener('input', updateCustomerFields);

        productSelect.addEventListener('change', () => {
            const option = productSelect.selectedOptions[0];
            if (!option || !option.value) {
                stockInfo.textContent = 'Select a product to view available stock.';
                unitTypeInput.value = 'pieces';
                return;
            }
            const stockLabel = option.dataset.stock_label || '';
            const defaultUnit = option.dataset.individual_unit || 'pieces';
            
            stockInfo.textContent = 'Available: ' + stockLabel;
            unitTypeInput.value = defaultUnit;
        });

        // Auto-focus quantity input when product is selected
        productSelect.addEventListener('change', () => {
            if (productSelect.value) {
                quantityInput.focus();
                quantityInput.select();
            }
        });

        // Auto-focus add button when quantity is entered
        quantityInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                addItemBtn.click();
            }
        });

        addItemBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const option = productSelect.selectedOptions[0];
            if (!option || !option.value) { 
                alert('Please select a product'); 
                productSelect.focus();
                return; 
            }
            
            const productId = parseInt(option.value, 10);
            const productName = option.textContent.split(' - ')[0];
            const available = parseInt(option.dataset.stock_total || '0', 10);
            const qty = parseInt(quantityInput.value, 10);
            const unitType = unitTypeInput.value.trim() || 'pieces';
            const unitPrice = parseFloat(option.dataset.unit_price || '0');
            
            if (!qty || qty < 1) { 
                alert('Please enter a valid quantity'); 
                quantityInput.focus();
                return; 
            }

            // Calculate total pieces needed (considering unit type conversion)
            let totalPiecesNeeded = qty;
            let displayPrice = unitPrice;
            
            if (unitType.includes('box') || unitType.includes('container') || unitType.includes('sack') || unitType.includes('bundle')) {
                const piecesPerContainer = parseInt(option.dataset.pieces_per_container || '1', 10);
                totalPiecesNeeded = qty * piecesPerContainer;
                displayPrice = unitPrice * piecesPerContainer;
            }
            
            const currentPiecesInCart = cart.filter(i => i.product_id === productId).reduce((s, i) => s + (i.pieces_deducted || i.quantity), 0);
            if (currentPiecesInCart + totalPiecesNeeded > available) {
                alert(`Not enough stock. Need ${totalPiecesNeeded} pieces, but only ${available - currentPiecesInCart} available.`);
                return;
            }

            cart.push({ 
                product_id: productId, 
                name: productName, 
                quantity: qty, 
                pieces_deducted: totalPiecesNeeded,
                unit_price: displayPrice, 
                unit_type: unitType 
            });
            
            renderReceipt();
            
            // Clear form and focus on product selection for next item
            productSelect.value = '';
            quantityInput.value = '1';
            unitTypeInput.value = 'pieces';
            stockInfo.textContent = 'Select a product to view available stock.';
            productSelect.focus();
        });

        function renderReceipt() {
            if (cart.length === 0) {
                receiptItems.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #666;">Add items to see receipt preview</td></tr>';
                receiptTotal.textContent = 'GHC 0.00';
                checkoutBtn.disabled = true;
                return;
            }

            let total = 0;
            receiptItems.innerHTML = '';
            
            cart.forEach((item, idx) => {
                const lineTotal = item.quantity * item.unit_price;
                total += lineTotal;
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="padding: 5px; border-bottom: 1px solid #ddd; text-align: right;">${item.quantity}</td>
                    <td style="padding: 5px; border-bottom: 1px solid #ddd;">${escapeHtml(item.name)}</td>
                    <td style="padding: 5px; border-bottom: 1px solid #ddd; text-align: right;">${item.unit_price.toFixed(2)}</td>
                    <td style="padding: 5px; border-bottom: 1px solid #ddd; text-align: right;">${lineTotal.toFixed(2)}</td>
                `;
                receiptItems.appendChild(tr);
            });
            
            receiptTotal.textContent = 'GHC ' + total.toFixed(2);
            itemsField.value = JSON.stringify(cart.map(i => ({ product_id: i.product_id, quantity: i.quantity, unit_type: i.unit_type, unit_price: i.unit_price })));
            checkoutBtn.disabled = false;
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        // Focus on product selection when page loads
        window.addEventListener('load', () => {
            productSelect.focus();
        });
    </script>
</body>
</html>



