CREATE DATABASE IF NOT EXISTS `sso` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sso`;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  email VARCHAR(128) DEFAULT NULL,
  phone VARCHAR(32) DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  nickname VARCHAR(64) NOT NULL DEFAULT '',
  avatar VARCHAR(255) NOT NULL DEFAULT '',
  profile JSON NULL,
  is_admin TINYINT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  last_login_at DATETIME DEFAULT NULL,
  last_login_ip VARCHAR(64) NOT NULL DEFAULT '',
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_username (username),
  UNIQUE KEY uk_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS applications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(64) NOT NULL,
  description VARCHAR(500) NOT NULL DEFAULT '',
  logo VARCHAR(255) NOT NULL DEFAULT '',
  homepage VARCHAR(255) NOT NULL DEFAULT '',
  client_id VARCHAR(64) NOT NULL,
  client_secret_hash CHAR(64) NOT NULL,
  client_type VARCHAR(20) NOT NULL DEFAULT 'confidential',
  redirect_uris JSON NOT NULL,
  post_logout_uris JSON NOT NULL,
  scopes JSON NOT NULL,
  access_ttl INT NOT NULL DEFAULT 1800,
  refresh_ttl INT NOT NULL DEFAULT 1209600,
  auto_provision TINYINT NOT NULL DEFAULT 0,
  require_consent TINYINT NOT NULL DEFAULT 1,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_app_code (code),
  UNIQUE KEY uk_app_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  app_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  external_id VARCHAR(64) NOT NULL DEFAULT '',
  roles JSON NOT NULL,
  profile JSON NOT NULL,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_app_user (app_id, user_id),
  KEY idx_app_user_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profile_fields (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  field_key VARCHAR(32) NOT NULL,
  label VARCHAR(40) NOT NULL,
  field_type VARCHAR(16) NOT NULL DEFAULT 'text',
  required TINYINT NOT NULL DEFAULT 0,
  options JSON NOT NULL,
  sort INT NOT NULL DEFAULT 0,
  builtin TINYINT NOT NULL DEFAULT 0,
  status TINYINT NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_profile_field_key (field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_consents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  app_id BIGINT UNSIGNED NOT NULL,
  scopes VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_consent (user_id, app_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS refresh_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token_hash CHAR(64) NOT NULL,
  family_id CHAR(32) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  app_id BIGINT UNSIGNED NOT NULL,
  scope VARCHAR(255) NOT NULL DEFAULT '',
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_refresh_hash (token_hash),
  KEY idx_refresh_family (family_id),
  KEY idx_refresh_user_app (user_id, app_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED DEFAULT NULL,
  app_id BIGINT UNSIGNED DEFAULT NULL,
  username VARCHAR(64) NOT NULL DEFAULT '',
  event VARCHAR(32) NOT NULL,
  success TINYINT NOT NULL DEFAULT 1,
  ip VARCHAR(64) NOT NULL DEFAULT '',
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  detail VARCHAR(500) NOT NULL DEFAULT '',
  created_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_log_created (created_at),
  KEY idx_log_event (event),
  KEY idx_log_user (user_id),
  KEY idx_log_app (app_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS access_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  app_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(200) NOT NULL DEFAULT '',
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  review_note VARCHAR(200) NOT NULL DEFAULT '',
  reviewer_id BIGINT UNSIGNED DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT NULL,
  updated_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_request_user (user_id),
  KEY idx_request_app (app_id),
  KEY idx_request_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
