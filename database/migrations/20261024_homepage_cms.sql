-- Homepage CMS content storage for Bali Tarantula Adventure.
-- Import this migration once after database/schema.sql.
CREATE TABLE IF NOT EXISTS cms_content (
  content_key VARCHAR(120) NOT NULL PRIMARY KEY,
  content_value MEDIUMTEXT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_cms_content_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
