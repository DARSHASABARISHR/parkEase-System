-- ParkEase database (import via phpMyAdmin > Import, or: mysql -u root < parkease_database.sql)
CREATE DATABASE IF NOT EXISTS parkease CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE parkease;

CREATE TABLE IF NOT EXISTS admins (
  admin_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  login_otp VARCHAR(10) NULL,
  otp_expiry DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vehicle_types (
  type_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  icon VARCHAR(40) NOT NULL DEFAULT 'bi-car-front',
  hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  is_active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS parking_records (
  record_id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_number VARCHAR(30) NOT NULL UNIQUE,
  vehicle_number VARCHAR(20) NOT NULL,
  type_id INT NOT NULL,
  driver_name VARCHAR(100) NULL,
  driver_mobile VARCHAR(15) NULL,
  driver_email VARCHAR(150) NULL,
  in_time DATETIME NOT NULL,
  out_time DATETIME NULL,
  duration_hours DECIMAL(6,2) NULL,
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_method ENUM('Cash','UPI_Online') NULL,
  payment_status ENUM('Unpaid','Paid') NOT NULL DEFAULT 'Unpaid',
  status ENUM('Parked','Completed','Cancelled') NOT NULL DEFAULT 'Parked',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status), INDEX idx_vehicle (vehicle_number), INDEX idx_in (in_time), INDEX idx_out (out_time),
  CONSTRAINT fk_rec_type FOREIGN KEY (type_id) REFERENCES vehicle_types(type_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_settings (
  setting_key VARCHAR(60) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB;

-- Seed: admin (password = "password")
INSERT INTO admins (name,email,password) VALUES
('Administrator','admin@parkease.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE email=email;

-- Seed: tariffs (only when table is empty)
INSERT INTO vehicle_types (name,icon,hourly_rate)
SELECT * FROM (
 SELECT 'Two Wheeler' AS name,'bi-bicycle' AS icon,10.00 AS hourly_rate UNION ALL
 SELECT 'Four Wheeler','bi-car-front',30.00 UNION ALL
 SELECT 'Auto / Commercial','bi-truck-front',50.00 UNION ALL
 SELECT 'Heavy Vehicle','bi-truck',100.00) t
WHERE NOT EXISTS (SELECT 1 FROM vehicle_types);

INSERT INTO system_settings (setting_key,setting_value) VALUES
('company_name','ParkEase'),
('admin_notification_email','admin@parkease.com'),
('smtp_host','smtp.gmail.com'),
('smtp_port','587'),
('smtp_user',''),
('smtp_pass',''),
('upi_id','parkease@upi')
ON DUPLICATE KEY UPDATE setting_key=setting_key;

-- Seed: sample records (only when empty)
INSERT INTO parking_records (ticket_number,vehicle_number,type_id,driver_name,driver_mobile,in_time,out_time,duration_hours,total_amount,payment_method,payment_status,status)
SELECT * FROM (
 SELECT 'PK-2026-A001' AS a,'TN45AB1234' AS b,2 AS c,'Ravi Kumar' AS d,'9876543210' AS e,DATE_SUB(NOW(),INTERVAL 5 HOUR) AS f,DATE_SUB(NOW(),INTERVAL 3 HOUR) AS g,2.00 AS h,60.00 AS i,'Cash' AS j,'Paid' AS k,'Completed' AS l UNION ALL
 SELECT 'PK-2026-A002','TN45CD5678',1,'Meena S','9876500011',DATE_SUB(NOW(),INTERVAL 4 HOUR),DATE_SUB(NOW(),INTERVAL 2 HOUR),2.00,20.00,'UPI_Online','Paid','Completed' UNION ALL
 SELECT 'PK-2026-A003','TN45EF9012',3,NULL,NULL,DATE_SUB(NOW(),INTERVAL 90 MINUTE),NULL,NULL,0.00,NULL,'Unpaid','Parked' UNION ALL
 SELECT 'PK-2026-A004','TN48GH3456',2,'Arun','9000011122',DATE_SUB(NOW(),INTERVAL 40 MINUTE),NULL,NULL,0.00,NULL,'Unpaid','Parked') s
WHERE NOT EXISTS (SELECT 1 FROM parking_records);
