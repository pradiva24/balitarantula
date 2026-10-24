-- Global brand, contact, social media, and SMTP settings.
-- Import once in phpMyAdmin for an existing installation.
CREATE TABLE IF NOT EXISTS site_settings (
  setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
  setting_value MEDIUMTEXT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_site_settings_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
