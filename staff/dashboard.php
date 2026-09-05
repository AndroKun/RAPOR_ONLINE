<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/fungsi.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$pageTitle = 'Dashboard Staf - MTs Roudlotul Qur\'an';
$contentTitle = 'Database Rapor Genap 2025/2026';
$activeMenu = 'dashboard';

// Filter Kelas
$filterKelas = trim($_GET['kelas'] ?? '');
$semester = 2; // Genap
$schoolYear = '2025/2026';

// 1. Hitung Total Siswa
$stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
$totalSiswa = (int)$stmt->fetch()['total'];

// 2. Hitung Rapor Siap Cetak (Ready atau Published)
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM reports WHERE semester = :sem AND school_year = :sy AND status IN ('ready', 'published')");
$stmt->execute(['sem' => $semester, 'sy' => $schoolYear]);
$totalSiapCetak = (int)$stmt->fetch()['total'];

// 3. Query Siswa beserta status nilainya
$sql = "SELECT s.id, s.nis, s.nisn, s.nama, s.kelas,
        (SELECT COUNT(*) FROM academic_grades ag WHERE ag.student_id = s.id AND ag.semester = :sem AND ag.school_year = :sy) as count_academic,
        (SELECT COUNT(*) FROM tahfidh_grades tg WHERE tg.student_id = s.id AND tg.semester = :sem AND tg.school_year = :sy) as count_tahfidh,
        (SELECT status FROM reports r WHERE r.student_id = s.id AND r.semester = :sem AND r.school_year = :sy LIMIT 1) as report_status
        FROM students s";

$params = ['sem' => $semester, 'sy' => $schoolYear];

if ($filterKelas !== '') {
    $sql .= " WHERE s.kelas = :kelas";
    $params['kelas'] = $filterKelas;
}

$sql .= " ORDER BY s.kelas ASC, s.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// 4. Hitung Progress Nilai Masuk
$studentsWithGrades = 0;
foreach ($students as $st) {
    if ((int)$st['count_academic'] > 0 && (int)$st['count_tahfidh'] > 0) {
        $studentsWithGrades++;
    }
}
$progressPercent = $totalSiswa > 0 ? round(($studentsWithGrades / $totalSiswa) * 100) : 0;

// Ambil daftar unik kelas untuk dropdown filter
$stmtKelas = $pdo->query("SELECT DISTINCT kelas FROM students ORDER BY kelas ASC");
$kelasList = $stmtKelas->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Total Siswa Aktif</h3>
        <p><?= (int)$totalSiswa ?> Siswa</p>
    </div>
    <div class="stat-card">
        <h3>Nilai Masuk (Progress)</h3>
        <p><?= (int)$progressPercent ?>%</p>
    </div>
    <div class="stat-card">
        <h3>Rapor Siap Cetak (PDF)</h3>
        <p><?= (int)$totalSiapCetak ?> Dokumen</p>
    </div>
</div>

<div class="data-section">
    <div class="data-header">
        <h2>Data Nilai Siswa <?= $filterKelas !== '' ? 'Kelas ' . e($filterKelas) : 'Seluruh Kelas' ?></h2>
        <div style="display:flex; gap:10px; align-items:center;">
            <form method="GET" action="" style="display:flex; gap:8px; align-items:center;">
                <select name="kelas" onchange="this.form.submit()" style="padding:8px 12px; border:1px solid #cbd5e1; border-radius:5px; font-size:13px;">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= e($k) ?>" <?= $filterKelas === $k ? 'selected' : '' ?>>Kelas <?= e($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="<?= e(base_url('/staff/siswa/tambah.php')) ?>" class="btn btn-add">+ Tambah Data Baru</a>
        </div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>NISN</th>
                    <th>Nama Lengkap</th>
                    <th>Kelas</th>
                    <th>Status Nilai</th>
                    <th>Aksi / Kelola</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:30px; color:#666;">
                            Belum ada data siswa yang terdaftar. Silakan klik <strong>+ Tambah Data Baru</strong>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <?php
                        $hasAcademic = (int)$student['count_academic'] > 0;
                        $hasTahfidh = (int)$student['count_tahfidh'] > 0;
                        $isComplete = $hasAcademic && $hasTahfidh;
                        ?>
                        <tr>
                            <td><strong><?= e($student['nisn']) ?></strong></td>
                            <td><?= e($student['nama']) ?></td>
                            <td><span class="badge badge-secondary"><?= e($student['kelas']) ?></span></td>
                            <td>
                                <?php if ($isComplete): ?>
                                    <span style="color: #27ae60; font-weight: bold;">✔ Lengkap</span>
                                <?php elseif ($hasAcademic && !$hasTahfidh): ?>
                                    <span style="color: #e67e22; font-weight: bold;">⚠ Tahfidh Kosong</span>
                                <?php elseif (!$hasAcademic && $hasTahfidh): ?>
                                    <span style="color: #e67e22; font-weight: bold;">⚠ Akademik Kosong</span>
                                <?php else: ?>
                                    <span style="color: #c0392b; font-weight: bold;">✖ Belum Ada Nilai</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= e(base_url('/staff/nilai/input.php?student_id=' . (int)$student['id'])) ?>" class="action-btn btn-edit" title="Input Nilai Akademik">Edit Nilai</a>
                                <a href="<?= e(base_url('/staff/tahfidh/input.php?student_id=' . (int)$student['id'])) ?>" class="action-btn btn-secondary" style="background:#27ae60; color:white;" title="Input Nilai Tahfidh">Tahfidh</a>
                                
                                <?php if ($isComplete): ?>
                                    <a href="<?= e(base_url('/staff/rapor/cetak.php?student_id=' . (int)$student['id'])) ?>" target="_blank" class="action-btn btn-pdf" title="Cetak Rapor PDF">Cetak PDF</a>
                                <?php else: ?>
                                    <button class="action-btn btn-pdf" style="opacity: 0.5; cursor: not-allowed;" onclick="alert('Nilai akademik atau tahfidh siswa ini belum lengkap. Silakan lengkapi nilai terlebih dahulu.')" title="Nilai belum lengkap">Cetak PDF</button>
                                <?php endif; ?>

                                <a href="<?= e(base_url('/staff/siswa/detail.php?id=' . (int)$student['id'])) ?>" class="action-btn btn-detail" title="Detail Siswa">Detail</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
