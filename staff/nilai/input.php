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
    $selectedSubject = trim($_GET['subject'] ?? ($_POST['subject'] ?? $availableSubjects[6] ?? 'Bahasa Indonesia'));
    if (!in_array($selectedSubject, $availableSubjects, true)) {
        $selectedSubject = $availableSubjects[6] ?? 'Bahasa Indonesia';
    }
} else {
    // Guru / Staff terkunci hanya pada mapel yang diampunya
    if (!empty($teacherMapel) && in_array($teacherMapel, $availableSubjects, true)) {
        $selectedSubject = $teacherMapel;
    } else {
        // Fallback jika belum diset oleh admin
        $selectedSubject = trim($_GET['subject'] ?? ($_POST['subject'] ?? $availableSubjects[6] ?? 'Bahasa Indonesia'));
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
$activeMenu = 'nilai';

// Ambil siswa pada kelas terpilih
$stmtSiswa = $pdo->prepare("
    SELECT id, nis, nisn, nama, kelas 
    FROM students 
    WHERE (kelas = :k1 OR kelas = :k2)
    ORDER BY nama ASC
");
$kNum = match($selectedKelas) {
    'VII', '7' => ['VII', '7'],
    'VIII', '8' => ['VIII', '8'],
    'IX', '9' => ['IX', '9'],
    default => [$selectedKelas, $selectedKelas]
};
$stmtSiswa->execute(['k1' => $kNum[0], 'k2' => $kNum[1]]);
$students = $stmtSiswa->fetchAll(PDO::FETCH_ASSOC);

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
    
    foreach ($stmtGrades->fetchAll(PDO::FETCH_ASSOC) as $g) {
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
            INSERT INTO academic_grades (student_id, subject, score, description, semester, school_year, created_at, updated_at) 
            VALUES (:sid, :subj, :score, :desc, :sem, :sy, NOW(), NOW())
            ON DUPLICATE KEY UPDATE score = VALUES(score), description = VALUES(description), updated_at = NOW()
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

// Generate avatar initials
$nameWords = preg_split('/\s+/', trim($user['nama_lengkap'] ?? $user['username'] ?? 'Admin Staf')) ?: ['A', 'S'];
$avatarInitials = '';
if (count($nameWords) >= 2) {
    $avatarInitials = strtoupper(mb_substr($nameWords[0], 0, 1) . mb_substr($nameWords[1], 0, 1));
} else {
    $avatarInitials = strtoupper(mb_substr($nameWords[0], 0, 2));
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Custom Hero Header Banner Matching Reference Theme -->
<div style="background: linear-gradient(135deg, #157A42 0%, #126336 100%); border-radius: 18px; padding: 26px 30px; color: #FFFFFF; display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; flex-wrap: wrap; gap: 16px; box-shadow: 0 8px 24px rgba(21, 122, 66, 0.18);">
    <div>
        <h1 style="margin: 0; font-size: 25px; font-weight: 800; color: #FFFFFF; letter-spacing: -0.01em;">Input Nilai Akademik: <?= e($selectedSubject) ?></h1>
        <p style="margin: 6px 0 0; color: #D1F0DC; font-size: 13.5px; max-width: 650px; line-height: 1.45;">
            Isi nilai untuk setiap mata pelajaran — kamu bisa isi sebagian dulu, sisanya bisa dilanjutkan nanti.
        </p>
    </div>
    <div style="display: flex; align-items: center; gap: 10px; background: rgba(0, 0, 0, 0.22); border: 1px solid rgba(255, 255, 255, 0.15); padding: 6px 14px 6px 8px; border-radius: 999px;">
        <span style="width: 28px; height: 28px; border-radius: 50%; background: var(--gold-500); color: #1A202C; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800;">
            <?= e($avatarInitials) ?>
        </span>
        <span style="font-size: 13px; font-weight: 700; color: #FFFFFF;"><?= e($user['nama_lengkap'] ?? $user['username'] ?? 'Admin Staf') ?></span>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="margin-bottom: 20px;">
        <ul style="margin:0; padding-left:20px;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Tips Callout Card Matching Reference Theme -->
<div style="background: #FEF7E6; border: 1.5px solid #F3DCAC; border-radius: 14px; padding: 14px 20px; display: flex; align-items: center; gap: 14px; margin-bottom: 22px;">
    <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--gold-500); color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; box-shadow: 0 2px 8px rgba(217,155,38,0.35);">
        💡
    </div>
    <div style="font-size: 13.5px; color: #78350F; line-height: 1.5;">
        <strong>Tips pengisian:</strong> nilai memakai skala 0–100. Predikat A/B/C/D akan muncul otomatis begitu kolom Pengetahuan dan Keterampilan sudah terisi keduanya.
    </div>
</div>

<!-- Form Filter & Pemilihan Mapel/Kelas/Semester -->
<div class="picker-card" style="background: #FFFFFF; border: 1px solid var(--line); border-radius: 14px; padding: 18px 22px; margin-bottom: 22px;">
    <!-- Mapel Info / Dropdown -->
    <div class="picker-field" style="min-width: 240px;">
        <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--ink-soft); margin-bottom: 6px;">Mata Pelajaran</label>
        <?php if ($isAdmin): ?>
            <select id="filterSubject" onchange="applyPenilaianFilter()" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--line); font-weight: 700; color: var(--green-900); font-family: inherit;">
                <?php foreach ($availableSubjects as $sub): ?>
                    <option value="<?= e($sub) ?>" <?= $selectedSubject === $sub ? 'selected' : '' ?>>
                        📘 <?= e($sub) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <div style="padding: 8px 14px; background: #FFFFFF; border: 1.5px solid var(--green-700); border-radius: 8px; font-weight: 700; color: var(--green-900); display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 15px;">📘</span>
                <span><?= e($selectedSubject) ?></span>
                <span class="badge badge-success" style="margin-left: auto; font-size: 11px;">Mapel Anda</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Dropdown Kelas (7, 8, 9) -->
    <div class="picker-field" style="min-width: 140px; max-width: 180px;">
        <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--ink-soft); margin-bottom: 6px;">Pilih Kelas</label>
        <select id="filterKelas" onchange="applyPenilaianFilter()" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--line); font-weight: 700; color: var(--green-900); font-family: inherit;">
            <option value="VII" <?= in_array($selectedKelas, ['VII', '7']) ? 'selected' : '' ?>>Kelas 7 (VII)</option>
            <option value="VIII" <?= in_array($selectedKelas, ['VIII', '8']) ? 'selected' : '' ?>>Kelas 8 (VIII)</option>
            <option value="IX" <?= in_array($selectedKelas, ['IX', '9']) ? 'selected' : '' ?>>Kelas 9 (IX)</option>
        </select>
    </div>

    <!-- Dropdown Semester (Ganjil & Genap) -->
    <div class="picker-field" style="min-width: 180px; max-width: 220px;">
        <label style="display: block; font-size: 12.5px; font-weight: 700; color: var(--ink-soft); margin-bottom: 6px;">Pilih Semester</label>
        <select id="filterSemester" onchange="applyPenilaianFilter()" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--line); font-weight: 700; color: var(--green-900); font-family: inherit;">
            <option value="1" <?= $selectedSemester === 1 ? 'selected' : '' ?>>Semester 1 (Ganjil)</option>
            <option value="2" <?= $selectedSemester === 2 ? 'selected' : '' ?>>Semester 2 (Genap)</option>
        </select>
    </div>

    <!-- Info Chip Siswa -->
    <div style="margin-left: auto; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        <span style="background: #D1F0DC; color: var(--green-800); font-weight: 700; font-size: 12.5px; padding: 6px 14px; border-radius: 999px;">
            Total di Kelas: <b><?= count($students) ?> Siswa</b>
        </span>
        <span style="font-size: 13px; font-weight: 600; color: var(--ink-soft); padding: 4px 8px;">
            <?= e($schoolYear) ?>
        </span>
    </div>
</div>

<!-- Form Penilaian Batch Per Kelas -->
<form class="card" method="POST" action="" id="formPenilaian" style="background: #FFFFFF; border: 1px solid var(--line); border-left: 4.5px solid var(--gold-500); border-radius: 14px; padding: 22px 24px;">
    <?= csrf_field() ?>
    <input type="hidden" name="subject" value="<?= e($selectedSubject) ?>">
    <input type="hidden" name="kelas" value="<?= e($selectedKelas) ?>">
    <input type="hidden" name="semester" value="<?= $selectedSemester ?>">
    <input type="hidden" name="school_year" value="<?= e($schoolYear) ?>">

    <div class="panel-head" style="margin-bottom: 18px;">
        <div>
            <h2 style="font-size: 18px; font-weight: 800; color: var(--green-900); margin: 0 0 4px;">Daftar Siswa Kelas <?= e($selectedKelas) ?></h2>
            <p class="section-hint" style="margin: 0; font-size: 13px; color: var(--ink-soft);">Ketik nilai <strong>Pengetahuan</strong> dan <strong>Keterampilan</strong> (skala 0–100). Nilai akhir dan predikat akan otomatis terkalkulasi.</p>
        </div>
        <div>
            <button type="submit" class="btn btn-primary" style="padding: 10px 20px; font-size: 14px; font-weight: 700; box-shadow: 0 4px 12px rgba(21,122,66,0.25);">
                💾 Simpan Semua Nilai
            </button>
        </div>
    </div>

    <!-- Live Search Input Siswa -->
    <div style="margin-bottom: 18px;">
        <div style="position: relative; max-width: 480px;">
            <input type="text" id="liveSearchInput" placeholder="🔍 Cari nama siswa atau NISN..." 
                   style="width: 100%; padding: 10px 14px 10px 38px; border: 1.5px solid var(--line); border-radius: 10px; font-size: 13.5px; font-family: inherit; outline: none;">
            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 15px; color: var(--ink-soft); pointer-events: none;">🔍</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="responsive-stack" id="gradingTable">
            <thead>
                <tr>
                    <th style="width: 40px;" class="num">No</th>
                    <th style="width: 120px;" class="nisn">NISN</th>
                    <th style="min-width: 200px;">Nama Siswa</th>
                    <th style="width: 105px;" class="num">Pengetahuan</th>
                    <th style="width: 105px;" class="num">Keterampilan</th>
                    <th style="width: 95px;" class="num">Rata-Rata</th>
                    <th style="width: 85px;" class="num">Predikat</th>
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
                            <td class="name" data-label="Nama Siswa">
                                <div style="font-weight: 700; color: var(--green-900);"><?= e($st['nama']) ?></div>
                            </td>
                            <td class="num" data-label="Pengetahuan">
                                <input type="number" step="0.1" min="0" max="100" 
                                       name="p_score[<?= $sid ?>]" 
                                       class="score-input input-p" 
                                       data-sid="<?= $sid ?>"
                                       value="<?= $savedScore !== null ? e((string)$savedScore) : '' ?>"
                                       placeholder="0 - 100"
                                       style="width: 95px; padding: 7px 10px; border: 1px solid var(--line); border-radius: 8px; font-weight: 700; text-align: center; font-family: inherit;">
                            </td>
                            <td class="num" data-label="Keterampilan">
                                <input type="number" step="0.1" min="0" max="100" 
                                       name="k_score[<?= $sid ?>]" 
                                       class="score-input input-k" 
                                       data-sid="<?= $sid ?>"
                                       value="<?= $savedScore !== null ? e((string)$savedScore) : '' ?>"
                                       placeholder="0 - 100"
                                       style="width: 95px; padding: 7px 10px; border: 1px solid var(--line); border-radius: 8px; font-weight: 700; text-align: center; font-family: inherit;">
                            </td>
                            <td class="num" data-label="Rata-Rata">
                                <strong id="avg-<?= $sid ?>" style="color: var(--green-800); font-size: 14.5px;"><?= $savedScore !== null ? number_format($savedScore, 1) : '–' ?></strong>
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
        <div class="form-actions" style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
            <a href="<?= e(base_url('/staff/dashboard.php')) ?>" class="btn btn-ghost">Kembali ke Dashboard</a>
            <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 14.5px; font-weight: 700; box-shadow: 0 4px 14px rgba(21,122,66,0.25);">
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
