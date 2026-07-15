-- Smart Inventory Management System
-- Complete Database Schema

CREATE DATABASE IF NOT EXISTS smart_inventory;
USE smart_inventory;

-- Users table with role-based access
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'cashier') DEFAULT 'staff',
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Categories
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Suppliers
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(150) NOT NULL,
    phone VARCHAR(50),
    email VARCHAR(100),
    address TEXT,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    sku VARCHAR(50) NOT NULL UNIQUE,
    barcode VARCHAR(100) UNIQUE NULL,
    unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
    purchase_price DECIMAL(10,2) NOT NULL,
    selling_price DECIMAL(10,2) NOT NULL,
    current_stock INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 10,
    image VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

-- Purchases (Stock In header)
CREATE TABLE purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    user_id INT NOT NULL,
    purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    payment_method ENUM('Cash','KBZPay','Mixed') NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('Paid','Partial','Unpaid') DEFAULT 'Paid',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
-- Purchase Details
CREATE TABLE purchase_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    purchase_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (purchase_id)
        REFERENCES purchases(id)
        ON DELETE CASCADE,
    FOREIGN KEY (product_id)
        REFERENCES products(id)
);
-- Purchase Payments (supports multiple/partial payments)
CREATE TABLE IF NOT EXISTS purchase_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    payment_method ENUM('Cash','KBZPay') NOT NULL DEFAULT 'Cash',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_date DATE NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sales
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(30) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    sale_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash','KBZPay','Mixed') NOT NULL,
    paid_amount DECIMAL(10,2) NOT NULL,
    change_amount DECIMAL(10,2) DEFAULT 0,
    payment_status ENUM('Paid','Partial') DEFAULT 'Paid',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Sale Details
CREATE TABLE IF NOT EXISTS sale_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 0,
    purchase_price DECIMAL(10,2) DEFAULT 0.00,
    selling_price DECIMAL(10,2) DEFAULT 0.00,
    subtotal DECIMAL(10,2) DEFAULT 0.00,
    profit DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

--sale payment table for mixed payment
CREATE TABLE sale_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    payment_method ENUM('Cash','KBZPay','Mixed') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
);

-- Settings
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_name VARCHAR(100) DEFAULT 'Smart Inventory',
    logo VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    address TEXT,
    currency VARCHAR(10) DEFAULT 'Ks',
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    minimum_profit_margin DECIMAL(5,2) DEFAULT 10.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Forecasts
CREATE TABLE IF NOT EXISTS forecasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    forecast_date DATE DEFAULT NULL,
    forecast_quantity INT DEFAULT 0,
    demand_level ENUM('High', 'Medium', 'Low') DEFAULT 'Medium',
    recommended_stock INT DEFAULT 0,
    method VARCHAR(50) DEFAULT 'moving_average',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Insert default admin user (password: admin123)
INSERT INTO users (name, username, email, password, role, status) VALUES
('System Admin', 'admin', 'admin@smartinventory.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Active'),
('Staff User', 'staff', 'staff@smartinventory.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'Active'),
('Cashier User', 'cashier', 'cashier@smartinventory.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', 'Active');

-- Default categories
INSERT INTO categories (name, description, status) VALUES
('Electronics', 'Electronic devices and accessories', 'Active'),
('Beverages', 'Beverage products including soft drinks', 'Active'),
('Snacks', 'Snack and food products', 'Active'),
('Clothing', 'Apparel and fashion items', 'Active');

-- Default suppliers
INSERT INTO suppliers (supplier_name, phone, email, address, status) VALUES
('ABC Trading', '0977777777', 'abc@gmail.com', 'Yangon', 'Active'),
('City Wholesale', '0988888888', 'city@gmail.com', 'Mandalay', 'Active');

-- Default products
INSERT INTO products (id, product_name, sku, purchase_price, selling_price, quantity, minimum_stock, unit, status) VALUES
(2, 1, 'Coca Cola 330ml', 'DRK001', 900.00, 1200.00, 100, 20, 'pcs', 'Active'),
(1, 2, 'HP Laptop 15s', 'ELE001', 450000.00, 520000.00, 10, 2, 'pcs', 'Active'),
(2, 1, 'Pepsi 500ml', 'DRK002', 800.00, 1100.00, 80, 15, 'pcs', 'Active'),
(3, 2, 'Lays Classic', 'SNK001', 500.00, 700.00, 200, 30, 'pcs', 'Active'),
(1, 2, 'Samsung Galaxy S24', 'ELE002', 850000.00, 950000.00, 12, 3, 'pcs', 'Active'),
(2, 1, 'Red Bull 250ml', 'DRK003', 1500.00, 2000.00, 60, 10, 'pcs', 'Active'),
(3, 2, 'Pringles Original', 'SNK002', 2500.00, 3200.00, 45, 10, 'pcs', 'Active');

-- Default settings
INSERT INTO settings (shop_name, phone, email, address, currency, tax_rate) VALUES
('Smart Inventory', '09987654321', 'info@smartinventory.com', '123 Main Street, Yangon', 'Ks', 0.00);

-- Add contact_person to suppliers
ALTER TABLE suppliers ADD COLUMN contact_person VARCHAR(150) DEFAULT NULL AFTER supplier_name;

-- Add theme preference to users
ALTER TABLE users ADD COLUMN theme ENUM('light', 'dark', 'system') DEFAULT 'system' AFTER status;

-- Add profit column to sale_details
ALTER TABLE sale_details ADD COLUMN profit DECIMAL(10,2) DEFAULT 0.00 AFTER subtotal;

-- Add price_update_required flag to products
ALTER TABLE products ADD COLUMN price_update_required TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
