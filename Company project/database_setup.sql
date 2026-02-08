-- Mining Equipment Management System Database Setup
-- Create database and tables

CREATE DATABASE IF NOT EXISTS mining_equipment_db;
USE mining_equipment_db;

-- Products table - stores all mining equipment items
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(255) NOT NULL,
    container_unit VARCHAR(50) DEFAULT 'Piece', -- Box, Sack, Bundle, Gallon, etc.
    container_quantity INT DEFAULT 0, -- How many containers in stock
    pieces_per_container INT DEFAULT 1, -- How many pieces in one container
    individual_unit VARCHAR(50) DEFAULT 'Piece', -- What they sell to customers
    model_size VARCHAR(100), -- Model, size, or any specification
    description TEXT, -- Additional details
    unit_price DECIMAL(10,2) NOT NULL, -- Price per individual unit
    minimum_stock INT DEFAULT 5, -- When to reorder
    supplier_name VARCHAR(255),
    supplier_contact VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sales table - stores sales transactions
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_number VARCHAR(20) UNIQUE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    staff_name VARCHAR(100) DEFAULT 'Staff',
    customer_name VARCHAR(255),
    customer_address TEXT,
    lpo_number VARCHAR(100),
    notes TEXT
);

-- Sale Items table - stores individual items in each sale
CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity_sold INT NOT NULL,
    unit_type VARCHAR(50) DEFAULT 'piece',
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Add loose_pieces column to products table (if missing)
ALTER TABLE products ADD COLUMN IF NOT EXISTS loose_pieces INT NOT NULL DEFAULT 0;

-- Sample data removed - add your own products through the inventory management interface

ADD COLUMN unit_type VARCHAR(50) DEFAULT 'piece' AFTER quantity_sold;

-- Add loose_pieces column to products table (if missing)
ALTER TABLE products ADD COLUMN loose_pieces INT NOT NULL DEFAULT 0;
