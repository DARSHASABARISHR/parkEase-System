<?php
require_once __DIR__ . '/config/config.php';
redirect(empty($_SESSION['admin_id']) ? 'auth/login.php' : 'dashboard.php');
