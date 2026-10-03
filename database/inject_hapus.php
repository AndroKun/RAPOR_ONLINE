<?php

declare(strict_types=1);

/*
 * Kredensial database hosting.
 * File ini akan menghapus seluruh isi tabel pada database tersebut.
 */
$dbHost = 'localhost';
$dbPort = '3306';
$dbName = 'projekfi_rapor_mtsrq';
$dbUser = 'projekfi_rapor_mtsrq';
$dbPassword = 'Nvq7Pae9psjWeG-^';

$success = false;
$message = '';
$clearedTables = 0;

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $dbHost,
        $dbPort,
        $dbName
    );
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $tableStatement = $pdo->prepare(
        'SELECT TABLE_NAME
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = :database_name
           AND TABLE_TYPE = \'BASE TABLE\''
    );
    $tableStatement->execute(['database_name' => $dbName]);
    $tables = $tableStatement->fetchAll(PDO::FETCH_COLUMN);

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        foreach ($tables as $table) {
            $quotedTable = '`' . str_replace('`', '``', (string)$table) . '`';
            $pdo->exec('DROP TABLE ' . $quotedTable);
            $clearedTables++;
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    $success = true;
    $message = 'Seluruh tabel, struktur, dan data berhasil dihapus. Database tetap tersedia untuk inject ulang.';
} catch (PDOException $exception) {
    error_log('RAPOR_ONLINE clear database error: ' . $exception->getMessage());
    $message = 'Gagal mengosongkan database. Periksa kredensial dan izin database hosting.';
} catch (Throwable $exception) {
    error_log('RAPOR_ONLINE clear database unexpected error: ' . $exception->getMessage());
    $message = 'Gagal mengosongkan database karena terjadi kesalahan server.';
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kosongkan Database</title>
    <style>
        body { background: #f3f6f8; color: #24323d; font-family: Arial, sans-serif; margin: 0; padding: 32px 16px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(23,42,54,.1); margin: auto; max-width: 560px; padding: 28px; }
        h1 { font-size: 24px; margin: 0 0 16px; }
        .notice { border-radius: 8px; padding: 16px; }
        .success { background: #e9f8ef; border: 1px solid #8fd3a8; color: #176b36; }
        .failure { background: #fff0f0; border: 1px solid #e2a1a1; color: #9b2525; }
        .warning { color: #9b2525; line-height: 1.5; margin-top: 20px; }
    </style>
</head>
<body>
<main class="card">
    <h1>Hapus Seluruh Struktur Database</h1>
    <div class="notice <?= $success ? 'success' : 'failure' ?>" role="status">
        <strong><?= $success ? 'Berhasil' : 'Gagal' ?></strong>
        <div><?= escapeHtml($message) ?></div>
        <?php if ($success): ?>
            <div><?= $clearedTables ?> tabel dikosongkan.</div>
        <?php endif; ?>
    </div>
    <p class="warning">
        File ini menghapus seluruh tabel, struktur, indeks, relasi, dan data dari database target.
        Database utama tetap dipertahankan agar dapat dibuat ulang menggunakan inject.php.
        Hapus
        <code>inject_hapus.php</code> dari hosting setelah selesai.
    </p>
</main>
</body>
</html>
