<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Escape HTML output to prevent XSS
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Resolve application base URL automatically for XAMPP subfolder or virtual host
 */
function base_url(string $path = ''): string
{
    static $baseUrl = null;
    if ($baseUrl === null) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        
        // Find the root folder name relative to document root
        $rootDir = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');
        $docRootDir = str_replace('\\', '/', realpath($docRoot) ?: '');

        if ($rootDir !== '' && $docRootDir !== '' && str_starts_with($rootDir, $docRootDir)) {
            $subPath = substr($rootDir, strlen($docRootDir));
            $baseUrl = rtrim($subPath, '/');
        } else {
            $baseUrl = '';
        }
    }

    $cleanPath = '/' . ltrim($path, '/');
    return ($path === '' || $path === '/') ? ($baseUrl ?: '/') : ($baseUrl . $cleanPath);
}

/**
 * Redirect to relative or base URL
 */
function redirect(string $path): never
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        header("Location: {$path}");
    } else {
        $url = base_url($path);
        header("Location: {$url}");
    }
    exit;
}

/**
 * CSRF Token Generator
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Render Hidden Input CSRF Field
 */
function csrf_field(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="_csrf_token" value="' . e($token) . '">';
}

/**
 * Verify CSRF Token for POST requests
 */
function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf_token'] ?? '';
        if ($token === '' || !hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
            http_response_code(403);
            exit('Permintaan tidak valid (CSRF token verification failed).');
        }
    }
}

/**
 * Set Flash Message
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['_flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message,
    ];
}

/**
 * Get and Clear Flash Message
 */
function get_flash(): ?array
{
    if (!empty($_SESSION['_flash'])) {
        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $flash;
    }
    return null;
}

/**
 * Get all available subjects list dynamically from database
 */
function get_all_subjects(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT nama_mapel FROM subjects ORDER BY urutan ASC, nama_mapel ASC");
        $list = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($list)) {
            return $list;
        }
    } catch (Throwable $e) {
        // Fallback if table not ready
    }

    return [
        'Al-Qur\'an Hadits',
        'Aqidah Akhlak',
        'Fiqih',
        'Sejarah Kebudayaan Islam (SKI)',
        'Bahasa Arab',
        'Pendidikan Pancasila',
        'Bahasa Indonesia',
        'Matematika',
        'Ilmu Pengetahuan Alam (IPA)',
        'Ilmu Pengetahuan Sosial (IPS)',
        'Bahasa Inggris',
        'Seni Budaya',
        'Pendidikan Jasmani (PJOK)',
        'Prakarya',
    ];
}

/**
 * Get all available Tahfidh categories/targets list dynamically from database
 */
function get_all_tahfidh_categories(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT nama_kategori FROM tahfidh_categories ORDER BY urutan ASC, nama_kategori ASC");
        $list = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($list)) {
            return $list;
        }
    } catch (Throwable $e) {
        // Fallback if table not ready
    }

    return [
        'Juz 30 (An-Naba s.d An-Nas)',
        'Juz 29 (Al-Mulk s.d Al-Mursalat)',
        'Surat Pilihan (Surat Yasin & Al-Waqi\'ah)',
        'Doa Harian & Dzikir Pagi Petang',
        'Hadits-hadits Pilihan Arbain'
    ];
}


