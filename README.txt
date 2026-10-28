ParkEase v3 - authentication overhaul
1. Copy the files over C:\xampp\htdocs\parkease\  (config/config.php is replaced: re-check DB_* values).
2. Run sql/migration_v3.sql once in phpMyAdmin. Master admin: rdshrish@gmail.com, initial password  ParkEase@2026
   Want a different admin/password? php tools\make_hash.php "NewPass#1" you@gmail.com "Your Name"  -> run the SQL it prints.
3. Edit config/config.php: SMTP_USER = your Gmail, SMTP_PASS = a Gmail App Password (16 chars), APP_SECRET = any long random text.
4. PHPMailer must be installed: composer require phpmailer/phpmailer  (or copy its src folder to includes/PHPMailer/src/).
5. Open http://localhost/parkease/auth/login.php. With DEV_MODE true the code also appears in a blue banner on the verify page.
   Set DEV_MODE to false before real use.
Notes: smtp_* rows in system_settings are no longer used. SMS (Fast2SMS) is no longer used for login.
