<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_academic_access();

$user = current_user();
$isAdmin = (($user['role'] ?? '') === 'admin');
$teacherMapel = trim((string)($user['mata_pelajaran'] ?? ''));

$pageTitle = 'Nilai ' . ($isAdmin ? 'Akademik' : $teacherMapel) . ' - MTs Roudlotul Qur\'an';
if ($isAdmin) {
    $contentTitle = 'Manajemen Nilai Akademik Siswa';
    $contentSubtitle = 'Pencatatan dan pemantauan nilai seluruh mata pelajaran santri/siswa MTs Roudlotul Qur\'an.';
} else {
    $contentTitle = 'Input Nilai: ' . ($teacherMapel ?: 'Mata Pelajaran Anda');
    $contentSubtitle = 'Pencatatan capaian nilai mata pelajaran ' . ($teacherMapel ? '<strong>' . e($teacherMapel) . '</strong>' : 'yang Anda ampu') . ' santri/siswa.';
}
$activeMenu = 'nilai';

$semester = (int)($_GET['semester'] ?? 2);
$schoolYear = trim($_GET['school_year'] ?? '2025/2026');
$kelasFilter = trim($_GET['kelas'] ?? '');
$search = trim($_GET['q'] ?? '');

$sql = "SELECT s.id, s.nis, s.nisn, s.nama, s.kelas,
        COUNT(ag.id) as total_mapel,
        AVG(ag.score) as rata_rata,
        MAX(CASE WHEN ag.subject = :t_mapel THEN ag.score ELSE NULL END) as teacher_score,
        MAX(CASE WHEN ag.subject = :t_mapel_desc THEN ag.description ELSE NULL END) as teacher_desc
        FROM students s
        LEFT JOIN academic_grades ag ON ag.student_id = s.id AND ag.semester = :sem AND ag.school_year = :sy
        WHERE 1=1";

$params = [
    'sem' => $semester, 
    'sy' => $schoolYear,
    't_mapel' => $teacherMapel,
    't_mapel_desc' => $teacherMapel
];

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

// Total Stats & Progress Calculation
$totalSiswa = count($students);
$sudahDiisi = 0;

foreach ($students as $st) {
    if ($isAdmin) {
        if ((int)$st['total_mapel'] > 0) {
            $sudahDiisi++;
        }
    } else {
        if ($teacherMapel !== '') {
            if ($st['teacher_score'] !== null) {
                $sudahDiisi++;
            }
        } else {
            if ((int)$st['total_mapel'] > 0) {
                $sudahDiisi++;
            }
        }
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
        <div class="label">
            <?= !$isAdmin && $teacherMapel ? 'Progress Penilaian ' . e($teacherMapel) : 'Progress Penilaian Akademik' ?>
        </div>
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

<!-- Panel Card Penilaian Akademik -->
<div class="panel-card">
    <div class="panel-head">
        <div>
            <h2><?= $isAdmin ? 'Daftar Capaian Nilai Akademik Siswa' : 'Daftar Penilaian Mata Pelajaran ' . e($teacherMapel ?: 'Guru') ?></h2>
            <p class="section-hint" style="margin: 2px 0 0;">
                <?= $isAdmin 
                    ? 'Cari siswa secara otomatis dan klik <strong>📝 Input / Edit Nilai</strong> untuk mengisi nilai mata pelajaran siswa.' 
                    : 'Pilih siswa dan klik <strong>📝 Input Nilai ' . e($teacherMapel ?: 'Mapel') . '</strong> untuk mengisi nilai mata pelajaran yang Anda ampu.' ?>
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="<?= e(base_url('/staff/riwayat/index.php')) ?>" class="btn btn-ghost btn-sm">📜 Lihat Riwayat Nilai</a>
        </div>
    </div>

    <!-- Filter & Live Search Toolbar -->
    <div class="filters" style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
        <div style="flex: 1; min-width: 240px;">
            <input type="text" id="liveSearchAkademik" placeholder="🔍 Ketik nama siswa atau NISN (pencarian otomatis)..." autofocus>
        </div>

        <div style="min-width: 150px;">
            <select id="selectKelas" onchange="applyAkademikFilter()">
                <option value="">Semua Kelas (7, 8, 9)</option>
                <option value="VII" <?= in_array($kelasFilter, ['VII', '7']) ? 'selected' : '' ?>>Kelas 7 (VII)</option>
                <option value="VIII" <?= in_array($kelasFilter, ['VIII', '8']) ? 'selected' : '' ?>>Kelas 8 (VIII)</option>
                <option value="IX" <?= in_array($kelasFilter, ['IX', '9']) ? 'selected' : '' ?>>Kelas 9 (IX)</option>
            </select>
        </div>

        <div style="min-width: 170px;">
            <select id="selectSemester" onchange="applyAkademikFilter()">
                <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>Semester 1 (Ganjil)</option>
                <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>Semester 2 (Genap)</option>
            </select>
        </div>

        <div style="min-width: 150px;">
            <select id="selectSchoolYear" onchange="applyAkademikFilter()">
                <option value="2025/2026" <?= $schoolYear === '2025/2026' ? 'selected' : '' ?>>2025/2026</option>
                <option value="2024/2025" <?= $schoolYear === '2024/2025' ? 'selected' : '' ?>>2024/2025</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="responsive-stack" id="akademikTable">
            <thead>
                <tr>
                    <th style="width: 45px;" class="num">No</th>
                    <th style="width: 130px;">NISN</th>
                    <th>Nama Lengkap Santri / Siswa</th>
                    <th style="width: 85px;">Kelas</th>
                    <?php if (!$isAdmin && $teacherMapel !== ''): ?>
                        <th style="width: 130px; text-align: center;">Nilai <?= e($teacherMapel) ?></th>
                        <th style="width: 90px; text-align: center;">Predikat</th>
                    <?php else: ?>
                        <th>Mata Pelajaran Terinput</th>
                        <th style="width: 110px; text-align: center;">Rata-Rata</th>
                    <?php endif; ?>
                    <th style="width: 140px;">Status Nilai</th>
                    <th style="width: 160px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="<?= (!$isAdmin && $teacherMapel !== '') ? '8' : '8' ?>" style="text-align: center; padding: 35px; color: var(--ink-soft);">
                            Tidak ditemukan data siswa di kelas yang dipilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = 1; 
                    foreach ($students as $st): 
                        $totalMapel = (int)$st['total_mapel']; 
                        $rataRata = $st['rata_rata'] !== null ? (float)$st['rata_rata'] : null;
                        $tScore = $st['teacher_score'] !== null ? (float)$st['teacher_score'] : null;

                        $pred = '–';
                        $predClass = '';
                        if ($tScore !== null) {
                            if ($tScore >= 90) { $pred = 'A'; $predClass = 'p-a'; }
                            elseif ($tScore >= 80) { $pred = 'B'; $predClass = 'p-b'; }
                            elseif ($tScore >= 70) { $pred = 'C'; $predClass = 'p-c'; }
                            else { $pred = 'D'; $predClass = 'p-d'; }
                        }
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

                            <?php if (!$isAdmin && $teacherMapel !== ''): ?>
                                <td data-label="Nilai <?= e($teacherMapel) ?>" style="text-align: center; font-weight: 800; color: var(--green-900); font-size: 15px;">
                                    <?= $tScore !== null ? number_format($tScore, 1) : '<span style="color:var(--ink-soft); font-weight:400;">–</span>' ?>
                                </td>
                                <td data-label="Predikat" style="text-align: center;">
                                    <span class="predikat-badge <?= $predClass ?>"><?= $pred ?></span>
                                </td>
                                <td data-label="Status">
                                    <?php if ($tScore !== null): ?>
                                        <span class="badge badge-green"><span class="dot"></span>Sudah Dinilai</span>
                                    <?php else: ?>
                                        <span class="badge badge-rose"><span class="dot"></span>Belum Diisi</span>
                                    <?php endif; ?>
                                </td>
                            <?php else: ?>
                                <td data-label="Mapel Terinput">
                                    <?php if ($totalMapel > 0): ?>
                                        <span style="font-weight: 600; color: var(--green-900);">📘 <?= $totalMapel ?> Mapel Terisi</span>
                                    <?php else: ?>
                                        <span style="color: var(--ink-soft); font-size: 13px;">Belum Diisi</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Rata-Rata" style="text-align: center; font-weight: 800; color: var(--green-800); font-size: 14.5px;">
                                    <?= $rataRata !== null ? number_format($rataRata, 1) : '<span style="color:var(--ink-soft); font-weight:400;">–</span>' ?>
                                </td>
                                <td data-label="Status">
                                    <?php if ($totalMapel >= 5): ?>
                                        <span class="badge badge-green"><span class="dot"></span>Lengkap (<?= $totalMapel ?>)</span>
                                    <?php elseif ($totalMapel >= 1): ?>
                                        <span class="badge badge-amber"><span class="dot"></span>Sebagian (<?= $totalMapel ?>)</span>
                                    <?php else: ?>
                                        <span class="badge badge-rose"><span class="dot"></span>Belum Diisi</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                            <td data-label="Aksi" style="text-align: center;">
                                <div class="actions" style="justify-content: center; gap: 6px;">
                                    <a href="<?= e(base_url('/staff/nilai/input.php?student_id=' . (int)$st['id'] . '&semester=' . $semester . '&school_year=' . urlencode($schoolYear))) ?>" class="btn btn-primary btn-sm">
                                        📝 Input Nilai
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
    const searchInput = document.getElementById('liveSearchAkademik');
    const tableRows = Array.from(document.querySelectorAll('#akademikTable tbody tr[data-student]'));

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
    window.applyAkademikFilter = function() {
        const k = document.getElementById('selectKelas').value;
        const s = document.getElementById('selectSemester').value;
        const sy = document.getElementById('selectSchoolYear').value;

        const url = `<?= e(base_url('/staff/nilai/index.php')) ?>?kelas=${encodeURIComponent(k)}&semester=${encodeURIComponent(s)}&school_year=${encodeURIComponent(sy)}`;
        window.location.href = url;
    };
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
