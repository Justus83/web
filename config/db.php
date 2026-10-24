<?php

define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'web_products');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

try {
    // Connect without database first to create it if needed
    $pdoSetup = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET),
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdoSetup->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    unset($pdoSetup);

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Auto-create tables if they don't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
        `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `model`       VARCHAR(255) NOT NULL,
        `image`       LONGTEXT DEFAULT NULL,
        `description` TEXT DEFAULT NULL,
        `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_collections` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `product_id` INT UNSIGNED NOT NULL,
        `collection` VARCHAR(100) NOT NULL,
        PRIMARY KEY (`id`),
        CONSTRAINT `fk_col_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_storages` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `product_id` INT UNSIGNED NOT NULL,
        `label`      VARCHAR(100) NOT NULL,
        `price`      DECIMAL(12,2) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        CONSTRAINT `fk_stor_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_colors` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `product_id` INT UNSIGNED NOT NULL,
        `name`       VARCHAR(100) NOT NULL,
        `image`      LONGTEXT DEFAULT NULL,
        PRIMARY KEY (`id`),
        CONSTRAINT `fk_color_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `storages` (
        `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `label` VARCHAR(100) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_storage_label` (`label`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `colors` (
        `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name`  VARCHAR(100) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_color_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `customers` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `orders` (
        `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `order_number`    VARCHAR(30)   NOT NULL,
        `customer_name`   VARCHAR(255)  NOT NULL,
        `customer_phone`  VARCHAR(50)   NOT NULL,
        `customer_email`  VARCHAR(255)  DEFAULT NULL,
        `address`         VARCHAR(500)  NOT NULL,
        `city`            VARCHAR(150)  NOT NULL,
        `country`         VARCHAR(10)   NOT NULL DEFAULT 'UG',
        `district`        VARCHAR(150)  DEFAULT NULL,
        `notes`           TEXT          DEFAULT NULL,
        `payment_method`  VARCHAR(100)  NOT NULL,
        `shipping_method` VARCHAR(100)  NOT NULL,
        `subtotal`        DECIMAL(14,2) NOT NULL DEFAULT 0,
        `shipping_cost`   DECIMAL(14,2) NOT NULL DEFAULT 0,
        `discount`        DECIMAL(14,2) NOT NULL DEFAULT 0,
        `total`           DECIMAL(14,2) NOT NULL DEFAULT 0,
        `status`          ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
        `customer_id`     INT UNSIGNED  DEFAULT NULL,
        `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_order_number` (`order_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `coupons` (
        `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `code`         VARCHAR(50)  NOT NULL,
        `discount_pct` TINYINT      NOT NULL DEFAULT 0,
        `max_uses`     INT UNSIGNED DEFAULT NULL,
        `used_count`   INT UNSIGNED NOT NULL DEFAULT 0,
        `expires_at`   DATETIME     DEFAULT NULL,
        `active`       TINYINT(1)   NOT NULL DEFAULT 1,
        `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_coupon_code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed starter coupons
    $pdo->exec("INSERT IGNORE INTO `coupons` (code, discount_pct, max_uses, active) VALUES
        ('SAVE10', 10, NULL, 1),
        ('GADGET20', 20, NULL, 1),
        ('WELCOME15', 15, 100, 1)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_users` (
        `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `username`       VARCHAR(100) NOT NULL,
        `password_hash`  VARCHAR(255) NOT NULL,
        `must_change_pw` TINYINT(1)   NOT NULL DEFAULT 0,
        `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_admin_username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed default admin: username=admin password=admin123
    $pdo->exec("INSERT IGNORE INTO `admin_users` (username, password_hash, must_change_pw)
        VALUES ('admin', '\$2y\$10\$nWnwLJmM7Oh/FPCGWUa6X.Z1/riD7fY1K1VXXmjIMUpTsXORz732u', 0)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_discounts` (
        `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
        `product_id` INT UNSIGNED   NOT NULL,
        `type`       ENUM('pct','fixed') NOT NULL DEFAULT 'pct',
        `value`      DECIMAL(12,2)  NOT NULL,
        `expires_at` DATETIME       DEFAULT NULL,
        `active`     TINYINT(1)     NOT NULL DEFAULT 1,
        `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_product_discount` (`product_id`),
        CONSTRAINT `fk_disc_product`
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Add customer_id column to existing orders tables that predate this migration
    try { $pdo->exec("ALTER TABLE `orders` ADD COLUMN `customer_id` INT UNSIGNED DEFAULT NULL"); } catch (PDOException $ignored) {}
    // Add FK only if not already present
    try {
        $pdo->exec("ALTER TABLE `orders` ADD CONSTRAINT `fk_order_customer`
            FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL");
    } catch (PDOException $ignored) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS `order_items` (
        `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `order_id`   INT UNSIGNED  NOT NULL,
        `product_id` INT UNSIGNED  DEFAULT NULL,
        `model`      VARCHAR(255)  NOT NULL,
        `storage`    VARCHAR(100)  DEFAULT NULL,
        `color`      VARCHAR(100)  DEFAULT NULL,
        `image`      LONGTEXT      DEFAULT NULL,
        `price`      DECIMAL(12,2) NOT NULL,
        `qty`        SMALLINT      NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Add image column to order_items for existing tables
    try { $pdo->exec("ALTER TABLE `order_items` ADD COLUMN `image` LONGTEXT DEFAULT NULL AFTER `color`"); } catch (PDOException $ignored) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_specs` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `product_id` INT UNSIGNED NOT NULL,
        `spec_key`   VARCHAR(100) NOT NULL,
        `spec_value` VARCHAR(500) NOT NULL,
        `sort_order` SMALLINT     NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        CONSTRAINT `fk_spec_product`
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_reviews` (
        `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        `product_id` INT UNSIGNED  NOT NULL,
        `customer_id`INT UNSIGNED  DEFAULT NULL,
        `name`       VARCHAR(100)  NOT NULL,
        `rating`     TINYINT       NOT NULL DEFAULT 5 COMMENT '1-5',
        `body`       TEXT          NOT NULL,
        `approved`   TINYINT(1)    NOT NULL DEFAULT 1,
        `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        CONSTRAINT `fk_rev_product`
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Task #3: Order tracking number ──
    try { $pdo->exec("ALTER TABLE `orders` ADD COLUMN `tracking_number` VARCHAR(100) DEFAULT NULL"); } catch (PDOException $ignored) {}

    // ── Task #7: Featured products ──
    try { $pdo->exec("ALTER TABLE `products` ADD COLUMN `is_featured` TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $ignored) {}

    // ── Task #8: Multi-image gallery ──
    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_images` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `product_id` INT UNSIGNED NOT NULL,
        `image`      LONGTEXT     NOT NULL COMMENT 'base64 encoded image',
        `caption`    VARCHAR(255) DEFAULT NULL,
        `sort_order` SMALLINT     NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        CONSTRAINT `fk_pimg_product`
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Task #9: Newsletter subscribers ──
    $pdo->exec("CREATE TABLE IF NOT EXISTS `newsletter` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `email`      VARCHAR(255) NOT NULL,
        `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_newsletter_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}
