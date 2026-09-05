<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

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
$activeMenu = 'tahfidh';

// Ambil nilai tahfidh yang sudah tersimpan
$stmtTahfidh = $pdo->prepare("SELECT memorization, score, description FROM tahfidh_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
$stmtTahfidh->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
$savedTahfidh = $stmtTahfidh->fetchAll();

// Target hafalan default untuk form
$defaultTargets = [
    'Juz 30 (An-Naba s.d An-Nas)',
    'Juz 29 (Al-Mulk s.d Al-Mursalat)',
    'Surat Pilihan (Surat Yasin & Al-Waqi\'ah)',
    'Doa Harian & Dzikir Pagi Petang',
    'Hadits-hadits Pilihan Arbain'
];

$existingMap = [];
foreach ($savedTahfidh as $t) {
    $existingMap[$t['memorization']] = [
        'score' => (float)$t['score'],
        'description' => $t['description'] ?? '',
    ];
}

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

<div class="data-section">
    <div class="data-header">
        <div>
            <h2>Penilaian Tahfidh: <?= e($student['nama']) ?></h2>
            <p style="margin:4px 0 0 0; color:#666; font-size:13.5px;">NISN: <?= e($student['nisn']) ?> | Kelas: <?= e($student['kelas']) ?> | Semester: <?= $semester === 1 ? '1 (Ganjil)' : '2 (Genap)' ?> <?= e($schoolYear) ?></p>
        </div>
        <a href="<?= e(base_url('/staff/tahfidh/index.php?semester=' . $semester . '&school_year=' . urlencode($schoolYear))) ?>" class="btn btn-secondary">Kembali</a>
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
                        <th style="width: 320px;">Target Hafalan / Surat / Juz</th>
                        <th style="width: 120px;">Nilai (0 - 100)</th>
                        <th>Catatan Fashahah, Kelancaran & Tajwid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($defaultTargets as $target): 
                        $valScore = isset($existingMap[$target]) ? (string)$existingMap[$target]['score'] : '';
                        $valDesc = isset($existingMap[$target]) ? $existingMap[$target]['description'] : '';
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <input type="text" name="memorizations[]" value="<?= e($target) ?>" style="width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:5px; font-weight:600;">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" max="100" name="scores[]" value="<?= e($valScore) ?>" placeholder="0.00" style="width:100px; padding:8px 10px; border:1px solid #cbd5e1; border-radius:5px; font-size:14px; text-align:center;">
                            </td>
                            <td>
                                <input type="text" name="descriptions[]" value="<?= e($valDesc) ?>" placeholder="Contoh: Mutqin, makhraj fasih, tajwid tartil..." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:5px; font-size:13.5px;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="form-actions" style="margin-top: 25px;">
            <a href="<?= e(base_url('/staff/tahfidh/index.php')) ?>" class="btn-cancel">Batal</a>
            <button type="submit" class="btn-save" style="background:#27ae60;">Simpan Nilai Tahfidh</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
