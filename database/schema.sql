-- Bali Tarantula Adventure booking system schema
-- Import this file once into the project's MySQL/MariaDB database.
SET NAMES utf8mb4;
SET time_zone = '+08:00';

CREATE TABLE IF NOT EXISTS admins (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('owner','admin','operations','finance','redeemer','content') NOT NULL DEFAULT 'admin',
 status ENUM('active','disabled') NOT NULL DEFAULT 'active',
 last_login_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activities (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(160) NOT NULL,
 slug VARCHAR(180) NOT NULL UNIQUE,
 short_description VARCHAR(500) NULL,
 description MEDIUMTEXT NULL,
 status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
 sort_order INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS packages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 activity_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(180) NOT NULL,
 slug VARCHAR(200) NOT NULL UNIQUE,
 description MEDIUMTEXT NULL,
 duration_minutes SMALLINT UNSIGNED NULL,
 price_per_person DECIMAL(12,2) NOT NULL DEFAULT 0,
 currency CHAR(3) NOT NULL DEFAULT 'IDR',
 pickup_available TINYINT(1) NOT NULL DEFAULT 0,
 pickup_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
 min_participants SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 max_participants SMALLINT UNSIGNED NULL,
 status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
 featured TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_packages_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 INDEX idx_packages_status (status, featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS package_images (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 package_id BIGINT UNSIGNED NOT NULL,
 image_path VARCHAR(500) NOT NULL,
 alt_text VARCHAR(255) NULL,
 sort_order INT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_package_images_package FOREIGN KEY (package_id) REFERENCES packages(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_slots (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 package_id BIGINT UNSIGNED NOT NULL,
 slot_date DATE NOT NULL,
 start_time TIME NOT NULL,
 capacity INT UNSIGNED NOT NULL,
 reserved_count INT UNSIGNED NOT NULL DEFAULT 0,
 status ENUM('open','closed') NOT NULL DEFAULT 'open',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_slots_package FOREIGN KEY (package_id) REFERENCES packages(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 UNIQUE KEY uq_package_slot (package_id, slot_date, start_time),
 INDEX idx_slot_search (slot_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(160) NOT NULL,
 email VARCHAR(190) NOT NULL,
 phone VARCHAR(40) NULL,
 country VARCHAR(100) NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_customers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pickup_areas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(160) NOT NULL,
 fee DECIMAL(12,2) NOT NULL DEFAULT 0,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 booking_code VARCHAR(40) NOT NULL UNIQUE,
 customer_id BIGINT UNSIGNED NOT NULL,
 package_id BIGINT UNSIGNED NOT NULL,
 slot_id BIGINT UNSIGNED NOT NULL,
 activity_date DATE NOT NULL,
 activity_time TIME NOT NULL,
 adults SMALLINT UNSIGNED NOT NULL,
 children SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 participants SMALLINT UNSIGNED NOT NULL,
 price_per_person_snapshot DECIMAL(12,2) NOT NULL,
 subtotal DECIMAL(12,2) NOT NULL,
 pickup_required TINYINT(1) NOT NULL DEFAULT 0,
 pickup_area_id BIGINT UNSIGNED NULL,
 pickup_area_snapshot VARCHAR(160) NULL,
 pickup_address VARCHAR(500) NULL,
 pickup_fee_snapshot DECIMAL(12,2) NOT NULL DEFAULT 0,
 discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 extras_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 total_amount DECIMAL(12,2) NOT NULL,
 currency CHAR(3) NOT NULL DEFAULT 'IDR',
 booking_status ENUM('pending','confirmed','cancelled','expired','completed') NOT NULL DEFAULT 'pending',
 payment_status ENUM('unpaid','pending_review','paid','failed','refunded','partially_refunded') NOT NULL DEFAULT 'unpaid',
 ticket_status ENUM('not_issued','issued','partially_redeemed','redeemed','void') NOT NULL DEFAULT 'not_issued',
 reservation_expires_at DATETIME NULL,
 customer_note TEXT NULL,
 source VARCHAR(40) NOT NULL DEFAULT 'website',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bookings_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bookings_slot FOREIGN KEY (slot_id) REFERENCES activity_slots(id) ON DELETE RESTRICT,
 CONSTRAINT fk_bookings_pickup_area FOREIGN KEY (pickup_area_id) REFERENCES pickup_areas(id) ON DELETE SET NULL,
 INDEX idx_bookings_date_status (activity_date, booking_status),
 INDEX idx_bookings_payment (payment_status, created_at),
 INDEX idx_bookings_expiry (booking_status, reservation_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 booking_id BIGINT UNSIGNED NOT NULL,
 item_type ENUM('extra','discount') NOT NULL,
 item_name VARCHAR(160) NOT NULL,
 quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 unit_amount DECIMAL(12,2) NOT NULL,
 total_amount DECIMAL(12,2) NOT NULL,
 CONSTRAINT fk_booking_items_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 booking_id BIGINT UNSIGNED NOT NULL,
 method ENUM('bank_transfer','payment_gateway','cash','other') NOT NULL,
 provider VARCHAR(80) NULL,
 provider_reference VARCHAR(190) NULL,
 amount DECIMAL(12,2) NOT NULL,
 currency CHAR(3) NOT NULL DEFAULT 'IDR',
 status ENUM('pending','submitted','paid','failed','rejected','refunded') NOT NULL DEFAULT 'pending',
 proof_path VARCHAR(500) NULL,
 gateway_event_id VARCHAR(190) NULL,
 verified_by BIGINT UNSIGNED NULL,
 verified_at DATETIME NULL,
 paid_at DATETIME NULL,
 notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT,
 CONSTRAINT fk_payments_admin FOREIGN KEY (verified_by) REFERENCES admins(id) ON DELETE SET NULL,
 UNIQUE KEY uq_gateway_event (gateway_event_id),
 INDEX idx_payments_booking_status (booking_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tickets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 booking_id BIGINT UNSIGNED NOT NULL,
 ticket_code VARCHAR(64) NOT NULL UNIQUE,
 quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 status ENUM('valid','partially_redeemed','redeemed','void') NOT NULL DEFAULT 'valid',
 issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 voided_at DATETIME NULL,
 CONSTRAINT fk_tickets_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT,
 INDEX idx_tickets_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS redemptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 ticket_id BIGINT UNSIGNED NOT NULL,
 booking_id BIGINT UNSIGNED NOT NULL,
 redeemed_quantity SMALLINT UNSIGNED NOT NULL,
 redeemed_by BIGINT UNSIGNED NOT NULL,
 redeemed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 notes VARCHAR(500) NULL,
 CONSTRAINT fk_redemptions_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE RESTRICT,
 CONSTRAINT fk_redemptions_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE RESTRICT,
 CONSTRAINT fk_redemptions_admin FOREIGN KEY (redeemed_by) REFERENCES admins(id) ON DELETE RESTRICT,
 INDEX idx_redemptions_date (redeemed_at),
 INDEX idx_redemptions_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
 setting_key VARCHAR(120) PRIMARY KEY,
 setting_value TEXT NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_settings_admin FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 admin_id BIGINT UNSIGNED NULL,
 action VARCHAR(120) NOT NULL,
 entity_type VARCHAR(100) NULL,
 entity_id BIGINT UNSIGNED NULL,
 ip_address VARCHAR(45) NULL,
 details JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
 INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Editable public homepage and shared site copy.
CREATE TABLE IF NOT EXISTS cms_content (
 content_key VARCHAR(120) NOT NULL PRIMARY KEY,
 content_value MEDIUMTEXT NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_cms_content_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
