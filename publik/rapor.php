<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/fungsi.php';

$query = trim($_GET['q'] ?? '');
$reports = [];

if ($query !== '' && strlen($query) >= 3) {
    $statement = $pdo->prepare(
        "SELECT r.id, s.nama, s.nisn, s.kelas, r.semester, r.school_year
         FROM reports r
         INNER JOIN students s ON s.id = r.student_id
         WHERE r.status = 'published'
           AND (s.nisn = :nisn OR s.nama LIKE :nama)
         ORDER BY s.nama ASC
         LIMIT 20"
    );
    $statement->execute(['nisn' => $query, 'nama' => "%{$query}%"]);
    $reports = $statement->fetchAll();
}

$pageTitle = 'Portal Rapor Online';
require_once __DIR__ . '/../includes/header.php';
?>
<main>
    <h1>Portal Rapor Online</h1>
    <form method="get">
        <label>Cari berdasarkan NISN atau nama
            <input type="search" name="q" value="<?= e($query) ?>" minlength="3" required>
        </label>
        <button type="submit">Cari</button>
    </form>

    <?php if ($query !== '' && strlen($query) < 3): ?>
        <p>Masukkan minimal 3 karakter.</p>
    <?php elseif ($query !== '' && $reports === []): ?>
        <p>Rapor tidak ditemukan.</p>
    <?php else: ?>
        <?php foreach ($reports as $report): ?>
            <article>
                <h2><?= e($report['nama']) ?></h2>
                <p>NISN: <?= e($report['nisn']) ?> | Kelas: <?= e($report['kelas']) ?></p>
                <p>Semester: <?= e($report['semester']) ?> | Tahun: <?= e($report['school_year']) ?></p>
                <a href="/publik/download.php?id=<?= (int) $report['id'] ?>">Unduh PDF Rapor</a>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
