<?php
require_once __DIR__ . '/config/database.php';

// Basic validation: accept numeric id or sale_number
$saleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$saleNumber = isset($_GET['sale_number']) ? trim($_GET['sale_number']) : '';
$autoPrint = isset($_GET['print']) && ($_GET['print'] === '1' || $_GET['print'] === 'true');

// Handle form submission for customer details
$customerName = '';
$customerAddress = '';
$lpoNumber = '';
$showReceipt = false;

// Check if we're coming from receipt search (view mode)
$fromSearch = isset($_GET['from_search']) && $_GET['from_search'] === '1';

// Always show receipt - customer info is now handled in POS
$showReceipt = true;

// Get customer information from database
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT customer_name, customer_address, lpo_number, staff_name FROM sales WHERE id = ?");
    $stmt->execute([$saleId]);
    $customerData = $stmt->fetch();
    
    if ($customerData) {
        $customerName = $customerData['customer_name'] ?: 'Customer';
        $customerAddress = $customerData['customer_address'] ?: 'Address not provided';
        $lpoNumber = $customerData['lpo_number'] ?: '';
        $staffName = $customerData['staff_name'] ?: 'Staff';
    } else {
        $customerName = 'Customer';
        $customerAddress = 'Address not provided';
        $lpoNumber = '';
        $staffName = 'Staff';
    }
} catch (Exception $e) {
    $customerName = 'Customer';
    $customerAddress = 'Address not provided';
    $lpoNumber = '';
    $staffName = 'Staff';
}

try {
	$pdo = getDBConnection();

	if ($saleId > 0) {
		$stmt = $pdo->prepare("SELECT s.*, DATE_FORMAT(s.sale_date, '%Y-%m-%d %h:%i %p') AS formatted_date FROM sales s WHERE s.id = ?");
		$stmt->execute([$saleId]);
	} elseif ($saleNumber !== '') {
		$stmt = $pdo->prepare("SELECT s.*, DATE_FORMAT(s.sale_date, '%Y-%m-%d %h:%i %p') AS formatted_date FROM sales s WHERE s.sale_number = ?");
		$stmt->execute([$saleNumber]);
	} else {
		http_response_code(400);
		echo 'Invalid request: supply id or sale_number';
		exit;
	}
	$sale = $stmt->fetch();
	if (!$sale) {
		http_response_code(404);
		echo 'Sale not found';
		exit;
	}

	// Use the actual sale id from the fetched sale record to load items
	$actualSaleId = (int)$sale['id'];
	$stmt = $pdo->prepare("SELECT si.*, p.product_name, COALESCE(si.unit_type, 'piece') AS unit_type FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = ? ORDER BY si.id ASC");
	$stmt->execute([$actualSaleId]);
	$items = $stmt->fetchAll();

	// Items are loaded into $items and used below for rendering the receipt

} catch (Exception $e) {
	http_response_code(500);
	echo 'Error: ' . htmlspecialchars($e->getMessage());
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Receipt - <?php echo htmlspecialchars($sale['sale_number']); ?></title>
	<style>
		@media print { 
			.no-print { display:none !important } 
			@page { margin: 0.5cm; size: A4; }
		}
		body { 
			font-family: 'Arial', sans-serif; 
			font-size: 14px; 
			max-width: 210mm; 
			margin: 0 auto; 
			padding: 20px;
			background: white;
			color: #333;
			line-height: 1.4;
		}
		.center { text-align: center; }
		.muted { color: #666; font-size: 12px; }
		table { 
			width: 100%; 
			border-collapse: collapse; 
			margin: 15px 0;
		}
		th, td { 
			padding: 8px 4px; 
			text-align: left;
			border-bottom: 1px solid #eee;
		}
		th {
			background-color: #f8f9fa;
			font-weight: bold;
			font-size: 13px;
		}
		.right { text-align: right; }
		.divider { 
			border-top: 2px dashed #333; 
			margin: 20px 0; 
		}
		.total { 
			font-weight: bold; 
			font-size: 16px; 
		}
		.btn { 
			display: inline-block; 
			background: #3498db; 
			color: #fff; 
			padding: 10px 15px; 
			text-decoration: none; 
			border-radius: 5px;
			margin: 5px;
		}
		.company-header {
			margin-bottom: 30px;
		}
		.company-name {
			font-size: 28px;
			font-weight: bold;
			color: #0066cc;
			margin-bottom: 8px;
			letter-spacing: 1px;
		}
		.company-details {
			font-size: 13px;
			color: #0066cc;
			line-height: 1.3;
		}
		.invoice-header {
			text-align: right;
			margin-bottom: 20px;
		}
		.invoice-title {
			font-size: 18px;
			font-weight: bold;
			color: #0066cc;
			margin-bottom: 5px;
		}
		.invoice-number {
			font-size: 16px;
			color: #0066cc;
			margin-bottom: 5px;
		}
		.invoice-date {
			font-size: 14px;
			color: #0066cc;
		}
		.customer-section {
			margin: 20px 0;
			display: flex;
			justify-content: space-between;
		}
		.customer-info {
			flex: 1;
		}
		.order-info {
			width: 200px;
		}
		.field-label {
			font-weight: bold;
			margin-bottom: 5px;
		}
		.field-line {
			border-bottom: 1px dashed #333;
			height: 25px;
			margin-bottom: 15px;
		}
		.items-table {
			margin: 20px 0;
		}
		.items-table th {
			background-color: #f0f0f0;
			padding: 10px 5px;
			font-weight: bold;
			border-bottom: 2px solid #333;
		}
		.items-table td {
			padding: 8px 5px;
			border-bottom: 1px solid #ddd;
		}
		.footer-section {
			margin-top: 30px;
			display: flex;
			justify-content: space-between;
			align-items: flex-end;
		}
		.return-policy {
			font-size: 12px;
			margin-bottom: 20px;
		}
		.signatures {
			display: flex;
			justify-content: space-between;
			margin-top: 20px;
		}
		.signature-field {
			text-align: center;
			font-size: 12px;
		}
		.total-section {
			text-align: right;
		}
		.total-label {
			font-weight: bold;
			font-size: 16px;
			margin-bottom: 5px;
		}
		.total-amount {
			font-weight: bold;
			font-size: 20px;
			color: #0066cc;
		}
		.thank-you {
			text-align: center;
			margin-top: 30px;
		}
		.thank-you-text {
			font-weight: bold;
			font-size: 16px;
			margin-bottom: 10px;
		}
		.heart-icon {
			font-size: 18px;
			color: #ff6b6b;
		}
	</style>
	<script>
		function doPrint() { window.print(); }
		<?php if ($autoPrint): ?>
		window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 300); });
		<?php endif; ?>
	</script>
</head>
<body>
	<!-- Receipt Display -->
	<div class="no-print" style="text-align:center;margin-bottom:10px">
		<button onclick="doPrint()" class="btn">🖨️ Print Receipt</button>
		<?php if ($fromSearch): ?>
			<a href="receipts.php" class="btn" style="background:#6c757d;margin-left:8px">↩️ Back to Search</a>
		<?php else: ?>
		<a href="pos.php" class="btn" style="background:#6c757d;margin-left:8px">↩️ Back to POS</a>
		<?php endif; ?>
	</div>
	
	<?php if ($fromSearch): ?>
	<div style="background: #e8f4fd; border: 1px solid #3498db; border-radius: 8px; padding: 15px; margin-bottom: 20px; text-align: center;">
		<strong>📋 Receipt from Search Results</strong><br>
		<small>This receipt was found through the search system.</small>
	</div>
	<?php endif; ?>

	<!-- Professional Header - Matching Template -->
	<div style="margin-bottom: 30px;">
		<!-- Company Information (Left Side) -->
		<div style="float: left; width: 60%;">
			<div style="font-size: 32px; font-weight: bold; color: #0066cc; margin-bottom: 8px; letter-spacing: 1px;">
				STEVE SANTOS VENTURES
			</div>
			<div style="font-size: 14px; color: #0066cc; margin-bottom: 4px;">
				Dealers In: General Spare Parts, Fan Belts, Bolt & Nuts And Small Scale Products
			</div>
			<div style="font-size: 14px; color: #0066cc; margin-bottom: 4px;">
				TEL: 0244-478001 / 0243-315287 / 0557-885800
			</div>
			<div style="font-size: 14px; color: #0066cc;">
				OPP. SHELL FILLING STATION TARKWA, W/R
			</div>
		</div>
		
		<!-- Invoice Details (Right Side) -->
		<div style="float: right; width: 35%; text-align: right;">
			<div style="font-size: 20px; font-weight: bold; color: #0066cc; margin-bottom: 8px;">
				INVOICE
			</div>
			<div style="font-size: 18px; color: #0066cc; margin-bottom: 8px;">
				<?php echo htmlspecialchars($sale['sale_number']); ?>
			</div>
			<div style="font-size: 14px; color: #0066cc;">
				Date: <?php echo htmlspecialchars($sale['formatted_date']); ?>
			</div>
		</div>
		
		<!-- Clear floats -->
		<div style="clear: both;"></div>
	</div>

	<!-- Customer Information Section - With Filled Details -->
	<div style="margin: 20px 0; display: flex; justify-content: space-between;">
		<!-- Left Side - Customer Details -->
		<div style="flex: 1; margin-right: 20px;">
			<div style="font-weight: bold; margin-bottom: 5px;">NAME:</div>
			<div style="border-bottom: 1px solid #333; height: 25px; margin-bottom: 15px; padding: 5px 0; font-weight: 500;">
				<?php echo htmlspecialchars($customerName); ?>
		</div>
			<div style="font-weight: bold; margin-bottom: 5px;">ADDRESS:</div>
			<div style="border-bottom: 1px solid #333; height: 25px; padding: 5px 0; font-weight: 500;">
				<?php echo htmlspecialchars($customerAddress); ?>
		</div>
	</div>

		<!-- Right Side - Order Details -->
		<div style="width: 200px;">
			<div style="font-weight: bold; margin-bottom: 5px;">L.P.O No:</div>
			<div style="border-bottom: 1px solid #333; height: 25px; margin-bottom: 15px; padding: 5px 0; font-weight: 500;">
				<?php echo htmlspecialchars($lpoNumber ?: 'N/A'); ?>
			</div>
			<div style="font-weight: bold; margin-bottom: 5px;">Staff:</div>
			<div style="border-bottom: 1px solid #333; height: 25px; padding: 5px 0; font-weight: 500;">
				<?php echo htmlspecialchars($staffName); ?>
			</div>
		</div>
	</div>

	<?php if ($showReceipt): ?>
	<div class="divider"></div>

	<!-- Items Table -->
	<table class="items-table">
		<thead>
			<tr>
				<th style="width:10%">QTY.</th>
				<th style="width:56%">DESCRIPTION</th>
				<th style="width:17%" class="right">UNIT PRICE</th>
				<th style="width:17%" class="right">AMOUNT GHC</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($items as $it): ?>
			<tr>
				<td class="right"><?php echo htmlspecialchars($it['quantity_sold']); ?></td>
				<td><?php echo htmlspecialchars($it['product_name']); ?></td>
				<td class="right"><?php echo number_format($it['unit_price'], 2); ?></td>
				<td class="right"><?php echo number_format($it['total_price'], 2); ?></td>
			</tr>
			<?php endforeach; ?>
			<!-- Fill remaining empty rows for professional look -->
			<?php for ($r = 0; $r < max(0, 8 - count($items)); $r++): ?>
			<tr>
				<td class="right">&nbsp;</td>
				<td>&nbsp;</td>
				<td class="right">&nbsp;</td>
				<td class="right">&nbsp;</td>
			</tr>
			<?php endfor; ?>
		</tbody>
	</table>

	<div class="divider"></div>

	<!-- Professional Footer -->
	<div class="footer-section">
		<div style="width:50%;">
			<div class="return-policy">Goods sold are not returnable</div>
			<div class="signatures">
				<div class="signature-field">
					<div>Customer's</div>
					<div>Sign.</div>
				</div>
				<div class="signature-field">
					<div>Manager's</div>
					<div>Sign.</div>
				</div>
			</div>
		</div>
		<div class="total-section">
			<div class="total-label">TOTAL GHC</div>
			<div class="total-amount">GHC <?php echo number_format($sale['total_amount'], 2); ?></div>
		</div>
	</div>

	<!-- Thank you message -->
	<div class="thank-you">
		<div class="thank-you-text">THANK YOU</div>
		<div class="heart-icon">❤️</div>
	</div>
	<?php endif; ?>

</body>
</html>
