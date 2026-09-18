SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
CREATE DATABASE IF NOT EXISTS pos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pos_db;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS held_transactions;
DROP TABLE IF EXISTS cashier_shifts;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS cash_transactions;
DROP TABLE IF EXISTS return_items;
DROP TABLE IF EXISTS returns;
DROP TABLE IF EXISTS stock_opname_items;
DROP TABLE IF EXISTS stock_opnames;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS sale_payments;
DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS purchase_items;
DROP TABLE IF EXISTS purchases;
DROP TABLE IF EXISTS product_price_history;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS product_units;
DROP TABLE IF EXISTS product_categories;
DROP TABLE IF EXISTS payment_methods;
DROP TABLE IF EXISTS stores;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS user_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

CREATE TABLE roles (
 id INT AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(30) UNIQUE NOT NULL,
 name VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE permissions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(60) UNIQUE NOT NULL,
 name VARCHAR(100) NOT NULL,
 module VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 role VARCHAR(20) NOT NULL DEFAULT 'kasir',
 role_id INT NULL,
 name VARCHAR(100) NOT NULL,
 username VARCHAR(50) UNIQUE NOT NULL,
 email VARCHAR(100) UNIQUE NOT NULL,
 password VARCHAR(255) NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 last_login DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL,
 INDEX idx_users_role (role),
 INDEX idx_users_username (username)
) ENGINE=InnoDB;

CREATE TABLE user_permissions (
 user_id INT NOT NULL,
 permission_id INT NOT NULL,
 PRIMARY KEY (user_id, permission_id),
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE stores (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 address TEXT,
 phone VARCHAR(30),
 email VARCHAR(100),
 logo VARCHAR(255),
 receipt_footer TEXT,
 currency VARCHAR(10) DEFAULT 'Rp',
 tax_percent DECIMAL(5,2) DEFAULT 0,
 transaction_prefix VARCHAR(10) DEFAULT 'TRX',
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE settings (
 id INT AUTO_INCREMENT PRIMARY KEY,
 `key` VARCHAR(80) UNIQUE NOT NULL,
 `value` TEXT,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE payment_methods (
 id INT AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(30) UNIQUE NOT NULL,
 name VARCHAR(60) NOT NULL,
 is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE product_categories (
 id INT AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(20) UNIQUE NOT NULL,
 name VARCHAR(80) NOT NULL,
 description TEXT,
 is_active TINYINT(1) DEFAULT 1,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_cat_name (name)
) ENGINE=InnoDB;

CREATE TABLE product_units (
 id INT AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(20) UNIQUE NOT NULL,
 name VARCHAR(40) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE suppliers (
 id INT AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(20) UNIQUE NOT NULL,
 name VARCHAR(120) NOT NULL,
 contact VARCHAR(80),
 phone VARCHAR(30),
 email VARCHAR(100),
 address TEXT,
 notes TEXT,
 is_active TINYINT(1) DEFAULT 1,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE customers (
 id INT AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(20) UNIQUE NOT NULL,
 name VARCHAR(120) NOT NULL,
 phone VARCHAR(30),
 address TEXT,
 type ENUM('umum','member','grosir') DEFAULT 'umum',
 points INT DEFAULT 0,
 is_active TINYINT(1) DEFAULT 1,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_cust_phone (phone),
 INDEX idx_cust_name (name)
) ENGINE=InnoDB;

CREATE TABLE products (
 id INT AUTO_INCREMENT PRIMARY KEY,
 sku VARCHAR(30) UNIQUE NOT NULL,
 barcode VARCHAR(50) UNIQUE NULL,
 name VARCHAR(150) NOT NULL,
 category_id INT NULL,
 unit_id INT NULL,
 purchase_price INT NOT NULL DEFAULT 0,
 selling_price INT NOT NULL DEFAULT 0,
 wholesale_price INT NOT NULL DEFAULT 0,
 stock INT NOT NULL DEFAULT 0,
 min_stock INT NOT NULL DEFAULT 5,
 location VARCHAR(60),
 supplier_id INT NULL,
 photo VARCHAR(255),
 is_active TINYINT(1) DEFAULT 1,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL,
 FOREIGN KEY (unit_id) REFERENCES product_units(id) ON DELETE SET NULL,
 FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
 INDEX idx_prod_barcode (barcode),
 INDEX idx_prod_sku (sku),
 INDEX idx_prod_name (name),
 INDEX idx_prod_category (category_id)
) ENGINE=InnoDB;

CREATE TABLE product_price_history (
 id INT AUTO_INCREMENT PRIMARY KEY,
 product_id INT NOT NULL,
 old_price INT NOT NULL,
 new_price INT NOT NULL,
 changed_by INT NULL,
 reason VARCHAR(255),
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE sales (
 id INT AUTO_INCREMENT PRIMARY KEY,
 transaction_number VARCHAR(30) UNIQUE NOT NULL,
 customer_id INT NULL,
 cashier_id INT NOT NULL,
 subtotal INT NOT NULL DEFAULT 0,
 discount_type ENUM('nominal','percent','none') DEFAULT 'none',
 discount_value DECIMAL(10,2) DEFAULT 0,
 discount_amount INT DEFAULT 0,
 tax_percent DECIMAL(5,2) DEFAULT 0,
 tax_amount INT DEFAULT 0,
 additional_cost INT DEFAULT 0,
 grand_total INT NOT NULL DEFAULT 0,
 paid_amount INT DEFAULT 0,
 change_amount INT DEFAULT 0,
 payment_method VARCHAR(30) DEFAULT 'tunai',
 status ENUM('completed','cancelled','refunded','corrected') DEFAULT 'completed',
 notes TEXT,
 correction_reason TEXT,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 FOREIGN KEY (cashier_id) REFERENCES users(id),
 INDEX idx_sales_number (transaction_number),
 INDEX idx_sales_created (created_at),
 INDEX idx_sales_cashier (cashier_id),
 INDEX idx_sales_customer (customer_id)
) ENGINE=InnoDB;

CREATE TABLE sale_items (
 id INT AUTO_INCREMENT PRIMARY KEY,
 sale_id INT NOT NULL,
 product_id INT NOT NULL,
 product_name VARCHAR(150) NOT NULL,
 sku VARCHAR(30),
 barcode VARCHAR(50),
 qty INT NOT NULL,
 price INT NOT NULL,
 discount_type ENUM('nominal','percent','none') DEFAULT 'none',
 discount_value DECIMAL(10,2) DEFAULT 0,
 discount_amount INT DEFAULT 0,
 subtotal INT NOT NULL,
 FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
 FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE sale_payments (
 id INT AUTO_INCREMENT PRIMARY KEY,
 sale_id INT NOT NULL,
 method VARCHAR(30) NOT NULL,
 amount INT NOT NULL,
 reference VARCHAR(100),
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE purchases (
 id INT AUTO_INCREMENT PRIMARY KEY,
 invoice_number VARCHAR(40) UNIQUE NOT NULL,
 supplier_id INT NOT NULL,
 user_id INT NOT NULL,
 total INT NOT NULL DEFAULT 0,
 status VARCHAR(20) DEFAULT 'completed',
 purchase_date DATE NOT NULL,
 notes TEXT,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
 FOREIGN KEY (user_id) REFERENCES users(id),
 INDEX idx_purch_date (purchase_date)
) ENGINE=InnoDB;

CREATE TABLE purchase_items (
 id INT AUTO_INCREMENT PRIMARY KEY,
 purchase_id INT NOT NULL,
 product_id INT NOT NULL,
 qty INT NOT NULL,
 price INT NOT NULL,
 subtotal INT NOT NULL,
 FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
 FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE stock_movements (
 id INT AUTO_INCREMENT PRIMARY KEY,
 product_id INT NOT NULL,
 type ENUM('PURCHASE','SALE','RETURN_SALE','RETURN_PURCHASE','ADJUSTMENT','STOCK_OPNAME','CORRECTION') NOT NULL,
 reference_type VARCHAR(30),
 reference_id INT,
 qty_change INT NOT NULL,
 stock_before INT NOT NULL,
 stock_after INT NOT NULL,
 notes TEXT,
 created_by INT NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (product_id) REFERENCES products(id),
 INDEX idx_stock_prod (product_id),
 INDEX idx_stock_type (type)
) ENGINE=InnoDB;

CREATE TABLE stock_opnames (
 id INT AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(30) UNIQUE NOT NULL,
 user_id INT NOT NULL,
 status ENUM('draft','approved') DEFAULT 'draft',
 notes TEXT,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 approved_at DATETIME NULL,
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE stock_opname_items (
 id INT AUTO_INCREMENT PRIMARY KEY,
 opname_id INT NOT NULL,
 product_id INT NOT NULL,
 system_stock INT NOT NULL,
 physical_stock INT NOT NULL,
 difference INT NOT NULL,
 notes VARCHAR(255),
 FOREIGN KEY (opname_id) REFERENCES stock_opnames(id) ON DELETE CASCADE,
 FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE returns (
 id INT AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(30) UNIQUE NOT NULL,
 sale_id INT NOT NULL,
 user_id INT NOT NULL,
 total_refund INT NOT NULL DEFAULT 0,
 reason TEXT,
 status VARCHAR(20) DEFAULT 'completed',
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (sale_id) REFERENCES sales(id),
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE return_items (
 id INT AUTO_INCREMENT PRIMARY KEY,
 return_id INT NOT NULL,
 sale_item_id INT NOT NULL,
 product_id INT NOT NULL,
 qty INT NOT NULL,
 refund_amount INT NOT NULL,
 reason VARCHAR(255),
 FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cash_transactions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 type ENUM('in','out') NOT NULL,
 category VARCHAR(60),
 amount INT NOT NULL,
 description TEXT,
 reference_type VARCHAR(30),
 reference_id INT,
 created_by INT NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_cash_type (type),
 INDEX idx_cash_date (created_at)
) ENGINE=InnoDB;

CREATE TABLE expenses (
 id INT AUTO_INCREMENT PRIMARY KEY,
 category VARCHAR(60) NOT NULL,
 amount INT NOT NULL,
 description TEXT,
 proof VARCHAR(255),
 expense_date DATE NOT NULL,
 created_by INT NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE cashier_shifts (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 opening_balance INT NOT NULL DEFAULT 0,
 closing_balance INT NULL,
 cash_sales INT DEFAULT 0,
 cash_in INT DEFAULT 0,
 cash_out INT DEFAULT 0,
 expected_balance INT DEFAULT 0,
 actual_balance INT NULL,
 difference INT NULL,
 status ENUM('open','closed') DEFAULT 'open',
 opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 closed_at DATETIME NULL,
 notes TEXT,
 FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE held_transactions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(30) UNIQUE NOT NULL,
 cashier_id INT NOT NULL,
 data JSON NOT NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (cashier_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NULL,
 action VARCHAR(60) NOT NULL,
 module VARCHAR(60) NOT NULL,
 record_id VARCHAR(60),
 description TEXT,
 ip_address VARCHAR(45),
 user_agent TEXT,
 old_data JSON NULL,
 new_data JSON NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_audit_action (action),
 INDEX idx_audit_module (module),
 INDEX idx_audit_user (user_id),
 INDEX idx_audit_date (created_at)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS=1;
