<?php
require_once dirname(__DIR__) . '/config/config.php';
$_SESSION = []; session_destroy();
header('Location: ' . BASE_URL . '/auth/login.php'); exit;
