<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_student_manage_access();

$currentUser = current_user();
$userRole = $currentUser['role'] ?? 'staff';
$isAdmin = ($userRole === 'admin');
$isWaliKelas = ($userRole === 'wali_kelas');
$waliKelasScope = current_user_wali_kelas_class();

$search = trim($_GET['q'] ?? '');
$kelasFilter = trim($_GET['kelas'] ?? ($isWaliKelas && $waliKelasScope ? $waliKelasScope : ''));

$pageTitle = 'Kelola Data Siswa - MTs Roudlotul Qur\'an';
$contentTitle = 'Kelola Data Siswa';
$contentSubtitle = 'Daftar data siswa, profil, nomor induk, serta pengaturan penandatangan lembar rapor.';
$activeMenu = 'siswa';

// Build SQL query
$sql = "SELECT s.*, g.nama_ayah, g.nama_ibu, g.nama_wali 
        FROM students s 
        LEFT JOIN guardians g ON g.student_id = s.id 
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (s.nama LIKE :q1 OR s.nis LIKE :q2 OR s.nisn LIKE :q3)";
    $params['q1'] = '%' . $search . '%';
    $params['q2'] = '%' . $search . '%';
    $params['q3'] = '%' . $search . '%';
}

if ($kelasFilter !== '') {
    $kMap = match(strtoupper($kelasFilter)) {
        'VII', '7' => ['VII', '7'],
        'VIII', '8' => ['VIII', '8'],
        'IX', '9' => ['IX', '9'],
        default => [strtoupper($kelasFilter)]
    };
    $sql .= " AND (s.kelas = :k1 OR s.kelas = :k2)";
    $params['k1'] = $kMap[0];
    $params['k2'] = $kMap[1] ?? $kMap[0];
} elseif ($isWaliKelas && $waliKelasScope) {
    $kMap = match(strtoupper($waliKelasScope)) {
        'VII', '7' => ['VII', '7'],
        'VIII', '8' => ['VIII', '8'],
        'IX', '9' => ['IX', '9'],
        default => [strtoupper($waliKelasScope)]
    };
    $sql .= " AND (s.kelas = :wk1 OR s.kelas = :wk2)";
    $params['wk1'] = $kMap[0];
    $params['wk2'] = $kMap[1] ?? $kMap[0];
}

$sql .= " ORDER BY s.kelas ASC, s.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Hitung total siswa untuk info
$stmtTotal = $pdo->query("SELECT COUNT(*) FROM students");
$totalAllStudents = (int)$stmtTotal->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Filter & Toolbar Card -->
<div class="picker-card" style="margin-bottom: 24px; padding: 18px 20px; border-radius: 12px; background: #ffffff; border: 1px solid var(--line); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
    <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 14px; align-items: center; width: 100%; justify-content: space-between;">
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center; flex: 1; min-width: 280px;">
            <!-- Input Pencarian -->
            <div style="position: relative; flex: 1; min-width: 200px;">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama, NIS, atau NISN..." 
                       style="width: 100%; padding: 9px 12px 9px 34px; border: 1px solid var(--line); border-radius: 8px; font-size: 14px; font-family: inherit;">
                <span style="position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px;">🔍</span>
            </div>

            <!-- Filter Kelas -->
            <div style="min-width: 140px;">
                <select name="kelas" onchange="this.form.submit()" 
                        style="width: 100%; padding: 9px 12px; border: 1px solid var(--line); border-radius: 8px; font-size: 14px; font-family: inherit; background: #ffffff;">
                    <?php if (!$isWaliKelas || !$waliKelasScope): ?>
                        <option value="" <?= $kelasFilter === '' ? 'selected' : '' ?>>Semua Kelas</option>
                    <?php endif; ?>
                    <option value="VII" <?= in_array(strtoupper($kelasFilter), ['VII', '7'], true) ? 'selected' : '' ?>>Kelas VII</option>
                    <option value="VIII" <?= in_array(strtoupper($kelasFilter), ['VIII', '8'], true) ? 'selected' : '' ?>>Kelas VIII</option>
                    <option value="IX" <?= in_array(strtoupper($kelasFilter), ['IX', '9'], true) ? 'selected' : '' ?>>Kelas IX</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="padding: 9px 18px; font-size: 13.5px;">Filter</button>
            <?php if ($search !== '' || ($kelasFilter !== '' && (!$isWaliKelas || $kelasFilter !== $waliKelasScope))): ?>
                <a href="<?= e(base_url('/staff/siswa/index.php')) ?>" class="btn btn-ghost" style="padding: 9px 14px; font-size: 13px;">Reset</a>
            <?php endif; ?>
        </div>

        <!-- Tombol Tambah Siswa Baru -->
        <div>
            <a href="<?= e(base_url('/staff/inputsiswa.php')) ?>" class="btn btn-primary" style="background: var(--green-900); display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; font-size: 14px; font-weight: 600;">
                <span style="font-size: 16px;">➕</span> Input Siswa Baru
            </a>
        </div>
    </form>
</div>

<!-- Table Card -->
<div class="card" style="padding: 0; overflow: hidden; border-radius: 12px; border: 1px solid var(--line); background: #ffffff;">
    <div style="padding: 16px 20px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; background: #fafbfc;">
        <div style="font-weight: 700; color: var(--green-900); font-size: 15px;">
            Daftar Siswa <?= $kelasFilter !== '' ? 'Kelas ' . e($kelasFilter) : '' ?>
            <span style="font-weight: 500; font-size: 13px; color: var(--ink-soft); margin-left: 6px;">(<?= count($students) ?> data ditemukan)</span>
        </div>
        <div style="font-size: 13px; color: var(--ink-soft);">
            Total Seluruh Siswa: <b><?= $totalAllStudents ?></b>
        </div>
    </div>

    <div class="table-responsive">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px;">
            <thead>
                <tr style="background: #f1f5f9; color: var(--green-900); font-weight: 700; border-bottom: 2px solid var(--line);">
                    <th style="padding: 12px 16px; width: 45px; text-align: center;">No</th>
                    <th style="padding: 12px 16px; width: 140px;">NIS / NISN</th>
                    <th style="padding: 12px 16px;">Nama Siswa</th>
                    <th style="padding: 12px 16px; width: 80px; text-align: center;">Kelas</th>
                    <th style="padding: 12px 16px; width: 60px; text-align: center;">L/P</th>
                    <th style="padding: 12px 16px;">Nama Orang Tua / Wali</th>
                    <th style="padding: 12px 16px;">Kepala Madrasah</th>
                    <th style="padding: 12px 16px; width: 180px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="8" style="padding: 40px 20px; text-align: center; color: var(--ink-soft);">
                            <div style="font-size: 32px; margin-bottom: 8px;">📋</div>
                            <div style="font-weight: 600; font-size: 15px; color: var(--ink);">Tidak ada data siswa yang sesuai</div>
                            <div style="font-size: 13px; margin-top: 4px;">Coba ubah kata kunci pencarian atau filter kelas di atas.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($students as $row): ?>
                        <tr style="border-bottom: 1px solid var(--line); transition: background 0.15s ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 12px 16px; text-align: center; font-weight: 600; color: var(--ink-soft);"><?= $no++ ?></td>
                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 700; color: var(--green-900);"><?= e($row['nis']) ?></div>
                                <div style="font-size: 12px; color: var(--ink-soft);">NISN: <?= e($row['nisn']) ?></div>
                            </td>
                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 700; font-size: 14px; color: var(--ink);"><?= e($row['nama']) ?></div>
                                <div style="font-size: 12px; color: var(--ink-soft); margin-top: 2px;">
                                    <?= e($row['tempat_lahir'] ?? '-') ?>, <?= !empty($row['tanggal_lahir']) ? date('d-m-Y', strtotime((string)$row['tanggal_lahir'])) : '-' ?>
                                </div>
                            </td>
                            <td style="padding: 12px 16px; text-align: center;">
                                <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 700; padding: 4px 10px; border-radius: 6px;">
                                    <?= e($row['kelas']) ?>
                                </span>
                            </td>
                            <td style="padding: 12px 16px; text-align: center;">
                                <?php if ($row['jenis_kelamin'] === 'L'): ?>
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; background: #eff6ff; color: #1d4ed8; font-weight: 700; font-size: 12px;">L</span>
                                <?php else: ?>
                                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; background: #fdf2f8; color: #be185d; font-weight: 700; font-size: 12px;">P</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 16px;">
                                <div style="font-weight: 600; color: var(--ink);">
                                    <?= e($row['nama_wali'] ?: ($row['nama_ayah'] ?: ($row['nama_ibu'] ?: '-'))) ?>
                                </div>
                                <div style="font-size: 12px; color: var(--ink-soft); max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= e($row['alamat'] ?? '-') ?>
                                </div>
                            </td>
                            <td style="padding: 12px 16px;">
                                <span style="font-size: 12.5px; color: #475569;">
                                    <?= e($row['nama_kepala_madrasah'] ?: '(Default)') ?>
                                </span>
                            </td>
                            <td style="padding: 12px 16px; text-align: center;">
                                <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                    <!-- Tombol Edit Siswa -->
                                    <a href="<?= e(base_url('/staff/siswa/edit.php?id=' . (int)$row['id'])) ?>" class="btn btn-sm btn-primary" title="Edit Data Siswa" style="padding: 6px 12px; font-size: 12.5px; display: inline-flex; align-items: center; gap: 4px;">
                                        ✏️ Edit
                                    </a>

                                    <!-- Tombol Detail Profil -->
                                    <a href="<?= e(base_url('/staff/siswa/detail.php?id=' . (int)$row['id'])) ?>" class="btn btn-sm btn-ghost" title="Detail Profil Siswa" style="padding: 6px 10px; font-size: 12.5px;">
                                        👁️ Detail
                                    </a>

                                    <!-- Tombol Hapus (Admin Only) -->
                                    <?php if ($isAdmin): ?>
                                        <form method="POST" action="<?= e(base_url('/staff/siswa/hapus.php')) ?>" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data siswa <?= e(addslashes($row['nama'])) ?>? Seluruh nilai dan riwayat rapor siswa ini akan ikut terhapus!');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-delete" title="Hapus Siswa" style="padding: 6px 10px; font-size: 12.5px;">
                                                🗑️
                                            </button>
                                        </form>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
