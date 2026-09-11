<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
if (!$studentId) {
    set_flash('danger', 'Siswa tidak ditemukan.');
    redirect('/staff/rapor/index.php');
}

$semester = (int)($_GET['semester'] ?? 2);
$schoolYear = trim($_GET['school_year'] ?? '2025/2026');

$stmt = $pdo->prepare("SELECT s.*, g.nama_ayah, g.nama_ibu FROM students s LEFT JOIN guardians g ON g.student_id = s.id WHERE s.id = :id LIMIT 1");
$stmt->execute(['id' => $studentId]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('danger', 'Data siswa tidak ditemukan.');
    redirect('/staff/rapor/index.php');
}

// Ambil status rapor
$stmtRep = $pdo->prepare("SELECT * FROM reports WHERE student_id = :sid AND semester = :sem AND school_year = :sy LIMIT 1");
$stmtRep->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
$report = $stmtRep->fetch();

// Ambil nilai akademik
$stmtAcad = $pdo->prepare("SELECT * FROM academic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy ORDER BY id ASC");
$stmtAcad->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
$academicGrades = $stmtAcad->fetchAll();

// Ambil nilai tahfidh
$stmtTahfidh = $pdo->prepare("SELECT * FROM tahfidh_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy ORDER BY id ASC");
$stmtTahfidh->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
$tahfidhGrades = $stmtTahfidh->fetchAll();

$pageTitle = 'Pratinjau Rapor - ' . $student['nama'];
$contentTitle = 'Pratinjau Rapor Elektronik Siswa';
$activeMenu = 'rapor';

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$status = $report['status'] ?? 'draft';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="data-section">
    <div class="data-header">
        <div>
            <h2>Rapor: <?= e($student['nama']) ?></h2>
            <p style="margin:4px 0 0 0; color:#666;">Kelas <?= e($student['kelas']) ?> | Semester <?= $semester === 1 ? '1 (Ganjil)' : '2 (Genap)' ?> <?= e($schoolYear) ?></p>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <a href="<?= e(base_url('/staff/rapor/cetak.php?student_id=' . $studentId . '&semester=' . $semester . '&school_year=' . urlencode($schoolYear))) ?>" target="_blank" class="btn btn-primary">🖨 Cetak Dokumen PDF</a>
            
            <?php if ($isAdmin): ?>
                <?php if ($status === 'published'): ?>
                    <form method="POST" action="<?= e(base_url('/staff/rapor/publish.php')) ?>" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="student_id" value="<?= $studentId ?>">
                        <input type="hidden" name="semester" value="<?= $semester ?>">
                        <input type="hidden" name="school_year" value="<?= e($schoolYear) ?>">
                        <input type="hidden" name="action" value="unpublish">
                        <button type="submit" class="btn btn-secondary" style="background:#e67e22; color:white;">🔒 Unpublish</button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="<?= e(base_url('/staff/rapor/publish.php')) ?>" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="student_id" value="<?= $studentId ?>">
                        <input type="hidden" name="semester" value="<?= $semester ?>">
                        <input type="hidden" name="school_year" value="<?= e($schoolYear) ?>">
                        <input type="hidden" name="action" value="publish">
                        <button type="submit" class="btn btn-publish">🌐 Publikasikan Rapor</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <a href="<?= e(base_url('/staff/rapor/index.php')) ?>" class="btn btn-secondary">Kembali</a>
        </div>
    </div>

    <!-- Kotak Biodata -->
    <div style="background:#f8fafc; padding:20px; border-radius:8px; margin-bottom:25px; border-left:5px solid var(--primary-color);">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:12px; font-size:14px;">
            <div><strong>Nama Siswa:</strong> <?= e($student['nama']) ?></div>
            <div><strong>Kelas:</strong> <?= e($student['kelas']) ?></div>
            <div><strong>NIS / NISN:</strong> <?= e($student['nis']) ?> / <?= e($student['nisn']) ?></div>
            <div><strong>Status Rapor:</strong> 
                <?php if ($status === 'published'): ?>
                    <span class="badge badge-success">PUBLISHED (Tampil di portal)</span>
                <?php elseif ($status === 'ready'): ?>
                    <span class="badge badge-info">READY (Siap Diterbitkan)</span>
                <?php else: ?>
                    <span class="badge badge-secondary">DRAFT</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- A. NILAI AKADEMIK -->
    <h3 style="color:var(--primary-color); border-bottom:2px solid #e2e8f0; padding-bottom:8px;">A. Capaian Nilai Akademik</h3>
    <div class="table-responsive" style="margin-bottom:30px;">
        <table>
            <thead>
                <tr>
                    <th style="width:40px;">No</th>
                    <th>Mata Pelajaran</th>
                    <th style="width:100px; text-align:center;">Nilai</th>
                    <th style="width:140px; text-align:center;">Predikat</th>
                    <th>Capaian Kompetensi / Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($academicGrades)): ?>
                    <tr><td colspan="5" style="text-align:center; color:#888;">Belum ada nilai akademik yang diinput.</td></tr>
                <?php else: ?>
                    <?php 
                    $no = 1; $totalScore = 0;
                    foreach ($academicGrades as $g): 
                        $score = (float)$g['score'];
                        $totalScore += $score;
                        $storedPredicate = strtoupper(trim((string)($g['predikat'] ?? '')));
                        if (in_array($storedPredicate, ['A', 'B', 'C', 'D', 'E'], true)) {
                            $predikat = $storedPredicate;
                        } elseif ($score >= 91) $predikat = 'A';
                        elseif ($score >= 81) $predikat = 'B';
                        elseif ($score >= 71) $predikat = 'C';
                        else $predikat = 'D';
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= e($g['subject']) ?></strong></td>
                            <td style="text-align:center; font-weight:bold;"><?= number_format($score, 1) ?></td>
                            <td style="text-align:center;"><?= $predikat ?></td>
                            <td><?= e($g['description'] ?: ($score >= 91 ? 'Sangat Baik' : ($score >= 81 ? 'Baik' : ($score >= 71 ? 'Cukup' : 'Perlu Bimbingan')))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background:#f1f5f9; font-weight:bold;">
                        <td colspan="2" style="text-align:right;">RATA-RATA NILAI:</td>
                        <td style="text-align:center; color:var(--primary-color); font-size:15px;"><?= number_format($totalScore / count($academicGrades), 2) ?></td>
                        <td colspan="2"></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- B. NILAI TAHFIDH -->
    <h3 style="color:var(--primary-color); border-bottom:2px solid #e2e8f0; padding-bottom:8px;">B. Capaian Tahfidh Al-Qur'an</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width:40px;">No</th>
                    <th>Target Hafalan / Surat / Juz</th>
                    <th style="width:100px; text-align:center;">Nilai</th>
                    <th>Catatan Fashahah & Tajwid</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tahfidhGrades)): ?>
                    <tr><td colspan="4" style="text-align:center; color:#888;">Belum ada capaian tahfidh yang diinput.</td></tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($tahfidhGrades as $t): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><strong><?= e($t['memorization']) ?></strong></td>
                            <td style="text-align:center; font-weight:bold; color:#27ae60;"><?= number_format((float)$t['score'], 1) ?></td>
                            <td><?= e($t['description'] ?: 'Lancar, tajwid baik') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
