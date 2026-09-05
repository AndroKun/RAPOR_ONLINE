<?php

declare(strict_types=1);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'raporonline';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Ensure mata_pelajaran column and guru_tahfidh role exist in users table
    try {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `mata_pelajaran` VARCHAR(100) NULL AFTER `role`");
    } catch (Throwable $e) {
        // Ignored if already exists
    }

    try {
        $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'staff', 'guru_tahfidh') NOT NULL DEFAULT 'staff'");
    } catch (Throwable $e) {
        // Ignored if already modified
    }

    // Ensure timestamp columns exist for activity tracking and history log
    try {
        $pdo->exec("ALTER TABLE `academic_grades` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    } catch (Throwable $e) {
        // Ignored if already exists
    }

    try {
        $pdo->exec("ALTER TABLE `tahfidh_grades` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    } catch (Throwable $e) {
        // Ignored if already exists
    }

    // Ensure subjects table exists and is populated
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `subjects` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `nama_mapel` VARCHAR(100) NOT NULL UNIQUE,
                `kelompok` VARCHAR(50) NOT NULL DEFAULT 'Kelompok B (Umum)',
                `urutan` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Seed initial standard subjects if empty
        $stmtCheckSubj = $pdo->query("SELECT COUNT(*) FROM `subjects`");
        if ((int)$stmtCheckSubj->fetchColumn() === 0) {
            $defaultList = [
                ['Al-Qur\'an Hadits', 'Kelompok A (Agama)', 1],
                ['Aqidah Akhlak', 'Kelompok A (Agama)', 2],
                ['Fiqih', 'Kelompok A (Agama)', 3],
                ['Sejarah Kebudayaan Islam (SKI)', 'Kelompok A (Agama)', 4],
                ['Bahasa Arab', 'Kelompok A (Agama)', 5],
                ['Pendidikan Pancasila', 'Kelompok B (Umum)', 6],
                ['Bahasa Indonesia', 'Kelompok B (Umum)', 7],
                ['Matematika', 'Kelompok B (Umum)', 8],
                ['Ilmu Pengetahuan Alam (IPA)', 'Kelompok B (Umum)', 9],
                ['Ilmu Pengetahuan Sosial (IPS)', 'Kelompok B (Umum)', 10],
                ['Bahasa Inggris', 'Kelompok B (Umum)', 11],
                ['Seni Budaya', 'Kelompok B (Umum)', 12],
                ['Pendidikan Jasmani (PJOK)', 'Kelompok B (Umum)', 13],
                ['Prakarya', 'Kelompok B (Umum)', 14],
            ];
            $stmtInsertSubj = $pdo->prepare("INSERT IGNORE INTO `subjects` (`nama_mapel`, `kelompok`, `urutan`) VALUES (?, ?, ?)");
            foreach ($defaultList as $item) {
                $stmtInsertSubj->execute($item);
            }
        }
    } catch (Throwable $e) {
        // Ignored
    }
} catch (PDOException $exception) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Koneksi Database Gagal</title>';
    echo '<style>body{font-family:sans-serif; background:#f4f7f6; display:flex; justify-content:center; align-items:center; height:100vh; margin:0;} .card{background:white; padding:30px; border-radius:10px; max-width:550px; box-shadow:0 4px 15px rgba(0,0,0,0.1); border-left:6px solid #e74c3c;}</style>';
    echo '</head><body><div class="card">';
    echo '<h2 style="color:#c0392b; margin-top:0;">Koneksi Database Gagal</h2>';
    echo '<p>Tidak dapat terhubung ke database MySQL/MariaDB. Pastikan MySQL di XAMPP sudah dinyalakan (Start MySQL) dan database <code>' . htmlspecialchars($dbname, ENT_QUOTES) . '</code> sudah dibuat.</p>';
    echo '<p><small style="color:#777;">Pesan Error: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES) . '</small></p>';
    echo '<p>Silakan import file <code>database/schema.sql</code> dan <code>database/seed.sql</code> melalui phpMyAdmin.</p>';
    echo '</div></body></html>';
    exit;
}
