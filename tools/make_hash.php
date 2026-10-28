<?php
// Command line only:  C:\xampp\php\php.exe tools\make_hash.php "YourPassword" you@gmail.com "Your Name"
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$pw = $argv[1] ?? null; $email = $argv[2] ?? 'rdshrish@gmail.com'; $name = $argv[3] ?? 'Master Admin';
if (!$pw) exit("Usage: php tools/make_hash.php \"Password\" email \"Name\"\n");
$h = password_hash($pw, PASSWORD_DEFAULT);
echo "\nINSERT INTO admins (name,email,password) VALUES ('" . addslashes($name) . "','" . addslashes($email) . "','$h')\n  ON DUPLICATE KEY UPDATE name=VALUES(name), password=VALUES(password);\n";
