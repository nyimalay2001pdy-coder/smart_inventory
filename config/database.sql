CREATE DATABASE smart_inventory;

USE smart_inventory;


CREATE TABLE users(

id INT AUTO_INCREMENT PRIMARY KEY,

name VARCHAR(100),

email VARCHAR(100) UNIQUE,

password VARCHAR(255),

role ENUM('admin','staff')
DEFAULT 'staff',

created_at TIMESTAMP
DEFAULT CURRENT_TIMESTAMP

);



CREATE TABLE categories(

id INT AUTO_INCREMENT PRIMARY KEY,

name VARCHAR(100),

description TEXT,
status ENUM('Active','Inactive')
DEFAULT 'Active',

created_at TIMESTAMP
DEFAULT CURRENT_TIMESTAMP

);



CREATE TABLE products(

    id INT AUTO_INCREMENT PRIMARY KEY,

    category_id INT NOT NULL,

    product_name VARCHAR(150) NOT NULL,

    sku VARCHAR(100) UNIQUE,

    purchase_price DECIMAL(10,2),

    selling_price DECIMAL(10,2),

    quantity INT DEFAULT 0,

    unit VARCHAR(50),

    image VARCHAR(255),

    status ENUM('Active','Inactive') DEFAULT 'Active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,


    FOREIGN KEY(category_id)
    REFERENCES categories(id)
    ON DELETE CASCADE

);



CREATE TABLE suppliers(

    id INT AUTO_INCREMENT PRIMARY KEY,

    supplier_name VARCHAR(150) NOT NULL,

    phone VARCHAR(50),

    email VARCHAR(100),

    address TEXT,

    status ENUM('Active','Inactive') DEFAULT 'Active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);


CREATE TABLE stock_in(

id INT AUTO_INCREMENT PRIMARY KEY,

supplier_id INT,

product_id INT,

user_id INT,

quantity INT,

unit_cost DECIMAL(10,2),

stock_date DATE,

created_at TIMESTAMP
DEFAULT CURRENT_TIMESTAMP,


FOREIGN KEY(supplier_id)
REFERENCES suppliers(id),


FOREIGN KEY(product_id)
REFERENCES products(id),


FOREIGN KEY(user_id)
REFERENCES users(id)

);



CREATE TABLE sales(

id INT AUTO_INCREMENT PRIMARY KEY,

invoice_no VARCHAR(50),

user_id INT,

sale_date DATETIME,

grand_total DECIMAL(10,2),


FOREIGN KEY(user_id)
REFERENCES users(id)

);



CREATE TABLE sale_items(

id INT AUTO_INCREMENT PRIMARY KEY,

sale_id INT,

product_id INT,

quantity INT,

unit_price DECIMAL(10,2),

total_price DECIMAL(10,2),


FOREIGN KEY(sale_id)
REFERENCES sales(id),


FOREIGN KEY(product_id)
REFERENCES products(id)

);



CREATE TABLE forecasts(

id INT AUTO_INCREMENT PRIMARY KEY,

product_id INT,

forecast_month VARCHAR(30),

forecast_quantity INT,

recommended_purchase INT,

method VARCHAR(50),


FOREIGN KEY(product_id)
REFERENCES products(id)

);

INSERT INTO categories(name,description,status)VALUES
('Electronics','Electronic devices and accessories','Active'),
('Drink','Beverage Products','Active'),
('Snack','Food Products','Active');

INSERT INTO products(category_id, product_name, sku, purchase_price, selling_price, stock, reorder_level, status) VALUES
(2, 'Coca Cola 330ml', 'DRK001', 900.00, 1200.00, 100, 20, 'Active'),
(1, 'HP Laptop 15s', 'ELE001', 450000.00, 520000.00, 5, 2, 'Active'),
(2, 'Pepsi 500ml', 'DRK002', 800.00, 1100.00, 80, 15, 'Active'),
(3, 'Lays Classic', 'SNK001', 500.00, 700.00, 200, 30, 'Active'),
(1, 'Samsung Galaxy S24', 'ELE002', 850000.00, 950000.00, 12, 3, 'Active'),
(1, 'Apple AirPods Pro', 'ELE003', 180000.00, 220000.00, 25, 5, 'Inactive'),
(2, 'Red Bull 250ml', 'DRK003', 1500.00, 2000.00, 60, 10, 'Active'),
(3, 'Pringles Original', 'SNK002', 2500.00, 3200.00, 45, 10, 'Hidden');


-- ALTER TABLE commands if you already have the old products table:
-- ALTER TABLE products ADD COLUMN sku VARCHAR(50) AFTER product_name;
-- ALTER TABLE products ADD COLUMN purchase_price DECIMAL(10,2) AFTER sku;
-- ALTER TABLE products CHANGE price selling_price DECIMAL(10,2);
-- ALTER TABLE products ADD COLUMN status ENUM('Active','Inactive','Hidden') DEFAULT 'Active' AFTER reorder_level;

INSERT INTO suppliers

(
supplier_name,
phone,
email,
address
)

VALUES

(
'ABC Trading',
'0977777777',
'abc@gmail.com',
'Yangon'
),


(
'City Wholesale',
'0988888888',
'city@gmail.com',
'Mandalay'
);

--purchase header
CREATE TABLE stock_in(

id INT AUTO_INCREMENT PRIMARY KEY,

supplier_id INT NOT NULL,

purchase_date DATE,

total_amount DECIMAL(10,2),

payment_status ENUM(
'Paid',
'Unpaid'
)
DEFAULT 'Unpaid',

created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,


FOREIGN KEY(supplier_id)
REFERENCES suppliers(id)

);

--product detail
CREATE TABLE stock_in_details(
id INT AUTO_INCREMENT PRIMARY KEY,
stock_in_id INT NOT NULL,
product_id INT NOT NULL,
quantity INT,
purchase_price DECIMAL(10,2),
subtotal DECIMAL(10,2),
FOREIGN KEY(stock_in_id)
REFERENCES stock_in(id),
FOREIGN KEY(product_id)
REFERENCES products(id)
);
