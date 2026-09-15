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
 * Redirect user to role-specific page after login.
 */
function user_home_route(?array $user = null): string
{
    $u = $user ?? current_user();
    $role = $u['role'] ?? 'staff';

    if ($role === 'guru_tahfidh') {
        return '/staff/tahfidh/index.php';
    }

    if ($role === 'guru_bahasa_arab') {
        return '/staff/bahasa_arab/index.php';
    }

    if ($role === 'staff') {
        return '/staff/nilai/index.php';
    }

    if ($role === 'wali_kelas') {
        return '/staff/dashboard.php';
    }

    return '/staff/dashboard.php';
}

/**
 * Determine whether role has a full admin-like access set besides role-specific admin management.
 */
function is_admin_like_role(string $role): bool
{
    return in_array($role, ['admin', 'wali_kelas'], true);
}

/**
 * For wali kelas, filter all data to class bound in user profile.
 */
function current_user_wali_kelas_class(): ?string
{
    $user = current_user();
    if (($user['role'] ?? '') !== 'wali_kelas') {
        return null;
    }

    $kelas = trim((string)($user['kelas_wali'] ?? ''));
    return $kelas !== '' ? $kelas : null;
}

/**
 * Guard tahfidh routes: accessible only by Admin, Guru Tahfidh or Wali Kelas
 */
function require_tahfidh_access(): void
{
    require_login();
    $user = current_user();
    $role = $user['role'] ?? 'staff';
    if ($role !== 'admin' && $role !== 'guru_tahfidh' && $role !== 'wali_kelas') {
        set_flash('danger', 'Akses ditolak: Menu Nilai Tahfidh hanya dapat diakses oleh Guru Tahfidh, Wali Kelas atau Administrator.');
        redirect('/staff/dashboard.php');
    }
}

/**
 * Guard academic routes: accessible only by Admin, Guru Mata Pelajaran (staff) or Wali Kelas
 */
function require_academic_access(): void
{
    require_login();
    $user = current_user();
    $role = $user['role'] ?? 'staff';
    if ($role !== 'admin' && $role !== 'staff' && $role !== 'wali_kelas') {
        set_flash('danger', 'Akses ditolak: Menu Nilai Akademik khusus untuk Guru Mata Pelajaran, Wali Kelas atau Administrator.');
        redirect('/staff/dashboard.php');
    }
}

/**
 * Guard bahasa arab routes: accessible only by Admin, Guru Bahasa Arab or Wali Kelas
 */
function require_bahasa_arab_access(): void
{
    require_login();
    $user = current_user();
    $role = $user['role'] ?? 'staff';
    if ($role !== 'admin' && $role !== 'guru_bahasa_arab' && $role !== 'wali_kelas') {
        set_flash('danger', 'Akses ditolak: Menu Nilai Bahasa Arab hanya dapat diakses oleh Guru Bahasa Arab, Wali Kelas atau Administrator.');
        redirect('/staff/dashboard.php');
    }
}

/**
 * Guard student data management: accessible by Admin and Wali Kelas
 */
function require_student_manage_access(): void
{
    require_login();
    $user = current_user();
    $role = $user['role'] ?? 'staff';
    if ($role !== 'admin' && $role !== 'wali_kelas') {
        set_flash('danger', 'Akses ditolak: Fitur kelola dan input data siswa hanya dapat diakses oleh Administrator dan Wali Kelas.');
        redirect('/staff/dashboard.php');
    }
}

