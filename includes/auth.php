<?php

declare(strict_types=1);

require_once __DIR__ . '/fungsi.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user']) && is_array($_SESSION['user']);
}

/**
 * Get current logged in user data
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Guard internal routes: require authentication
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('danger', 'Silakan login terlebih dahulu untuk mengakses area staff.');
        redirect('/auth/login.php');
    }
}

/**
 * Guard admin-only routes: require specific role (e.g. 'admin')
 */
function require_role(string $role): void
{
    require_login();

    $user = current_user();
    if (($user['role'] ?? '') !== $role) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403 - Akses Ditolak</title>';
        echo '<link rel="stylesheet" href="' . e(base_url('/assets/css/desainstaff.css')) . '">';
        echo '</head><body style="justify-content:center; align-items:center; height:100vh;">';
        echo '<div class="stat-card" style="max-width:500px; text-align:center; padding:40px;">';
        echo '<h1 style="color:#e74c3c; margin-top:0;">403 - Akses Ditolak</h1>';
        echo '<p>Halaman ini hanya dapat diakses oleh Administrator.</p>';
        echo '<a href="' . e(base_url('/staff/dashboard.php')) . '" class="btn btn-primary" style="margin-top:20px;">Kembali ke Dashboard</a>';
        echo '</div></body></html>';
        exit;
    }
}

/**
 * Guard tahfidh routes: accessible only by Admin or Guru Tahfidh
 */
function require_tahfidh_access(): void
{
    require_login();
    $user = current_user();
    $role = $user['role'] ?? 'staff';
    if ($role !== 'admin' && $role !== 'guru_tahfidh') {
        set_flash('danger', 'Akses ditolak: Menu Nilai Tahfidh hanya dapat diakses oleh Guru Tahfidh atau Administrator.');
        redirect('/staff/dashboard.php');
    }
}

/**
 * Guard academic routes: accessible only by Admin or Guru Mata Pelajaran (staff)
 */
function require_academic_access(): void
{
    require_login();
    $user = current_user();
    $role = $user['role'] ?? 'staff';
    if ($role !== 'admin' && $role !== 'staff') {
        set_flash('danger', 'Akses ditolak: Menu Nilai Akademik khusus untuk Guru Mata Pelajaran atau Administrator.');
        redirect('/staff/dashboard.php');
    }
}

