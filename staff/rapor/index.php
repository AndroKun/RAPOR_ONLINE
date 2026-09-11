<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$pageTitle = 'Manajemen Rapor (PDF) - MTs Roudlotul Qur\'an';
$contentTitle = 'Manajemen & Publikasi Rapor Elektronik';
$contentSubtitle = 'Cetak dokumen PDF dan publikasikan hasil rapor santri MTs Tahfidh.';
$activeMenu = 'rapor';

$semester = (int)($_GET['semester'] ?? 2);
$schoolYear = trim($_GET['school_year'] ?? '2025/2026');
$kelasFilter = trim($_GET['kelas'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');

$sql = "SELECT s.id as student_id, s.nis, s.nisn, s.nama, s.kelas,
        r.id as report_id, r.status, r.published_at,
        (SELECT COUNT(*) FROM academic_grades ag WHERE ag.student_id = s.id AND ag.semester = :sem_academic AND ag.school_year = :sy_academic) as count_academic,
        (SELECT COUNT(*) FROM tahfidh_grades tg WHERE tg.student_id = s.id AND tg.semester = :sem_tahfidh AND tg.school_year = :sy_tahfidh) as count_tahfidh
        FROM students s
        LEFT JOIN reports r ON r.student_id = s.id AND r.semester = :sem_report AND r.school_year = :sy_report
        WHERE 1=1";

$params = [
    'sem_academic' => $semester,
    'sy_academic' => $schoolYear,
    'sem_tahfidh' => $semester,
    'sy_tahfidh' => $schoolYear,
    'sem_report' => $semester,
    'sy_report' => $schoolYear,
];

if ($search !== '') {
    $sql .= " AND (s.nama LIKE :q_nama OR s.nisn LIKE :q_nisn)";
    $params['q_nama'] = "%{$search}%";
    $params['q_nisn'] = "%{$search}%";
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

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="panel-card">
    <div class="panel-head">
        <div>
            <h2>Daftar Rapor Santri Semester <?= $semester === 1 ? '1 (Ganjil)' : '2 (Genap)' ?> <?= e($schoolYear) ?></h2>
            <p class="section-hint" style="margin: 2px 0 0;">Seluruh guru dan staf dapat mencetak rapor PDF. Publikasi ke portal dilakukan oleh Admin.</p>
        </div>
    </div>

    <!-- Filter & Live Search Bar -->
    <div class="filters" style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
        <div style="flex: 1; min-width: 220px;">
            <input type="text" id="liveSearchRapor" placeholder="🔍 Cari nama siswa atau NISN..." autofocus>
        </div>

        <div style="min-width: 150px;">
            <select id="selectKelasRapor" onchange="applyRaporFilter()">
                <option value="">Semua Kelas (7, 8, 9)</option>
                <option value="VII" <?= in_array($kelasFilter, ['VII', '7']) ? 'selected' : '' ?>>Kelas 7 (VII)</option>
                <option value="VIII" <?= in_array($kelasFilter, ['VIII', '8']) ? 'selected' : '' ?>>Kelas 8 (VIII)</option>
                <option value="IX" <?= in_array($kelasFilter, ['IX', '9']) ? 'selected' : '' ?>>Kelas 9 (IX)</option>
            </select>
        </div>

        <div style="min-width: 160px;">
            <select id="selectSemesterRapor" onchange="applyRaporFilter()">
                <option value="1" <?= $semester === 1 ? 'selected' : '' ?>>Semester 1 (Ganjil)</option>
                <option value="2" <?= $semester === 2 ? 'selected' : '' ?>>Semester 2 (Genap)</option>
            </select>
        </div>

        <div style="min-width: 160px;">
            <select id="selectStatusRapor" onchange="applyRaporFilter()">
                <option value="">Semua Status</option>
                <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Published (Publik)</option>
                <option value="ready" <?= $statusFilter === 'ready' ? 'selected' : '' ?>>Ready (Siap Cetak)</option>
                <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft (Belum Lengkap)</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="responsive-stack" id="raporTable">
            <thead>
                <tr>
                    <th style="width: 45px;" class="num">No</th>
                    <th style="width: 120px;">NISN</th>
                    <th>Nama Santri / Siswa</th>
                    <th style="width: 85px;">Kelas</th>
                    <th>Kelengkapan Nilai</th>
                    <th style="width: 140px;">Status Publikasi</th>
                    <th style="width: 220px; text-align: center;">Aksi &amp; Cetak</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 35px; color: var(--ink-soft);">
                            Tidak ditemukan data rapor santri.
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
                        <tr data-student="<?= e(strtolower($rep['nama'] . ' ' . $rep['nisn'] . ' ' . $rep['nis'])) ?>">
                            <td class="num" data-label="No"><?= $no++ ?></td>
                            <td class="nisn" data-label="NISN"><?= e($rep['nisn']) ?></td>
                            <td class="name" data-label="Nama Siswa">
                                <strong><?= e($rep['nama']) ?></strong>
                            </td>
                            <td data-label="Kelas">
                                <span class="badge badge-secondary">Kelas <?= e($rep['kelas']) ?></span>
                            </td>
                            <td data-label="Kelengkapan">
                                <?php if ($isComplete): ?>
                                    <span class="badge badge-green"><span class="dot"></span>Lengkap (<?= (int)$rep['count_academic'] ?> Mapel, <?= (int)$rep['count_tahfidh'] ?> Tahfidh)</span>
                                <?php elseif ($hasAcademic): ?>
                                    <span class="badge badge-amber"><span class="dot"></span>Tahfidh Belum Ada</span>
                                <?php elseif ($hasTahfidh): ?>
                                    <span class="badge badge-amber"><span class="dot"></span>Akademik Belum Ada</span>
                                <?php else: ?>
                                    <span class="badge badge-rose"><span class="dot"></span>Nilai Kosong</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status">
                                <?php if ($status === 'published'): ?>
                                    <span class="badge badge-green">🌐 PUBLISHED</span>
                                <?php elseif ($status === 'ready'): ?>
                                    <span class="badge badge-green">✔ READY</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">🔒 DRAFT</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Aksi" style="text-align: center;">
                                <div class="actions" style="justify-content: center; gap: 4px;">
                                    <a href="<?= e(base_url('/staff/rapor/detail.php?student_id=' . (int)$rep['student_id'] . '&semester=' . $semester . '&school_year=' . urlencode($schoolYear))) ?>" class="btn btn-ghost btn-sm" title="Pratinjau Rapor">Preview</a>
                                    
                                    <a href="<?= e(base_url('/staff/rapor/cetak.php?student_id=' . (int)$rep['student_id'] . '&semester=' . $semester . '&school_year=' . urlencode($schoolYear))) ?>" target="_blank" class="btn btn-gold btn-sm" title="Cetak PDF">PDF</a>

                                    <?php if ($isAdmin): ?>
                                        <?php if ($status === 'published'): ?>
                                            <form method="POST" action="<?= e(base_url('/staff/rapor/publish.php')) ?>" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="student_id" value="<?= (int)$rep['student_id'] ?>">
                                                <input type="hidden" name="semester" value="<?= $semester ?>">
                                                <input type="hidden" name="school_year" value="<?= e($schoolYear) ?>">
                                                <input type="hidden" name="action" value="unpublish">
                                                <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--rose-500);" title="Tarik dari portal publik">Unpublish</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="<?= e(base_url('/staff/rapor/publish.php')) ?>" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="student_id" value="<?= (int)$rep['student_id'] ?>">
                                                <input type="hidden" name="semester" value="<?= $semester ?>">
                                                <input type="hidden" name="school_year" value="<?= e($schoolYear) ?>">
                                                <input type="hidden" name="action" value="publish">
                                                <button type="submit" class="btn btn-primary btn-sm" title="Publikasikan ke portal publik">Publish</button>
                                            </form>
                                        <?php endif; ?>
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
    // 1. Live instant search
    const searchInput = document.getElementById('liveSearchRapor');
    const rows = Array.from(document.querySelectorAll('#raporTable tbody tr[data-student]'));

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim().toLowerCase();
            rows.forEach(r => {
                const text = r.dataset.student || '';
                r.style.display = (!q || text.includes(q)) ? '' : 'none';
            });
        });
    }

    // 2. Dropdown Filter Router
    window.applyRaporFilter = function() {
        const k = document.getElementById('selectKelasRapor').value;
        const s = document.getElementById('selectSemesterRapor').value;
        const st = document.getElementById('selectStatusRapor').value;

        const url = `<?= e(base_url('/staff/rapor/index.php')) ?>?kelas=${encodeURIComponent(k)}&semester=${encodeURIComponent(s)}&status=${encodeURIComponent(st)}`;
        window.location.href = url;
    };
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
