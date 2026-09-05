<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_academic_access();

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$teacherMapel = trim((string)($user['mata_pelajaran'] ?? ''));

// Daftar Mata Pelajaran Dinamis dari Database
$availableSubjects = get_all_subjects($pdo);

// Ambil semua data siswa untuk dropdown
$stmtAllStudents = $pdo->query("SELECT id, nis, nisn, nama, kelas FROM students ORDER BY nama ASC");
$allStudents = $stmtAllStudents->fetchAll(PDO::FETCH_ASSOC);

// Tentukan Siswa yang sedang dipilih
$selectedStudentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT) ?: (int)($_POST['student_id'] ?? 0);
if (!$selectedStudentId && !empty($allStudents)) {
    // Default to the first student if not specified, or allow 0 for placeholder
    $selectedStudentId = (int)$allStudents[0]['id'];
}

$currentStudent = null;
foreach ($allStudents as $st) {
    if ((int)$st['id'] === $selectedStudentId) {
        $currentStudent = $st;
        break;
    }
}

// Semester dan Tahun Ajaran
$selectedSemester = (int)($_GET['semester'] ?? ($_POST['semester'] ?? 2));
if (!in_array($selectedSemester, [1, 2], true)) {
    $selectedSemester = 2;
}
$schoolYear = trim($_GET['school_year'] ?? ($_POST['school_year'] ?? '2025/2026'));

// Ambil nilai yang sudah tersimpan untuk siswa ini pada semester & tahun ajaran terpilih
$existingGrades = [];
if ($selectedStudentId > 0) {
    $stmtGrades = $pdo->prepare("
        SELECT subject, score, description 
        FROM academic_grades 
        WHERE student_id = :sid AND semester = :sem AND school_year = :sy
    ");
    $stmtGrades->execute(['sid' => $selectedStudentId, 'sem' => $selectedSemester, 'sy' => $schoolYear]);
    foreach ($stmtGrades->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existingGrades[$row['subject']] = [
            'score' => (float)$row['score'],
            'description' => $row['description'] ?? '',
        ];
    }
}

$errors = [];

// Handle Simpan Nilai
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $pScores = $_POST['p_scores'] ?? [];
    $kScores = $_POST['k_scores'] ?? [];
    $postStudentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);

    if (!$postStudentId) {
        $errors[] = 'Silakan pilih siswa terlebih dahulu.';
    } else {
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

            foreach ($availableSubjects as $sub) {
                // If user is a subject teacher, only allow saving their assigned subject (or all if admin)
                if (!$isAdmin && !empty($teacherMapel) && $teacherMapel !== $sub) {
                    continue;
                }

                $valP = trim((string)($pScores[$sub] ?? ''));
                $valK = trim((string)($kScores[$sub] ?? ''));

                if ($valP !== '' || $valK !== '') {
                    $numP = $valP !== '' ? (float)$valP : 0.0;
                    $numK = $valK !== '' ? (float)$valK : $numP;
                    $finalScore = ($valP !== '' && $valK !== '') ? (($numP + $numK) / 2) : ($valP !== '' ? $numP : $numK);

                    if ($finalScore < 0 || $finalScore > 100) {
                        throw new Exception("Nilai untuk mata pelajaran '{$sub}' harus berada dalam rentang 0 sampai 100.");
                    }

                    $stmtUpsert->execute([
                        'sid' => $postStudentId,
                        'subj' => $sub,
                        'score' => $finalScore,
                        'desc' => null,
                        'sem' => $selectedSemester,
                        'sy' => $schoolYear,
                    ]);
                    $savedCount++;
                } else {
                    // Jika kedua input dikosongkan untuk mapel yang diizinkan
                    if ($isAdmin || (!empty($teacherMapel) && $teacherMapel === $sub)) {
                        $stmtDelete->execute([
                            'sid' => $postStudentId,
                            'subj' => $sub,
                            'sem' => $selectedSemester,
                            'sy' => $schoolYear,
                        ]);
                    }
                }
            }

            // Perbarui status rapor siswa
            $stmtCountMapel = $pdo->prepare("SELECT COUNT(*) FROM academic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
            $stmtCountMapel->execute(['sid' => $postStudentId, 'sem' => $selectedSemester, 'sy' => $schoolYear]);
            $totalMapel = (int)$stmtCountMapel->fetchColumn();

            $stmtCountTahfidh = $pdo->prepare("SELECT COUNT(*) FROM tahfidh_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
            $stmtCountTahfidh->execute(['sid' => $postStudentId, 'sem' => $selectedSemester, 'sy' => $schoolYear]);
            $hasTahfidh = (int)$stmtCountTahfidh->fetchColumn() > 0;

            $reportStatus = ($totalMapel >= 5 && $hasTahfidh) ? 'ready' : 'draft';
            $stmtRep = $pdo->prepare("
                INSERT INTO reports (student_id, semester, school_year, status) 
                VALUES (:sid, :sem, :sy, :status) 
                ON DUPLICATE KEY UPDATE status = CASE WHEN status = 'published' THEN 'published' ELSE VALUES(status) END
            ");
            $stmtRep->execute(['sid' => $postStudentId, 'sem' => $selectedSemester, 'sy' => $schoolYear, 'status' => $reportStatus]);

            $pdo->commit();

            $studentName = $currentStudent['nama'] ?? 'Siswa';
            set_flash('success', "Nilai akademik untuk {$studentName} berhasil disimpan ({$savedCount} mata pelajaran terisi).");
            redirect("/staff/nilai/input.php?student_id={$postStudentId}&semester={$selectedSemester}&school_year=" . urlencode($schoolYear));
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
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

$pageTitle = 'Input Nilai Akademik - MTs Roudlotul Qur\'an';
$activeMenu = 'nilai';

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Custom Hero Header Banner Matching Reference Image -->
<div style="background: linear-gradient(135deg, #157A42 0%, #126336 100%); border-radius: 18px; padding: 26px 30px; color: #FFFFFF; display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; flex-wrap: wrap; gap: 16px; box-shadow: 0 8px 24px rgba(21, 122, 66, 0.18);">
    <div>
        <h1 style="margin: 0; font-size: 25px; font-weight: 800; color: #FFFFFF; letter-spacing: -0.01em;">Input Nilai Akademik</h1>
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

<!-- Tips Callout Card Matching Reference Image -->
<div style="background: #FEF7E6; border: 1.5px solid #F3DCAC; border-radius: 14px; padding: 14px 20px; display: flex; align-items: center; gap: 14px; margin-bottom: 22px;">
    <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--gold-500); color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; box-shadow: 0 2px 8px rgba(217,155,38,0.35);">
        💡
    </div>
    <div style="font-size: 13.5px; color: #78350F; line-height: 1.5;">
        <strong>Tips pengisian:</strong> nilai memakai skala 0–100. Predikat A/B/C/D akan muncul otomatis begitu kolom Pengetahuan dan Keterampilan sudah terisi keduanya.
    </div>
</div>

<!-- Main Form Penilaian -->
<form method="POST" action="" id="formAkademik">
    <?= csrf_field() ?>
    <input type="hidden" name="semester" value="<?= $selectedSemester ?>">
    <input type="hidden" name="school_year" value="<?= e($schoolYear) ?>">

    <!-- Pilih Siswa Card -->
    <div class="card" style="margin-bottom: 22px; padding: 18px 22px; border-radius: 14px; background: #FFFFFF; border: 1px solid var(--line);">
        <label for="studentSelect" style="display: block; font-size: 13px; font-weight: 700; color: var(--ink-soft); margin-bottom: 8px;">Pilih Siswa</label>
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 260px;">
                <select id="studentSelect" name="student_id" onchange="onStudentChange(this)" 
                        style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 1.5px solid var(--line); font-size: 14px; font-weight: 600; color: var(--green-900); background: #FFFFFF; font-family: inherit; cursor: pointer;">
                    <option value="">— Cari nama siswa —</option>
                    <?php foreach ($allStudents as $st): ?>
                        <option value="<?= (int)$st['id'] ?>" 
                                data-nisn="<?= e($st['nisn']) ?>" 
                                data-kelas="<?= e($st['kelas']) ?>"
                                <?= ($selectedStudentId === (int)$st['id']) ? 'selected' : '' ?>>
                            <?= e($st['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <span id="badgeNisn" style="background: #D1F0DC; color: var(--green-800); font-weight: 700; font-size: 12.5px; padding: 7px 16px; border-radius: 999px;">
                    NISN: <span id="valNisn"><?= !empty($currentStudent['nisn']) ? e($currentStudent['nisn']) : '-' ?></span>
                </span>
                <span id="badgeKelas" style="background: #D1F0DC; color: var(--green-800); font-weight: 700; font-size: 12.5px; padding: 7px 16px; border-radius: 999px;">
                    Kelas: <span id="valKelas"><?= !empty($currentStudent['kelas']) ? e($currentStudent['kelas']) : '-' ?></span>
                </span>
                <span style="font-size: 13px; font-weight: 600; color: var(--ink-soft); padding: 6px 12px; background: #F8FAF9; border-radius: 8px; border: 1px solid var(--line);">
                    <?= $selectedSemester === 1 ? 'Ganjil' : 'Genap' ?> <?= e($schoolYear) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Section Subheader -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
        <h3 style="margin: 0; font-size: 16.5px; font-weight: 800; color: var(--green-900);">Nilai Per Mata Pelajaran</h3>
        <div id="statusCounter" style="font-size: 13.5px; font-weight: 600; color: var(--ink-soft);">
            0 dari <?= count($availableSubjects) ?> mapel terisi
        </div>
    </div>

    <!-- Cards Grid (2 Columns) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 16px; margin-bottom: 26px;">
        <?php 
        $religiousSubjects = ['Al-Qur\'an Hadits', 'Aqidah Akhlak', 'Fiqih', 'Sejarah Kebudayaan Islam', 'Sejarah Kebudayaan Islam (SKI)', 'Bahasa Arab'];
        
        foreach ($availableSubjects as $sub): 
            $isAgama = in_array($sub, $religiousSubjects, true);
            $kelompokLabel = $isAgama ? 'Kelompok Agama' : 'Kelompok Umum';
            
            $existingScore = isset($existingGrades[$sub]) ? (string)$existingGrades[$sub]['score'] : '';
            // Pre-fill Pengetahuan & Keterampilan with existing score if available
            $pVal = $existingScore;
            $kVal = $existingScore;
            
            $isEditable = $isAdmin || (empty($teacherMapel) || $teacherMapel === $sub);
        ?>
            <div class="subject-card" style="background: #FFFFFF; border: 1px solid var(--line); border-left: 4.5px solid var(--gold-500); border-radius: 14px; padding: 18px 20px; box-shadow: 0 2px 10px rgba(18,99,54,0.03);">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 14px;">
                    <div>
                        <div style="font-size: 15px; font-weight: 800; color: var(--green-900);"><?= e($sub) ?></div>
                        <span style="display: inline-block; background: #FEF3C7; color: #92400E; font-size: 11px; font-weight: 700; padding: 2.5px 9px; border-radius: 999px; margin-top: 4px;">
                            <?= e($kelompokLabel) ?>
                        </span>
                    </div>
                    <div class="predikat-badge-box" id="pred_<?= md5($sub) ?>" 
                         style="width: 34px; height: 28px; border-radius: 6px; background: #EDF7F0; color: var(--green-800); font-size: 13.5px; font-weight: 800; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease;">
                        -
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <label style="display: block; font-size: 11.5px; font-weight: 600; color: var(--ink-soft); margin-bottom: 4px;">Pengetahuan</label>
                        <input type="number" step="0.1" min="0" max="100" name="p_scores[<?= e($sub) ?>]" value="<?= e($pVal) ?>" placeholder="" 
                               class="score-input p-score" data-mapel="<?= e($sub) ?>" data-pred-id="pred_<?= md5($sub) ?>"
                               <?= !$isEditable ? 'readonly style="background: #f7faf8; cursor: not-allowed;"' : '' ?>
                               style="width: 100%; padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; font-size: 14px; font-weight: 700; text-align: center; font-family: inherit; color: var(--green-900);">
                    </div>
                    <div>
                        <label style="display: block; font-size: 11.5px; font-weight: 600; color: var(--ink-soft); margin-bottom: 4px;">Keterampilan</label>
                        <input type="number" step="0.1" min="0" max="100" name="k_scores[<?= e($sub) ?>]" value="<?= e($kVal) ?>" placeholder="" 
                               class="score-input k-score" data-mapel="<?= e($sub) ?>" data-pred-id="pred_<?= md5($sub) ?>"
                               <?= !$isEditable ? 'readonly style="background: #f7faf8; cursor: not-allowed;"' : '' ?>
                               style="width: 100%; padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; font-size: 14px; font-weight: 700; text-align: center; font-family: inherit; color: var(--green-900);">
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Form Action Bottom -->
    <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 10px; padding-bottom: 20px;">
        <button type="submit" class="btn btn-primary" style="padding: 13px 32px; font-size: 15px; font-weight: 700; border-radius: 10px; box-shadow: 0 4px 14px rgba(21,122,66,0.25);">
            💾 Simpan Nilai Akademik Siswa
        </button>
    </div>
</form>

<script>
function onStudentChange(select) {
    const studentId = select.value;
    if (studentId) {
        window.location.href = `<?= e(base_url('/staff/nilai/input.php')) ?>?student_id=${studentId}&semester=<?= $selectedSemester ?>&school_year=<?= urlencode($schoolYear) ?>`;
    } else {
        document.getElementById('valNisn').textContent = '-';
        document.getElementById('valKelas').textContent = '-';
    }
}

function calculateGradePredikat(pVal, kVal) {
    if (pVal === '' || kVal === '') {
        return '-';
    }
    const p = parseFloat(pVal);
    const k = parseFloat(kVal);
    if (isNaN(p) || isNaN(k)) {
        return '-';
    }
    const avg = (p + k) / 2;
    if (avg >= 90) return 'A';
    if (avg >= 80) return 'B';
    if (avg >= 70) return 'C';
    return 'D';
}

function refreshAllPredikatsAndCounter() {
    const cards = document.querySelectorAll('.subject-card');
    let filledCount = 0;
    const totalCount = cards.length;

    cards.forEach(card => {
        const pInput = card.querySelector('.p-score');
        const kInput = card.querySelector('.k-score');
        const predBox = card.querySelector('.predikat-badge-box');

        const p = pInput ? pInput.value.trim() : '';
        const k = kInput ? kInput.value.trim() : '';

        const pred = calculateGradePredikat(p, k);
        if (predBox) {
            predBox.textContent = pred;
            if (pred === 'A') {
                predBox.style.background = '#D1FAE5';
                predBox.style.color = '#065F46';
            } else if (pred === 'B') {
                predBox.style.background = '#DBEAFE';
                predBox.style.color = '#1E40AF';
            } else if (pred === 'C') {
                predBox.style.background = '#FEF3C7';
                predBox.style.color = '#92400E';
            } else if (pred === 'D') {
                predBox.style.background = '#FEE2E2';
                predBox.style.color = '#991B1B';
            } else {
                predBox.style.background = '#EDF7F0';
                predBox.style.color = '#157A42';
            }
        }

        if (p !== '' || k !== '') {
            filledCount++;
        }
    });

    const statusCounter = document.getElementById('statusCounter');
    if (statusCounter) {
        statusCounter.textContent = `${filledCount} dari ${totalCount} mapel terisi`;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Attach input listeners
    document.querySelectorAll('.score-input').forEach(input => {
        input.addEventListener('input', () => {
            refreshAllPredikatsAndCounter();
        });
    });

    // Initial calculation on page load
    refreshAllPredikatsAndCounter();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
