<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_bahasa_arab_access();

$pageTitle = 'Nilai Bahasa Arab - MTs Roudlotul Qur\'an';
$contentTitle = 'Manajemen Nilai Bahasa Arab';
$contentSubtitle = 'Pencatatan capaian 4 elemen pembelajaran Bahasa Arab, nilai grade (A/B/C), dan saran evaluasi santri.';
$activeMenu = 'bahasa_arab';

$semester = (int)($_GET['semester'] ?? 2);
$schoolYear = trim($_GET['school_year'] ?? '2025/2026');
$kelasFilter = trim($_GET['kelas'] ?? '');
$search = trim($_GET['q'] ?? '');
$user = current_user();
$isWaliKelas = (($user['role'] ?? '') === 'wali_kelas');
$isAdmin = (($user['role'] ?? '') === 'admin');
$waliKelas = current_user_wali_kelas_class();

$sql = "SELECT s.id, s.nis, s.nisn, s.nama, s.kelas,
        COUNT(ag.id) as total_elemen,
        AVG(ag.score) as rata_rata
        FROM students s
        LEFT JOIN arabic_grades ag ON ag.student_id = s.id AND ag.semester = :sem AND ag.school_year = :sy
        WHERE 1=1";

$params = ['sem' => $semester, 'sy' => $schoolYear];

if ($isWaliKelas && $waliKelas !== null && $waliKelas !== '') {
    $sql .= " AND (s.kelas = :scope_kelas_1 OR s.kelas = :scope_kelas_2)";
    $params['scope_kelas_1'] = $waliKelas;
    $params['scope_kelas_2'] = $waliKelas;
}

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

// Total Stats
$totalSiswa = count($students);
$sudahDiisi = 0;
foreach ($students as $st) {
    if ((int)$st['total_elemen'] >= 4) {
        $sudahDiisi++;
    }
}
$progressPercent = $totalSiswa > 0 ? (int)round(($sudahDiisi / $totalSiswa) * 100) : 0;

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Stat Cards -->
<div class="stats">
    <div class="stat-card">
        <div class="label">Total Siswa Terdaftar</div>
        <div class="value"><?= $totalSiswa ?> Siswa</div>
    </div>
    <div class="stat-card">
        <div class="label">Progress Penilaian Bahasa Arab</div>
        <div class="value"><?= $progressPercent ?>% (<?= $sudahDiisi ?> Selesai 4 Elemen)</div>
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

<!-- Panel Card Penilaian Bahasa Arab -->
<div class="panel-card">
    <div class="panel-head">
        <div>
            <h2>Daftar Penilaian Capaian Bahasa Arab</h2>
            <p class="section-hint" style="margin: 2px 0 0;">Cari siswa secara otomatis dan klik <strong>📝 Input / Edit Nilai</strong> untuk mengisi 4 elemen, grade, dan saran guru.</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="<?= e(base_url('/staff/riwayat/index.php')) ?>" class="btn btn-ghost btn-sm">📜 Riwayat Nilai</a>
        </div>
    </div>

    <!-- Filter & Live Search Toolbar -->
    <div class="filters" style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
        <div style="flex: 1; min-width: 240px;">
            <input type="text" id="liveSearchArab" placeholder="🔍 Ketik nama siswa atau NISN (pencarian otomatis)..." autofocus>
        </div>

        <div style="min-width: 150px;">
            <?php if ($isWaliKelas && $waliKelas !== null && $waliKelas !== ''): ?>
                <select id="selectKelas" onchange="applyArabFilter()">
                    <option value="<?= e($waliKelas) ?>" selected><?= $waliKelas === 'IX' ? 'Kelas 9 (IX)' : ($waliKelas === 'VIII' ? 'Kelas 8 (VIII)' : 'Kelas 7 (VII)') ?></option>
                </select>
            <?php else: ?>
                <select id="selectKelas" onchange="applyArabFilter()">
                    <option value="">Semua Kelas (7, 8, 9)</option>
                    <option value="VII" <?= in_array($kelasFilter, ['VII', '7']) ? 'selected' : '' ?>>Kelas 7 (VII)</option>
                    <option value="VIII" <?= in_array($kelasFilter, ['VIII', '8']) ? 'selected' : '' ?>>Kelas 8 (VIII)</option>
                    <option value="IX" <?= in_array($kelasFilter, ['IX', '9']) ? 'selected' : '' ?>>Kelas 9 (IX)</option>
                </select>
            <?php endif; ?>
        </div>

        <div style="min-width: 170px;">
            <select id="selectSemester" onchange="applyArabFilter()">
                <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>Semester 1 (Ganjil)</option>
                <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>Semester 2 (Genap)</option>
            </select>
        </div>

        <div style="min-width: 150px;">
            <select id="selectSchoolYear" onchange="applyArabFilter()">
                <option value="2025/2026" <?= $schoolYear === '2025/2026' ? 'selected' : '' ?>>2025/2026</option>
                <option value="2024/2025" <?= $schoolYear === '2024/2025' ? 'selected' : '' ?>>2024/2025</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="responsive-stack" id="arabTable">
            <thead>
                <tr>
                    <th style="width: 45px;">No</th>
                    <th style="width: 90px;">NISN</th>
                    <th>Nama Lengkap Santri</th>
                    <th style="width: 80px;">Kelas</th>
                    <th style="width: 140px; text-align: center;">Elemen Terisi</th>
                    <th style="width: 130px; text-align: center;">Status</th>
                    <th style="width: 160px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr class="no-data-row">
                        <td colspan="7" style="text-align: center; color: var(--ink-soft); padding: 32px;">
                            Tidak ada data siswa yang sesuai filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($students as $st): ?>
                        <?php 
                        $elemenCount = (int)$st['total_elemen'];
                        $isComplete = ($elemenCount >= 4);
                        ?>
                        <tr class="student-row" data-search="<?= strtolower(e($st['nama'] . ' ' . $st['nisn'] . ' ' . $st['nis'])) ?>">
                            <td data-label="No"><?= $no++ ?></td>
                            <td data-label="NISN"><code style="font-size: 12px;"><?= e($st['nisn']) ?></code></td>
                            <td data-label="Nama Lengkap">
                                <strong><?= e($st['nama']) ?></strong>
                                <div style="font-size: 11.5px; color: var(--ink-soft);">NIS: <?= e($st['nis']) ?></div>
                            </td>
                            <td data-label="Kelas"><span class="badge badge-primary">Kelas <?= e($st['kelas']) ?></span></td>
                            <td data-label="Elemen Terisi" style="text-align: center;">
                                <strong style="color: <?= $isComplete ? '#166534' : ($elemenCount > 0 ? '#b45309' : 'var(--ink-soft)') ?>;">
                                    <?= $elemenCount ?> / 4 Elemen
                                </strong>
                            </td>
                            <td data-label="Status" style="text-align: center;">
                                <?php if ($isComplete): ?>
                                    <span class="badge badge-success">Lengkap</span>
                                <?php elseif ($elemenCount > 0): ?>
                                    <span class="badge badge-warning">Sebagian</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Belum Diisi</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Aksi" style="text-align: center;">
                                <a href="<?= e(base_url('/staff/bahasa_arab/input.php?student_id=' . (int)$st['id'] . '&semester=' . $semester . '&school_year=' . urlencode($schoolYear))) ?>" 
                                   class="btn btn-sm btn-primary" 
                                   style="text-decoration: none; padding: 6px 12px; font-size: 12.5px;">
                                    📝 Input / Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function applyArabFilter() {
    const kls = document.getElementById('selectKelas').value;
    const sem = document.getElementById('selectSemester').value;
    const sy = document.getElementById('selectSchoolYear').value;
    const url = new URL(window.location.href);
    url.searchParams.set('kelas', kls);
    url.searchParams.set('semester', sem);
    url.searchParams.set('school_year', sy);
    window.location.href = url.toString();
}

// Live Search Siswa
document.getElementById('liveSearchArab')?.addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#arabTable tbody tr.student-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const text = row.getAttribute('data-search') || '';
        if (term === '' || text.includes(term)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    let emptyRow = document.getElementById('liveSearchEmptyRow');
    if (visibleCount === 0 && term !== '') {
        if (!emptyRow) {
            emptyRow = document.createElement('tr');
            emptyRow.id = 'liveSearchEmptyRow';
            emptyRow.innerHTML = `<td colspan="7" style="text-align: center; color: var(--ink-soft); padding: 32px;">
                Siswa dengan kata kunci "<strong>${term}</strong>" tidak ditemukan.
            </td>`;
            document.querySelector('#arabTable tbody').appendChild(emptyRow);
        } else {
            emptyRow.style.display = '';
            emptyRow.querySelector('strong').textContent = term;
        }
    } else if (emptyRow) {
        emptyRow.style.display = 'none';
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
