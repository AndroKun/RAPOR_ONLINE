<?php

declare(strict_types=1);

require_once __DIR__ . '/fungsi.php';
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'Portal Staf - MTs Roudlotul Qur\'an';
$contentTitle = $contentTitle ?? $pageTitle;
$contentSubtitle = $contentSubtitle ?? 'Ringkasan data dan status kelengkapan nilai siswa.';
$activeMenu = $activeMenu ?? 'dashboard';
$user = current_user();

// Generate avatar initials
$nameWords = preg_split('/\s+/', trim($user['nama_lengkap'] ?? $user['username'] ?? 'Staf')) ?: ['S'];
$avatarInitials = '';
if (count($nameWords) >= 2) {
    $avatarInitials = strtoupper(mb_substr($nameWords[0], 0, 1) . mb_substr($nameWords[1], 0, 1));
} else {
    $avatarInitials = strtoupper(mb_substr($nameWords[0], 0, 2));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <!-- Master Design System (Fraunces & Public Sans) -->
    <link rel="stylesheet" href="<?= e(base_url('/assets/css/desainstaff.css')) ?>">
</head>
<body>

<div class="backdrop" id="backdrop"></div>
<div class="shell">

    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <main class="main">
        <button class="menu-toggle" id="menuToggle" type="button">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            <span>Menu</span>
        </button>

        <div class="topbar">
            <div>
                <h1><?= e($contentTitle) ?></h1>
                <p><?= e($contentSubtitle) ?></p>
            </div>
            <?php if ($user): ?>
                <div class="staff-pill">
                    <span class="avatar"><?= e($avatarInitials) ?></span>
                    <span><?= e($user['nama_lengkap'] ?? $user['username']) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <?php 
        $flash = get_flash();
        if ($flash): 
        ?>
            <div class="alert alert-<?= e($flash['type']) ?>" id="flashAlert">
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>
