-- Market Scan · MySQL 8 schema. Market Scan's own database: every table starts with ms_.
-- abraa.com and GHL are reached only through their APIs (NFR-06); their ids are stored here as plain columns.
SET NAMES utf8mb4;

-- Capture App users (FR-01)
CREATE TABLE ms_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NOT NULL UNIQUE,                       -- E.164
  role ENUM('field_sales','admin','owner') NOT NULL,
  area VARCHAR(120) NULL,
  daily_visit_target SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  daily_supplier_target SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  supplier_id BIGINT UNSIGNED NULL,                        -- owners: their shop
  locale ENUM('en','ar') NOT NULL DEFAULT 'en',
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ms_otp_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(20) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at TIMESTAMP NOT NULL,
  used_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  INDEX (phone, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Local copy of every supplier Market Scan created or updated, linked to abraa.com and GHL (FR-31, FR-36)
CREATE TABLE ms_suppliers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  abraa_supplier_id BIGINT UNSIGNED NULL UNIQUE,
  ghl_contact_id VARCHAR(64) NULL UNIQUE,
  company_name VARCHAR(190) NOT NULL,
  contact_name VARCHAR(120) NULL,
  whatsapp VARCHAR(20) NULL,
  landline VARCHAR(20) NULL,
  email VARCHAR(190) NULL,
  website VARCHAR(190) NULL,
  area VARCHAR(120) NULL,
  gps_lat DECIMAL(10,7) NULL, gps_lng DECIMAL(10,7) NULL,
  source ENUM('field_visit','owner') NOT NULL,
  last_visit_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX (company_name), INDEX (email), INDEX (gps_lat, gps_lng)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ms_supplier_phones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id BIGINT UNSIGNED NOT NULL,
  phone VARCHAR(20) NOT NULL UNIQUE,                       -- E.164
  kind ENUM('mobile','whatsapp','landline') NOT NULL DEFAULT 'mobile',
  FOREIGN KEY (supplier_id) REFERENCES ms_suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ms_visits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE,                           -- made on the phone: offline retries are idempotent (FR-07)
  user_id BIGINT UNSIGNED NOT NULL,
  supplier_id BIGINT UNSIGNED NULL,
  shop_name VARCHAR(190) NOT NULL,
  source ENUM('field_visit','owner') NOT NULL,
  gps_lat DECIMAL(10,7) NULL, gps_lng DECIMAL(10,7) NULL,
  status ENUM('draft','processing','review','needs_review','submitted','pending_approval','live') NOT NULL DEFAULT 'draft',
  extraction JSON NULL,                                    -- AI result and the reviewed version
  candidates JSON NULL,                                    -- supplier candidates found on abraa.com / GHL (FR-30)
  captured_at TIMESTAMP NULL, submitted_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES ms_users(id),
  FOREIGN KEY (supplier_id) REFERENCES ms_suppliers(id),
  INDEX (user_id, created_at), INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ms_photos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visit_id BIGINT UNSIGNED NOT NULL,
  client_id VARCHAR(64) NOT NULL,                          -- phone-side id; repeats ignored
  type ENUM('card','storefront','shelf','closeup') NOT NULL,
  path VARCHAR(255) NOT NULL,
  width SMALLINT UNSIGNED NULL, height SMALLINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  FOREIGN KEY (visit_id) REFERENCES ms_visits(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_client (visit_id, client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- category > brand > line > product (FR-23)
CREATE TABLE ms_product_refs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  level ENUM('category','brand','line','product') NOT NULL,
  name VARCHAR(190) NOT NULL,
  name_ar VARCHAR(190) NULL,
  aliases JSON NULL,
  parent_id BIGINT UNSIGNED NULL,
  abraa_category_id BIGINT UNSIGNED NULL,
  abraa_product_id BIGINT UNSIGNED NULL,                   -- product level: listed on abraa.com
  size VARCHAR(40) NULL, barcode VARCHAR(32) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (parent_id) REFERENCES ms_product_refs(id),
  UNIQUE KEY uniq_ref (level, parent_id, name),
  INDEX (barcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product keywords on a supplier (FR-40..FR-43)
CREATE TABLE ms_supplier_tags (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id BIGINT UNSIGNED NOT NULL,
  ref_id BIGINT UNSIGNED NOT NULL,                         -- product level
  self_reported BOOLEAN NOT NULL DEFAULT FALSE,
  seen_on_last_visit BOOLEAN NOT NULL DEFAULT TRUE,
  visit_id BIGINT UNSIGNED NULL,
  evidence_photo_id BIGINT UNSIGNED NULL,
  first_seen_at TIMESTAMP NOT NULL, last_seen_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (supplier_id) REFERENCES ms_suppliers(id) ON DELETE CASCADE,
  FOREIGN KEY (ref_id) REFERENCES ms_product_refs(id),
  UNIQUE KEY uniq_tag (supplier_id, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supplier updates waiting for admin approval (FR-32..FR-35)
CREATE TABLE ms_supplier_changes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visit_id BIGINT UNSIGNED NOT NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  supplier_id BIGINT UNSIGNED NULL,                        -- local record, when there is one
  abraa_supplier_id BIGINT UNSIGNED NULL,                  -- the candidate chosen
  ghl_contact_id VARCHAR(64) NULL,
  current_values JSON NOT NULL,                            -- what abraa.com / GHL had
  proposed_values JSON NOT NULL,                           -- what the visit says
  keywords_to_add JSON NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  decided_by BIGINT UNSIGNED NULL, decided_at TIMESTAMP NULL, note VARCHAR(255) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (visit_id) REFERENCES ms_visits(id),
  INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products not on abraa.com yet (FR-50..FR-56)
CREATE TABLE ms_new_products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visit_id BIGINT UNSIGNED NOT NULL,
  ref_id BIGINT UNSIGNED NOT NULL UNIQUE,                  -- one record per product
  status ENUM('searching','ready','needs_person','published','rejected') NOT NULL DEFAULT 'searching',
  match_confidence DECIMAL(4,3) NULL,
  match_reasons TEXT NULL,
  source_name VARCHAR(120) NULL, source_url VARCHAR(500) NULL,
  title_en VARCHAR(255) NULL, title_ar VARCHAR(255) NULL,
  desc_en TEXT NULL, desc_ar TEXT NULL,
  specs JSON NULL, images JSON NULL,                       -- stored copies + original URLs
  abraa_category_id BIGINT UNSIGNED NULL,
  moq SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  image_photo_id BIGINT UNSIGNED NULL,                     -- close-up fallback (FR-22)
  abraa_product_id BIGINT UNSIGNED NULL,
  decided_by BIGINT UNSIGNED NULL, decided_at TIMESTAMP NULL, note VARCHAR(255) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (visit_id) REFERENCES ms_visits(id),
  FOREIGN KEY (ref_id) REFERENCES ms_product_refs(id),
  INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every push to abraa.com and GHL (FR-36, NFR-08)
CREATE TABLE ms_sync_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  target ENUM('abraa','ghl') NOT NULL,
  action VARCHAR(40) NOT NULL,                             -- create_supplier, update_supplier, set_keywords, create_product
  subject_type VARCHAR(40) NOT NULL, subject_id BIGINT UNSIGNED NOT NULL,
  payload JSON NOT NULL,
  status ENUM('queued','ok','failed') NOT NULL DEFAULT 'queued',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  remote_id VARCHAR(64) NULL, error TEXT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX (subject_type, subject_id), INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin switches, e.g. auto_publish = false (FR-54)
CREATE TABLE ms_settings (
  name VARCHAR(64) PRIMARY KEY,
  value JSON NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
