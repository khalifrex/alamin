-- ==========================================================
-- Stallion Custom Apparels — MySQL / MariaDB schema (XAMPP)
-- Import via phpMyAdmin:  http://localhost/phpmyadmin  ->  Import
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `stallion_db`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `stallion_db`;

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `fullname`      VARCHAR(150) NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `phone`         VARCHAR(50)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_ref`        VARCHAR(20)  NOT NULL,
  `user_id`          INT UNSIGNED NOT NULL,
  `customer_name`    VARCHAR(150) NOT NULL,
  `customer_email`   VARCHAR(150) NOT NULL,
  `customer_phone`   VARCHAR(50)  NOT NULL,
  `delivery_address` VARCHAR(255) NOT NULL,
  `delivery_city`    VARCHAR(100) NOT NULL,
  `delivery_state`   VARCHAR(100) NOT NULL,
  `notes`            VARCHAR(255) NULL,
  `shirt_type`       VARCHAR(20)  NOT NULL,
  `shirt_size`       VARCHAR(10)  NOT NULL,
  `shirt_color`      VARCHAR(7)   NOT NULL,
  `quantity`         INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price`       DECIMAL(12,2) NOT NULL,
  `discount_pct`     DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `total_amount`     DECIMAL(12,2) NOT NULL,
  `payment_method`   VARCHAR(20)  NOT NULL DEFAULT 'bank',
  `payment_status`   VARCHAR(30)  NOT NULL DEFAULT 'awaiting_payment',
  `order_status`     VARCHAR(30)  NOT NULL DEFAULT 'new',
  `design_front`     VARCHAR(80)  NULL COMMENT 'preview PNG in uploads/designs/',
  `design_back`      VARCHAR(80)  NULL,
  `design_data`      LONGTEXT     NOT NULL COMMENT 'JSON layers: text/images/positions for the printer',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_orders_ref` (`order_ref`),
  KEY `idx_orders_user` (`user_id`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- Admin panel + editable catalogue (included here for fresh installs)
-- ==========================================================

CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username`      VARCHAR(50)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `catalog_types` (
  `code`       VARCHAR(20) NOT NULL PRIMARY KEY COMMENT 'matches the shapes drawn by designer.js: tshirt, polo, hoodie, longsleeve',
  `name`       VARCHAR(50) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `catalog_sizes` (
  `code`       VARCHAR(10) NOT NULL PRIMARY KEY,
  `base_price` DECIMAL(12,2) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `catalog_colors` (
  `hex`        CHAR(7)     NOT NULL PRIMARY KEY,
  `name`       VARCHAR(50) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `catalog_discounts` (
  `min_qty` INT UNSIGNED NOT NULL PRIMARY KEY,
  `percent` DECIMAL(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(40)  NOT NULL PRIMARY KEY,
  `setting_value` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- starting catalogue (same values the shop used before) ----------
INSERT IGNORE INTO `catalog_types` (`code`, `name`, `sort_order`) VALUES
  ('tshirt','T-Shirt',1), ('polo','Polo',2), ('hoodie','Hoodie',3), ('longsleeve','Long Sleeve',4);

INSERT IGNORE INTO `catalog_sizes` (`code`, `base_price`, `sort_order`) VALUES
  ('XS',3000,1), ('S',3200,2), ('M',3500,3), ('L',3800,4), ('XL',4200,5), ('2XL',4800,6), ('3XL',5500,7);

INSERT IGNORE INTO `catalog_colors` (`hex`, `name`, `sort_order`) VALUES
  ('#FFFFFF','White',1), ('#000000','Black',2), ('#FF0000','Red',3), ('#1E3A8A','Navy Blue',4),
  ('#2563EB','Royal Blue',5), ('#16A34A','Forest Green',6), ('#DC6800','Orange',7), ('#7E22CE','Purple',8),
  ('#DB2777','Hot Pink',9), ('#6B7280','Gray',10), ('#92400E','Brown',11), ('#0EA5E9','Sky Blue',12);

INSERT IGNORE INTO `catalog_discounts` (`min_qty`, `percent`) VALUES (5,5), (10,10), (20,15), (50,20);

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES ('image_fee','500'), ('text_fee','300');

-- Starter admin: username admin / password admin123 — CHANGE IT after the first sign-in (Admin > Account).
INSERT IGNORE INTO `admins` (`username`, `password_hash`) VALUES ('admin', '$2y$10$qELirzGyxDWYJZWRWdiFtuxtnHXk9D45ybCP4FG3NXJyx4Kz5IsZ6');
