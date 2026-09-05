<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_tahfidh_access();

$pageTitle = 'Nilai Tahfidh - MTs Roudlotul Qur\'an';
$contentTitle = 'Manajemen Nilai Tahfidh Al-Qur\'an';
$contentSubtitle = 'Pencatatan target capaian juz, surat pilihan, doa, dan hadits santri MTs Tahfidh.';
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

if ($kelasFilter !== '') {
    // Cocokkan VII / 7, VIII / 8, IX / 9
    $kList = match($kelasFilter) {
        'VII', '7' => ['VII', '7'],
        'VIII', '8' => ['VIII', '8'],
        'IX', '9' => ['IX', '9'],
        default => [$kelasFilter]
    };
    $inPlaceholders = implode(',', array_fill(0, count($kList), '?'));
    // Since we already have named params, let's use named or positional
}

// Fetch all students for this semester & school year
if ($kelasFilter !== '') {
    $sql .= " AND (s.kelas = :k1 OR s.kelas = :k2)";
    $kMap = match($kelasFilter) {
        'VII', '7' => ['VII', '7'],
        'VIII', '8' => ['VIII', '8'],
        'IX', '9' => ['IX', '9'],
        default => [$kelasFilter, $kelasFilter]
    };
    $params['k1'] = $kMap[0];
    $params['k2'] = $kMap[1];
}

$sql .= " GROUP BY s.id, s.nis, s.nisn, s.nama, s.kelas ORDER BY s.kelas ASC, s.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// List kelas standar lengkap (Kelas 7, 8, 9)
$standardKelas = ['VII', 'VIII', 'IX'];

// Total Stats
$totalSiswa = count($students);
$sudahDiisi = 0;
foreach ($students as $st) {
    if ((int)$st['total_hafalan'] > 0) {
        $sudahDiisi++;
    }
}
$progressPercent = $totalSiswa > 0 ? (int)round(($sudahDiisi / $totalSiswa) * 100) : 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Stat Cards -->
<div class="stats">
    <div class="stat-card">
        <div class="label">Total Santri / Siswa Terdaftar</div>
        <div class="value"><?= $totalSiswa ?> Santri</div>
    </div>
    <div class="stat-card">
        <div class="label">Progress Penilaian Tahfidh</div>
        <div class="value"><?= $progressPercent ?>% (<?= $sudahDiisi ?> Terisi)</div>
        <div class="progress-track">
            <div class="progress-fill" style="width: <?= $progressPercent ?>%;"></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="label">Semester &amp; Tahun Ajaran</div>
        <div class="value" style="font-size: 18px; font-weight: 700;">
            Semester <?= $semester === 1 ? '1 (Ganjil)' : '2 (Genap)' ?> <?= e($schoolYear) ?>
        </div>
    </div>
</div>

<!-- Panel Card Penilaian Tahfidh -->
<div class="panel-card">
    <div class="panel-head">
        <div>
            <h2>Daftar Capaian Tahfidh Siswa</h2>
            <p class="section-hint" style="margin: 2px 0 0;">Cari siswa secara otomatis dan klik <strong>📖 Input / Edit Tahfidh</strong> untuk mengisi nilai hafalan.</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="<?= e(base_url('/staff/riwayat/index.php')) ?>" class="btn btn-ghost btn-sm">📜 Lihat Riwayat Tahfidh</a>
        </div>
    </div>

    <!-- Filter & Live Search Toolbar -->
    <div class="filters" style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
        <div style="flex: 1; min-width: 240px;">
            <input type="text" id="liveSearchTahfidh" placeholder="🔍 Ketik nama siswa atau NISN (pencarian otomatis)..." autofocus>
        </div>

        <div style="min-width: 150px;">
            <select id="selectKelas" onchange="applyTahfidhFilter()">
                <option value="">Semua Kelas (7, 8, 9)</option>
                <option value="VII" <?= in_array($kelasFilter, ['VII', '7']) ? 'selected' : '' ?>>Kelas 7 (VII)</option>
                <option value="VIII" <?= in_array($kelasFilter, ['VIII', '8']) ? 'selected' : '' ?>>Kelas 8 (VIII)</option>
                <option value="IX" <?= in_array($kelasFilter, ['IX', '9']) ? 'selected' : '' ?>>Kelas 9 (IX)</option>
            </select>
        </div>

        <div style="min-width: 170px;">
            <select id="selectSemester" onchange="applyTahfidhFilter()">
                <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>Semester 1 (Ganjil)</option>
                <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>Semester 2 (Genap)</option>
            </select>
        </div>

        <div style="min-width: 150px;">
            <select id="selectSchoolYear" onchange="applyTahfidhFilter()">
                <option value="2025/2026" <?= $schoolYear === '2025/2026' ? 'selected' : '' ?>>2025/2026</option>
                <option value="2024/2025" <?= $schoolYear === '2024/2025' ? 'selected' : '' ?>>2024/2025</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="responsive-stack" id="tahfidhTable">
            <thead>
                <tr>
                    <th style="width: 45px;" class="num">No</th>
                    <th style="width: 130px;">NISN</th>
                    <th>Nama Lengkap Santri / Siswa</th>
                    <th style="width: 85px;">Kelas</th>
                    <th>Target Hafalan Terinput</th>
                    <th style="width: 110px; text-align: center;">Rata-Rata</th>
                    <th style="width: 130px;">Status Nilai</th>
                    <th style="width: 160px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 35px; color: var(--ink-soft);">
                            Tidak ditemukan data siswa di kelas yang dipilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = 1; 
                    foreach ($students as $st): 
                        $totalHafalan = (int)$st['total_hafalan']; 
                        $rataRata = $st['rata_rata'] !== null ? (float)$st['rata_rata'] : null;
                    ?>
                        <tr data-student="<?= e(strtolower($st['nama'] . ' ' . $st['nisn'] . ' ' . $st['nis'] . ' kelas ' . $st['kelas'])) ?>" data-kelas="<?= e($st['kelas']) ?>">
                            <td class="num" data-label="No"><?= $no++ ?></td>
                            <td class="nisn" data-label="NISN"><?= e($st['nisn']) ?></td>
                            <td class="name" data-label="Nama Siswa">
                                <strong><?= e($st['nama']) ?></strong>
                            </td>
                            <td data-label="Kelas">
                                <span class="badge badge-secondary">Kelas <?= e($st['kelas']) ?></span>
                            </td>
                            <td data-label="Target Hafalan">
                                <?php if ($totalHafalan > 0): ?>
                                    <span style="font-weight: 600; color: var(--green-900);">📖 <?= $totalHafalan ?> Target / Surat</span>
                                <?php else: ?>
                                    <span style="color: var(--ink-soft); font-size: 13px;">Belum Diisi</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Rata-Rata" style="text-align: center; font-weight: 800; color: var(--green-800); font-size: 14.5px;">
                                <?= $rataRata !== null ? number_format($rataRata, 1) : '<span style="color:var(--ink-soft); font-weight:400;">–</span>' ?>
                            </td>
                            <td data-label="Status">
                                <?php if ($totalHafalan >= 2): ?>
                                    <span class="badge badge-green"><span class="dot"></span>Lengkap (<?= $totalHafalan ?>)</span>
                                <?php elseif ($totalHafalan === 1): ?>
                                    <span class="badge badge-amber"><span class="dot"></span>Sebagian (1)</span>
                                <?php else: ?>
                                    <span class="badge badge-rose"><span class="dot"></span>Belum Diisi</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Aksi" style="text-align: center;">
                                <div class="actions" style="justify-content: center; gap: 6px;">
                                    <a href="<?= e(base_url('/staff/tahfidh/input.php?student_id=' . (int)$st['id'] . '&semester=' . $semester . '&school_year=' . urlencode($schoolYear))) ?>" class="btn btn-primary btn-sm">
                                        📖 Input Tahfidh
                                    </a>
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
    // 1. Live Automatic Search on Input
    const searchInput = document.getElementById('liveSearchTahfidh');
    const tableRows = Array.from(document.querySelectorAll('#tahfidhTable tbody tr[data-student]'));

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            tableRows.forEach(row => {
                const text = row.dataset.student || '';
                row.style.display = (!query || text.includes(query)) ? '' : 'none';
            });
        });
    }

    // 2. Dropdown Filter Redirection
    window.applyTahfidhFilter = function() {
        const k = document.getElementById('selectKelas').value;
        const s = document.getElementById('selectSemester').value;
        const sy = document.getElementById('selectSchoolYear').value;

        const url = `<?= e(base_url('/staff/tahfidh/index.php')) ?>?kelas=${encodeURIComponent(k)}&semester=${encodeURIComponent(s)}&school_year=${encodeURIComponent(sy)}`;
        window.location.href = url;
    };
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
