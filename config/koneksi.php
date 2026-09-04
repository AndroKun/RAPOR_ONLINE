<?php

declare(strict_types=1);

$databaseDirectory = __DIR__ . '/../storage';
$databasePath = $databaseDirectory . '/database.sqlite';

if (!is_dir($databaseDirectory)) {
    mkdir($databaseDirectory, 0750, true);
}

$dsn = "sqlite:{$databasePath}";

try {
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    exit('Koneksi database gagal. Pastikan ekstensi PDO SQLite aktif.');
}
