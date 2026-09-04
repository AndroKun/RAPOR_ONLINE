<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/koneksi.php';

$remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Panel database hanya dapat diakses dari komputer lokal.');
}

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
    ->fetchAll(PDO::FETCH_COLUMN);
$selectedTable = $_GET['table'] ?? '';
$rows = [];

if (in_array($selectedTable, $tables, true)) {
    $rows = $pdo->query('SELECT * FROM "' . str_replace('"', '""', $selectedTable) . '" LIMIT 100')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Rapor Online</title>
    <style>
        body { margin: 0; padding: 2rem; font: 14px Arial, sans-serif; color: #243028; background: #f4f7f4; }
        main { max-width: 1200px; margin: auto; }
        nav { display: flex; flex-wrap: wrap; gap: .5rem; margin: 1rem 0; }
        nav a { padding: .6rem .8rem; color: #fff; background: #1a5632; text-decoration: none; }
        .table-wrap { overflow-x: auto; background: #fff; border: 1px solid #d8e1da; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: .7rem; text-align: left; vertical-align: top; border-bottom: 1px solid #e3e8e4; white-space: nowrap; }
        th { background: #e8c678; }
        .notice { padding: .8rem; background: #fff8df; border-left: 4px solid #e8c678; }
    </style>
</head>
<body>
<main>
    <h1>Database Rapor Online</h1>
    <p class="notice">Panel lokal read-only untuk memeriksa SQLite. Maksimal 100 baris ditampilkan.</p>
    <h2>Tabel</h2>
    <nav>
        <?php foreach ($tables as $table): ?>
            <a href="?table=<?= urlencode($table) ?>"><?= htmlspecialchars($table, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($selectedTable !== '' && in_array($selectedTable, $tables, true)): ?>
        <h2>Isi tabel: <?= htmlspecialchars($selectedTable, ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if ($rows === []): ?>
            <p>Tabel masih kosong.</p>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><?php foreach (array_keys($rows[0]) as $column): ?><th><?= htmlspecialchars((string) $column, ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?><tr>
                    <?php foreach ($row as $value): ?><td><?= htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8') ?></td><?php endforeach; ?>
                </tr><?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>