<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$pageTitle = 'Nilai Tahfidh - MTs Roudlotul Qur\'an';
$contentTitle = 'Manajemen Nilai Tahfidh Al-Qur\'an';
$activeMenu = 'tahfidh';

$semester = (int)($_GET['semester'] ?? 2);
$schoolYear = trim($_GET['school_year'] ?? '2025/2026');
$kelasFilter = trim($_GET['kelas'] ?? '');
$search = trim($_GET['q'] ?? '');

$sql = "SELECT s.id, s.nis, s.nisn, s.nama, s.kelas,
        COUNT(tg.id) as total_hafalan,
        AVG(tg.score) as rata_rata
        FROM students s
        LEFT JOIN tahfidh_grades tg ON tg.student_id = s.id AND tg.semester = :sem AND tg.school_year = :sy
        WHERE 1=1";

$params = ['sem' => $semester, 'sy' => $schoolYear];

if ($search !== '') {
    $sql .= " AND (s.nama LIKE :q OR s.nisn LIKE :q)";
    $params['q'] = "%{$search}%";
}

if ($kelasFilter !== '') {
    $sql .= " AND s.kelas = :kelas";
    $params['kelas'] = $kelasFilter;
}

$sql .= " GROUP BY s.id, s.nis, s.nisn, s.nama, s.kelas ORDER BY s.kelas ASC, s.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// List kelas
$stmtKelas = $pdo->query("SELECT DISTINCT kelas FROM students ORDER BY kelas ASC");
$kelasList = $stmtKelas->fetchAll(PDO::FETCH_COLUMN);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="data-section">
    <div class="data-header">
        <h2>Daftar Capaian Tahfidh Semester <?= $semester === 1 ? '1 (Ganjil)' : '2 (Genap)' ?> <?= e($schoolYear) ?></h2>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="" class="filter-bar">
        <select name="semester" onchange="this.form.submit()">
            <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>Semester 1 (Ganjil)</option>
            <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>Semester 2 (Genap)</option>
        </select>
        <select name="school_year" onchange="this.form.submit()">
            <option value="2025/2026" <?= $schoolYear === '2025/2026' ? 'selected' : '' ?>>2025/2026</option>
            <option value="2024/2025" <?= $schoolYear === '2024/2025' ? 'selected' : '' ?>>2024/2025</option>
        </select>
        <select name="kelas" onchange="this.form.submit()">
            <option value="">-- Semua Kelas --</option>
            <?php foreach ($kelasList as $k): ?>
                <option value="<?= e($k) ?>" <?= $kelasFilter === $k ? 'selected' : '' ?>>Kelas <?= e($k) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama / NISN..." style="min-width: 200px;">
        <button type="submit" class="btn btn-secondary">Filter</button>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>NISN</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th>Jumlah Target Hafalan</th>
                    <th>Rata-Rata Nilai</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 25px; color: #777;">
                            Tidak ditemukan data siswa.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($students as $st): ?>
                        <?php 
                        $totalHafalan = (int)$st['total_hafalan']; 
                        $rataRata = $st['rata_rata'] !== null ? (float)$st['rata_rata'] : null;
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= e($st['nisn']) ?></strong></td>
                            <td><?= e($st['nama']) ?></td>
                            <td><span class="badge badge-secondary"><?= e($st['kelas']) ?></span></td>
                            <td><?= $totalHafalan ?> Target / Surat</td>
                            <td>
                                <?= $rataRata !== null ? '<strong>' . number_format($rataRata, 2) . '</strong>' : '<span style="color:#999;">-</span>' ?>
                            </td>
                            <td>
                                <?php if ($totalHafalan >= 2): ?>
                                    <span class="badge badge-success">Terisi (<?= $totalHafalan ?>)</span>
                                <?php elseif ($totalHafalan === 1): ?>
                                    <span class="badge badge-warning">Sebagian (1)</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Belum Diisi</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= e(base_url('/staff/tahfidh/input.php?student_id=' . (int)$st['id'] . '&semester=' . $semester . '&school_year=' . urlencode($schoolYear))) ?>" class="btn btn-primary" style="background:#27ae60; padding:5px 12px; font-size:12px;">
                                    📖 Input / Edit Tahfidh
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
