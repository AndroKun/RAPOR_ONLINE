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

// Ambil nilai bahasa arab
$stmtArab = $pdo->prepare("SELECT * FROM arabic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy ORDER BY urutan ASC, id ASC");
$stmtArab->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
$arabicGrades = $stmtArab->fetchAll();

// Ambil catatan saran bahasa arab
$stmtNotes = $pdo->prepare("SELECT * FROM arabic_notes WHERE student_id = :sid AND semester = :sem AND school_year = :sy LIMIT 1");
$stmtNotes->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
$arabicNotes = $stmtNotes->fetch() ?: [];

$pageTitle = 'Pratinjau Rapor - ' . $student['nama'];
$contentTitle = 'Pratinjau Rapor Elektronik Siswa';
$activeMenu = 'rapor';

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$status = $report['status'] ?? 'draft';

// Handle POST to save signatures & notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_signatures'])) {
    verify_csrf();

    $namaWaliKelas = trim($_POST['nama_wali_kelas'] ?? '');
    $namaKepalaMadrasah = trim($_POST['nama_kepala_madrasah'] ?? '');
    $namaGuruBahasaArab = trim($_POST['nama_guru_bahasa_arab'] ?? '');
    $catatanWaliKelas = trim($_POST['catatan_wali_kelas'] ?? '');
    $catatanWaliMurid = trim($_POST['catatan_wali_murid'] ?? '');
    $tempatRapor = trim($_POST['tempat_rapor'] ?? 'Sidoarjo');
    $tanggalRapor = trim($_POST['tanggal_rapor'] ?? date('Y-m-d'));

    $stmtUpdRep = $pdo->prepare("INSERT INTO reports (student_id, semester, school_year, status, nama_wali_kelas, nama_kepala_madrasah, catatan_wali_kelas, catatan_wali_murid, tempat_rapor, tanggal_rapor, updated_at)
        VALUES (:sid, :sem, :sy, 'draft', :nwk, :nkm, :cwk, :cwm, :tr, :tgr, NOW())
        ON DUPLICATE KEY UPDATE
        nama_wali_kelas = VALUES(nama_wali_kelas),
        nama_kepala_madrasah = VALUES(nama_kepala_madrasah),
        catatan_wali_kelas = VALUES(catatan_wali_kelas),
        catatan_wali_murid = VALUES(catatan_wali_murid),
        tempat_rapor = VALUES(tempat_rapor),
        tanggal_rapor = VALUES(tanggal_rapor),
        updated_at = NOW()");
    
    $stmtUpdRep->execute([
        'sid' => $studentId,
        'sem' => $semester,
        'sy' => $schoolYear,
        'nwk' => $namaWaliKelas ?: null,
        'nkm' => $namaKepalaMadrasah ?: null,
        'cwk' => $catatanWaliKelas ?: null,
        'cwm' => $catatanWaliMurid ?: null,
        'tr' => $tempatRapor ?: 'Sidoarjo',
        'tgr' => $tanggalRapor ?: date('Y-m-d'),
    ]);

    $stmtUpdArabNotes = $pdo->prepare("INSERT INTO arabic_notes (student_id, semester, school_year, nama_guru, created_at, updated_at)
        VALUES (:sid, :sem, :sy, :nama_guru, NOW(), NOW())
        ON DUPLICATE KEY UPDATE nama_guru = VALUES(nama_guru), updated_at = NOW()");
    $stmtUpdArabNotes->execute([
        'sid' => $studentId,
        'sem' => $semester,
        'sy' => $schoolYear,
        'nama_guru' => $namaGuruBahasaArab ?: null,
    ]);

    set_flash('success', 'Pengaturan tanda tangan rapor & pengembangan diri berhasil disimpan.');
    redirect('/staff/rapor/detail.php?student_id=' . $studentId . '&semester=' . $semester . '&school_year=' . urlencode($schoolYear));
}

// Wali kelas otomatis berdasarkan kelas murid
$autoWaliKelas = get_wali_kelas_by_class($pdo, (string)($student['kelas'] ?? ''));

$defaultKepala = $student['nama_kepala_madrasah'] ?? '';
if (!$defaultKepala) {
    $stmtKep = $pdo->query("SELECT nama_lengkap FROM users WHERE role = 'admin' LIMIT 1");
    $defaultKepala = $stmtKep->fetchColumn() ?: '';
}

// Otomatis terisi mengikuti kelas murid jika belum ada custom nama_wali_kelas
$namaWaliKelas = !empty($report['nama_wali_kelas']) ? $report['nama_wali_kelas'] : $autoWaliKelas;
$namaKepalaMadrasah = $report['nama_kepala_madrasah'] ?? $defaultKepala;
$namaGuruBahasaArab = $arabicNotes['nama_guru'] ?? '';
$catatanWaliKelas = $report['catatan_wali_kelas'] ?? '';
$catatanWaliMurid = $report['catatan_wali_murid'] ?? '';
$tempatRapor = $report['tempat_rapor'] ?? 'Sidoarjo';
$tanggalRapor = $report['tanggal_rapor'] ?? date('Y-m-d');

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
    <div class="table-responsive" style="margin-bottom:30px;">
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

    <!-- C. NILAI BAHASA ARAB -->
    <h3 style="color:var(--primary-color); border-bottom:2px solid #e2e8f0; padding-bottom:8px;">C. Capaian Pembelajaran Bahasa Arab</h3>
    <div class="table-responsive" style="margin-bottom:20px;">
        <table>
            <thead>
                <tr>
                    <th style="width:40px; text-align:center;">No</th>
                    <th>Elemen Pembelajaran</th>
                    <th style="width:120px; text-align:center;">Nilai (GRADE)</th>
                    <th>Keterangan Capaian</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($arabicGrades)): ?>
                    <tr><td colspan="4" style="text-align:center; color:#888;">Belum ada penilaian Bahasa Arab yang diinput.</td></tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($arabicGrades as $ag): ?>
                        <tr>
                            <td style="text-align:center; font-weight:bold;"><?= $no++ ?></td>
                            <td><strong><?= e($ag['element']) ?></strong></td>
                            <td style="text-align:center; font-weight:bold; font-size:15px; color:#166534;">
                                <?= e($ag['predikat']) ?>
                            </td>
                            <td><?= e($ag['description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($arabicNotes)): ?>
        <div style="background:#f8fafc; padding:18px 20px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:20px; font-size:13.5px;">
            <div style="font-weight:700; color:var(--primary-color); margin-bottom:8px;">Saran - Saran Evaluasi Guru Bahasa Arab:</div>
            <ol style="margin:0 0 14px 0; padding-left:22px; line-height:1.6;">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <?php if (!empty($arabicNotes['saran_' . $i])): ?>
                        <li><?= e($arabicNotes['saran_' . $i]) ?></li>
                    <?php endif; ?>
                <?php endfor; ?>
            </ol>
            <div style="display:flex; justify-content:space-between; flex-wrap:wrap; color:#555; border-top:1px dashed #cbd5e1; padding-top:10px; font-size:13px;">
                <div>Diberikan di: <strong><?= e($arabicNotes['diberikan_di'] ?: 'Sidoarjo') ?></strong>, Tanggal: <strong><?= !empty($arabicNotes['tanggal']) ? date('d-m-Y', strtotime((string)$arabicNotes['tanggal'])) : date('d-m-Y') ?></strong></div>
                <div>Guru Pengampu: <strong><?= e($arabicNotes['nama_guru'] ?: 'Ustadz Pengampu') ?></strong></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- D. TANDA TANGAN RAPOR & PENGEMBANGAN DIRI -->
    <h3 style="color:var(--primary-color); border-bottom:2px solid #e2e8f0; padding-bottom:8px; margin-top:35px;">
        D. Pengaturan Tanda Tangan Rapor & Pengembangan Diri
    </h3>
    <div style="margin:0 0 12px 0;">
        <a href="<?= e(base_url('/staff/tahfidh/input.php?student_id=' . $studentId . '&semester=' . $semester . '&school_year=' . urlencode($schoolYear) . '#tanda-tangan-tahfidh')) ?>" class="btn btn-ghost btn-sm">
            Atur Nama Guru Tahfidh
        </a>
        <small style="margin-left:8px; color:#64748b;">Textbox ini ada di bagian tanda tangan laporan Tahfidh.</small>
    </div>
    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:24px; margin-bottom:30px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="save_signatures" value="1">
            
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:18px; margin-bottom:20px;">
                <div class="form-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label style="font-weight:600; font-size:13.5px; margin-bottom:0; color:#1e293b;">
                            Nama Wali Kelas / Penandatangan Rapor
                        </label>
                        <button type="button" onclick="document.getElementById('input_nama_wali_kelas').value = <?= json_encode($autoWaliKelas) ?>;" 
                                style="background:none; border:none; color:var(--primary-color); font-size:12px; cursor:pointer; padding:0; text-decoration:underline;">
                            <i class="fas fa-sync-alt"></i> Reset ke Otomatis
                        </button>
                    </div>
                    <input type="text" id="input_nama_wali_kelas" name="nama_wali_kelas" value="<?= e($namaWaliKelas) ?>" placeholder="<?= e($autoWaliKelas) ?>" required
                           style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                    <small style="color:#64748b; font-size:12px; display:block; margin-top:4px;">
                        <i class="fas fa-magic" style="color:var(--primary-color);"></i> Isi nama yang akan dicetak di bawah kolom tanda tangan Wali Kelas. Otomatis untuk kelas <strong><?= e($student['kelas'] ?? '') ?></strong>: <?= e($autoWaliKelas) ?>.
                    </small>
                </div>

                <div class="form-group">
                    <label style="font-weight:600; font-size:13.5px; display:block; margin-bottom:6px; color:#1e293b;">
                        Nama Kepala Madrasah (Tanda Tangan Lembar Rapor)
                    </label>
                    <input type="text" name="nama_kepala_madrasah" value="<?= e($namaKepalaMadrasah) ?>" placeholder="Contoh: Ahmad Dahlan, S.Pd.I" required
                           style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                    <small style="color:#64748b; font-size:12px;">Nama kepala madrasah/sekolah untuk pengesahan rapor.</small>
                </div>

                <div class="form-group">
                    <label style="font-weight:600; font-size:13.5px; display:block; margin-bottom:6px; color:#1e293b;">
                        Nama Guru Bahasa Arab (Tanda Tangan Halaman 4)
                    </label>
                    <input type="text" name="nama_guru_bahasa_arab" value="<?= e($namaGuruBahasaArab) ?>" placeholder="Contoh: Ustadz Ahmad, S.Pd.I"
                           style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                    <small style="color:#64748b; font-size:12px;">Nama ini dicetak pada tanda tangan laporan Bahasa Arab.</small>
                </div>

                <div class="form-group">
                    <label style="font-weight:600; font-size:13.5px; display:block; margin-bottom:6px; color:#1e293b;">
                        Tempat Titimangsa Rapor
                    </label>
                    <input type="text" name="tempat_rapor" value="<?= e($tempatRapor) ?>" placeholder="Sidoarjo" required
                           style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                </div>

                <div class="form-group">
                    <label style="font-weight:600; font-size:13.5px; display:block; margin-bottom:6px; color:#1e293b;">
                        Tanggal Pembagian Rapor
                    </label>
                    <input type="date" name="tanggal_rapor" value="<?= e($tanggalRapor) ?>" required
                           style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:18px; margin-bottom:20px;">
                <div class="form-group">
                    <label style="font-weight:600; font-size:13.5px; display:block; margin-bottom:6px; color:#1e293b;">
                        Catatan Wali Kelas (Halaman Pengembangan Diri & Pembiasaan)
                    </label>
                    <textarea name="catatan_wali_kelas" rows="3" placeholder="Tuliskan catatan kemajuan, sikap, atau pesan wali kelas untuk siswa..."
                              style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13.5px; resize:vertical;"><?= e($catatanWaliKelas) ?></textarea>
                </div>

                <div class="form-group">
                    <label style="font-weight:600; font-size:13.5px; display:block; margin-bottom:6px; color:#1e293b;">
                        Catatan Wali Murid (Opsional)
                    </label>
                    <textarea name="catatan_wali_murid" rows="3" placeholder="Catatan atau tanggapan orang tua/wali murid..."
                              style="width:100%; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13.5px; resize:vertical;"><?= e($catatanWaliMurid) ?></textarea>
                </div>
            </div>

            <div style="text-align:right;">
                <button type="submit" class="btn btn-primary" style="padding:10px 24px; font-size:14px;">
                    💾 Simpan Pengaturan Tanda Tangan & Catatan
                </button>
            </div>
        </form>
    </div>
</div>


<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
