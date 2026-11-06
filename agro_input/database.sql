CREATE DATABASE IF NOT EXISTS agro_input_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agro_input_db;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NOT NULL,
  address TEXT NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  avatar VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  category ENUM('Improved Seeds','Fertilizers','Pesticides','Animal Feeds','Veterinary Supplies','Farm Tools') NOT NULL,
  description TEXT,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  stock_quantity INT NOT NULL DEFAULT 0,
  image_url VARCHAR(500) DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_products_category (category),
  INDEX idx_products_active (active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  customer_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  delivery_address TEXT NOT NULL,
  payment_method ENUM('Cash on delivery','Mobile Money - manual confirmation','Bank transfer - manual confirmation') NOT NULL,
  payment_status ENUM('Pending','Confirmed','Failed','Refunded') NOT NULL DEFAULT 'Pending',
  order_status ENUM('Pending','Processing','Ready for delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  total_amount DECIMAL(12,2) NOT NULL,
  customer_note TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_orders_user (user_id),
  INDEX idx_orders_status (order_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  product_name VARCHAR(160) NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS feedback (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO products (name, category, description, price, stock_quantity, image_url) VALUES
('Maize Seeds — 1 kg',    'Improved Seeds',      'Quality maize seed for planting. Confirm variety and planting guidance with the dealer.', 1500,  80, 'https://images.unsplash.com/photo-1601593768799-76e4bb31ddac?w=400&q=75&fit=crop'),
('Bean Seeds — 1 kg',     'Improved Seeds',      'Bean seed suitable for seasonal planting. Ask staff for variety availability.',           1800,  60, 'https://images.unsplash.com/photo-1515543237350-b3eea1ec8082?w=400&q=75&fit=crop'),
('NPK Fertilizer — 50 kg','Fertilizers',         'Compound fertilizer. Follow the product label and qualified agricultural advice.',        2000,  25, 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=75&fit=crop'),
('Urea Fertilizer — 50 kg','Fertilizers',        'Nitrogen fertilizer. Use according to label instructions.',                              1900,  20, 'https://images.unsplash.com/photo-1464226184884-fa280b87c399?w=400&q=75&fit=crop'),
('Crop Protection Product','Pesticides',         'Ask the dealer for approved products appropriate to the crop and pest.',                  1200,  30, 'https://images.unsplash.com/photo-1586771107445-d3ca888129ff?w=400&q=75&fit=crop'),
('Dairy Meal — 50 kg',    'Animal Feeds',        'Livestock feed. Check the label for feeding instructions.',                              1700,  35, 'https://images.unsplash.com/photo-1500595046743-cd271d694d30?w=400&q=75&fit=crop'),
('Veterinary Supplies Pack','Veterinary Supplies','General veterinary supplies. Seek advice from a qualified veterinary professional.',     1300,  15, 'https://images.unsplash.com/photo-1628771065518-0d82f1938462?w=400&q=75&fit=crop'),
('Hand Hoe',              'Farm Tools',          'Basic farm hand tool for common field tasks.',                                           1000,  40, 'https://images.unsplash.com/photo-1592417817098-8fd3d9eb14a5?w=400&q=75&fit=crop');
