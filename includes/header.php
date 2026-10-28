<?php
$pageTitle = $pageTitle ?? 'ParkEase';
$cur = basename($_SERVER['SCRIPT_NAME']);
$nav = ['dashboard.php' => ['Dashboard','bi-speedometer2'], 'entry.php' => ['Check-in','bi-box-arrow-in-right'],
        'exit.php' => ['Check-out','bi-box-arrow-right'], 'reports.php' => ['Reports','bi-journal-text'], 'tariffs.php' => ['Tariffs','bi-tags']];
?><!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · ParkEase</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet">
<link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
<?php if (!empty($noNav)): ?><link href="<?= url('assets/css/auth.css') ?>" rel="stylesheet">
<script>document.documentElement.setAttribute('data-bs-theme', 'dark');</script>
<?php else: ?><script>document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('pe-theme') || 'dark');</script><?php endif; ?>
</head>
<body class="<?= !empty($noNav) ? 'auth-body' : '' ?>">
<?php if (empty($noNav)): ?>
<nav class="navbar navbar-expand-lg glass-nav sticky-top no-print">
  <div class="container-xl">
    <a class="navbar-brand brand" href="<?= url('dashboard.php') ?>"><i class="bi bi-p-square-fill"></i> ParkEase</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nv"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nv">
      <ul class="navbar-nav me-auto">
        <?php foreach ($nav as $f => [$label, $ic]): ?>
          <li class="nav-item"><a class="nav-link <?= $cur === $f ? 'active' : '' ?>" href="<?= url($f) ?>"><i class="bi <?= $ic ?>"></i> <?= $label ?></a></li>
        <?php endforeach; ?>
      </ul>
      <button class="btn btn-sm btn-outline-secondary me-2" id="themeBtn" type="button" aria-label="Toggle theme"><i class="bi bi-circle-half"></i></button>
      <a class="btn btn-sm btn-outline-secondary me-2" href="<?= url('change_password.php') ?>"><i class="bi bi-key"></i> Password</a>
      <a class="btn btn-sm btn-outline-danger" href="<?= url('auth/logout.php') ?>">Sign out</a>
    </div>
  </div>
</nav>
<?php endif; ?>
<main class="container-xl py-4">
<?php foreach (flash() as [$t, $m]): ?>
  <div class="alert alert-<?= e($t) ?> alert-dismissible fade show no-print"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endforeach; ?>
