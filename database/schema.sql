-- =====================================================================
-- Restaurant Project - Database Schema v2 (Phase 2: Normalized + RBAC)
-- Database: restaurant_db
--
-- This REPLACES the Phase 1 schema. Key changes from v1:
--   - users.role (ENUM) removed -> replaced with users.role_id (FK to roles)
--   - New tables: roles, permissions, role_permissions (many-to-many RBAC)
--   - Added remember-me token columns to users (for Phase 3 "Remember Me")
--   - Added proper constraints/indexes throughout
-- =====================================================================

DROP DATABASE IF EXISTS restaurant_db;
CREATE DATABASE restaurant_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE restaurant_db;

-- ---------------------------------------------------------------------
-- 1. ROLES
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50)  NOT NULL UNIQUE COMMENT 'Admin, Manager, Staff, Customer',
    description VARCHAR(255) DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. PERMISSIONS  (granular capability keys, e.g. 'users.create')
-- ---------------------------------------------------------------------
CREATE TABLE permissions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key`       VARCHAR(80)  NOT NULL UNIQUE COMMENT 'e.g. users.create, orders.edit',
    module      VARCHAR(50)  NOT NULL COMMENT 'e.g. users, orders, menu',
    description VARCHAR(255) DEFAULT NULL,
    INDEX idx_module (module)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. ROLE_PERMISSIONS  (many-to-many junction: which roles have which permissions)
-- ---------------------------------------------------------------------
CREATE TABLE role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. USERS  (admin/staff + site customers, role now normalized via role_id)
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name             VARCHAR(150) NOT NULL,
    username              VARCHAR(60)  NOT NULL UNIQUE,
    email                 VARCHAR(150) NOT NULL UNIQUE,
    phone                 VARCHAR(30)  DEFAULT NULL,
    password              VARCHAR(255) NOT NULL,
    role_id               INT UNSIGNED NOT NULL,
    avatar                VARCHAR(255) DEFAULT NULL,
    status                TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=active,0=suspended',
    remember_token        VARCHAR(255) DEFAULT NULL,
    remember_token_expires DATETIME DEFAULT NULL,
    last_login_at         DATETIME DEFAULT NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    INDEX idx_role (role_id),
    INDEX idx_status (status),
    FULLTEXT INDEX ft_user_search (full_name, email, username)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. CATEGORIES
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(120) NOT NULL,
    slug       VARCHAR(140) NOT NULL UNIQUE,
    image      VARCHAR(255) DEFAULT NULL,
    status     TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. MENU ITEMS
-- ---------------------------------------------------------------------
CREATE TABLE menu_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    title       VARCHAR(160) NOT NULL,
    slug        VARCHAR(180) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    price       DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
    image       VARCHAR(255) DEFAULT NULL,
    featured    TINYINT(1) NOT NULL DEFAULT 0,
    status      TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_menu_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_status (status),
    INDEX idx_featured (featured),
    INDEX idx_category (category_id),
    INDEX idx_category_status (category_id, status),
    FULLTEXT INDEX ft_title (title)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. RESERVATIONS
-- ---------------------------------------------------------------------
CREATE TABLE reservations (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED DEFAULT NULL COMMENT 'NULL = guest reservation',
    full_name         VARCHAR(150) NOT NULL,
    email             VARCHAR(150) NOT NULL,
    phone             VARCHAR(30)  NOT NULL,
    reservation_date  DATE NOT NULL,
    reservation_time  TIME NOT NULL,
    guests            SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    message           TEXT DEFAULT NULL,
    status            ENUM('Pending','Confirmed','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservation_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_date (reservation_date),
    INDEX idx_user (user_id),
    INDEX idx_status_date (status, reservation_date),
    FULLTEXT INDEX ft_customer (full_name, email, phone)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. ORDERS
-- ---------------------------------------------------------------------
CREATE TABLE orders (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED DEFAULT NULL COMMENT 'NULL = guest checkout',
    full_name      VARCHAR(150) NOT NULL,
    email          VARCHAR(150) NOT NULL,
    phone          VARCHAR(30)  NOT NULL,
    address        TEXT NOT NULL,
    payment_method VARCHAR(60)  NOT NULL DEFAULT 'Cash on Delivery',
    total_price    DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
    status         ENUM('Pending','Preparing','Out for Delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_user (user_id),
    INDEX idx_status_created (status, created_at),
    FULLTEXT INDEX ft_customer (full_name, email, phone)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. ORDER ITEMS
-- ---------------------------------------------------------------------
CREATE TABLE order_items (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id     INT UNSIGNED NOT NULL,
    menu_item_id INT UNSIGNED DEFAULT NULL,
    title        VARCHAR(160) NOT NULL COMMENT 'snapshot of item name at order time',
    price        DECIMAL(10,2) UNSIGNED NOT NULL COMMENT 'snapshot of unit price at order time (server-verified)',
    quantity     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_orderitem_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_orderitem_menu FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE SET NULL,
    INDEX idx_order (order_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. CONTACT MESSAGES  (+ reply support for Phase 4)
-- ---------------------------------------------------------------------
CREATE TABLE contact_messages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(150) NOT NULL,
    email       VARCHAR(150) NOT NULL,
    phone       VARCHAR(30) DEFAULT NULL,
    subject     VARCHAR(150) DEFAULT NULL,
    message     TEXT NOT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    reply       TEXT DEFAULT NULL,
    replied_by  INT UNSIGNED DEFAULT NULL,
    replied_at  DATETIME DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_message_replied_by FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_is_read (is_read),
    FULLTEXT INDEX ft_message (full_name, email, subject, message)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. SETTINGS  (single-row site configuration, editable from admin)
-- ---------------------------------------------------------------------
CREATE TABLE settings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_name       VARCHAR(150) NOT NULL DEFAULT 'Restaurant',
    phone           VARCHAR(30)  DEFAULT NULL,
    email           VARCHAR(150) DEFAULT NULL,
    address         VARCHAR(255) DEFAULT NULL,
    opening_hours   VARCHAR(150) DEFAULT NULL,
    facebook        VARCHAR(255) DEFAULT NULL,
    instagram       VARCHAR(255) DEFAULT NULL,
    twitter         VARCHAR(255) DEFAULT NULL,
    youtube         VARCHAR(255) DEFAULT NULL,
    currency_symbol VARCHAR(5)   NOT NULL DEFAULT '$',
    logo            VARCHAR(255) DEFAULT NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 12. ACTIVITY LOGS  (admin audit trail)
-- ---------------------------------------------------------------------
CREATE TABLE activity_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED DEFAULT NULL,
    action      VARCHAR(100) NOT NULL COMMENT 'e.g. order_status_change, user_created, menu_item_deleted',
    description VARCHAR(255) DEFAULT NULL,
    old_value   TEXT DEFAULT NULL COMMENT 'JSON snapshot of the record before the change',
    new_value   TEXT DEFAULT NULL COMMENT 'JSON snapshot of the record after the change',
    ip_address  VARCHAR(45) DEFAULT NULL,
    user_agent  VARCHAR(255) DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 13. PASSWORD RESETS  (forgot-password flow)
-- ---------------------------------------------------------------------
CREATE TABLE password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(150) NOT NULL,
    token      VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE=InnoDB;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Roles
INSERT INTO roles (id, name, description) VALUES
(1, 'Admin',   'Full access to everything, including staff management and settings'),
(2, 'Manager', 'Manages day-to-day operations: menu, orders, reservations, messages'),
(3, 'Staff',   'Handles orders, reservations, and customer messages only'),
(4, 'Customer','Registered site customer (order history, no admin access)');

-- Permissions (module.action convention)
INSERT INTO permissions (`key`, module, description) VALUES
('users.view',          'users',        'View staff/user accounts'),
('users.create',        'users',        'Create staff/user accounts'),
('users.edit',          'users',        'Edit staff/user accounts'),
('users.delete',        'users',        'Delete staff/user accounts'),
('categories.view',     'categories',   'View menu categories'),
('categories.create',   'categories',   'Create menu categories'),
('categories.edit',     'categories',   'Edit menu categories'),
('categories.delete',   'categories',   'Delete menu categories'),
('menu.view',           'menu',         'View menu items'),
('menu.create',         'menu',         'Create menu items'),
('menu.edit',           'menu',         'Edit menu items'),
('menu.delete',         'menu',         'Delete menu items'),
('orders.view',         'orders',       'View orders'),
('orders.edit',         'orders',       'Edit / change order status'),
('orders.delete',       'orders',       'Delete orders'),
('reservations.view',   'reservations', 'View reservations'),
('reservations.edit',   'reservations', 'Edit / change reservation status'),
('reservations.delete', 'reservations', 'Delete reservations'),
('messages.view',       'messages',     'View contact messages'),
('messages.reply',      'messages',     'Reply to contact messages'),
('messages.delete',     'messages',     'Delete contact messages'),
('settings.edit',       'settings',     'Edit site settings'),
('activity_logs.view',  'activity_logs','View the admin activity log');

-- Role <-> Permission mapping
-- Admin: every permission
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Manager: everything except users.delete and users.create (can view/edit staff, not create/remove them)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions
WHERE `key` NOT IN ('users.create', 'users.delete');

-- Staff: operational permissions only (orders, reservations, messages, read-only menu/categories)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions
WHERE `key` IN (
  'menu.view', 'categories.view',
  'orders.view', 'orders.edit',
  'reservations.view', 'reservations.edit',
  'messages.view', 'messages.reply'
);

-- Default settings row
INSERT INTO settings (site_name, phone, email, address, opening_hours, facebook, instagram, twitter, currency_symbol)
VALUES ('SarabFood', '+1 (800) 123-4567', 'hello@sarabfood.com', '42 Flavor Street, Manhattan, New York, NY 10001', 'Wed - Sun: 9 AM - 11 PM', '#', '#', '#', '$');

-- Default admin user
-- Password below is a bcrypt hash for: Admin@123  (CHANGE THIS immediately after first login)
INSERT INTO users (full_name, username, email, phone, password, role_id, status)
VALUES ('Super Admin', 'admin', 'admin@sarabfood.com', NULL, '$2y$10$l4pN7ZInHSfBdcn7ry8cb.JPP7rGDkUis53kKl/2dsukawHQfsajG', 1, 1);

-- Sample categories
INSERT INTO categories (title, slug, status, sort_order) VALUES
('Burgers', 'burgers', 1, 1),
('Pizza', 'pizza', 1, 2),
('Drinks', 'drinks', 1, 3),
('Desserts', 'desserts', 1, 4);
