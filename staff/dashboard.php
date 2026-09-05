<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/fungsi.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$semester = 2;
$schoolYear = '2025/2026';

$pageTitle = 'Dashboard Staf - MTs Roudlotul Qur\'an';
$contentTitle = 'Database Rapor Genap 2025/2026';
$contentSubtitle = 'Ringkasan data dan status kelengkapan nilai siswa.';
$activeMenu = 'dashboard';

// 1. Total Siswa Aktif
$stmtCount = $pdo->query("SELECT COUNT(*) FROM students");
$totalSiswa = (int)$stmtCount->fetchColumn();

// 2. Data Siswa beserta status kelengkapan nilai
$sql = "SELECT s.id, s.nis, s.nisn, s.nama, s.kelas,
               (SELECT COUNT(*) FROM academic_grades ag WHERE ag.student_id = s.id AND ag.semester = :sem1 AND ag.school_year = :sy1) AS academic_count,
               (SELECT COUNT(*) FROM tahfidh_grades tg WHERE tg.student_id = s.id AND tg.semester = :sem2 AND tg.school_year = :sy2) AS tahfidh_count,
               r.status AS report_status,
               r.pdf_path
        FROM students s
        LEFT JOIN reports r ON r.student_id = s.id AND r.semester = :sem3 AND r.school_year = :sy3
        ORDER BY s.kelas ASC, s.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'sem1' => $semester, 'sy1' => $schoolYear,
    'sem2' => $semester, 'sy2' => $schoolYear,
    'sem3' => $semester, 'sy3' => $schoolYear,
]);
$studentRows = $stmt->fetchAll();

// 3. Hitung Progress & Rapor Siap Cetak
$siswaLengkap = 0;
$raporSiap = 0;

foreach ($studentRows as $st) {
    $hasAcademic = (int)$st['academic_count'] >= 5;
    $hasTahfidh = (int)$st['tahfidh_count'] >= 1;
    if ($hasAcademic && $hasTahfidh) {
        $siswaLengkap++;
    }
    if (in_array($st['report_status'] ?? '', ['ready', 'published'], true) || ($hasAcademic && $hasTahfidh)) {
        $raporSiap++;
    }
}

$progressPercent = $totalSiswa > 0 ? (int)round(($siswaLengkap / $totalSiswa) * 100) : 0;

// List Kelas unik
$kelasList = array_values(array_unique(array_filter(array_column($studentRows, 'kelas'))));
sort($kelasList);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stat Cards -->
<div class="stats">
    <div class="stat-card">
        <div class="label">Total Siswa Aktif</div>
        <div class="value"><?= $totalSiswa ?> Siswa</div>
    </div>
    <div class="stat-card">
        <div class="label">Nilai Masuk (Progress)</div>
        <div class="value"><?= $progressPercent ?>%</div>
        <div class="progress-track">
            <div class="progress-fill" style="width: <?= $progressPercent ?>%;"></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="label">Rapor Siap Cetak (PDF)</div>
        <div class="value"><?= $raporSiap ?> Dokumen</div>
    </div>
</div>

<!-- Panel Table Card -->
<div class="panel-card">
    <div class="panel-head">
        <h2>Data Nilai Siswa</h2>
        <?php 
        $user = current_user();
        $userRole = $user['role'] ?? 'staff';
        ?>
        <?php if ($userRole === 'guru_tahfidh'): ?>
            <a href="<?= e(base_url('/staff/tahfidh/index.php')) ?>" class="btn btn-primary" id="addBtn">📖 Input Nilai Tahfidh</a>
        <?php else: ?>
            <a href="<?= e(base_url('/staff/inputsiswa.php')) ?>" class="btn btn-primary" id="addBtn">+ Tambah Data Baru</a>
        <?php endif; ?>
    </div>

    <div class="filters">
        <input type="text" id="searchInput" placeholder="Cari nama atau NISN...">
        <select id="kelasFilter">
            <option value="">Semua Kelas</option>
            <?php foreach ($kelasList as $kls): ?>
                <option value="<?= e($kls) ?>">Kelas <?= e($kls) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="table-responsive">
        <table id="siswaTable" class="responsive-stack">
            <thead>
                <tr>
                    <th>NISN</th>
                    <th>Nama Lengkap</th>
                    <th>Kelas</th>
                    <th>Status Nilai</th>
                    <th>Aksi / Edit</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($studentRows)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding: 28px; color: var(--ink-soft);">
                            Belum ada data siswa terdaftar. Silakan klik <strong>+ Tambah Data Baru</strong>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($studentRows as $row): 
                        $acadCount = (int)$row['academic_count'];
                        $tahfCount = (int)$row['tahfidh_count'];
                        
                        $isLengkap = ($acadCount >= 5 && $tahfCount >= 1);
                        $isTahfidhKosong = ($acadCount >= 1 && $tahfCount === 0);
                        $isKosong = ($acadCount === 0);
                    ?>
                        <tr data-kelas="<?= e($row['kelas']) ?>" data-name="<?= e(strtolower($row['nama'] . ' ' . $row['nisn'])) ?>">
                            <td class="nisn" data-label="NISN"><?= e($row['nisn']) ?></td>
                            <td class="name" data-label="Nama Lengkap"><?= e($row['nama']) ?></td>
                            <td data-label="Kelas"><?= e($row['kelas']) ?></td>
                            <td data-label="Status Nilai">
                                <?php if ($isLengkap): ?>
                                    <span class="badge badge-green"><span class="dot"></span>Lengkap</span>
                                <?php elseif ($isTahfidhKosong): ?>
                                    <span class="badge badge-amber"><span class="dot"></span>Tahfidh Kosong</span>
                                <?php else: ?>
                                    <span class="badge badge-rose"><span class="dot"></span>Nilai Kosong</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Aksi">
                                <div class="actions">
                                    <?php if ($userRole === 'guru_tahfidh'): ?>
                                        <a href="<?= e(base_url('/staff/tahfidh/input.php?student_id=' . (int)$row['id'])) ?>" class="btn btn-ghost btn-sm">📖 Input Tahfidh</a>
                                    <?php elseif ($userRole === 'admin'): ?>
                                        <a href="<?= e(base_url('/staff/nilai/input.php?kelas=' . urlencode($row['kelas']))) ?>" class="btn btn-ghost btn-sm">Nilai Mapel</a>
                                        <a href="<?= e(base_url('/staff/tahfidh/input.php?student_id=' . (int)$row['id'])) ?>" class="btn btn-ghost btn-sm">Tahfidh</a>
                                    <?php else: ?>
                                        <a href="<?= e(base_url('/staff/nilai/input.php?kelas=' . urlencode($row['kelas']))) ?>" class="btn btn-ghost btn-sm">Edit Nilai</a>
                                    <?php endif; ?>

                                    <?php if ($isLengkap || in_array($row['report_status'] ?? '', ['ready', 'published'], true)): ?>
                                        <a href="<?= e(base_url('/staff/rapor/cetak.php?student_id=' . (int)$row['id'])) ?>" target="_blank" class="btn btn-gold btn-sm">Cetak PDF</a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-gold btn-sm" disabled title="Lengkapi nilai akademik dan tahfidh terlebih dahulu">Cetak PDF</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = Array.from(document.querySelectorAll('#siswaTable tbody tr[data-name]'));
    const searchInput = document.getElementById('searchInput');
    const kelasFilter = document.getElementById('kelasFilter');

    function applyFilters() {
        const q = searchInput.value.trim().toLowerCase();
        const kelas = kelasFilter.value;
        rows.forEach(r => {
            const matchesQ = !q || r.dataset.name.includes(q);
            const matchesKelas = !kelas || r.dataset.kelas === kelas;
            r.style.display = (matchesQ && matchesKelas) ? '' : 'none';
        });
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (kelasFilter) kelasFilter.addEventListener('change', applyFilters);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>