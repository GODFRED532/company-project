-- Complete Database Update Script for Mining Equipment Management System
-- Run this script to add all missing fields and features

USE mining_equipment_db;

-- Add customer fields to sales table (if they don't exist)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'sales' 
     AND COLUMN_NAME = 'customer_name') = 0,
    'ALTER TABLE sales ADD COLUMN customer_name VARCHAR(255) AFTER staff_name',
    'SELECT "customer_name column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'sales' 
     AND COLUMN_NAME = 'customer_address') = 0,
    'ALTER TABLE sales ADD COLUMN customer_address TEXT AFTER customer_name',
    'SELECT "customer_address column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'sales' 
     AND COLUMN_NAME = 'lpo_number') = 0,
    'ALTER TABLE sales ADD COLUMN lpo_number VARCHAR(100) AFTER customer_address',
    'SELECT "lpo_number column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add unit_type field to sale_items table (if it doesn't exist)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'sale_items' 
     AND COLUMN_NAME = 'unit_type') = 0,
    'ALTER TABLE sale_items ADD COLUMN unit_type VARCHAR(50) DEFAULT "piece" AFTER quantity_sold',
    'SELECT "unit_type column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add loose_pieces field to products table (if it doesn't exist)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'products' 
     AND COLUMN_NAME = 'loose_pieces') = 0,
    'ALTER TABLE products ADD COLUMN loose_pieces INT NOT NULL DEFAULT 0',
    'SELECT "loose_pieces column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Show final table structures
DESCRIBE sales;
DESCRIBE sale_items;
DESCRIBE products;

SELECT 'Database update completed successfully!' as status;
