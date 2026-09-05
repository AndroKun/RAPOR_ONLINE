<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
if (!$studentId) {
    set_flash('danger', 'Siswa tidak ditemukan.');
    redirect('/staff/nilai/index.php');
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $studentId]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('danger', 'Data siswa tidak ditemukan.');
    redirect('/staff/nilai/index.php');
}

$semester = (int)($_GET['semester'] ?? 2);
$schoolYear = trim($_GET['school_year'] ?? '2025/2026');

$pageTitle = 'Input Nilai Akademik - ' . $student['nama'];
$contentTitle = 'Input Nilai Akademik Siswa';
$activeMenu = 'nilai';

// Daftar Mata Pelajaran Standar MTs
$defaultSubjects = [
    'Al-Qur\'an Hadits',
    'Aqidah Akhlak',
    'Fiqih',
    'Sejarah Kebudayaan Islam (SKI)',
    'Bahasa Arab',
    'Pendidikan Pancasila dan Kewarganegaraan (PPKn)',
    'Bahasa Indonesia',
    'Matematika',
    'Ilmu Pengetahuan Alam (IPA)',
    'Ilmu Pengetahuan Sosial (IPS)',
    'Bahasa Inggris',
    'Seni Budaya & Prakarya (SBK)',
    'Pendidikan Jasmani, Olahraga & Kesehatan (PJOK)',
    'Bahasa Jawa / Muatan Lokal'
];

// Ambil nilai yang sudah ada
$stmtGrades = $pdo->prepare("SELECT subject, score, description FROM academic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
$stmtGrades->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
$existingGrades = [];
foreach ($stmtGrades->fetchAll() as $g) {
    $existingGrades[$g['subject']] = [
        'score' => (float)$g['score'],
        'description' => $g['description'] ?? '',
    ];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $scores = $_POST['scores'] ?? [];
    $descriptions = $_POST['descriptions'] ?? [];

    try {
        $pdo->beginTransaction();

        $stmtUpsert = $pdo->prepare("INSERT INTO academic_grades (student_id, subject, score, description, semester, school_year) 
                                     VALUES (:sid, :subj, :score, :desc, :sem, :sy)
                                     ON DUPLICATE KEY UPDATE score = VALUES(score), description = VALUES(description)");

        $insertedCount = 0;
        foreach ($scores as $subject => $scoreVal) {
            $scoreVal = trim((string)$scoreVal);
            if ($scoreVal !== '') {
                $scoreNum = (float)$scoreVal;
                if ($scoreNum < 0 || $scoreNum > 100) {
                    throw new Exception("Nilai mata pelajaran '{$subject}' harus berada pada rentang 0 sampai 100.");
                }
                $desc = trim((string)($descriptions[$subject] ?? ''));

                $stmtUpsert->execute([
                    'sid' => $studentId,
                    'subj' => $subject,
                    'score' => $scoreNum,
                    'desc' => $desc ?: null,
                    'sem' => $semester,
                    'sy' => $schoolYear,
                ]);
                $insertedCount++;
            } else {
                // Delete if cleared
                $stmtDel = $pdo->prepare("DELETE FROM academic_grades WHERE student_id = :sid AND subject = :subj AND semester = :sem AND school_year = :sy");
                $stmtDel->execute(['sid' => $studentId, 'subj' => $subject, 'sem' => $semester, 'sy' => $schoolYear]);
            }
        }

        // Cek apakah rapor siap (ready)
        $stmtTahfidh = $pdo->prepare("SELECT COUNT(*) FROM tahfidh_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
        $stmtTahfidh->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
        $hasTahfidh = (int)$stmtTahfidh->fetchColumn() > 0;

        $newStatus = ($insertedCount >= 5 && $hasTahfidh) ? 'ready' : 'draft';

        $stmtRep = $pdo->prepare("INSERT INTO reports (student_id, semester, school_year, status) 
                                  VALUES (:sid, :sem, :sy, :status) 
                                  ON DUPLICATE KEY UPDATE status = CASE WHEN status = 'published' THEN 'published' ELSE VALUES(status) END");
        $stmtRep->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear, 'status' => $newStatus]);

        $pdo->commit();

        set_flash('success', "Nilai akademik untuk {$student['nama']} berhasil disimpan.");
        redirect('/staff/nilai/index.php?semester=' . $semester . '&school_year=' . urlencode($schoolYear));
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="data-section">
    <div class="data-header">
        <div>
            <h2>Input Nilai: <?= e($student['nama']) ?></h2>
            <p style="margin:4px 0 0 0; color:#666; font-size:13.5px;">NISN: <?= e($student['nisn']) ?> | Kelas: <?= e($student['kelas']) ?> | Semester: <?= $semester === 1 ? '1 (Ganjil)' : '2 (Genap)' ?> <?= e($schoolYear) ?></p>
        </div>
        <a href="<?= e(base_url('/staff/nilai/index.php?semester=' . $semester . '&school_year=' . urlencode($schoolYear))) ?>" class="btn btn-secondary">Kembali</a>
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

    <form method="POST" action="" class="form-container" style="box-shadow:none; padding:10px 0;">
        <?= csrf_field() ?>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;">No</th>
                        <th style="width: 250px;">Mata Pelajaran</th>
                        <th style="width: 120px;">Nilai (0 - 100)</th>
                        <th>Capaian Kompetensi / Catatan Guru</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($defaultSubjects as $subj): ?>
                        <?php 
                        $valScore = isset($existingGrades[$subj]) ? (string)$existingGrades[$subj]['score'] : '';
                        $valDesc = isset($existingGrades[$subj]) ? $existingGrades[$subj]['description'] : '';
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= e($subj) ?></strong></td>
                            <td>
                                <input type="number" step="0.01" min="0" max="100" name="scores[<?= e($subj) ?>]" value="<?= e($valScore) ?>" placeholder="0.00" style="width:100px; padding:8px 10px; border:1px solid #cbd5e1; border-radius:5px; font-size:14px; text-align:center;">
                            </td>
                            <td>
                                <input type="text" name="descriptions[<?= e($subj) ?>]" value="<?= e($valDesc) ?>" placeholder="Contoh: Sangat baik dalam memahami materi..." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:5px; font-size:13.5px;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="form-actions" style="margin-top: 25px;">
            <a href="<?= e(base_url('/staff/nilai/index.php')) ?>" class="btn-cancel">Batal</a>
            <button type="submit" class="btn-save">Simpan Semua Nilai</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
