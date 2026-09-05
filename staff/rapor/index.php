<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$pageTitle = 'Manajemen Rapor (PDF) - MTs Roudlotul Qur\'an';
$contentTitle = 'Manajemen & Publikasi Rapor Elektronik';
$activeMenu = 'rapor';

$semester = (int)($_GET['semester'] ?? 2);
$schoolYear = trim($_GET['school_year'] ?? '2025/2026');
$kelasFilter = trim($_GET['kelas'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');

$sql = "SELECT s.id as student_id, s.nis, s.nisn, s.nama, s.kelas,
        r.id as report_id, r.status, r.published_at,
        (SELECT COUNT(*) FROM academic_grades ag WHERE ag.student_id = s.id AND ag.semester = :sem AND ag.school_year = :sy) as count_academic,
        (SELECT COUNT(*) FROM tahfidh_grades tg WHERE tg.student_id = s.id AND tg.semester = :sem AND tg.school_year = :sy) as count_tahfidh
        FROM students s
        LEFT JOIN reports r ON r.student_id = s.id AND r.semester = :sem AND r.school_year = :sy
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

if ($statusFilter !== '') {
    if ($statusFilter === 'draft') {
        $sql .= " AND (r.status = 'draft' OR r.status IS NULL)";
    } else {
        $sql .= " AND r.status = :st";
        $params['st'] = $statusFilter;
    }
}

$sql .= " ORDER BY s.kelas ASC, s.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// List kelas
$stmtKelas = $pdo->query("SELECT DISTINCT kelas FROM students ORDER BY kelas ASC");
$kelasList = $stmtKelas->fetchAll(PDO::FETCH_COLUMN);

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="data-section">
    <div class="data-header">
        <h2>Daftar Rapor Siswa Semester <?= $semester === 1 ? '1 (Ganjil)' : '2 (Genap)' ?> <?= e($schoolYear) ?></h2>
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
        <select name="status" onchange="this.form.submit()">
            <option value="">-- Semua Status --</option>
            <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Published (Publik)</option>
            <option value="ready" <?= $statusFilter === 'ready' ? 'selected' : '' ?>>Ready (Siap Publikasi)</option>
            <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft (Belum Lengkap)</option>
        </select>
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama / NISN..." style="min-width: 180px;">
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
                    <th>Kelengkapan Nilai</th>
                    <th>Status Publikasi</th>
                    <th>Aksi & Cetak</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 25px; color: #777;">
                            Tidak ditemukan data rapor.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($reports as $rep): ?>
                        <?php 
                        $status = $rep['status'] ?? 'draft';
                        $hasAcademic = (int)$rep['count_academic'] > 0;
                        $hasTahfidh = (int)$rep['count_tahfidh'] > 0;
                        $isComplete = $hasAcademic && $hasTahfidh;
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= e($rep['nisn']) ?></strong></td>
                            <td><?= e($rep['nama']) ?></td>
                            <td><span class="badge badge-secondary"><?= e($rep['kelas']) ?></span></td>
                            <td>
                                <?php if ($isComplete): ?>
                                    <span class="badge badge-success">Lengkap (<?= (int)$rep['count_academic'] ?> Mapel, <?= (int)$rep['count_tahfidh'] ?> Tahfidh)</span>
                                <?php elseif ($hasAcademic): ?>
                                    <span class="badge badge-warning">Tahfidh Belum Ada</span>
                                <?php elseif ($hasTahfidh): ?>
                                    <span class="badge badge-warning">Akademik Belum Ada</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Kosong</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($status === 'published'): ?>
                                    <span class="badge badge-success" style="background:#27ae60; color:white;">🌐 PUBLISHED</span>
                                    <div style="font-size:11px; color:#666; margin-top:2px;">Tampil di publik</div>
                                <?php elseif ($status === 'ready'): ?>
                                    <span class="badge badge-info">✔ READY (Siap)</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">🔒 DRAFT (Internal)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= e(base_url('/staff/rapor/detail.php?student_id=' . (int)$rep['student_id'] . '&semester=' . $semester . '&school_year=' . urlencode($schoolYear))) ?>" class="action-btn btn-detail" title="Lihat Rapor">Preview</a>
                                
                                <a href="<?= e(base_url('/staff/rapor/cetak.php?student_id=' . (int)$rep['student_id'] . '&semester=' . $semester . '&school_year=' . urlencode($schoolYear))) ?>" target="_blank" class="action-btn btn-pdf" title="Cetak PDF">PDF</a>

                                <?php if ($isAdmin): ?>
                                    <?php if ($status === 'published'): ?>
                                        <form method="POST" action="<?= e(base_url('/staff/rapor/publish.php')) ?>" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="student_id" value="<?= (int)$rep['student_id'] ?>">
                                            <input type="hidden" name="semester" value="<?= $semester ?>">
                                            <input type="hidden" name="school_year" value="<?= e($schoolYear) ?>">
                                            <input type="hidden" name="action" value="unpublish">
                                            <button type="submit" class="action-btn btn-edit" title="Tarik dari publik">Unpublish</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="<?= e(base_url('/staff/rapor/publish.php')) ?>" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="student_id" value="<?= (int)$rep['student_id'] ?>">
                                            <input type="hidden" name="semester" value="<?= $semester ?>">
                                            <input type="hidden" name="school_year" value="<?= e($schoolYear) ?>">
                                            <input type="hidden" name="action" value="publish">
                                            <button type="submit" class="action-btn btn-publish" title="Publikasikan ke portal wali murid">Publish</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
