-- Smart Inventory Backup - 2026-07-02 05:44:30

TRUNCATE TABLE users;
INSERT INTO users (id, name, username, email, password, role, status, created_at) VALUES ('1', 'System Admin', 'admin', 'admin@smartinventory.com', '$2y$10$7z4FzaEG.jzN5MK9yPqnFePpzMZsU7vHkG2QTO77nHmO8E81DUIfK', 'admin', 'Active', '2026-07-01 23:36:10');
INSERT INTO users (id, name, username, email, password, role, status, created_at) VALUES ('2', 'Staff User', 'staff', 'staff@smartinventory.com', '$2y$10$zK7WdV3.N9A/HeedjHqVL.Pz0RRXlhihdR2QEe/BsskDKJkOxoZ1W', 'staff', 'Active', '2026-07-01 23:36:10');
INSERT INTO users (id, name, username, email, password, role, status, created_at) VALUES ('3', 'Cashier User', 'cashier', 'cashier@smartinventory.com', '$2y$10$jbUp07WGV50xHXEUB9ZRPORG0GHz1B/rTqmp9R05ROWovY0qd482e', 'cashier', 'Active', '2026-07-01 23:36:10');

TRUNCATE TABLE categories;
INSERT INTO categories (id, name, description, status, created_at) VALUES ('1', 'Electronics', 'Electronic devices and accessories', 'Active', '2026-07-01 23:36:10');
INSERT INTO categories (id, name, description, status, created_at) VALUES ('2', 'Beverages', 'Beverage products including soft drinks', 'Active', '2026-07-01 23:36:10');
INSERT INTO categories (id, name, description, status, created_at) VALUES ('3', 'Snacks', 'Snack and food products', 'Active', '2026-07-01 23:36:10');
INSERT INTO categories (id, name, description, status, created_at) VALUES ('4', 'Clothing', 'Apparel and fashion items', 'Active', '2026-07-01 23:36:10');
INSERT INTO categories (id, name, description, status, created_at) VALUES ('5', 'Drink', 'Food product', 'Active', '2026-07-02 10:06:01');
INSERT INTO categories (id, name, description, status, created_at) VALUES ('6', 'Book', 'stationery', 'Active', '2026-07-02 10:06:04');
INSERT INTO categories (id, name, description, status, created_at) VALUES ('7', 'Rulers', 'Stationery', 'Active', '2026-07-02 11:48:13');

TRUNCATE TABLE suppliers;
INSERT INTO suppliers (id, supplier_name, phone, email, address, status, created_at) VALUES ('1', 'ABC Trading', '0977777777', 'abc@gmail.com', 'Yangon', 'Active', '2026-07-01 23:36:10');
INSERT INTO suppliers (id, supplier_name, phone, email, address, status, created_at) VALUES ('2', 'City Wholesale', '0988888888', 'city@gmail.com', 'Mandalay', 'Active', '2026-07-01 23:36:10');

TRUNCATE TABLE products;
INSERT INTO products (id, category_id, supplier_id, product_name, sku, barcode, purchase_price, selling_price, quantity, minimum_stock, unit, image, status, created_at) VALUES ('1', '2', '1', 'Coca Cola 330ml', 'DRK001', '', '2300.00', '1200.00', '195', '20', 'pcs', '', 'Active', '2026-07-01 23:36:10');
INSERT INTO products (id, category_id, supplier_id, product_name, sku, barcode, purchase_price, selling_price, quantity, minimum_stock, unit, image, status, created_at) VALUES ('2', '1', '2', 'HP Laptop 15s', 'ELE001', '', '450000.00', '520000.00', '7', '2', 'pcs', '', 'Active', '2026-07-01 23:36:10');
INSERT INTO products (id, category_id, supplier_id, product_name, sku, barcode, purchase_price, selling_price, quantity, minimum_stock, unit, image, status, created_at) VALUES ('3', '2', '1', 'Pepsi 500ml', 'DRK002', '', '2000.00', '1100.00', '110', '15', 'pcs', '', 'Active', '2026-07-01 23:36:10');
INSERT INTO products (id, category_id, supplier_id, product_name, sku, barcode, purchase_price, selling_price, quantity, minimum_stock, unit, image, status, created_at) VALUES ('4', '3', '2', 'Lays Classic', 'SNK001', '', '500.00', '700.00', '199', '30', 'pcs', '', 'Active', '2026-07-01 23:36:10');
INSERT INTO products (id, category_id, supplier_id, product_name, sku, barcode, purchase_price, selling_price, quantity, minimum_stock, unit, image, status, created_at) VALUES ('5', '1', '2', 'Samsung Galaxy S24', 'ELE002', '', '850000.00', '950000.00', '12', '3', 'pcs', '', 'Active', '2026-07-01 23:36:10');
INSERT INTO products (id, category_id, supplier_id, product_name, sku, barcode, purchase_price, selling_price, quantity, minimum_stock, unit, image, status, created_at) VALUES ('6', '2', '1', 'Red Bull 250ml', 'DRK003', '', '1500.00', '2000.00', '50', '10', 'pcs', '', 'Active', '2026-07-01 23:36:10');
INSERT INTO products (id, category_id, supplier_id, product_name, sku, barcode, purchase_price, selling_price, quantity, minimum_stock, unit, image, status, created_at) VALUES ('7', '3', '2', 'Pringles Original', 'SNK002', '', '2500.00', '3200.00', '45', '10', 'pcs', '', 'Active', '2026-07-01 23:36:10');

TRUNCATE TABLE purchases;

TRUNCATE TABLE purchase_details;

TRUNCATE TABLE sales;
INSERT INTO sales (id, invoice_no, user_id, grand_total, payment_method, paid_amount, discount, sale_date, created_at) VALUES ('2', 'INV-00001', '', '521200.00', 'Card', '500000.00', '0.00', '2026-07-01 23:46:57', '2026-07-01 23:46:57');
INSERT INTO sales (id, invoice_no, user_id, grand_total, payment_method, paid_amount, discount, sale_date, created_at) VALUES ('3', 'INV-00003', '', '521200.00', 'Card', '500000.00', '0.00', '2026-07-01 23:48:25', '2026-07-01 23:48:25');
INSERT INTO sales (id, invoice_no, user_id, grand_total, payment_method, paid_amount, discount, sale_date, created_at) VALUES ('4', 'INV-00004', '', '3600.00', 'Cash', '5000.00', '0.00', '2026-07-01 23:51:52', '2026-07-01 23:51:52');
INSERT INTO sales (id, invoice_no, user_id, grand_total, payment_method, paid_amount, discount, sale_date, created_at) VALUES ('5', 'POS-20260701-0005', '3', '941900.00', 'Cash', '941900.00', '100000.00', '2026-07-02 00:08:06', '2026-07-02 00:08:06');

TRUNCATE TABLE sale_details;
INSERT INTO sale_details (id, sale_id, product_id, quantity, purchase_price, selling_price, subtotal) VALUES ('1', '3', '1', '1', '0.00', '1200.00', '1200.00');
INSERT INTO sale_details (id, sale_id, product_id, quantity, purchase_price, selling_price, subtotal) VALUES ('2', '3', '2', '1', '0.00', '520000.00', '520000.00');
INSERT INTO sale_details (id, sale_id, product_id, quantity, purchase_price, selling_price, subtotal) VALUES ('3', '4', '1', '3', '0.00', '1200.00', '3600.00');
INSERT INTO sale_details (id, sale_id, product_id, quantity, purchase_price, selling_price, subtotal) VALUES ('4', '5', '1', '1', '2300.00', '1200.00', '1200.00');
INSERT INTO sale_details (id, sale_id, product_id, quantity, purchase_price, selling_price, subtotal) VALUES ('5', '5', '2', '2', '450000.00', '520000.00', '1040000.00');
INSERT INTO sale_details (id, sale_id, product_id, quantity, purchase_price, selling_price, subtotal) VALUES ('6', '5', '4', '1', '500.00', '700.00', '700.00');

TRUNCATE TABLE settings;
INSERT INTO settings (id, shop_name, logo, phone, email, address, currency, tax_rate, created_at) VALUES ('1', 'Inventory Management System', 'logo_1782970938.png', '0977777777', 'inventory@shop.com', '123 Main Street, Yangon', 'ks', '0.00', '2026-07-01 23:36:11');

TRUNCATE TABLE forecasts;

