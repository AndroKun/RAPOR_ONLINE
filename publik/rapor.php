<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/fungsi.php';

$query = trim($_GET['q'] ?? '');
$reports = [];
$searched = false;

if ($query !== '') {
    $searched = true;
    if (strlen($query) >= 3) {
        $stmt = $pdo->prepare(
            "SELECT r.id as report_id, r.semester, r.school_year, r.published_at,
                    s.id as student_id, s.nama, s.nisn, s.kelas
             FROM reports r
             INNER JOIN students s ON s.id = r.student_id
             WHERE r.status = 'published'
               AND (s.nisn = :nisn OR s.nama LIKE :nama)
             ORDER BY s.nama ASC
             LIMIT 20"
        );
        $stmt->execute([
            'nisn' => $query,
            'nama' => "%{$query}%"
        ]);
        $reports = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Rapor Online - MTs Roudlotul Qur'an</title>
    <!-- Memanggil file CSS eksternal portal publik -->
    <link rel="stylesheet" href="<?= e(base_url('/assets/css/desainrapor.css')) ?>">
</head>
<body>

    <div class="header">
        <div class="header-nav">
            <a href="<?= e(base_url('/auth/login.php')) ?>">🔒 Area Staf & Guru</a>
        </div>
        <h1>PORTAL RAPOR ONLINE</h1>
        <p>MTs Tahfidh Roudlotul Qur'an - Yayasan Az Zuhri</p>
    </div>

    <div class="container">
        <div class="search-box">
            <h2>Cari Rapor Siswa</h2>
            <p>Masukkan NISN (Nomor Induk Siswa Nasional) atau Nama Lengkap Siswa</p>
            <form method="GET" action="" class="search-form">
                <input type="text" name="q" value="<?= e($query) ?>" placeholder="Contoh: 0136347734 atau Nalaa..." minlength="3" required autofocus>
                <button type="submit">Cari Data</button>
            </form>
        </div>

        <?php if ($searched): ?>
            <?php if (strlen($query) < 3): ?>
                <div class="alert-box alert-notfound">
                    Mohon masukkan minimal <strong>3 karakter</strong> untuk pencarian data siswa.
                </div>
            <?php elseif (empty($reports)): ?>
                <div class="alert-box alert-notfound">
                    <p style="margin:0 0 5px 0; font-weight:bold;">Data rapor tidak ditemukan.</p>
                    <small>Pastikan NISN atau penulisan nama sudah benar, dan rapor telah resmi dipublikasikan oleh pihak madrasah.</small>
                </div>
            <?php else: ?>
                <?php foreach ($reports as $report): ?>
                    <?php
                    $semText = ((int)$report['semester'] === 1) ? 'I (Satu) / Ganjil' : 'II (Dua) / Genap';
                    ?>
                    <div class="result-card">
                        <h3>
                            <span>Hasil Pencarian</span>
                            <span style="font-size:12px; background:#d4edda; color:#155724; padding:3px 10px; border-radius:12px; font-weight:normal;">Resmi Dipublikasikan</span>
                        </h3>
                        <div class="student-info">
                            <p><strong>Nama Siswa:</strong> <?= e($report['nama']) ?></p>
                            <p><strong>NISN:</strong> <?= e($report['nisn']) ?></p>
                            <p><strong>Kelas:</strong> <?= e($report['kelas']) ?></p>
                            <p><strong>Semester:</strong> <?= $semText ?></p>
                            <p><strong>Tahun Pelajaran:</strong> <?= e($report['school_year']) ?></p>
                        </div>
                        <a href="<?= e(base_url('/publik/download.php?id=' . (int)$report['report_id'])) ?>" class="download-btn" target="_blank">
                            📥 Unduh Dokumen PDF Rapor
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>Desa Ngampelsari Rt. 03 Ngampelsari, Candi, Sidoarjo, Jawa Timur</p>
        <p>&copy; <?= date('Y') ?> MTs Tahfidh Roudlotul Qur'an. Hak Cipta Dilindungi.</p>
    </div>

</body>
</html>
