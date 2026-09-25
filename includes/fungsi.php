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

        if ($rootDir !== '' && $docRootDir !== '' && strpos($rootDir, $docRootDir) === 0) {
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
function redirect(string $path): void
{
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
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

/**
 * Parse komponen tingkat kelas dan rombel / peminatan
 * Contoh: 'VII A' -> ['7', 'A', 'VII A'], '8' -> ['8', null, '8']
 */
function parse_class_components(string $str): array
{
    $str = strtoupper(trim($str));
    $str = preg_replace('/^KELAS\s+/i', '', $str);

    $romanMap = [
        'XII' => '12',
        'XI' => '11',
        'IX' => '9',
        'X' => '10',
        'VIII' => '8',
        'VII' => '7',
        'VI' => '6',
        'IV' => '4',
        'V' => '5',
        'III' => '3',
        'II' => '2',
        'I' => '1',
    ];

    $level = null;
    $section = null;

    if (preg_match('/^(XII|XI|IX|X|VIII|VII|VI|IV|V|III|II|I)(?:[\s\-_]*([A-Z0-9]+))?$/i', $str, $m)) {
        $level = $romanMap[strtoupper($m[1])] ?? null;
        $section = !empty($m[2]) ? strtoupper($m[2]) : null;
    } elseif (preg_match('/^(\d+)(?:[\s\-_]*([A-Z]+))?$/i', $str, $m)) {
        $level = $m[1];
        $section = !empty($m[2]) ? strtoupper($m[2]) : null;
    }

    return [$level, $section, $str];
}

/**
 * Ambil nama wali kelas otomatis berdasarkan kelas murid
 */
function get_wali_kelas_by_class(PDO $pdo, string $kelas): string
{
    $k = strtoupper(trim($kelas));
    if ($k === '') {
        return 'Wali Kelas';
    }

    // 1. Exact match (case-insensitive)
    try {
        $stmt = $pdo->prepare("SELECT nama_lengkap FROM users WHERE role = 'wali_kelas' AND is_active = 1 AND UPPER(TRIM(kelas_wali)) = :k LIMIT 1");
        $stmt->execute(['k' => $k]);
        $nama = $stmt->fetchColumn();
        if ($nama) {
            return (string)$nama;
        }

        // 2. Component matching (Romawi <-> Angka, dengan atau tanpa rombel A/B/C)
        [$studentLevel, $studentSection] = parse_class_components($k);

        $stmtAll = $pdo->query("SELECT id, nama_lengkap, kelas_wali FROM users WHERE role = 'wali_kelas' AND is_active = 1");
        $allWali = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

        if ($studentLevel !== null && !empty($allWali)) {
            // Cocokkan tingkat + rombel secara presisi (misal VII A dengan 7-A)
            foreach ($allWali as $w) {
                [$wLevel, $wSection] = parse_class_components((string)($w['kelas_wali'] ?? ''));
                if ($wLevel === $studentLevel && $wSection !== null && $wSection === $studentSection) {
                    return (string)$w['nama_lengkap'];
                }
            }

            // Cocokkan tingkat saja jika wali kelas mengampu seluruh jenjang tingkat tersebut
            foreach ($allWali as $w) {
                [$wLevel, $wSection] = parse_class_components((string)($w['kelas_wali'] ?? ''));
                if ($wLevel === $studentLevel && ($wSection === null || $wSection === '' || $studentSection === null)) {
                    return (string)$w['nama_lengkap'];
                }
            }
        }
    } catch (Throwable $e) {
        // Fallback jika terjadi error
    }

    return 'Wali Kelas ' . $kelas;
}



