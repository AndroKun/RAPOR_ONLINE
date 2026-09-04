<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/koneksi.php';

$alreadyInitialized = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'users'")
    ->fetchColumn();

if ((int) $alreadyInitialized > 0) {
    echo "Database SQLite sudah siap di storage/database.sqlite\n";
    exit;
}

$schema = file_get_contents(__DIR__ . '/schema.sql');

if ($schema === false) {
    exit("Schema database tidak dapat dibaca.\n");
}

$pdo->exec($schema);
echo "Database SQLite berhasil dibuat di storage/database.sqlite\n";