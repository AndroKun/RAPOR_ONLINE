<?php

declare(strict_types=1);

/*
 * Isi kredensial database di bagian ini.
 * Nilai di bawah mengikuti konfigurasi bawaan XAMPP.
 */
$dbHost = 'localhost';
$dbPort = '3306';
$dbName = 'projekfi_rapor_mtsrq';
$dbUser = 'projekfi_rapor_mtsrq';
$dbPassword = 'Nvq7Pae9psjWeG-^';

$connectionSucceeded = false;
$message = '';
$serverVersion = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim((string)($_POST['db_host'] ?? $dbHost));
    $dbPort = trim((string)($_POST['db_port'] ?? $dbPort));
    $dbName = trim((string)($_POST['db_name'] ?? $dbName));
    $dbUser = trim((string)($_POST['db_user'] ?? $dbUser));
    $dbPassword = (string)($_POST['db_password'] ?? '');

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

        $serverVersion = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
        $connectionSucceeded = true;
        $message = 'Koneksi ke database berhasil.';
    } catch (PDOException $exception) {
        $message = 'Koneksi gagal. Periksa host, port, nama database, username, dan password.';
    }
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
    <title>Tes Koneksi Database</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f3f6f8;
            color: #24323d;
            margin: 0;
            padding: 32px 16px;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(23, 42, 54, .1);
            margin: 0 auto;
            max-width: 560px;
            padding: 28px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 24px;
        }

        .description {
            color: #5d6b75;
            line-height: 1.5;
            margin-top: 0;
        }

        .notice {
            border-radius: 8px;
            margin: 20px 0;
            padding: 14px 16px;
        }

        .success {
            background: #e9f8ef;
            border: 1px solid #8fd3a8;
            color: #176b36;
        }

        .failure {
            background: #fff0f0;
            border: 1px solid #e2a1a1;
            color: #9b2525;
        }

        label {
            display: block;
            font-weight: 700;
            margin: 14px 0 6px;
        }

        input {
            border: 1px solid #c6d0d6;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            padding: 10px 12px;
            width: 100%;
        }

        button {
            background: #176b8f;
            border: 0;
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
            margin-top: 22px;
            padding: 11px 18px;
        }

        button:hover {
            background: #125673;
        }

        .details {
            line-height: 1.7;
            margin: 8px 0 0;
        }

        .warning {
            color: #7a5a00;
            font-size: 13px;
            line-height: 1.5;
            margin-top: 22px;
        }
    </style>
</head>
<body>
<main class="card">
    <h1>Tes Koneksi Database</h1>
    <p class="description">
        Ubah username dan password pada bagian konfigurasi di awal file ini,
        lalu tekan tombol untuk menguji koneksi MySQL/MariaDB.
    </p>

    <?php if ($message !== ''): ?>
        <div class="notice <?= $connectionSucceeded ? 'success' : 'failure' ?>" role="status">
            <strong><?= $connectionSucceeded ? 'Berhasil' : 'Gagal' ?></strong>
            <div><?= escapeHtml($message) ?></div>
            <?php if ($connectionSucceeded): ?>
                <p class="details">
                    Database: <strong><?= escapeHtml($dbName) ?></strong><br>
                    Server: <strong><?= escapeHtml($serverVersion) ?></strong>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <label for="db-host">Host</label>
        <input id="db-host" name="db_host" type="text" value="<?= escapeHtml($dbHost) ?>" required>

        <label for="db-port">Port</label>
        <input id="db-port" name="db_port" type="text" value="<?= escapeHtml($dbPort) ?>" required>

        <label for="db-name">Nama Database</label>
        <input id="db-name" name="db_name" type="text" value="<?= escapeHtml($dbName) ?>" required>

        <label for="db-user">Username Database</label>
        <input id="db-user" name="db_user" type="text" value="<?= escapeHtml($dbUser) ?>" required>

        <label for="db-password">Password Database</label>
        <input id="db-password" name="db_password" type="password" placeholder="Isi password database (boleh kosong)">

        <button type="submit">Tes Koneksi</button>
    </form>

    <p class="warning">
        Demi keamanan, hapus atau lindungi file ini setelah selesai digunakan.
        Password tidak ditampilkan pada halaman.
    </p>
</main>
</body>
</html>