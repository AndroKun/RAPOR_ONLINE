<?php

declare(strict_types=1);

require_once __DIR__ . '/fungsi.php';
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'Portal Staf - MTs Roudlotul Qur\'an';
$activeMenu = $activeMenu ?? 'dashboard';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <!-- CSS Staff & Admin Master -->
    <link rel="stylesheet" href="<?= e(base_url('/assets/css/desainstaff.css')) ?>">
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
    <div class="header-content">
        <h1><?= e($contentTitle ?? $pageTitle) ?></h1>
        <?php if ($user): ?>
            <div class="user-profile">
                <span>Halo, <strong><?= e($user['nama_lengkap'] ?? $user['username']) ?></strong></span>
                <span class="badge-role"><?= e(strtoupper($user['role'] ?? 'STAFF')) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <?php 
    $flash = get_flash();
    if ($flash): 
    ?>
        <div class="alert alert-<?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>
