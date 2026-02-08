<?php
// Database configuration for Mining Equipment Management System

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mining_equipment_db');

// Create database connection
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Generate unique sale number
function generateSaleNumber() {
    $prefix = 'SALE';
    $date = date('Ymd');
    $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return $prefix . $date . $random;
}

// Calculate total pieces in stock
function calculateTotalPieces($containerQuantity, $piecesPerContainer) {
    return $containerQuantity * $piecesPerContainer;
}

// Format stock display
function formatStockDisplay($containerQuantity, $piecesPerContainer) {
    if ($piecesPerContainer == 1) {
        return $containerQuantity . ' ' . ($containerQuantity == 1 ? 'piece' : 'pieces');
    }
    
    $totalPieces = $containerQuantity * $piecesPerContainer;
    $fullContainers = intval($totalPieces / $piecesPerContainer);
    $remainingPieces = $totalPieces % $piecesPerContainer;
    
    if ($remainingPieces == 0) {
        return $fullContainers . ' ' . ($fullContainers == 1 ? 'container' : 'containers');
    } else {
        return $fullContainers . ' ' . ($fullContainers == 1 ? 'container' : 'containers') . ' ' . $remainingPieces . ' pieces';
    }
}
?>

