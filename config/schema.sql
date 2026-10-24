-- ═══════════════════════════════════════════════════════════════════════
-- Gadget Genesis — Database Schema
-- Run this once in HeidiSQL / phpMyAdmin
-- ═══════════════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS `web_products`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `web_products`;

-- ── Products ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `products` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `model`       VARCHAR(255)    NOT NULL,
  `image`       LONGTEXT        DEFAULT NULL COMMENT 'base64 encoded image',
  `description` TEXT            DEFAULT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Collections (many-to-many) ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_collections` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `collection` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_col_product`
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Storage variants (price editable) ────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_storages` (
  `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED   NOT NULL,
  `label`      VARCHAR(100)   NOT NULL,
  `price`      DECIMAL(12,2)  NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_stor_product`
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Color variants ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_colors` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `image`      LONGTEXT     DEFAULT NULL COMMENT 'base64 encoded image',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_color_product`
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Reusable storage labels (autocomplete) ────────────────────────────
CREATE TABLE IF NOT EXISTS `storages` (
  `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_storage_label` (`label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Reusable color names (autocomplete) ──────────────────────────────
CREATE TABLE IF NOT EXISTS `colors` (
  `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_color_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Orders ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `orders` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `order_number`    VARCHAR(30)     NOT NULL,
  `customer_name`   VARCHAR(255)    NOT NULL,
  `customer_phone`  VARCHAR(50)     NOT NULL,
  `customer_email`  VARCHAR(255)    DEFAULT NULL,
  `address`         VARCHAR(500)    NOT NULL,
  `city`            VARCHAR(150)    NOT NULL,
  `country`         VARCHAR(10)     NOT NULL DEFAULT 'UG',
  `district`        VARCHAR(150)    DEFAULT NULL,
  `notes`           TEXT            DEFAULT NULL,
  `payment_method`  VARCHAR(100)    NOT NULL,
  `shipping_method` VARCHAR(100)    NOT NULL,
  `subtotal`        DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `shipping_cost`   DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `discount`        DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `total`           DECIMAL(14,2)   NOT NULL DEFAULT 0,
  `status`          ENUM('pending','processing','shipped','delivered','cancelled')
                                    NOT NULL DEFAULT 'pending',
  `customer_id`     INT UNSIGNED    DEFAULT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_number` (`order_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Order Items ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `order_id`   INT UNSIGNED   NOT NULL,
  `product_id` INT UNSIGNED   DEFAULT NULL,
  `model`      VARCHAR(255)   NOT NULL,
  `storage`    VARCHAR(100)   DEFAULT NULL,
  `color`      VARCHAR(100)   DEFAULT NULL,
  `image`      LONGTEXT       DEFAULT NULL COMMENT 'base64 or URL of product image at time of order',
  `price`      DECIMAL(12,2)  NOT NULL,
  `qty`        SMALLINT       NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_oi_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Customers ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `customers` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(255)  NOT NULL,
  `phone`         VARCHAR(50)   NOT NULL,
  `email`         VARCHAR(255)  DEFAULT NULL,
  `password_hash` VARCHAR(255)  NOT NULL,
  `address`       VARCHAR(500)  DEFAULT NULL,
  `city`          VARCHAR(150)  DEFAULT NULL,
  `district`      VARCHAR(150)  DEFAULT NULL,
  `country`       VARCHAR(10)   NOT NULL DEFAULT 'UG',
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_phone` (`phone`),
  UNIQUE KEY `uq_customer_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Add customer_id FK to orders (customers table must exist first) ──
-- NOTE: Run only once. If re-running this script on an existing DB,
--       comment out this ALTER if the constraint already exists.
ALTER TABLE `orders`
  ADD CONSTRAINT IF NOT EXISTS `fk_order_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL;

-- ── Coupons ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `coupons` (
  `id`            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `code`          VARCHAR(50)    NOT NULL,
  `discount_pct`  TINYINT        NOT NULL DEFAULT 0  COMMENT 'percentage off, e.g. 10 = 10%',
  `max_uses`      INT UNSIGNED   DEFAULT NULL        COMMENT 'NULL = unlimited',
  `used_count`    INT UNSIGNED   NOT NULL DEFAULT 0,
  `expires_at`    DATETIME       DEFAULT NULL        COMMENT 'NULL = never expires',
  `active`        TINYINT(1)     NOT NULL DEFAULT 1,
  `created_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed some starter coupons (same as the old hardcoded ones)
INSERT IGNORE INTO `coupons` (code, discount_pct, max_uses, expires_at, active) VALUES
  ('SAVE10',    10, NULL, NULL, 1),
  ('GADGET20',  20, NULL, NULL, 1),
  ('WELCOME15', 15, 100,  NULL, 1);

-- ── Admin Users ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `username`        VARCHAR(100)  NOT NULL,
  `password_hash`   VARCHAR(255)  NOT NULL,
  `must_change_pw`  TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin: username=admin password=admin123 (change after first login)
INSERT IGNORE INTO `admin_users` (username, password_hash, must_change_pw)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- ── Product Specifications ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_specs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `spec_key`   VARCHAR(100) NOT NULL,
  `spec_value` VARCHAR(500) NOT NULL,
  `sort_order` SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_spec_product`
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Product Reviews ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_reviews` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `product_id`  INT UNSIGNED  NOT NULL,
  `customer_id` INT UNSIGNED  DEFAULT NULL,
  `name`        VARCHAR(100)  NOT NULL,
  `rating`      TINYINT       NOT NULL DEFAULT 5 COMMENT '1-5',
  `body`        TEXT          NOT NULL,
  `approved`    TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_rev_product`
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Product Discounts ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_discounts` (
  `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED   NOT NULL,
  `type`       ENUM('pct','fixed') NOT NULL DEFAULT 'pct'
               COMMENT 'pct = percentage off, fixed = new flat price',
  `value`      DECIMAL(12,2)  NOT NULL COMMENT 'percent 0-100 or fixed price in UGX',
  `expires_at` DATETIME       DEFAULT NULL COMMENT 'NULL = never expires',
  `active`     TINYINT(1)     NOT NULL DEFAULT 1,
  `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_discount` (`product_id`),
  CONSTRAINT `fk_disc_product`
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
