-- MySQL 5.6 compatibility fix:
-- InnoDB older versions have a 767-byte index limit.
-- Indexed utf8mb4 VARCHAR values are limited to 191 characters.
-- ============================================================
-- BALI TARANTULA ADVENTURE
-- Native PHP + MySQL / MAMP
-- Complete initial database
-- ============================================================

CREATE DATABASE IF NOT EXISTS `balitarantula`
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `balitarantula`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `booking_items`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `package_pickup_prices`;
DROP TABLE IF EXISTS `pickup_areas`;
DROP TABLE IF EXISTS `package_excludes`;
DROP TABLE IF EXISTS `package_includes`;
DROP TABLE IF EXISTS `package_highlights`;
DROP TABLE IF EXISTS `package_images`;
DROP TABLE IF EXISTS `packages`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `gallery`;
DROP TABLE IF EXISTS `blog_posts`;
DROP TABLE IF EXISTS `pages`;
DROP TABLE IF EXISTS `seo_meta`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `admins`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','editor') NOT NULL DEFAULT 'admin',
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(180) NOT NULL,
  `description` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_category_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `packages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NULL,
  `name` VARCHAR(180) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `short_description` TEXT NULL,
  `description` LONGTEXT NULL,
  `duration` VARCHAR(100) NULL,
  `price_adult` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `price_child` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `cover_image` VARCHAR(255) NULL,
  `seo_title` VARCHAR(255) NULL,
  `meta_description` VARCHAR(320) NULL,
  `focus_keyword` VARCHAR(190) NULL,
  `canonical_url` VARCHAR(191) NULL,
  `og_title` VARCHAR(255) NULL,
  `og_description` VARCHAR(320) NULL,
  `og_image` VARCHAR(191) NULL,
  `schema_json` LONGTEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_package_slug` (`slug`),
  KEY `idx_package_category` (`category_id`),
  CONSTRAINT `fk_package_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `package_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(191) NOT NULL,
  `alt_text` VARCHAR(255) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_package_images_package` (`package_id`),
  CONSTRAINT `fk_package_images_package`
    FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `package_highlights` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id` INT UNSIGNED NOT NULL,
  `highlight_text` VARCHAR(500) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_package_highlights_package` (`package_id`),
  CONSTRAINT `fk_package_highlights_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `package_includes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id` INT UNSIGNED NOT NULL,
  `item_text` VARCHAR(500) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_package_includes_package` (`package_id`),
  CONSTRAINT `fk_package_includes_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `package_excludes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id` INT UNSIGNED NOT NULL,
  `item_text` VARCHAR(500) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_package_excludes_package` (`package_id`),
  CONSTRAINT `fk_package_excludes_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pickup_areas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(180) NOT NULL,
  `description` VARCHAR(255) NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pickup_area_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `package_pickup_prices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id` INT UNSIGNED NOT NULL,
  `pickup_area_id` INT UNSIGNED NOT NULL,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_package_pickup` (`package_id`,`pickup_area_id`),
  CONSTRAINT `fk_pickup_price_package`
    FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pickup_price_area`
    FOREIGN KEY (`pickup_area_id`) REFERENCES `pickup_areas` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bookings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_code` VARCHAR(40) NOT NULL,
  `package_id` INT UNSIGNED NOT NULL,
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_email` VARCHAR(190) NOT NULL,
  `customer_country` VARCHAR(100) NULL,
  `customer_whatsapp` VARCHAR(50) NULL,
  `booking_date` DATE NOT NULL,
  `adults` INT UNSIGNED NOT NULL DEFAULT 1,
  `children` INT UNSIGNED NOT NULL DEFAULT 0,
  `pickup_required` TINYINT(1) NOT NULL DEFAULT 0,
  `pickup_area_id` INT UNSIGNED NULL,
  `hotel_name` VARCHAR(255) NULL,
  `room_number` VARCHAR(100) NULL,
  `pickup_time` TIME NULL,
  `special_request` TEXT NULL,
  `activity_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `pickup_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `currency` CHAR(3) NOT NULL DEFAULT 'IDR',
  `booking_status` ENUM('new','confirmed','paid','completed','cancelled') NOT NULL DEFAULT 'new',
  `payment_status` ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `admin_note` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_booking_code` (`booking_code`),
  KEY `idx_booking_date` (`booking_date`),
  KEY `idx_booking_status` (`booking_status`),
  KEY `idx_payment_status` (`payment_status`),
  CONSTRAINT `fk_booking_package`
    FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_booking_pickup_area`
    FOREIGN KEY (`pickup_area_id`) REFERENCES `pickup_areas` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `booking_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` BIGINT UNSIGNED NOT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `total_price` DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_booking_items_booking` (`booking_id`),
  CONSTRAINT `fk_booking_items_booking`
    FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_name` VARCHAR(150) NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `review_text` TEXT NOT NULL,
  `image` VARCHAR(255) NULL,
  `source` VARCHAR(100) NULL,
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `review_date` DATE NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gallery` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(180) NULL,
  `image_path` VARCHAR(191) NOT NULL,
  `alt_text` VARCHAR(255) NULL,
  `category` VARCHAR(100) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `blog_posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `excerpt` TEXT NULL,
  `content` LONGTEXT NOT NULL,
  `cover_image` VARCHAR(255) NULL,
  `author_name` VARCHAR(150) NULL,
  `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `published_at` DATETIME NULL,
  `seo_title` VARCHAR(255) NULL,
  `meta_description` VARCHAR(320) NULL,
  `focus_keyword` VARCHAR(190) NULL,
  `canonical_url` VARCHAR(191) NULL,
  `og_image` VARCHAR(191) NULL,
  `schema_json` LONGTEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blog_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `blog_highlights` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blog_id` INT UNSIGNED NOT NULL,
  `highlight_text` VARCHAR(500) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_blog_highlights_blog` (`blog_id`),
  CONSTRAINT `fk_blog_highlights_blog` FOREIGN KEY (`blog_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(191) NOT NULL,
  `content` LONGTEXT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_page_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `seo_meta` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_key` VARCHAR(191) NOT NULL,
  `seo_title` VARCHAR(255) NULL,
  `meta_description` VARCHAR(320) NULL,
  `focus_keyword` VARCHAR(190) NULL,
  `canonical_url` VARCHAR(191) NULL,
  `og_title` VARCHAR(255) NULL,
  `og_description` VARCHAR(320) NULL,
  `og_image` VARCHAR(191) NULL,
  `robots` VARCHAR(100) NOT NULL DEFAULT 'index,follow',
  `schema_json` LONGTEXT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seo_page_key` (`page_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(191) NOT NULL,
  `setting_value` LONGTEXT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories
INSERT INTO categories (name,slug,description,sort_order) VALUES
('Bali ATV Adventure','bali-atv-adventure','ATV adventure experiences in Bali.',1),
('Ayung River Rafting','ayung-river-rafting','White water rafting on the Ayung River.',2),
('Bali Swing','bali-swing','Bali swing and photo experiences.',3),
('Volkswagen Tour','volkswagen-tour','Classic Volkswagen tours around Bali.',4),
('Romantic Boat','romantic-boat','Romantic boat experiences in Bali.',5),
('Combo Packages','combo-packages','Adventure combination packages.',6);

-- Pickup areas
INSERT INTO pickup_areas (name,slug,sort_order) VALUES
('Ubud','ubud',1),
('Canggu','canggu',2),
('Seminyak','seminyak',3),
('Kuta','kuta',4),
('Legian','legian',5),
('Sanur','sanur',6),
('Nusa Dua','nusa-dua',7),
('Jimbaran','jimbaran',8),
('Denpasar','denpasar',9),
('Kerobokan','kerobokan',10),
('Other Area','other-area',99);

-- Demo packages
INSERT INTO packages
(category_id,name,slug,short_description,description,duration,price_adult,price_child,featured,status,seo_title,meta_description,focus_keyword)
SELECT id,
'Bali ATV Adventure',
'bali-atv-adventure',
'Exciting ATV adventure through Bali nature, village paths and scenic tracks.',
'Enjoy an exciting ATV adventure through scenic tracks, villages and natural landscapes in Bali.',
'2-3 Hours',350000,0,1,1,
'Bali ATV Adventure | Bali Tarantula Adventure',
'Enjoy an exciting Bali ATV Adventure through scenic tracks, villages and nature with Bali Tarantula Adventure.',
'Bali ATV Adventure'
FROM categories WHERE slug='bali-atv-adventure';

INSERT INTO packages
(category_id,name,slug,short_description,description,duration,price_adult,price_child,featured,status,seo_title,meta_description,focus_keyword)
SELECT id,
'Ayung River Rafting',
'ayung-river-rafting',
'Fun and refreshing white water rafting adventure on the Ayung River.',
'Experience a fun rafting adventure on the Ayung River with beautiful scenery and professional guides.',
'2-3 Hours',400000,0,1,1,
'Ayung River Rafting Bali | Bali Tarantula Adventure',
'Experience Ayung River Rafting in Bali with an exciting river adventure, professional guides and beautiful scenery.',
'Ayung River Rafting Bali'
FROM categories WHERE slug='ayung-river-rafting';

INSERT INTO packages
(category_id,name,slug,short_description,description,duration,price_adult,price_child,featured,status,seo_title,meta_description,focus_keyword)
SELECT id,
'Bali ATV + Rafting + Swing',
'bali-atv-rafting-swing',
'Combine ATV adventure, Ayung River rafting and Bali swing in one exciting package.',
'Combine three popular Bali experiences in one convenient adventure package.',
'Full Day',850000,0,1,1,
'Bali ATV Rafting Swing Package | Bali Tarantula Adventure',
'Enjoy a Bali ATV, rafting and swing combination package with adventure, nature and memorable Bali experiences.',
'Bali ATV Rafting Swing'
FROM categories WHERE slug='combo-packages';

-- Pickup prices for ATV demo package
INSERT INTO package_pickup_prices (package_id,pickup_area_id,price)
SELECT p.id,a.id,
CASE a.slug
 WHEN 'ubud' THEN 100000
 WHEN 'canggu' THEN 150000
 WHEN 'seminyak' THEN 150000
 WHEN 'kuta' THEN 150000
 WHEN 'legian' THEN 150000
 WHEN 'sanur' THEN 150000
 WHEN 'nusa-dua' THEN 200000
 WHEN 'jimbaran' THEN 200000
 WHEN 'denpasar' THEN 150000
 WHEN 'kerobokan' THEN 150000
 ELSE 200000
END
FROM packages p CROSS JOIN pickup_areas a
WHERE p.slug='bali-atv-adventure';

-- Settings
INSERT INTO settings (setting_key,setting_value) VALUES
('site_name','Bali Tarantula Adventure'),
('site_url','http://localhost:8888/balitarantulaadventure'),
('site_email',''),
('site_whatsapp',''),
('site_phone',''),
('site_address','Bali, Indonesia'),
('currency','IDR'),
('timezone','Asia/Makassar');

-- SEO
INSERT INTO seo_meta
(page_key,seo_title,meta_description,focus_keyword,canonical_url,robots)
VALUES
('home',
'Bali Tarantula Adventure | ATV, Rafting, Swing & Bali Tours',
'Book Bali ATV Adventure, Ayung River Rafting, Bali Swing, Volkswagen Tour and exciting Bali activity packages with Bali Tarantula Adventure.',
'Bali Tarantula Adventure',
'http://localhost:8888/balitarantulaadventure/',
'index,follow');

-- NOTE:
-- Admin user is intentionally NOT inserted here.
-- The included setup_admin.php creates a secure password_hash().
