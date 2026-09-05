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
