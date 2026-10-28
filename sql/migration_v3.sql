-- ParkEase v3 migration (auth overhaul). Run once in phpMyAdmin > parkease > SQL. Safe to re-run.
USE parkease;

ALTER TABLE admins
  MODIFY login_otp VARCHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS otp_attempts TINYINT NOT NULL DEFAULT 0 AFTER otp_expiry,
  ADD COLUMN IF NOT EXISTS reset_otp VARCHAR(64) NULL AFTER otp_attempts,
  ADD COLUMN IF NOT EXISTS reset_expiry DATETIME NULL AFTER reset_otp,
  ADD COLUMN IF NOT EXISTS reset_attempts TINYINT NOT NULL DEFAULT 0 AFTER reset_expiry;

-- Register / update the master admin with the initial password:  ParkEase@2026
-- Change it right after first sign-in (Password button in the top bar). Edit name/email if needed.
INSERT INTO admins (name, email, password) VALUES
 ('Darsha Sabarish R', 'rdshrish@gmail.com', '$2y$12$Ep330idMOSwPYM4Zj3IUVOhAZY2cNO8C.9XSG6hEx.fZTxBtNcHka')
ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password),
  login_otp = NULL, otp_expiry = NULL, otp_attempts = 0, reset_otp = NULL, reset_expiry = NULL, reset_attempts = 0;

-- Keep only the master admin
DELETE FROM admins WHERE email <> 'rdshrish@gmail.com';
