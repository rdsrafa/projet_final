-- database/adoosneakers.sql
-- Base de données complète pour Adoo Sneakers ERP
-- MySQL/MariaDB

CREATE DATABASE IF NOT EXISTS adoosneakers CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE adoosneakers;

-- ============================================================================
-- TABLES DE RÉFÉRENCE
-- ============================================================================

-- Table des marques
CREATE TABLE brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#4ECDC4',
    logo_url VARCHAR(255),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table des produits (modèles de sneakers)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    model VARCHAR(200) NOT NULL,
    colorway VARCHAR(100) NOT NULL,
    year INT NOT NULL,
    sku VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    cost DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (brand_id) REFERENCES brands(id),
    INDEX idx_brand (brand_id),
    INDEX idx_sku (sku)
) ENGINE=InnoDB;

-- Table des stocks (par pointure)
CREATE TABLE stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    location VARCHAR(50) DEFAULT 'Entrepôt',
    min_quantity INT DEFAULT 5,
    max_quantity INT DEFAULT 50,
    serial_numbers TEXT, -- JSON array des numéros de série
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_size (product_id, size, location),
    INDEX idx_product (product_id),
    INDEX idx_low_stock (quantity)
) ENGINE=InnoDB;

-- ============================================================================
-- GESTION COMMERCIALE
-- ============================================================================

-- Table des clients
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(10),
    country VARCHAR(100) DEFAULT 'France',
    type ENUM('Regular', 'VIP', 'Pro') DEFAULT 'Regular',
    loyalty_points INT DEFAULT 0,
    total_spent DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_type (type)
) ENGINE=InnoDB;

-- Table des ventes
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_number VARCHAR(50) UNIQUE NOT NULL,
    client_id INT NOT NULL,
    employee_id INT,
    channel ENUM('Boutique', 'Web') NOT NULL,
    status ENUM('pending', 'completed', 'cancelled', 'refunded') DEFAULT 'pending',
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    tax DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    payment_method ENUM('CB', 'Especes', 'Virement', 'Cheque') NOT NULL,
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    notes TEXT,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    INDEX idx_sale_number (sale_number),
    INDEX idx_client (client_id),
    INDEX idx_status (status),
    INDEX idx_date (sale_date)
) ENGINE=InnoDB;

-- Table des lignes de vente
CREATE TABLE sale_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    size INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    serial_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_sale (sale_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

-- ============================================================================
-- GESTION DES ACHATS
-- ============================================================================

-- Table des fournisseurs
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    contact_name VARCHAR(150),
    email VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(10),
    country VARCHAR(100) DEFAULT 'France',
    siret VARCHAR(20),
    tva VARCHAR(20),
    payment_terms INT DEFAULT 30, -- Jours
    notes TEXT,
    rating INT DEFAULT 5, -- Note sur 5
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB;

-- Table des commandes fournisseurs
CREATE TABLE purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    supplier_id INT NOT NULL,
    status ENUM('draft', 'sent', 'received', 'cancelled') DEFAULT 'draft',
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expected_date DATE,
    received_date DATE,
    subtotal DECIMAL(10,2) NOT NULL,
    tax DECIMAL(10,2) DEFAULT 0,
    shipping DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    INDEX idx_order_number (order_number),
    INDEX idx_supplier (supplier_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Table des lignes de commande fournisseur
CREATE TABLE purchase_order_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT NOT NULL,
    product_id INT NOT NULL,
    size INT NOT NULL,
    quantity_ordered INT NOT NULL,
    quantity_received INT DEFAULT 0,
    unit_cost DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_purchase_order (purchase_order_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB;

-- ============================================================================
-- MOUVEMENTS DE STOCK
-- ============================================================================

-- Table des mouvements de stock
CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size INT NOT NULL,
    type ENUM('in', 'out', 'transfer', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    from_location VARCHAR(50),
    to_location VARCHAR(50),
    reference_type ENUM('sale', 'purchase', 'transfer', 'inventory', 'other'),
    reference_id INT,
    serial_number VARCHAR(100),
    employee_id INT,
    notes TEXT,
    movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_product (product_id),
    INDEX idx_type (type),
    INDEX idx_date (movement_date)
) ENGINE=InnoDB;

-- ============================================================================
-- RESSOURCES HUMAINES
-- ============================================================================

-- Table des employés
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(10),
    hire_date DATE NOT NULL,
    birth_date DATE,
    social_security VARCHAR(20),
    position VARCHAR(150) NOT NULL,
    department VARCHAR(100),
    contract_type ENUM('CDI', 'CDD', 'Vacataire', 'Stage') DEFAULT 'CDI',
    salary DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'inactive', 'vacation') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Table des congés
CREATE TABLE leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    type ENUM('CP', 'RTT', 'Maladie', 'Sans solde', 'Formation') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_count INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reason TEXT,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (approved_by) REFERENCES employees(id),
    INDEX idx_employee (employee_id),
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB;

-- Table des bulletins de paie
CREATE TABLE payslips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    gross_salary DECIMAL(10,2) NOT NULL,
    net_salary DECIMAL(10,2) NOT NULL,
    deductions DECIMAL(10,2) DEFAULT 0,
    bonuses DECIMAL(10,2) DEFAULT 0,
    hours_worked DECIMAL(5,2) DEFAULT 151.67,
    overtime_hours DECIMAL(5,2) DEFAULT 0,
    status ENUM('draft', 'validated', 'paid') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    UNIQUE KEY unique_employee_period (employee_id, month, year),
    INDEX idx_employee (employee_id),
    INDEX idx_period (year, month)
) ENGINE=InnoDB;

-- ============================================================================
-- UTILISATEURS ET AUTHENTIFICATION
-- ============================================================================

-- Table des utilisateurs (pour l'authentification)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNIQUE,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'employee') DEFAULT 'employee',
    last_login TIMESTAMP NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ============================================================================
-- DONNÉES DE DÉMONSTRATION
-- ============================================================================

-- Insertion des marques
INSERT INTO brands (name, color) VALUES
('Nike', '#FF6B6B'),
('Adidas', '#4ECDC4'),
('Jordan', '#FFE66D'),
('Yeezy', '#6C5CE7'),
('New Balance', '#A8E6CF');

-- Insertion des produits
INSERT INTO products (brand_id, model, colorway, year, sku, price, cost) VALUES
(1, 'Air Jordan 1 Retro High', 'Chicago', 2024, 'NKE-AJ1-CHI-001', 189.00, 95.00),
(2, 'Yeezy Boost 350 V2', 'Zebra', 2024, 'ADI-YZY-ZEB-002', 210.00, 110.00),
(1, 'Dunk Low', 'Panda', 2024, 'NKE-DNK-PAN-003', 145.00, 75.00),
(1, 'Air Force 1 \'07', 'White', 2024, 'NKE-AF1-WHT-004', 110.00, 60.00),
(3, 'Travis Scott Fragment', 'Military Blue', 2024, 'JRD-TSF-MIL-005', 1500.00, 800.00);

-- Insertion des stocks
INSERT INTO stock (product_id, size, quantity, location) VALUES
-- Air Jordan 1
(1, 40, 12, 'Entrepôt'),
(1, 41, 8, 'Entrepôt'),
(1, 42, 15, 'Entrepôt'),
(1, 43, 10, 'Entrepôt'),
-- Yeezy 350
(2, 40, 25, 'Entrepôt'),
(2, 41, 18, 'Entrepôt'),
(2, 42, 22, 'Entrepôt'),
(2, 43, 14, 'Entrepôt'),
-- Dunk Low
(3, 40, 4, 'Entrepôt'),
(3, 41, 3, 'Entrepôt'),
(3, 42, 5, 'Entrepôt');

-- Insertion des clients
INSERT INTO clients (first_name, last_name, email, phone, type, total_spent) VALUES
('Thomas', 'Martin', 'thomas.m@email.com', '06 12 34 56 78', 'VIP', 4500.00),
('Sophie', 'Durand', 'sophie.d@email.com', '06 23 45 67 89', 'Regular', 890.00),
('Lucas', 'Bernard', 'lucas.b@email.com', '06 34 56 78 90', 'VIP', 6200.00),
('Emma', 'Petit', 'emma.p@email.com', '06 45 67 89 01', 'Regular', 450.00);

-- Insertion des employés
INSERT INTO employees (first_name, last_name, email, phone, hire_date, position, salary, status) VALUES
('Marie', 'Laurent', 'marie.l@adoo.fr', '06 12 34 56 78', '2023-01-15', 'Responsable Ventes', 3200.00, 'active'),
('Jean', 'Perrin', 'jean.p@adoo.fr', '06 23 45 67 89', '2023-03-10', 'Vendeur Senior', 2400.00, 'active'),
('Sophie', 'Moreau', 'sophie.m@adoo.fr', '06 34 56 78 90', '2023-02-20', 'Responsable Stock', 2800.00, 'active'),
('Marc', 'Dubois', 'marc.d@adoo.fr', '06 45 67 89 01', '2023-04-05', 'Responsable Achats', 3000.00, 'active');

-- Insertion d'un utilisateur admin (mot de passe: admin123)
INSERT INTO users (employee_id, username, email, password_hash, role) VALUES
(1, 'admin', 'admin@adoo.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- ============================================================================
-- VUES UTILES
-- ============================================================================

-- Vue des stocks avec informations produit
CREATE VIEW v_stock_overview AS
SELECT 
    s.id,
    s.product_id,
    p.sku,
    b.name AS brand,
    p.model,
    p.colorway,
    s.size,
    s.quantity,
    s.location,
    s.min_quantity,
    p.price,
    (s.quantity * p.cost) AS stock_value,
    CASE 
        WHEN s.quantity = 0 THEN 'out'
        WHEN s.quantity <= s.min_quantity THEN 'low'
        ELSE 'ok'
    END AS stock_status
FROM stock s
JOIN products p ON s.product_id = p.id
JOIN brands b ON p.brand_id = b.id;

-- Vue des ventes avec détails
CREATE VIEW v_sales_detail AS
SELECT 
    s.id,
    s.sale_number,
    CONCAT(c.first_name, ' ', c.last_name) AS client_name,
    c.type AS client_type,
    s.channel,
    s.status,
    s.total,
    s.sale_date,
    COUNT(sl.id) AS items_count
FROM sales s
JOIN clients c ON s.client_id = c.id
LEFT JOIN sale_lines sl ON s.id = sl.sale_id
GROUP BY s.id;

-- ============================================================================
-- TRIGGERS POUR AUTOMATISATION
-- ============================================================================

-- Trigger: Mise à jour automatique du stock après une vente
DELIMITER //
CREATE TRIGGER after_sale_line_insert
AFTER INSERT ON sale_lines
FOR EACH ROW
BEGIN
    UPDATE stock 
    SET quantity = quantity - NEW.quantity
    WHERE product_id = NEW.product_id AND size = NEW.size;
    
    INSERT INTO stock_movements (product_id, size, type, quantity, reference_type, reference_id, movement_date)
    VALUES (NEW.product_id, NEW.size, 'out', NEW.quantity, 'sale', NEW.sale_id, NOW());
END//
DELIMITER ;

-- ============================================================================
-- INDEX POUR PERFORMANCE
-- ============================================================================

-- Index sur les colonnes fréquemment recherchées
ALTER TABLE products ADD FULLTEXT INDEX idx_search (model, colorway, description);
ALTER TABLE clients ADD FULLTEXT INDEX idx_client_search (first_name, last_name, email);

-- ============================================================================
-- FIN DU SCRIPT
-- ============================================================================

-- Afficher un résumé
SELECT 'Base de données Adoo Sneakers créée avec succès!' AS message;