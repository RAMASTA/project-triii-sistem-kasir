-- MaggotTrack - skema database (MySQL / MariaDB)
CREATE DATABASE IF NOT EXISTS maggottrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE maggottrack;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS batches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_code VARCHAR(30) NOT NULL UNIQUE,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  egg_date DATE NOT NULL,
  hatch_date DATE NULL,
  initial_population INT UNSIGNED NOT NULL,
  target_harvest_kg DECIMAL(10,2) NULL,
  target_harvest_date DATE NULL,
  status ENUM('aktif','siap_panen','selesai','gagal') NOT NULL DEFAULT 'aktif',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_batches_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS feedings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  feed_date DATE NOT NULL,
  feed_type VARCHAR(100) NOT NULL,
  weight_kg DECIMAL(10,3) NOT NULL,
  waste_source VARCHAR(150) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_feed_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS growth_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  measure_date DATE NOT NULL,
  age_days SMALLINT UNSIGNED NOT NULL,
  sample_weight_g DECIMAL(10,3) NOT NULL,
  avg_length_mm DECIMAL(7,2) NULL,
  condition_status VARCHAR(30) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_growth_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS environment_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  log_date DATE NOT NULL,
  temperature_c DECIMAL(5,2) NOT NULL,
  humidity_pct DECIMAL(5,2) NOT NULL,
  media_condition VARCHAR(30) NULL,
  odor_condition VARCHAR(30) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_env_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS harvests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  harvest_date DATE NOT NULL,
  weight_kg DECIMAL(10,3) NOT NULL,
  quality VARCHAR(30) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_harvest_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS batch_photos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  growth_log_id INT UNSIGNED NULL,
  file_path VARCHAR(100) NOT NULL,
  caption VARCHAR(150) NULL,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photo_batch FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
  CONSTRAINT fk_photo_growth FOREIGN KEY (growth_log_id) REFERENCES growth_logs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_feed_date ON feedings(feed_date);
CREATE INDEX idx_growth_batch_age ON growth_logs(batch_id, age_days);
CREATE INDEX idx_env_date ON environment_logs(log_date);
CREATE INDEX idx_harvest_date ON harvests(harvest_date);
