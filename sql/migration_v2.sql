-- ParkEase v2 migration. Run ONCE after the original parkease_database.sql (phpMyAdmin > parkease > SQL tab).
-- Written for XAMPP's MariaDB (supports IF NOT EXISTS). Safe to re-run.
USE parkease;

-- 1) admins: sole master admin, mobile for SMS OTP, hashed OTP storage
ALTER TABLE admins ADD COLUMN IF NOT EXISTS mobile VARCHAR(15) NULL AFTER email;
ALTER TABLE admins MODIFY login_otp VARCHAR(64) NULL;
UPDATE admins SET name='Darsha Sabarish R', email='rdshrish@gmail.com' WHERE email='admin@parkease.com';
INSERT INTO admins (name,email,password) VALUES
 ('Darsha Sabarish R','rdshrish@gmail.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
 ON DUPLICATE KEY UPDATE name='Darsha Sabarish R';
DELETE FROM admins WHERE email <> 'rdshrish@gmail.com';
UPDATE admins SET login_otp=NULL, otp_expiry=NULL;
-- >>> SET YOUR REAL MOBILE (10 digits) so the SMS OTP can reach you:
-- UPDATE admins SET mobile='98XXXXXXXX' WHERE email='rdshrish@gmail.com';

-- 2) vehicle_types: per-24-hour-cycle rate
ALTER TABLE vehicle_types ADD COLUMN IF NOT EXISTS daily_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER icon;
UPDATE vehicle_types SET daily_rate = CASE name
  WHEN 'Two Wheeler' THEN 20.00 WHEN 'Four Wheeler' THEN 60.00
  WHEN 'Auto / Commercial' THEN 100.00 WHEN 'Heavy Vehicle' THEN 200.00 ELSE daily_rate END
WHERE daily_rate = 0;

-- 3) parking_records: advance, balance, cycles, Razorpay
ALTER TABLE parking_records
  ADD COLUMN IF NOT EXISTS advance_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_amount,
  ADD COLUMN IF NOT EXISTS balance_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER advance_paid,
  ADD COLUMN IF NOT EXISTS days_charged INT NULL AFTER balance_paid,
  ADD COLUMN IF NOT EXISTS rate_applied DECIMAL(10,2) NULL AFTER days_charged,
  ADD COLUMN IF NOT EXISTS razorpay_order_id VARCHAR(40) NULL,
  ADD COLUMN IF NOT EXISTS razorpay_payment_id VARCHAR(40) NULL,
  ADD UNIQUE INDEX IF NOT EXISTS uq_rzp_payment (razorpay_payment_id);
-- old completed rows: treat the full amount as settled at exit
UPDATE parking_records SET balance_paid = total_amount WHERE status='Completed' AND balance_paid=0 AND advance_paid=0 AND total_amount>0;

-- 4) settings
INSERT INTO system_settings (setting_key,setting_value) VALUES
 ('razorpay_mode','test'),('rzp_test_key_id',''),('rzp_test_key_secret',''),
 ('rzp_live_key_id',''),('rzp_live_key_secret',''),('fast2sms_api_key','')
 ON DUPLICATE KEY UPDATE setting_key=setting_key;
UPDATE system_settings SET setting_value='rdshrish@gmail.com' WHERE setting_key='admin_notification_email';
