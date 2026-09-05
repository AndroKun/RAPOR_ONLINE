<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_tahfidh_access();

$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
if (!$studentId) {
    set_flash('danger', 'Siswa tidak ditemukan.');
    redirect('/staff/tahfidh/index.php');
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $studentId]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('danger', 'Data siswa tidak ditemukan.');
    redirect('/staff/tahfidh/index.php');
}

$semester = (int)($_GET['semester'] ?? 2);
$schoolYear = trim($_GET['school_year'] ?? '2025/2026');

$pageTitle = 'Input Nilai Tahfidh - ' . $student['nama'];
$contentTitle = 'Input Nilai Tahfidh Al-Qur\'an';
$contentSubtitle = 'Penilaian setoran hafalan, fashahah, kelancaran, dan tajwid santri.';
$activeMenu = 'tahfidh';

// Ambil nilai tahfidh yang sudah tersimpan
$stmtTahfidh = $pdo->prepare("SELECT memorization, score, description FROM tahfidh_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
$stmtTahfidh->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
$savedTahfidh = $stmtTahfidh->fetchAll();

// Target hafalan dinamis dari master kategori tahfidh di database
$masterTargets = get_all_tahfidh_categories($pdo);

$existingMap = [];
foreach ($savedTahfidh as $t) {
    $existingMap[$t['memorization']] = [
        'score' => (float)$t['score'],
        'description' => $t['description'] ?? '',
    ];
}

// Gabungkan master targets dan targets yang sudah tersimpan
$combinedTargets = array_values(array_unique(array_merge($masterTargets, array_keys($existingMap))));

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $memorizations = $_POST['memorizations'] ?? [];
    $scores = $_POST['scores'] ?? [];
    $descriptions = $_POST['descriptions'] ?? [];

    try {
        $pdo->beginTransaction();

        // Hapus entri lama untuk periode ini
        $stmtDel = $pdo->prepare("DELETE FROM tahfidh_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
        $stmtDel->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);

        $stmtInsert = $pdo->prepare("INSERT INTO tahfidh_grades (student_id, memorization, score, description, semester, school_year) 
                                     VALUES (:sid, :mem, :score, :desc, :sem, :sy)");

        $insertedCount = 0;
        for ($i = 0; $i < count($memorizations); $i++) {
            $mem = trim((string)($memorizations[$i] ?? ''));
            $scoreVal = trim((string)($scores[$i] ?? ''));
            $desc = trim((string)($descriptions[$i] ?? ''));

            if ($mem !== '' && $scoreVal !== '') {
                $scoreNum = (float)$scoreVal;
                if ($scoreNum < 0 || $scoreNum > 100) {
                    throw new Exception("Nilai hafalan '{$mem}' harus berada pada rentang 0 sampai 100.");
                }

                $stmtInsert->execute([
                    'sid' => $studentId,
                    'mem' => $mem,
                    'score' => $scoreNum,
                    'desc' => $desc ?: null,
                    'sem' => $semester,
                    'sy' => $schoolYear,
                ]);
                $insertedCount++;
            }
        }

        // Cek kelengkapan untuk status rapor
        $stmtAcademic = $pdo->prepare("SELECT COUNT(*) FROM academic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
        $stmtAcademic->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
        $hasAcademic = (int)$stmtAcademic->fetchColumn() >= 5;

        $newStatus = ($insertedCount > 0 && $hasAcademic) ? 'ready' : 'draft';

        $stmtRep = $pdo->prepare("INSERT INTO reports (student_id, semester, school_year, status) 
                                  VALUES (:sid, :sem, :sy, :status) 
                                  ON DUPLICATE KEY UPDATE status = CASE WHEN status = 'published' THEN 'published' ELSE VALUES(status) END");
        $stmtRep->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear, 'status' => $newStatus]);

        $pdo->commit();

        set_flash('success', "Nilai tahfidh untuk {$student['nama']} berhasil disimpan.");
        redirect('/staff/tahfidh/index.php?semester=' . $semester . '&school_year=' . urlencode($schoolYear));
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Info Siswa Card -->
<div class="picker-card" style="margin-bottom: 20px;">
    <div style="display: flex; align-items: center; gap: 14px; flex: 1;">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: var(--green-100); color: var(--green-800); display: flex; align-items: center; justify-content: center; font-size: 20px;">
            📖
        </div>
        <div>
            <div style="font-size: 16px; font-weight: 800; color: var(--green-900);"><?= e($student['nama']) ?></div>
            <div style="font-size: 13px; color: var(--ink-soft); margin-top: 2px;">
                Kelas: <b><?= e($student['kelas']) ?></b> &nbsp;·&nbsp; 
                NISN: <b><?= e($student['nisn']) ?></b> &nbsp;·&nbsp; 
                Semester: <b><?= $semester === 1 ? '1 (Ganjil)' : '2 (Genap)' ?> <?= e($schoolYear) ?></b>
            </div>
        </div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="<?= e(base_url('/staff/tahfidh/index.php?semester=' . $semester . '&school_year=' . urlencode($schoolYear))) ?>" class="btn btn-ghost btn-sm">
            ← Kembali ke Daftar
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul style="margin:0; padding-left:20px;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Form Penilaian Tahfidh -->
<form method="POST" action="" class="card" id="formTahfidh">
    <?= csrf_field() ?>

    <div class="panel-head">
        <div>
            <h2>Formulir Capaian Tahfidh Al-Qur'an</h2>
            <p class="section-hint" style="margin: 2px 0 0;">Isi nilai (0–100) dan catatan fashahah/tajwid pada target hafalan di bawah ini.</p>
        </div>
        <button type="submit" class="btn btn-primary">
            💾 Simpan Nilai Tahfidh
        </button>
    </div>

    <div class="table-responsive">
        <table class="responsive-stack">
            <thead>
                <tr>
                    <th style="width: 45px;" class="num">No</th>
                    <th style="min-width: 280px;">Target Hafalan / Surat / Juz</th>
                    <th style="width: 120px;" class="num">Nilai (0–100)</th>
                    <th>Catatan Fashahah, Kelancaran &amp; Tajwid</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($combinedTargets as $target): 
                    $valScore = isset($existingMap[$target]) ? (string)$existingMap[$target]['score'] : '';
                    $valDesc = isset($existingMap[$target]) ? $existingMap[$target]['description'] : '';
                ?>
                    <tr>
                        <td class="num" data-label="No"><?= $no++ ?></td>
                        <td data-label="Target Hafalan">
                            <input type="text" name="memorizations[]" value="<?= e($target) ?>" 
                                   style="width: 100%; padding: 8px 12px; font-weight: 700; color: var(--green-900); border: 1px solid var(--line); border-radius: 8px; font-family: inherit;">
                        </td>
                        <td class="num" data-label="Nilai (0-100)">
                            <input type="number" step="0.1" min="0" max="100" name="scores[]" value="<?= e($valScore) ?>" placeholder="0 - 100" 
                                   style="width: 100px; padding: 8px 12px; font-size: 14px; text-align: center; border: 1px solid var(--line); border-radius: 8px; font-weight: 700; font-family: inherit;">
                        </td>
                        <td data-label="Catatan Guru">
                            <input type="text" name="descriptions[]" value="<?= e($valDesc) ?>" placeholder="Contoh: Mutqin, fashahah makhraj baik, tartil..." 
                                   style="width: 100%; padding: 8px 12px; font-size: 13.5px; border: 1px solid var(--line); border-radius: 8px; font-family: inherit;">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions" style="margin-top: 24px;">
        <a href="<?= e(base_url('/staff/tahfidh/index.php?semester=' . $semester . '&school_year=' . urlencode($schoolYear))) ?>" class="btn btn-ghost">Batal</a>
        <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-size: 14.5px;">
            💾 Simpan Nilai Tahfidh Siswa
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
