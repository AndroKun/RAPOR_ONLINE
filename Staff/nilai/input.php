<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_academic_access();

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$teacherMapel = $user['mata_pelajaran'] ?? null;

// Daftar Mata Pelajaran Dinamis dari Database
$availableSubjects = get_all_subjects($pdo);

// Tentukan Mata Pelajaran Aktif
if ($isAdmin) {
    // Admin bisa memilih mapel apapun dari GET/POST, default ke yang pertama
    $selectedSubject = trim($_GET['subject'] ?? ($_POST['subject'] ?? $availableSubjects[6])); // default Bahasa Indonesia
    if (!in_array($selectedSubject, $availableSubjects, true)) {
        $selectedSubject = $availableSubjects[6];
    }
} else {
    // Guru / Staff terkunci hanya pada mapel yang diampunya
    if (!empty($teacherMapel) && in_array($teacherMapel, $availableSubjects, true)) {
        $selectedSubject = $teacherMapel;
    } else {
        // Fallback jika belum diset oleh admin
        $selectedSubject = trim($_GET['subject'] ?? ($_POST['subject'] ?? $availableSubjects[6]));
    }
}

// Filter Kelas (7, 8, 9 atau VII, VIII, IX)
$selectedKelas = trim($_GET['kelas'] ?? ($_POST['kelas'] ?? 'VII'));
if (!in_array($selectedKelas, ['VII', 'VIII', 'IX', '7', '8', '9'], true)) {
    $selectedKelas = 'VII';
}

// Filter Semester (1=Ganjil, 2=Genap)
$selectedSemester = (int)($_GET['semester'] ?? ($_POST['semester'] ?? 2));
if (!in_array($selectedSemester, [1, 2], true)) {
    $selectedSemester = 2;
}

$schoolYear = trim($_GET['school_year'] ?? ($_POST['school_year'] ?? '2025/2026'));

$pageTitle = 'Penilaian ' . $selectedSubject . ' - MTs Roudlotul Qur\'an';
$contentTitle = 'Penilaian Mata Pelajaran: ' . $selectedSubject;
$contentSubtitle = 'Pengisian nilai per kelas dan semester untuk mata pelajaran yang Anda ampu.';
$activeMenu = 'nilai';

// Ambil siswa pada kelas terpilih
$stmtSiswa = $pdo->prepare("
    SELECT id, nis, nisn, nama, kelas 
    FROM students 
    WHERE (kelas = :k1 OR kelas = :k2)
    ORDER BY nama ASC
");
// Cocokkan VII / 7
$kNum = match($selectedKelas) {
    'VII', '7' => ['VII', '7'],
    'VIII', '8' => ['VIII', '8'],
    'IX', '9' => ['IX', '9'],
    default => [$selectedKelas, $selectedKelas]
};
$stmtSiswa->execute(['k1' => $kNum[0], 'k2' => $kNum[1]]);
$students = $stmtSiswa->fetchAll();

// Ambil nilai yang sudah ada untuk Mapel, Kelas, dan Semester ini
$existingGrades = [];
if (!empty($students)) {
    $studentIds = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
    
    $sqlGrades = "
        SELECT student_id, score, description 
        FROM academic_grades 
        WHERE student_id IN ($placeholders)
          AND subject = ?
          AND semester = ?
          AND school_year = ?
    ";
    $params = array_merge($studentIds, [$selectedSubject, $selectedSemester, $schoolYear]);
    $stmtGrades = $pdo->prepare($sqlGrades);
    $stmtGrades->execute($params);
    
    foreach ($stmtGrades->fetchAll() as $g) {
        $existingGrades[(int)$g['student_id']] = [
            'score' => (float)$g['score'],
            'description' => $g['description'] ?? '',
        ];
    }
}

$errors = [];

// Handle Simpan Nilai Batch
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $pScores = $_POST['p_score'] ?? [];
    $kScores = $_POST['k_score'] ?? [];
    $notes = $_POST['description'] ?? [];

    try {
        $pdo->beginTransaction();

        $stmtUpsert = $pdo->prepare("
            INSERT INTO academic_grades (student_id, subject, score, description, semester, school_year) 
            VALUES (:sid, :subj, :score, :desc, :sem, :sy)
            ON DUPLICATE KEY UPDATE score = VALUES(score), description = VALUES(description)
        ");

        $stmtDelete = $pdo->prepare("
            DELETE FROM academic_grades 
            WHERE student_id = :sid AND subject = :subj AND semester = :sem AND school_year = :sy
        ");

        $savedCount = 0;

        foreach ($students as $st) {
            $sid = (int)$st['id'];
            $valP = trim((string)($pScores[$sid] ?? ''));
            $valK = trim((string)($kScores[$sid] ?? ''));
            $desc = trim((string)($notes[$sid] ?? ''));

            if ($valP !== '' || $valK !== '') {
                $numP = $valP !== '' ? (float)$valP : 0.0;
                $numK = $valK !== '' ? (float)$valK : $numP;
                $finalScore = ($valP !== '' && $valK !== '') ? (($numP + $numK) / 2) : ($valP !== '' ? $numP : $numK);

                if ($finalScore < 0 || $finalScore > 100) {
                    throw new Exception("Nilai untuk siswa '{$st['nama']}' harus berada dalam rentang 0 sampai 100.");
                }

                $stmtUpsert->execute([
                    'sid' => $sid,
                    'subj' => $selectedSubject,
                    'score' => $finalScore,
                    'desc' => $desc ?: null,
                    'sem' => $selectedSemester,
                    'sy' => $schoolYear,
                ]);
                $savedCount++;

                // Perbarui status rapor siswa
                $stmtCountMapel = $pdo->prepare("SELECT COUNT(*) FROM academic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
                $stmtCountMapel->execute(['sid' => $sid, 'sem' => $selectedSemester, 'sy' => $schoolYear]);
                $totalMapel = (int)$stmtCountMapel->fetchColumn();

                $stmtCountTahfidh = $pdo->prepare("SELECT COUNT(*) FROM tahfidh_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
                $stmtCountTahfidh->execute(['sid' => $sid, 'sem' => $selectedSemester, 'sy' => $schoolYear]);
                $hasTahfidh = (int)$stmtCountTahfidh->fetchColumn() > 0;

                $reportStatus = ($totalMapel >= 5 && $hasTahfidh) ? 'ready' : 'draft';
                $stmtRep = $pdo->prepare("
                    INSERT INTO reports (student_id, semester, school_year, status) 
                    VALUES (:sid, :sem, :sy, :status)
                    ON DUPLICATE KEY UPDATE status = CASE WHEN status = 'published' THEN 'published' ELSE VALUES(status) END
                ");
                $stmtRep->execute(['sid' => $sid, 'sem' => $selectedSemester, 'sy' => $schoolYear, 'status' => $reportStatus]);
            } else {
                // Hapus nilai jika dikosongkan
                $stmtDelete->execute([
                    'sid' => $sid,
                    'subj' => $selectedSubject,
                    'sem' => $selectedSemester,
                    'sy' => $schoolYear,
                ]);
            }
        }

        $pdo->commit();

        set_flash('success', "Nilai mata pelajaran '{$selectedSubject}' Kelas {$selectedKelas} (Semester {$selectedSemester}) berhasil disimpan ({$savedCount} siswa diperbarui).");
        redirect("/staff/nilai/input.php?kelas=" . urlencode($selectedKelas) . "&semester=" . $selectedSemester . "&subject=" . urlencode($selectedSubject));
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul style="margin:0; padding-left:20px;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Form Filter & Pemilihan Mapel/Kelas/Semester -->
<div class="picker-card">
    <!-- Mapel Info / Dropdown -->
    <div class="picker-field" style="min-width: 240px;">
        <label>Mata Pelajaran</label>
        <?php if ($isAdmin): ?>
            <select id="filterSubject" onchange="applyPenilaianFilter()">
                <?php foreach ($availableSubjects as $sub): ?>
                    <option value="<?= e($sub) ?>" <?= $selectedSubject === $sub ? 'selected' : '' ?>>
                        📘 <?= e($sub) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <div style="padding: 10px 14px; background: #FFFFFF; border: 1.5px solid var(--green-700); border-radius: 10px; font-weight: 700; color: var(--green-900); display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 16px;">📘</span>
                <span><?= e($selectedSubject) ?></span>
                <span class="badge badge-success" style="margin-left: auto; font-size: 11px;">Mata Pelajaran Anda</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Dropdown Kelas (7, 8, 9) -->
    <div class="picker-field" style="min-width: 140px; max-width: 180px;">
        <label>Pilih Kelas</label>
        <select id="filterKelas" onchange="applyPenilaianFilter()">
            <option value="VII" <?= in_array($selectedKelas, ['VII', '7']) ? 'selected' : '' ?>>Kelas 7 (VII)</option>
            <option value="VIII" <?= in_array($selectedKelas, ['VIII', '8']) ? 'selected' : '' ?>>Kelas 8 (VIII)</option>
            <option value="IX" <?= in_array($selectedKelas, ['IX', '9']) ? 'selected' : '' ?>>Kelas 9 (IX)</option>
        </select>
    </div>

    <!-- Dropdown Semester (Ganjil & Genap) -->
    <div class="picker-field" style="min-width: 180px; max-width: 220px;">
        <label>Pilih Semester</label>
        <select id="filterSemester" onchange="applyPenilaianFilter()">
            <option value="1" <?= $selectedSemester === 1 ? 'selected' : '' ?>>Semester 1 (Ganjil)</option>
            <option value="2" <?= $selectedSemester === 2 ? 'selected' : '' ?>>Semester 2 (Genap)</option>
        </select>
    </div>

    <!-- Info Chip Siswa -->
    <div class="info-chip" style="margin-left: auto;">
        <span>Total di Kelas: <b><?= count($students) ?> Siswa</b></span> &nbsp;·&nbsp;
        <span>Tahun Pelajaran: <b><?= e($schoolYear) ?></b></span>
    </div>
</div>

<!-- Form Penilaian Batch Per Kelas -->
<form class="card" method="POST" action="" id="formPenilaian">
    <?= csrf_field() ?>
    <input type="hidden" name="subject" value="<?= e($selectedSubject) ?>">
    <input type="hidden" name="kelas" value="<?= e($selectedKelas) ?>">
    <input type="hidden" name="semester" value="<?= $selectedSemester ?>">
    <input type="hidden" name="school_year" value="<?= e($schoolYear) ?>">

    <div class="panel-head">
        <div>
            <h2>Tabel Penilaian: <?= e($selectedSubject) ?> (Kelas <?= e($selectedKelas) ?>)</h2>
            <p class="section-hint" style="margin: 3px 0 0;">Ketik nilai Pengetahuan &amp; Keterampilan (0–100). Predikat akan terhitung otomatis secara live.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">💾 Simpan Semua Nilai</button>
        </div>
    </div>

    <!-- Live Search Filter untuk Ketik Nama Siswa -->
    <div class="filters" style="margin-bottom: 18px;">
        <input type="text" id="liveSearchInput" placeholder="🔍 Ketik nama siswa atau NISN untuk mencari dengan cepat..." autofocus>
        <button type="button" class="btn btn-ghost" onclick="resetLiveSearch()">Reset Pencarian</button>
    </div>

    <div class="table-responsive">
        <table class="responsive-stack" id="gradingTable">
            <thead>
                <tr>
                    <th style="width: 45px;" class="num">No</th>
                    <th style="width: 120px;">NISN</th>
                    <th>Nama Lengkap Siswa</th>
                    <th class="num" style="width: 110px;">Pengetahuan</th>
                    <th class="num" style="width: 110px;">Keterampilan</th>
                    <th class="num" style="width: 95px;">Rata-Rata</th>
                    <th class="num" style="width: 90px;">Predikat</th>
                    <th style="min-width: 240px;">Capaian Kompetensi / Catatan Guru</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 35px; color: var(--ink-soft);">
                            Belum ada siswa yang terdaftar di <strong>Kelas <?= e($selectedKelas) ?></strong>.
                            Silakan tambahkan data siswa di menu <strong>Input Data Siswa</strong>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = 1; 
                    foreach ($students as $st): 
                        $sid = (int)$st['id'];
                        $savedScore = isset($existingGrades[$sid]) ? $existingGrades[$sid]['score'] : null;
                        $savedDesc = isset($existingGrades[$sid]) ? $existingGrades[$sid]['description'] : '';

                        // Predikat awal
                        $pred = '–';
                        $predClass = '';
                        if ($savedScore !== null) {
                            if ($savedScore >= 90) { $pred = 'A'; $predClass = 'p-a'; }
                            elseif ($savedScore >= 80) { $pred = 'B'; $predClass = 'p-b'; }
                            elseif ($savedScore >= 70) { $pred = 'C'; $predClass = 'p-c'; }
                            else { $pred = 'D'; $predClass = 'p-d'; }
                        }
                    ?>
                        <tr data-student-search="<?= e(strtolower($st['nama'] . ' ' . $st['nisn'] . ' ' . $st['nis'])) ?>">
                            <td class="num" data-label="No"><?= $no++ ?></td>
                            <td class="nisn" data-label="NISN"><?= e($st['nisn']) ?></td>
                            <td class="name" data-label="Nama Siswa"><?= e($st['nama']) ?></td>
                            <td class="num" data-label="Pengetahuan">
                                <input type="number" step="0.1" min="0" max="100" 
                                       name="p_score[<?= $sid ?>]" 
                                       class="score-input input-p" 
                                       data-sid="<?= $sid ?>"
                                       value="<?= $savedScore !== null ? e((string)$savedScore) : '' ?>"
                                       placeholder="0 - 100">
                            </td>
                            <td class="num" data-label="Keterampilan">
                                <input type="number" step="0.1" min="0" max="100" 
                                       name="k_score[<?= $sid ?>]" 
                                       class="score-input input-k" 
                                       data-sid="<?= $sid ?>"
                                       value="<?= $savedScore !== null ? e((string)$savedScore) : '' ?>"
                                       placeholder="0 - 100">
                            </td>
                            <td class="num" data-label="Rata-Rata">
                                <strong id="avg-<?= $sid ?>"><?= $savedScore !== null ? number_format($savedScore, 1) : '–' ?></strong>
                            </td>
                            <td class="num predikat" data-label="Predikat">
                                <span class="predikat-badge <?= $predClass ?>" id="badge-<?= $sid ?>"><?= $pred ?></span>
                            </td>
                            <td data-label="Catatan Guru">
                                <input type="text" name="description[<?= $sid ?>]" 
                                       value="<?= e($savedDesc) ?>" 
                                       placeholder="Contoh: Sangat baik dalam memahami materi..." 
                                       style="width: 100%; padding: 8px 12px; font-size: 13.5px; border: 1px solid var(--line); border-radius: 8px; font-family: inherit;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($students)): ?>
        <div class="form-actions" style="margin-top: 24px;">
            <a href="<?= e(base_url('/staff/dashboard.php')) ?>" class="btn btn-ghost">Kembali ke Dashboard</a>
            <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-size: 14.5px;">
                💾 Simpan Semua Nilai <?= e($selectedSubject) ?>
            </button>
        </div>
    <?php endif; ?>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Live Predikat & Average Calculation
    const table = document.getElementById('gradingTable');
    if (table) {
        table.addEventListener('input', (e) => {
            if (e.target.classList.contains('input-p') || e.target.classList.contains('input-k')) {
                const sid = e.target.dataset.sid;
                if (!sid) return;

                const inputP = table.querySelector(`.input-p[data-sid="${sid}"]`);
                const inputK = table.querySelector(`.input-k[data-sid="${sid}"]`);
                const avgElem = document.getElementById(`avg-${sid}`);
                const badgeElem = document.getElementById(`badge-${sid}`);

                const valP = inputP ? inputP.value.trim() : '';
                const valK = inputK ? inputK.value.trim() : '';

                if (valP === '' && valK === '') {
                    if (avgElem) avgElem.textContent = '–';
                    if (badgeElem) {
                        badgeElem.textContent = '–';
                        badgeElem.className = 'predikat-badge';
                    }
                    return;
                }

                let avg = 0;
                if (valP !== '' && valK !== '') {
                    avg = (Number(valP) + Number(valK)) / 2;
                } else if (valP !== '') {
                    avg = Number(valP);
                } else {
                    avg = Number(valK);
                }

                if (avgElem) avgElem.textContent = avg.toFixed(1);

                if (badgeElem) {
                    let pLabel = 'D';
                    let pClass = 'p-d';
                    if (avg >= 90) { pLabel = 'A'; pClass = 'p-a'; }
                    else if (avg >= 80) { pLabel = 'B'; pClass = 'p-b'; }
                    else if (avg >= 70) { pLabel = 'C'; pClass = 'p-c'; }

                    badgeElem.textContent = pLabel;
                    badgeElem.className = `predikat-badge ${pClass}`;
                }
            }
        });
    }

    // 2. Live Search Filter per Siswa
    const searchInput = document.getElementById('liveSearchInput');
    const rows = Array.from(document.querySelectorAll('#gradingTable tbody tr[data-student-search]'));

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            rows.forEach(r => {
                const text = r.dataset.studentSearch || '';
                r.style.display = (!query || text.includes(query)) ? '' : 'none';
            });
        });
    }

    window.resetLiveSearch = function() {
        if (searchInput) {
            searchInput.value = '';
            rows.forEach(r => r.style.display = '');
            searchInput.focus();
        }
    };

    // 3. Dropdown Filter Router
    window.applyPenilaianFilter = function() {
        const k = document.getElementById('filterKelas').value;
        const s = document.getElementById('filterSemester').value;
        const subElem = document.getElementById('filterSubject');
        const sub = subElem ? subElem.value : '<?= e(urlencode($selectedSubject)) ?>';

        const url = `<?= e(base_url('/staff/nilai/input.php')) ?>?kelas=${encodeURIComponent(k)}&semester=${encodeURIComponent(s)}&subject=${encodeURIComponent(sub)}`;
        window.location.href = url;
    };
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
