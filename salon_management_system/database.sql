-- Salon Management System Database
-- Drop database if exists and create fresh
DROP DATABASE IF EXISTS salon_management;
CREATE DATABASE salon_management;
USE salon_management;

-- Users table (Admin and Staff accounts)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'staff', -- 'admin' or 'staff'
    job_title VARCHAR(50),
    rate DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User permissions table (feature-based access control)
CREATE TABLE user_permissions (
    permission_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    can_access_inventory BOOLEAN DEFAULT FALSE,
    can_access_schedules BOOLEAN DEFAULT FALSE,
    can_access_pos BOOLEAN DEFAULT FALSE,
    can_view_reports BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Product categories
CREATE TABLE product_categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products/Inventory table
CREATE TABLE products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    product_name VARCHAR(100) NOT NULL,
    category_id INT,
    price DECIMAL(10,2) NOT NULL,
    quantity INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 10,
    status ENUM('available', 'out_of_stock') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES product_categories(category_id) ON DELETE SET NULL
);

-- Sales transactions
CREATE TABLE sales (
    sale_id INT PRIMARY KEY AUTO_INCREMENT,
    sale_date DATE NOT NULL,
    staff_id INT,
    product_id INT,
    service_name VARCHAR(100),
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'online') DEFAULT 'cash',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL
);

-- Staff duty tracking (manual entry by admin)
CREATE TABLE staff_duties (
    duty_id INT PRIMARY KEY AUTO_INCREMENT,
    duty_date DATE NOT NULL,
    staff_id INT NOT NULL,
    customers_treated INT DEFAULT 0,
    additional_earnings DECIMAL(10,2) DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Insert default admin account
-- Note: Run setup.php after importing this database to set the admin password
-- Or manually insert with: INSERT INTO users (username, password, full_name, role, status) 
-- VALUES ('admin', '$2y$10$yourHashHere', 'System Administrator', 'admin', 'active');
-- The setup.php script will create this account automatically

-- Insert default product categories
INSERT INTO product_categories (category_name) VALUES 
('Hair Products'),
('Skin Care'),
('Nail Care'),
('Tools & Equipment'),
('Services');

-- Insert sample products
INSERT INTO products (product_name, category_id, price, quantity, low_stock_threshold) VALUES
('Shampoo Premium', 1, 250.00, 50, 10),
('Hair Conditioner', 1, 200.00, 45, 10),
('Facial Cleanser', 2, 350.00, 30, 8),
('Moisturizer', 2, 450.00, 25, 8),
('Nail Polish', 3, 120.00, 60, 15),
('Nail Polish Remover', 3, 80.00, 40, 10),
('Hair Dryer', 4, 1500.00, 5, 2),
('Scissors Professional', 4, 800.00, 8, 3);

-- Create views for reporting

-- Daily sales summary view
CREATE VIEW daily_sales_summary AS
SELECT 
    sale_date,
    COUNT(*) as total_transactions,
    SUM(total_amount) as total_sales,
    COUNT(DISTINCT staff_id) as staff_count
FROM sales
GROUP BY sale_date
ORDER BY sale_date DESC;

-- Low stock products view
CREATE VIEW low_stock_products AS
SELECT 
    p.product_id,
    p.product_name,
    c.category_name,
    p.quantity,
    p.low_stock_threshold,
    p.price
FROM products p
LEFT JOIN product_categories c ON p.category_id = c.category_id
WHERE p.quantity <= p.low_stock_threshold
ORDER BY p.quantity ASC;

-- Staff performance view
CREATE VIEW staff_performance AS
SELECT 
    u.user_id,
    u.full_name,
    u.job_title,
    COUNT(DISTINCT sd.duty_date) as days_worked,
    SUM(sd.customers_treated) as total_customers,
    SUM(sd.additional_earnings) as total_additional_earnings,
    COUNT(s.sale_id) as total_sales_transactions,
    COALESCE(SUM(s.total_amount), 0) as total_sales_amount
FROM users u
LEFT JOIN staff_duties sd ON u.user_id = sd.staff_id
LEFT JOIN sales s ON u.user_id = s.staff_id
WHERE u.role = 'staff' AND u.status = 'active'
GROUP BY u.user_id, u.full_name, u.job_title;