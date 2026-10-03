<?php

declare(strict_types=1);

$dbHost = 'localhost';
$dbPort = '3306';
$dbName = 'projekfi_rapor_mtsrq';
$dbUser = 'projekfi_rapor_mtsrq';
$dbPassword = 'Nvq7Pae9psjWeG-^';
$result = ['success' => false, 'message' => ''];

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $dbHost,
        $dbPort,
        $dbName
    );
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    runSqlFile($pdo, __DIR__ . '/schema.sql', 'Schema');
    runSqlFile($pdo, __DIR__ . '/seed.sql', 'Seed');
    $result = ['success' => true, 'message' => 'Schema dan seed berhasil di-inject ke database.'];
} catch (PDOException $exception) {
    error_log('RAPOR_ONLINE injector database error: ' . $exception->getMessage());
    $result['message'] = 'Inject gagal. Periksa koneksi, kredensial, dan izin database hosting.';
} catch (RuntimeException $exception) {
    $result['message'] = 'Inject gagal: ' . $exception->getMessage();
}

/**
 * Menjalankan file SQL statement per statement agar kompatibel dengan PDO.
 * Perintah CREATE DATABASE dan USE diabaikan karena database target sudah dipilih
 * dari form hosting.
 */
function runSqlFile(PDO $pdo, string $filePath, string $label): string
{
    if (!is_readable($filePath)) {
        throw new RuntimeException($label . ' tidak ditemukan: ' . basename($filePath));
    }

    $sql = (string)file_get_contents($filePath);
    $sql = preg_replace('/CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`[^`]+`\s+CHARACTER\s+SET\s+[^;]+;/is', '', $sql) ?? $sql;
    $sql = preg_replace('/USE\s+`[^`]+`\s*;/i', '', $sql) ?? $sql;
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;
    $sql = preg_replace('/^\s*(?:--|#).*$/m', '', $sql) ?? $sql;

    $statements = splitSqlStatements($sql);
    $executed = 0;

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }

        $pdo->exec($statement);
        $executed++;
    }

    return $label . ' berhasil dijalankan (' . $executed . ' statement).';
}

/**
 * Memisahkan SQL berdasarkan titik koma di luar string dan komentar.
 *
 * @return list<string>
 */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $current = '';
    $length = strlen($sql);
    $inSingleQuote = false;
    $inDoubleQuote = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($index = 0; $index < $length; $index++) {
        $character = $sql[$index];
        $nextCharacter = $index + 1 < $length ? $sql[$index + 1] : '';

        if ($inLineComment) {
            $current .= $character;
            if ($character === "\n") {
                $inLineComment = false;
            }
            continue;
        }

        if ($inBlockComment) {
            $current .= $character;
            if ($character === '*' && $nextCharacter === '/') {
                $current .= $nextCharacter;
                $index++;
                $inBlockComment = false;
            }
            continue;
        }

        if (!$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
            if (($character === '-' && $nextCharacter === '-') || $character === '#') {
                $inLineComment = true;
                $current .= $character;
                continue;
            }
            if ($character === '/' && $nextCharacter === '*') {
                $inBlockComment = true;
                $current .= $character . $nextCharacter;
                $index++;
                continue;
            }
        }

        if ($character === "'" && !$inDoubleQuote && !$inBacktick) {
            $inSingleQuote = !$inSingleQuote;
        } elseif ($character === '"' && !$inSingleQuote && !$inBacktick) {
            $inDoubleQuote = !$inDoubleQuote;
        } elseif ($character === '`' && !$inSingleQuote && !$inDoubleQuote) {
            $inBacktick = !$inBacktick;
        }

        if ($character === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
            $statements[] = $current;
            $current = '';
        } else {
            $current .= $character;
        }
    }

    if (trim($current) !== '') {
        $statements[] = $current;
    }

    return $statements;
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
    <title>Install Database Rapor Online</title>
    <style>
        :root { font-family: Arial, sans-serif; color: #24323d; }
        body { background: #f3f6f8; margin: 0; padding: 32px 16px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(23,42,54,.1); margin: auto; max-width: 620px; padding: 28px; }
        h1 { font-size: 24px; margin: 0 0 8px; }
        .description, .warning { color: #5d6b75; line-height: 1.5; }
        .notice { border-radius: 8px; margin: 20px 0; padding: 14px 16px; }
        .success { background: #e9f8ef; border: 1px solid #8fd3a8; color: #176b36; }
        .failure { background: #fff0f0; border: 1px solid #e2a1a1; color: #9b2525; }
        label { display: block; font-weight: 700; margin: 14px 0 6px; }
        input { border: 1px solid #c6d0d6; border-radius: 6px; box-sizing: border-box; font-size: 16px; padding: 10px 12px; width: 100%; }
        button { background: #176b8f; border: 0; border-radius: 6px; color: #fff; cursor: pointer; font-size: 16px; font-weight: 700; margin-top: 22px; padding: 11px 18px; }
        .warning { font-size: 13px; margin-top: 22px; }
    </style>
</head>
<body>
<main class="card">
    <h1>Install Schema & Seed Database</h1>
    <div class="notice <?= $result['success'] ? 'success' : 'failure' ?>" role="status">
        <strong><?= $result['success'] ? 'Inject berhasil' : 'Inject gagal' ?></strong>
        <div><?= escapeHtml($result['message']) ?></div>
    </div>
    <p class="warning">
        Injector berjalan otomatis saat halaman dibuka. Hapus file inject.php
        setelah notifikasi berhasil muncul.
    </p>
</main>
</body>
</html>
