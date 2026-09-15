<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_bahasa_arab_access();

$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
if (!$studentId) {
    set_flash('danger', 'Siswa tidak ditemukan.');
    redirect('/staff/bahasa_arab/index.php');
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $studentId]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('danger', 'Data siswa tidak ditemukan.');
    redirect('/staff/bahasa_arab/index.php');
}

$semester = (int)($_GET['semester'] ?? 2);
$schoolYear = trim($_GET['school_year'] ?? '2025/2026');

$pageTitle = 'Input Nilai Bahasa Arab - ' . $student['nama'];
$contentTitle = 'Penilaian Hasil Pembelajaran Bahasa Arab';
$contentSubtitle = 'Formulir penilaian 4 elemen, grade A/B/C, dan saran evaluasi sesuai format rapor resmi.';
$activeMenu = 'bahasa_arab';

$user = current_user();
$defaultTeacherName = $user['nama_lengkap'] ?? 'Ustadz Pengampu Bahasa Arab';

// Ambil master elemen Bahasa Arab dari arabic_categories
$stmtCat = $pdo->query("SELECT nama_kategori FROM arabic_categories ORDER BY urutan ASC, id ASC");
$masterCategories = $stmtCat->fetchAll(PDO::FETCH_COLUMN) ?: [
    'Menyimak (Istima\')',
    'Berbicara (Kalam)',
    'Membaca (Qira\'ah)',
    'Menulis (Kitabah)',
];

// Pastikan minimal 4 elemen
while (count($masterCategories) < 4) {
    $masterCategories[] = 'Elemen ' . (count($masterCategories) + 1);
}

// Ambil data nilai tersimpan
$stmtGrades = $pdo->prepare("SELECT element, score, predikat, description, urutan FROM arabic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy ORDER BY urutan ASC, id ASC");
$stmtGrades->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
$savedGrades = $stmtGrades->fetchAll();

// Ambil catatan & saran tersimpan
$stmtNotes = $pdo->prepare("SELECT * FROM arabic_notes WHERE student_id = :sid AND semester = :sem AND school_year = :sy LIMIT 1");
$stmtNotes->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
$savedNotes = $stmtNotes->fetch() ?: [];

// Mapping elemen yang akan ditampilkan (persis 4 baris)
$rowsData = [];
for ($i = 0; $i < 4; $i++) {
    if (isset($savedGrades[$i])) {
        $rowsData[$i] = [
            'element' => $savedGrades[$i]['element'],
            'score' => $savedGrades[$i]['score'] !== null ? (float)$savedGrades[$i]['score'] : null,
            'predikat' => strtoupper((string)($savedGrades[$i]['predikat'] ?? 'B')),
            'description' => (string)($savedGrades[$i]['description'] ?? ''),
        ];
    } else {
        $defaultElem = $masterCategories[$i] ?? ('Elemen ' . ($i + 1));
        $rowsData[$i] = [
            'element' => $defaultElem,
            'score' => 85,
            'predikat' => 'B',
            'description' => 'Sesuai Target',
        ];
    }
}

$saran1 = $savedNotes['saran_1'] ?? '';
$saran2 = $savedNotes['saran_2'] ?? '';
$saran3 = $savedNotes['saran_3'] ?? '';
$saran4 = $savedNotes['saran_4'] ?? '';
$diberikanDi = $savedNotes['diberikan_di'] ?? 'Sidoarjo';
$tanggalRapor = !empty($savedNotes['tanggal']) ? $savedNotes['tanggal'] : date('Y-m-d');
$namaGuru = $savedNotes['nama_guru'] ?? $defaultTeacherName;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $elements = $_POST['elements'] ?? [];
    $predikats = $_POST['predikats'] ?? [];
    $scores = $_POST['scores'] ?? [];
    $descriptions = $_POST['descriptions'] ?? [];

    $saran1 = trim($_POST['saran_1'] ?? '');
    $saran2 = trim($_POST['saran_2'] ?? '');
    $saran3 = trim($_POST['saran_3'] ?? '');
    $saran4 = trim($_POST['saran_4'] ?? '');
    $diberikanDi = trim($_POST['diberikan_di'] ?? 'Sidoarjo');
    $tanggalRapor = trim($_POST['tanggal'] ?? date('Y-m-d'));
    $namaGuru = trim($_POST['nama_guru'] ?? $defaultTeacherName);

    try {
        $pdo->beginTransaction();

        // 1. Hapus entri nilai lama untuk periode ini
        $stmtDel = $pdo->prepare("DELETE FROM arabic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
        $stmtDel->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);

        // 2. Insert 4 elemen nilai
        $stmtInsertGrade = $pdo->prepare("INSERT INTO arabic_grades 
            (student_id, element, score, predikat, description, urutan, semester, school_year, created_at, updated_at) 
            VALUES (:sid, :el, :sc, :pr, :ds, :ur, :sem, :sy, NOW(), NOW())");

        for ($i = 0; $i < 4; $i++) {
            $el = trim((string)($elements[$i] ?? ('Elemen ' . ($i + 1))));
            $pr = strtoupper(trim((string)($predikats[$i] ?? 'B')));
            if (!in_array($pr, ['A', 'B', 'C'], true)) {
                $pr = 'B';
            }
            $scVal = isset($scores[$i]) && $scores[$i] !== '' ? (float)$scores[$i] : ($pr === 'A' ? 95 : ($pr === 'B' ? 85 : 70));
            $ds = trim((string)($descriptions[$i] ?? ''));
            if ($ds === '') {
                $ds = match($pr) {
                    'A' => 'Melampaui Target',
                    'B' => 'Sesuai Target',
                    'C' => 'Belum sesuai target',
                    default => 'Sesuai Target'
                };
            }

            $stmtInsertGrade->execute([
                'sid' => $studentId,
                'el' => $el,
                'sc' => $scVal,
                'pr' => $pr,
                'ds' => $ds,
                'ur' => $i + 1,
                'sem' => $semester,
                'sy' => $schoolYear,
            ]);
        }

        // 3. Simpan / Perbarui Catatan & Saran (arabic_notes)
        $stmtSaveNotes = $pdo->prepare("INSERT INTO arabic_notes 
            (student_id, semester, school_year, saran_1, saran_2, saran_3, saran_4, diberikan_di, tanggal, nama_guru, created_at, updated_at)
            VALUES (:sid, :sem, :sy, :s1, :s2, :s3, :s4, :dd, :tg, :ng, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                saran_1 = VALUES(saran_1),
                saran_2 = VALUES(saran_2),
                saran_3 = VALUES(saran_3),
                saran_4 = VALUES(saran_4),
                diberikan_di = VALUES(diberikan_di),
                tanggal = VALUES(tanggal),
                nama_guru = VALUES(nama_guru),
                updated_at = NOW()");

        $stmtSaveNotes->execute([
            'sid' => $studentId,
            'sem' => $semester,
            'sy' => $schoolYear,
            's1' => $saran1,
            's2' => $saran2,
            's3' => $saran3,
            's4' => $saran4,
            'dd' => $diberikanDi ?: 'Sidoarjo',
            'tg' => $tanggalRapor ?: date('Y-m-d'),
            'ng' => $namaGuru ?: $defaultTeacherName,
        ]);

        $pdo->commit();

        set_flash('success', "Penilaian Bahasa Arab untuk siswa {$student['nama']} berhasil disimpan.");
        redirect('/staff/bahasa_arab/index.php?kelas=' . urlencode($student['kelas']) . '&semester=' . $semester . '&school_year=' . urlencode($schoolYear));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = 'Gagal menyimpan nilai: ' . $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul style="margin: 0; padding-left: 20px;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Biodata Siswa Mini Card -->
<div class="stat-card" style="margin-bottom: 24px; padding: 20px 24px; border-left: 5px solid var(--green-700);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="font-size: 13px; color: var(--ink-soft); font-weight: 600; text-transform: uppercase;">Siswa yang Dinilai</div>
            <h2 style="margin: 4px 0 0; font-size: 22px; color: var(--green-800);"><?= e($student['nama']) ?></h2>
            <div style="font-size: 13.5px; color: var(--ink-soft); margin-top: 4px;">
                NIS: <strong><?= e($student['nis']) ?></strong> &nbsp;|&nbsp;
                NISN: <strong><?= e($student['nisn']) ?></strong> &nbsp;|&nbsp;
                Kelas: <span class="badge badge-primary">Kelas <?= e($student['kelas']) ?></span> &nbsp;|&nbsp;
                Program: <strong>MTs Roudlotul Qur'an</strong>
            </div>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="<?= e(base_url('/staff/rapor/cetak.php?student_id=' . $studentId . '&semester=' . $semester . '&school_year=' . urlencode($schoolYear))) ?>" target="_blank" class="btn btn-ghost btn-sm">
                🖨 Pratinjau Rapor PDF
            </a>
            <a href="<?= e(base_url('/staff/bahasa_arab/index.php')) ?>" class="btn btn-ghost btn-sm">
                ← Kembali ke Daftar
            </a>
        </div>
    </div>
</div>

<!-- Form Penilaian Sesuai Rapor Resmi -->
<form method="POST" action="">
    <?= csrf_field() ?>

    <div class="panel-card" style="margin-bottom: 24px;">
        <div class="panel-head" style="margin-bottom: 16px;">
            <div>
                <h2 style="font-size: 18px;">Tabel Penilaian 4 Elemen Pembelajaran Bahasa Arab</h2>
                <p class="section-hint" style="margin: 2px 0 0;">Sesuaikan capaian 4 elemen, tentukan GRADE (A/B/C), dan berikan keterangan capaian.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--surface-soft); border-bottom: 2px solid var(--line);">
                        <th style="width: 50px; text-align: center;">NO</th>
                        <th style="width: 280px;">Elemen</th>
                        <th style="width: 100px; text-align: center;">Nilai</th>
                        <th>Keterangan Capaian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <?php 
                        $row = $rowsData[$i];
                        $pred = $row['predikat'];
                        ?>
                        <tr style="border-bottom: 1px solid var(--line);">
                            <td style="text-align: center; font-weight: 700; font-size: 15px; color: var(--green-800);">
                                <?= $i + 1 ?>
                            </td>
                            <td>
                                <input type="text" name="elements[]" value="<?= e($row['element']) ?>" required 
                                       placeholder="Nama Elemen Pembelajaran" style="width: 100%; font-weight: 600;">
                            </td>
                            <td style="text-align: center;">
                                <select name="predikats[]" id="predikat_<?= $i ?>" onchange="updateKeterangan(<?= $i ?>, this.value)" 
                                        style="width: 100%; font-weight: 800; font-size: 15px; text-align: center; color: var(--green-800); padding: 7px 10px;">
                                    <option value="A" <?= $pred === 'A' ? 'selected' : '' ?>>A</option>
                                    <option value="B" <?= $pred === 'B' ? 'selected' : '' ?>>B</option>
                                    <option value="C" <?= $pred === 'C' ? 'selected' : '' ?>>C</option>
                                </select>
                                <input type="hidden" name="scores[]" id="score_<?= $i ?>" value="<?= $row['score'] ?? 85 ?>">
                            </td>
                            <td>
                                <input type="text" name="descriptions[]" id="desc_<?= $i ?>" value="<?= e($row['description']) ?>" 
                                       placeholder="Contoh: Sesuai Target / Melampaui Target" style="width: 100%;">
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <!-- Grade Legend Box -->
        <div style="margin-top: 18px; padding: 14px 18px; background: #f8fafc; border-radius: 8px; border: 1px dashed var(--line); display: flex; gap: 24px; flex-wrap: wrap; font-size: 13px;">
            <div style="font-weight: 700; color: var(--green-800);">STANDAR GRADE:</div>
            <div><strong>A</strong> : Melampaui Target</div>
            <div><strong>B</strong> : Sesuai Target</div>
            <div><strong>C</strong> : Belum sesuai target</div>
        </div>
    </div>

    <!-- Panel Saran - Saran & Titimangsa Rapor -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
        <!-- Saran - saran -->
        <div class="panel-card" style="margin-bottom: 0;">
            <div class="panel-head" style="margin-bottom: 12px;">
                <h2 style="font-size: 17px;">Saran - Saran Evaluasi Guru</h2>
            </div>
            <p class="section-hint" style="margin-bottom: 16px;">Catatan motivasi atau saran perbaikan untuk santri (tercetak 4 nomor di rapor):</p>

            <div class="field" style="margin-bottom: 12px;">
                <label style="font-size: 13px;">Saran 1</label>
                <input type="text" name="saran_1" value="<?= e($saran1) ?>" placeholder="1. Tingkatkan perbendaharaan mufradat harian.">
            </div>
            <div class="field" style="margin-bottom: 12px;">
                <label style="font-size: 13px;">Saran 2</label>
                <input type="text" name="saran_2" value="<?= e($saran2) ?>" placeholder="2. Biasakan berbicara dengan bahasa Arab sederhana.">
            </div>
            <div class="field" style="margin-bottom: 12px;">
                <label style="font-size: 13px;">Saran 3</label>
                <input type="text" name="saran_3" value="<?= e($saran3) ?>" placeholder="3. Rajin membaca teks bacaan (qira'ah) berbahasa Arab.">
            </div>
            <div class="field" style="margin-bottom: 12px;">
                <label style="font-size: 13px;">Saran 4</label>
                <input type="text" name="saran_4" value="<?= e($saran4) ?>" placeholder="4. Pertahankan semangat belajar dan istiqomah.">
            </div>
        </div>

        <!-- Titimangsa & Tanda Tangan Guru -->
        <div class="panel-card" style="margin-bottom: 0;">
            <div class="panel-head" style="margin-bottom: 12px;">
                <h2 style="font-size: 17px;">Titimangsa &amp; Guru Pengampu</h2>
            </div>
            <p class="section-hint" style="margin-bottom: 16px;">Informasi tempat, tanggal terbit rapor, dan nama ustadz/ustadzah penilai:</p>

            <div class="field" style="margin-bottom: 14px;">
                <label style="font-size: 13px;">Diberikan di *</label>
                <input type="text" name="diberikan_di" value="<?= e($diberikanDi) ?>" required placeholder="Contoh: Sidoarjo">
            </div>

            <div class="field" style="margin-bottom: 14px;">
                <label style="font-size: 13px;">Tanggal Rapor *</label>
                <input type="date" name="tanggal" value="<?= e($tanggalRapor) ?>" required>
            </div>

            <div class="field" style="margin-bottom: 14px;">
                <label style="font-size: 13px;">Nama Guru / Ustadz (Beserta Gelar) *</label>
                <input type="text" name="nama_guru" value="<?= e($namaGuru) ?>" required placeholder="Contoh: Ustadz Ahmad, S.Pd.I">
            </div>

            <div style="background: #eef8f1; padding: 12px; border-radius: 6px; font-size: 12.5px; color: #166534; margin-top: 10px;">
                💡 Nama guru dan tanggal ini akan langsung tercantum pada kolom tanda tangan lembar <strong>Laporan Hasil Pembelajaran Bahasa Arab</strong>.
            </div>
        </div>
    </div>

    <!-- Tombol Simpan -->
    <div class="panel-card" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <a href="<?= e(base_url('/staff/bahasa_arab/index.php')) ?>" class="btn btn-ghost">Batal / Kembali</a>
        <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-size: 15px;">
            💾 Simpan Penilaian Bahasa Arab
        </button>
    </div>
</form>

<script>
function updateKeterangan(index, grade) {
    const descInput = document.getElementById('desc_' + index);
    const scoreInput = document.getElementById('score_' + index);
    
    if (grade === 'A') {
        if (!descInput.value || descInput.value === 'Sesuai Target' || descInput.value === 'Belum sesuai target') {
            descInput.value = 'Melampaui Target';
        }
        scoreInput.value = 95;
    } else if (grade === 'B') {
        if (!descInput.value || descInput.value === 'Melampaui Target' || descInput.value === 'Belum sesuai target') {
            descInput.value = 'Sesuai Target';
        }
        scoreInput.value = 85;
    } else if (grade === 'C') {
        if (!descInput.value || descInput.value === 'Melampaui Target' || descInput.value === 'Sesuai Target') {
            descInput.value = 'Belum sesuai target';
        }
        scoreInput.value = 70;
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
