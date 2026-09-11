<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$user = current_user();
$userRole = $user['role'] ?? 'staff';
$isAdmin = ($userRole === 'admin');
$isGuruTahfidh = ($userRole === 'guru_tahfidh');
$isGuruMapel = ($userRole === 'staff');
$teacherMapel = trim((string)($user['mata_pelajaran'] ?? ''));

// Handle POST request untuk Hapus Riwayat Nilai
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_history') {
    verify_csrf();

    $deleteId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $deleteType = trim($_POST['tipe'] ?? '');

    if ($deleteId && in_array($deleteType, ['akademik', 'tahfidh'], true)) {
        try {
            $pdo->beginTransaction();

            $studentId = null;
            $semester = null;
            $schoolYear = null;
            $itemName = '';
            $studentName = '';

            if ($deleteType === 'akademik') {
                if (!$isAdmin) {
                    if (!$isGuruMapel || empty($teacherMapel)) {
                        throw new Exception("Anda tidak memiliki izin untuk menghapus nilai ini.");
                    }
                    $stmtCheck = $pdo->prepare("
                        SELECT ag.student_id, ag.subject, ag.semester, ag.school_year, s.nama 
                        FROM academic_grades ag
                        JOIN students s ON s.id = ag.student_id
                        WHERE ag.id = :id AND ag.subject = :subj 
                        LIMIT 1
                    ");
                    $stmtCheck->execute(['id' => $deleteId, 'subj' => $teacherMapel]);
                } else {
                    $stmtCheck = $pdo->prepare("
                        SELECT ag.student_id, ag.subject, ag.semester, ag.school_year, s.nama 
                        FROM academic_grades ag
                        JOIN students s ON s.id = ag.student_id
                        WHERE ag.id = :id 
                        LIMIT 1
                    ");
                    $stmtCheck->execute(['id' => $deleteId]);
                }
                $record = $stmtCheck->fetch();
                if (!$record) {
                    throw new Exception("Data riwayat nilai akademik tidak ditemukan atau Anda tidak memiliki akses.");
                }

                $studentId = (int)$record['student_id'];
                $semester = (int)$record['semester'];
                $schoolYear = (string)$record['school_year'];
                $itemName = (string)$record['subject'];
                $studentName = (string)$record['nama'];

                $stmtDel = $pdo->prepare("DELETE FROM academic_grades WHERE id = :id");
                $stmtDel->execute(['id' => $deleteId]);
            } else {
                // Tahfidh
                if (!$isAdmin && !$isGuruTahfidh) {
                    throw new Exception("Anda tidak memiliki izin untuk menghapus riwayat nilai tahfidh.");
                }

                $stmtCheck = $pdo->prepare("
                    SELECT tg.student_id, tg.memorization, tg.semester, tg.school_year, s.nama 
                    FROM tahfidh_grades tg
                    JOIN students s ON s.id = tg.student_id
                    WHERE tg.id = :id 
                    LIMIT 1
                ");
                $stmtCheck->execute(['id' => $deleteId]);
                $record = $stmtCheck->fetch();
                if (!$record) {
                    throw new Exception("Data riwayat tahfidh tidak ditemukan.");
                }

                $studentId = (int)$record['student_id'];
                $semester = (int)$record['semester'];
                $schoolYear = (string)$record['school_year'];
                $itemName = (string)$record['memorization'];
                $studentName = (string)$record['nama'];

                $stmtDel = $pdo->prepare("DELETE FROM tahfidh_grades WHERE id = :id");
                $stmtDel->execute(['id' => $deleteId]);
            }

            // Update status rapor siswa
            if ($studentId && $semester && $schoolYear) {
                $stmtAcademic = $pdo->prepare("SELECT COUNT(*) FROM academic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
                $stmtAcademic->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
                $totalAcademic = (int)$stmtAcademic->fetchColumn();

                $stmtTahfidh = $pdo->prepare("SELECT COUNT(*) FROM tahfidh_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
                $stmtTahfidh->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
                $hasTahfidh = (int)$stmtTahfidh->fetchColumn() > 0;

                $newStatus = ($totalAcademic >= 5 && $hasTahfidh) ? 'ready' : 'draft';

                $stmtRep = $pdo->prepare("
                    INSERT INTO reports (student_id, semester, school_year, status) 
                    VALUES (:sid, :sem, :sy, :status) 
                    ON DUPLICATE KEY UPDATE status = CASE WHEN status = 'published' THEN 'published' ELSE VALUES(status) END
                ");
                $stmtRep->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear, 'status' => $newStatus]);
            }

            $pdo->commit();
            set_flash('success', "Riwayat nilai '{$itemName}' untuk {$studentName} berhasil dihapus.");
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', "Gagal menghapus data: " . $e->getMessage());
        }
    }

    // Preserve filter parameters on redirect
    $redirectQuery = $_GET;
    unset($redirectQuery['action'], $redirectQuery['id'], $redirectQuery['tipe'], $redirectQuery['_csrf_token']);
    $url = '/staff/riwayat/index.php' . (!empty($redirectQuery) ? '?' . http_build_query($redirectQuery) : '');
    redirect($url);
}

$pageTitle = 'Riwayat Pengisian Nilai - MTs Roudlotul Qur\'an';
$activeMenu = 'riwayat';

// Set Titles and Subtitles based on Role
if ($isAdmin) {
    $contentTitle = 'Riwayat Pengisian Nilai (Semua Guru & Mapel)';
    $contentSubtitle = 'Log dan audit riwayat penilaian akademik serta tahfidh siswa oleh seluruh dewan guru.';
} elseif ($isGuruTahfidh) {
    $contentTitle = 'Riwayat Pengisian Nilai: Tahfidh Al-Qur\'an';
    $contentSubtitle = 'Daftar riwayat setoran dan capaian hafalan santri/siswa yang telah Anda nilai.';
} else {
    $contentTitle = 'Riwayat Pengisian Nilai: ' . ($teacherMapel ?: 'Mata Pelajaran Anda');
    $contentSubtitle = 'Daftar riwayat penilaian mata pelajaran ' . ($teacherMapel ?: 'yang Anda ampu') . ' secara terpusat.';
}

// Filters from Query String
$kelasFilter = trim($_GET['kelas'] ?? '');
$semesterFilter = isset($_GET['semester']) && $_GET['semester'] !== '' ? (int)$_GET['semester'] : null;
$search = trim($_GET['q'] ?? '');
$kategoriFilter = trim($_GET['kategori'] ?? '');

// 1. Fetch Stats Count strictly based on Role
if ($isAdmin) {
    $stmtCountAcad = $pdo->query("SELECT COUNT(*) FROM academic_grades");
    $totalAcadEntries = (int)$stmtCountAcad->fetchColumn();

    $stmtCountTahfidh = $pdo->query("SELECT COUNT(*) FROM tahfidh_grades");
    $totalTahfidhEntries = (int)$stmtCountTahfidh->fetchColumn();

    $totalEntries = $totalAcadEntries + $totalTahfidhEntries;
} elseif ($isGuruTahfidh) {
    $stmtCountTahfidh = $pdo->query("SELECT COUNT(*) FROM tahfidh_grades");
    $totalTahfidhEntries = (int)$stmtCountTahfidh->fetchColumn();
    $totalAcadEntries = 0;
    $totalEntries = $totalTahfidhEntries;

    $stmtCountStudent = $pdo->query("SELECT COUNT(DISTINCT student_id) FROM tahfidh_grades");
    $totalSiswaDinilai = (int)$stmtCountStudent->fetchColumn();

    $stmtAvgScore = $pdo->query("SELECT AVG(score) FROM tahfidh_grades");
    $avgScore = (float)$stmtAvgScore->fetchColumn();
} else {
    // Guru Mapel
    $stmtCountMapel = $pdo->prepare("SELECT COUNT(*) FROM academic_grades WHERE subject = :sub");
    $stmtCountMapel->execute(['sub' => $teacherMapel]);
    $totalAcadEntries = (int)$stmtCountMapel->fetchColumn();
    $totalTahfidhEntries = 0;
    $totalEntries = $totalAcadEntries;

    $stmtCountStudent = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM academic_grades WHERE subject = :sub");
    $stmtCountStudent->execute(['sub' => $teacherMapel]);
    $totalSiswaDinilai = (int)$stmtCountStudent->fetchColumn();

    $stmtAvgScore = $pdo->prepare("SELECT AVG(score) FROM academic_grades WHERE subject = :sub");
    $stmtAvgScore->execute(['sub' => $teacherMapel]);
    $avgScore = (float)$stmtAvgScore->fetchColumn();
}

// 2. Build Query with Strict Role Separation
$sqlParts = [];
$params = [];

// ACADEMIC QUERY SECTION (Only for Admin or Guru Mapel)
if ($isAdmin || $isGuruMapel) {
    if (!($isAdmin && $kategoriFilter === 'tahfidh')) {
        $acadSql = "
            SELECT 
                'akademik' AS tipe,
                ag.id,
                ag.student_id,
                ag.subject AS item_name,
                ag.score,
                ag.description,
                ag.semester,
                ag.school_year,
                ag.updated_at,
                ag.created_at,
                s.nama AS student_name,
                s.nisn,
                s.kelas
            FROM academic_grades ag
            JOIN students s ON s.id = ag.student_id
            WHERE 1=1
        ";

        if ($isGuruMapel) {
            $acadSql .= " AND ag.subject = :lock_subject";
            $params['lock_subject'] = $teacherMapel;
        }

        if ($kelasFilter !== '') {
            $acadSql .= " AND (s.kelas = :kelas_a1 OR s.kelas = :kelas_a2)";
            $kMapA = match($kelasFilter) {
                'VII', '7' => ['VII', '7'],
                'VIII', '8' => ['VIII', '8'],
                'IX', '9' => ['IX', '9'],
                default => [$kelasFilter, $kelasFilter]
            };
            $params['kelas_a1'] = $kMapA[0];
            $params['kelas_a2'] = $kMapA[1];
        }
        if ($semesterFilter !== null) {
            $acadSql .= " AND ag.semester = :sem_a";
            $params['sem_a'] = $semesterFilter;
        }
        if ($search !== '') {
            $acadSql .= " AND (s.nama LIKE :q_a OR s.nisn LIKE :q_a)";
            $params['q_a'] = "%{$search}%";
        }

        $sqlParts[] = $acadSql;
    }
}

// TAHFIDH QUERY SECTION (Only for Admin or Guru Tahfidh)
if ($isAdmin || $isGuruTahfidh) {
    if (!($isAdmin && $kategoriFilter === 'akademik')) {
        $tahfSql = "
            SELECT 
                'tahfidh' AS tipe,
                tg.id,
                tg.student_id,
                tg.memorization AS item_name,
                tg.score,
                tg.description,
                tg.semester,
                tg.school_year,
                tg.updated_at,
                tg.created_at,
                s.nama AS student_name,
                s.nisn,
                s.kelas
            FROM tahfidh_grades tg
            JOIN students s ON s.id = tg.student_id
            WHERE 1=1
        ";

        if ($kelasFilter !== '') {
            $tahfSql .= " AND (s.kelas = :kelas_t1 OR s.kelas = :kelas_t2)";
            $kMapT = match($kelasFilter) {
                'VII', '7' => ['VII', '7'],
                'VIII', '8' => ['VIII', '8'],
                'IX', '9' => ['IX', '9'],
                default => [$kelasFilter, $kelasFilter]
            };
            $params['kelas_t1'] = $kMapT[0];
            $params['kelas_t2'] = $kMapT[1];
        }
        if ($semesterFilter !== null) {
            $tahfSql .= " AND tg.semester = :sem_t";
            $params['sem_t'] = $semesterFilter;
        }
        if ($search !== '') {
            $tahfSql .= " AND (s.nama LIKE :q_t OR s.nisn LIKE :q_t)";
            $params['q_t'] = "%{$search}%";
        }

        $sqlParts[] = $tahfSql;
    }
}

$historyRows = [];
if (!empty($sqlParts)) {
    $unionSql = implode(" UNION ALL ", $sqlParts);
    $fullSql = "SELECT * FROM ({$unionSql}) AS combined_history ORDER BY COALESCE(updated_at, created_at) DESC, id DESC LIMIT 200";
    $stmtHistory = $pdo->prepare($fullSql);
    $stmtHistory->execute($params);
    $historyRows = $stmtHistory->fetchAll();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Stat Cards Tailored to Role -->
<div class="stats">
    <?php if ($isAdmin): ?>
        <div class="stat-card">
            <div class="label">Total Seluruh Riwayat</div>
            <div class="value"><?= number_format($totalEntries) ?> Entri</div>
        </div>
        <div class="stat-card">
            <div class="label">Riwayat Mapel Akademik</div>
            <div class="value"><?= number_format($totalAcadEntries) ?> Nilai</div>
        </div>
        <div class="stat-card">
            <div class="label">Riwayat Tahfidh Al-Qur'an</div>
            <div class="value"><?= number_format($totalTahfidhEntries) ?> Capaian</div>
        </div>
    <?php elseif ($isGuruTahfidh): ?>
        <div class="stat-card">
            <div class="label">Total Entri Setoran Tahfidh</div>
            <div class="value"><?= number_format($totalTahfidhEntries) ?> Capaian</div>
        </div>
        <div class="stat-card">
            <div class="label">Santri/Siswa Dinilai</div>
            <div class="value"><?= number_format($totalSiswaDinilai) ?> Orang</div>
        </div>
        <div class="stat-card">
            <div class="label">Rata-Rata Nilai Tahfidh</div>
            <div class="value"><?= number_format($avgScore, 1) ?></div>
        </div>
    <?php else: ?>
        <div class="stat-card">
            <div class="label">Total Entri Nilai (<?= e($teacherMapel) ?>)</div>
            <div class="value"><?= number_format($totalAcadEntries) ?> Nilai</div>
        </div>
        <div class="stat-card">
            <div class="label">Siswa Telah Dinilai</div>
            <div class="value"><?= number_format($totalSiswaDinilai) ?> Siswa</div>
        </div>
        <div class="stat-card">
            <div class="label">Rata-Rata Nilai Mapel</div>
            <div class="value"><?= number_format($avgScore, 1) ?></div>
        </div>
    <?php endif; ?>
</div>

<!-- Panel Card Riwayat -->
<div class="panel-card">
    <div class="panel-head">
        <div>
            <h2>
                <?php if ($isAdmin): ?>
                    Log Aktivitas Pengisian Nilai
                <?php elseif ($isGuruTahfidh): ?>
                    Log Riwayat Nilai Tahfidh
                <?php else: ?>
                    Log Riwayat Nilai: <?= e($teacherMapel) ?>
                <?php endif; ?>
            </h2>
            <p class="section-hint" style="margin: 2px 0 0;">
                <?php if ($isAdmin): ?>
                    Menampilkan 200 transaksi pembaruan nilai terkini dari seluruh mata pelajaran dan tahfidh.
                <?php elseif ($isGuruTahfidh): ?>
                    Menampilkan riwayat penilaian setoran tahfidh Al-Qur'an siswa yang telah diinput.
                <?php else: ?>
                    Menampilkan riwayat penilaian khusus mata pelajaran <strong><?= e($teacherMapel) ?></strong>.
                <?php endif; ?>
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="<?= e(base_url('/staff/rapor/index.php')) ?>" class="btn btn-gold btn-sm">🖨 Buka Manajemen Cetak PDF</a>
        </div>
    </div>

    <!-- Filter & Toolbar Form -->
    <form method="GET" action="" class="filters" style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
        <div style="flex: 1; min-width: 200px;">
            <input type="text" id="liveSearchRiwayat" name="q" value="<?= e($search) ?>" placeholder="🔍 Ketik nama siswa atau NISN (pencarian otomatis)..." autofocus>
        </div>

        <?php if ($isAdmin): ?>
            <div style="min-width: 170px;">
                <select name="kategori" onchange="this.form.submit()">
                    <option value="">Semua Kategori (Akademik &amp; Tahfidh)</option>
                    <option value="akademik" <?= $kategoriFilter === 'akademik' ? 'selected' : '' ?>>📘 Nilai Akademik Saja</option>
                    <option value="tahfidh" <?= $kategoriFilter === 'tahfidh' ? 'selected' : '' ?>>📖 Nilai Tahfidh Saja</option>
                </select>
            </div>
        <?php elseif ($isGuruMapel): ?>
            <div style="padding: 8px 14px; background: #FFFFFF; border: 1.5px solid var(--green-700); border-radius: 8px; font-weight: 700; color: var(--green-900); font-size: 13px; display: flex; align-items: center; gap: 6px;">
                <span>📘 <?= e($teacherMapel) ?></span>
            </div>
        <?php elseif ($isGuruTahfidh): ?>
            <div style="padding: 8px 14px; background: #FFFFFF; border: 1.5px solid var(--green-700); border-radius: 8px; font-weight: 700; color: var(--green-900); font-size: 13px; display: flex; align-items: center; gap: 6px;">
                <span>📖 Tahfidh Al-Qur'an</span>
            </div>
        <?php endif; ?>

        <div style="min-width: 150px;">
            <select name="kelas" onchange="this.form.submit()">
                <option value="">Semua Kelas (7, 8, 9)</option>
                <option value="VII" <?= in_array($kelasFilter, ['VII', '7']) ? 'selected' : '' ?>>Kelas 7 (VII)</option>
                <option value="VIII" <?= in_array($kelasFilter, ['VIII', '8']) ? 'selected' : '' ?>>Kelas 8 (VIII)</option>
                <option value="IX" <?= in_array($kelasFilter, ['IX', '9']) ? 'selected' : '' ?>>Kelas 9 (IX)</option>
            </select>
        </div>

        <div style="min-width: 160px;">
            <select name="semester" onchange="this.form.submit()">
                <option value="">Semua Semester</option>
                <option value="1" <?= $semesterFilter === 1 ? 'selected' : '' ?>>Semester 1 (Ganjil)</option>
                <option value="2" <?= $semesterFilter === 2 ? 'selected' : '' ?>>Semester 2 (Genap)</option>
            </select>
        </div>

        <!-- Tombol Hapus Riwayat diposisikan tepat di tempat tombol filter sebelumnya -->
        <button type="button" id="btnToggleDeleteMode" class="btn btn-danger" style="padding: 9px 18px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; background: #DC2626; color: #FFFFFF; border: none; border-radius: 8px; cursor: pointer; transition: all 0.2s;">
            <span id="btnDeleteIcon">🗑️</span>
            <span id="btnDeleteText">Hapus Riwayat</span>
        </button>

        <?php if ($search !== '' || $kelasFilter !== '' || $semesterFilter !== null || ($isAdmin && $kategoriFilter !== '')): ?>
            <a href="<?= e(base_url('/staff/riwayat/index.php')) ?>" class="btn btn-ghost" style="padding: 9px 14px;">Reset Filter</a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="responsive-stack" id="riwayatTable">
            <thead>
                <tr>
                    <th style="width: 130px;">Waktu Update</th>
                    <th style="width: 110px;">Kategori</th>
                    <th>Mata Pelajaran / Target Hafalan</th>
                    <th>Nama Siswa &amp; Kelas</th>
                    <th style="width: 80px; text-align: center;">Nilai</th>
                    <th style="width: 75px; text-align: center;">Predikat</th>
                    <th style="width: 95px;">Semester</th>
                    <th style="min-width: 180px;">Catatan Guru</th>
                    <th style="width: 150px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historyRows)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 35px; color: var(--ink-soft);">
                            Tidak ada riwayat pengisian nilai yang sesuai dengan kriteria Anda.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($historyRows as $row): 
                        $score = (float)$row['score'];
                        $isTahfidh = ($row['tipe'] === 'tahfidh');
                        
                        $pred = '–';
                        $predClass = '';
                        if ($score >= 90) { $pred = 'A'; $predClass = 'p-a'; }
                        elseif ($score >= 80) { $pred = 'B'; $predClass = 'p-b'; }
                        elseif ($score >= 70) { $pred = 'C'; $predClass = 'p-c'; }
                        else { $pred = 'D'; $predClass = 'p-d'; }

                        $waktuFormatted = !empty($row['updated_at']) ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '-';
                    ?>
                        <tr data-student-search="<?= e(strtolower($row['student_name'] . ' ' . $row['nisn'])) ?>">
                            <td data-label="Waktu Update">
                                <span style="font-size: 12px; font-weight: 600; color: var(--ink-base);"><?= e($waktuFormatted) ?></span>
                            </td>
                            <td data-label="Kategori">
                                <?php if ($isTahfidh): ?>
                                    <span class="badge badge-amber" style="font-size: 11px;">📖 Tahfidh</span>
                                <?php else: ?>
                                    <span class="badge badge-green" style="font-size: 11px;">📘 Akademik</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Mata Pelajaran / Hafalan">
                                <strong><?= e($row['item_name']) ?></strong>
                            </td>
                            <td data-label="Siswa & Kelas">
                                <div style="font-weight: 700; color: var(--green-900);"><?= e($row['student_name']) ?></div>
                                <div style="font-size: 12px; color: var(--ink-soft);">Kelas <?= e($row['kelas']) ?> · NISN: <?= e($row['nisn']) ?></div>
                            </td>
                            <td data-label="Nilai" style="text-align: center; font-weight: 800; color: var(--green-800); font-size: 15px;">
                                <?= number_format($score, 1) ?>
                            </td>
                            <td data-label="Predikat" style="text-align: center;">
                                <span class="predikat-badge <?= $predClass ?>"><?= $pred ?></span>
                            </td>
                            <td data-label="Semester" style="font-size: 12.5px;">
                                Sem <?= (int)$row['semester'] ?> (<?= (int)$row['semester'] === 1 ? 'Ganjil' : 'Genap' ?>)<br>
                                <span style="font-size: 11.5px; color: var(--ink-soft);"><?= e($row['school_year']) ?></span>
                            </td>
                            <td data-label="Catatan Guru" style="font-size: 12.5px; color: var(--ink-soft);">
                                <?= e($row['description'] ?: '–') ?>
                            </td>
                            <td data-label="Aksi" style="text-align: center;">
                                <div class="actions" style="justify-content: center; gap: 4px;">
                                    <?php if ($isTahfidh): ?>
                                        <a href="<?= e(base_url('/staff/tahfidh/input.php?student_id=' . (int)$row['student_id'] . '&semester=' . (int)$row['semester'] . '&school_year=' . urlencode($row['school_year']))) ?>" class="btn btn-ghost btn-sm" title="Edit Nilai Tahfidh">Edit</a>
                                    <?php else: ?>
                                        <a href="<?= e(base_url('/staff/nilai/input.php?student_id=' . (int)$row['student_id'] . '&semester=' . (int)$row['semester'] . '&school_year=' . urlencode($row['school_year']))) ?>" class="btn btn-ghost btn-sm" title="Edit Nilai Mapel">Edit</a>
                                    <?php endif; ?>
                                    <a href="<?= e(base_url('/staff/rapor/cetak.php?student_id=' . (int)$row['student_id'] . '&semester=' . (int)$row['semester'] . '&school_year=' . urlencode($row['school_year']))) ?>" target="_blank" class="btn btn-gold btn-sm" title="Cetak Rapor PDF">PDF</a>
                                    
                                    <!-- Tombol Sampah Hapus Riwayat (Muncul saat klik Hapus Riwayat) -->
                                    <button type="button" 
                                            class="btn btn-delete btn-sm btn-delete-row" 
                                            onclick="openDeleteModal('<?= (int)$row['id'] ?>', '<?= e($row['tipe']) ?>', '<?= e(addslashes($row['item_name'])) ?>', '<?= e(addslashes($row['student_name'])) ?>')" 
                                            title="Hapus Nilai Ini" 
                                            style="padding: 6px 9px; font-size: 12px; display: none; background: #DC2626; color: #FFFFFF; border: none; border-radius: 6px; cursor: pointer;">
                                        🗑
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Pop-up Konfirmasi Hapus Data -->
<div id="modalConfirmDelete" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div style="background: #FFFFFF; border-radius: 16px; max-width: 440px; width: 90%; padding: 26px 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.25); text-align: center; border: 1px solid var(--line); animation: fadeIn 0.2s ease-out;">
        <div style="width: 54px; height: 54px; border-radius: 50%; background: #FEE2E2; color: #DC2626; display: flex; align-items: center; justify-content: center; font-size: 26px; margin: 0 auto 16px;">
            🗑️
        </div>
        <h3 style="margin: 0 0 8px; font-size: 18px; font-weight: 800; color: #143523;">Konfirmasi Hapus Riwayat Nilai</h3>
        <p style="margin: 0 0 22px; font-size: 13.5px; color: var(--ink-soft); line-height: 1.5;" id="deleteConfirmMessage">
            Apakah Anda yakin ingin menghapus riwayat nilai ini? Data yang dihapus tidak dapat dikembalikan.
        </p>
        <form method="POST" action="" id="formDeleteHistory">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_history">
            <input type="hidden" name="id" id="deleteTargetId" value="">
            <input type="hidden" name="tipe" id="deleteTargetType" value="">
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="button" class="btn btn-ghost" onclick="closeDeleteModal()" style="flex: 1; padding: 10px 16px; font-weight: 600;">Batal</button>
                <button type="submit" class="btn btn-delete" style="flex: 1; padding: 10px 16px; background: #DC2626; color: #FFFFFF; border: none; font-weight: 700; border-radius: 8px; cursor: pointer;">Ya, Hapus Data</button>
            </div>
        </form>
    </div>
</div>

<script>
let isDeleteModeActive = false;

function toggleDeleteMode() {
    isDeleteModeActive = !isDeleteModeActive;
    const btnToggle = document.getElementById('btnToggleDeleteMode');
    const btnIcon = document.getElementById('btnDeleteIcon');
    const btnText = document.getElementById('btnDeleteText');
    const deleteButtons = document.querySelectorAll('.btn-delete-row');

    if (isDeleteModeActive) {
        btnToggle.style.background = '#FEE2E2';
        btnToggle.style.color = '#991B1B';
        btnToggle.style.border = '1.5px solid #FCA5A5';
        if (btnIcon) btnIcon.textContent = '✕';
        if (btnText) btnText.textContent = 'Selesai Hapus';

        deleteButtons.forEach(btn => {
            btn.style.display = 'inline-flex';
        });
    } else {
        btnToggle.style.background = '#DC2626';
        btnToggle.style.color = '#FFFFFF';
        btnToggle.style.border = 'none';
        if (btnIcon) btnIcon.textContent = '🗑️';
        if (btnText) btnText.textContent = 'Hapus Riwayat';

        deleteButtons.forEach(btn => {
            btn.style.display = 'none';
        });
    }
}

function openDeleteModal(id, tipe, itemName, studentName) {
    const modal = document.getElementById('modalConfirmDelete');
    const msg = document.getElementById('deleteConfirmMessage');
    const inputId = document.getElementById('deleteTargetId');
    const inputType = document.getElementById('deleteTargetType');

    if (modal && inputId && inputType && msg) {
        inputId.value = id;
        inputType.value = tipe;
        msg.innerHTML = `Apakah Anda yakin ingin menghapus riwayat nilai <strong>${escapeHtml(itemName)}</strong> untuk siswa <strong>${escapeHtml(studentName)}</strong>?<br><span style="font-size:12.5px; color:#DC2626; display:inline-block; margin-top:6px;">Tindakan ini akan menghapus nilai tersebut secara permanen.</span>`;
        modal.style.display = 'flex';
    }
}

function closeDeleteModal() {
    const modal = document.getElementById('modalConfirmDelete');
    if (modal) {
        modal.style.display = 'none';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. Live Automatic Search on Input
    const searchInput = document.getElementById('liveSearchRiwayat');
    const tableRows = Array.from(document.querySelectorAll('#riwayatTable tbody tr[data-student-search]'));

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            tableRows.forEach(row => {
                const text = row.dataset.studentSearch || '';
                row.style.display = (!query || text.includes(query)) ? '' : 'none';
            });
        });
    }

    // 2. Button Toggle Delete Mode
    const btnToggle = document.getElementById('btnToggleDeleteMode');
    if (btnToggle) {
        btnToggle.addEventListener('click', toggleDeleteMode);
    }

    // 3. Close modal on backdrop click or ESC key
    const modal = document.getElementById('modalConfirmDelete');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeDeleteModal();
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeDeleteModal();
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
