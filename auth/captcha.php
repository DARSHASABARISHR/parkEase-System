<?php
require_once dirname(__DIR__) . '/config/config.php';
if (isset($_GET['new']) || empty($_SESSION['captcha_code'])) new_captcha();
if (!function_exists('imagecreatetruecolor')) { http_response_code(501); exit; }
$code = $_SESSION['captcha_code']; $w = 190; $h = 54;
$im = imagecreatetruecolor($w, $h);
imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 245, 243, 236));
for ($i = 0; $i < 7; $i++) imageline($im, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), imagecolorallocate($im, random_int(150, 210), random_int(150, 210), random_int(150, 210)));
for ($i = 0; $i < 120; $i++) imagesetpixel($im, random_int(0, $w - 1), random_int(0, $h - 1), imagecolorallocate($im, 120, 120, 120));
foreach (str_split($code) as $i => $ch) {
    $t = imagecreatetruecolor(9, 15); imagefilledrectangle($t, 0, 0, 9, 15, imagecolorallocate($t, 245, 243, 236));
    imagestring($t, 5, 1, 0, $ch, imagecolorallocate($t, random_int(20, 90), random_int(20, 90), random_int(20, 90)));
    $sw = random_int(26, 32); $sh = random_int(34, 42);
    imagecopyresized($im, $t, 14 + $i * 33, random_int(4, 12), 0, 0, $sw, $sh, 9, 15); imagedestroy($t);
}
header('Content-Type: image/png'); header('Cache-Control: no-store, no-cache, must-revalidate');
imagepng($im); imagedestroy($im);
