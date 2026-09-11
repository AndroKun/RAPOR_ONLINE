<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_academic_access();

$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
if (!$studentId) {
    set_flash('danger', 'Silakan pilih siswa terlebih dahulu dari daftar nilai akademik.');
    redirect('/staff/nilai/index.php');
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $studentId]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('danger', 'Data siswa tidak ditemukan.');
    redirect('/staff/nilai/index.php');
}

$user = current_user();
$isAdmin = (($user['role'] ?? '') === 'admin');
$teacherMapel = trim((string)($user['mata_pelajaran'] ?? ''));

$semester = (int)($_GET['semester'] ?? 2);
$schoolYear = trim($_GET['school_year'] ?? '2025/2026');

$pageTitle = 'Input Nilai ' . ($isAdmin ? 'Akademik' : $teacherMapel) . ' - ' . $student['nama'];
$contentTitle = $isAdmin ? 'Input Nilai Akademik Siswa' : 'Input Nilai: ' . ($teacherMapel ?: 'Mata Pelajaran Anda');
$contentSubtitle = $isAdmin 
    ? 'Penilaian capaian kompetensi seluruh mata pelajaran kurikulum santri MTs Roudlotul Qur\'an.'
    : 'Penilaian capaian kompetensi mata pelajaran ' . ($teacherMapel ?: 'yang Anda ampu') . ' untuk santri/siswa.';
$activeMenu = 'nilai';

// Ambil master daftar mata pelajaran dari database
$availableSubjects = get_all_subjects($pdo);

$initialRows = [];
$predicateOptions = ['A', 'B', 'C', 'D', 'E'];

$defaultPredicate = static function (string $score): string {
    if ($score === '') return '';
    $value = (float)$score;
    if ($value >= 91) return 'A';
    if ($value >= 81) return 'B';
    if ($value >= 71) return 'C';
    return 'D';
};

if ($isAdmin) {
    // Admin: Ambil semua nilai akademik yang tersimpan untuk siswa ini
    $stmtSaved = $pdo->prepare("
        SELECT subject, score, predikat, description 
        FROM academic_grades 
        WHERE student_id = :sid AND semester = :sem AND school_year = :sy 
        ORDER BY id ASC
    ");
    $stmtSaved->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
    $savedGrades = $stmtSaved->fetchAll();

    if (!empty($savedGrades)) {
        foreach ($savedGrades as $g) {
            $initialRows[] = [
                'subject' => (string)$g['subject'],
                'score' => (string)$g['score'],
                'predikat' => (string)($g['predikat'] ?: $defaultPredicate((string)$g['score'])),
                'description' => (string)($g['description'] ?? '')
            ];
        }
    } else {
        // Jika belum ada nilai tersimpan, siapkan semua mata pelajaran kurikulum
        foreach ($availableSubjects as $sub) {
            $initialRows[] = [
                'subject' => $sub,
                'score' => '',
                'predikat' => '',
                'description' => ''
            ];
        }
    }
} else {
    // Guru / Staff: Khusus dan terkunci HANYA pada mata pelajaran yang diampunya
    if ($teacherMapel === '') {
        $subjectForTeacher = $availableSubjects[0] ?? 'Bahasa Indonesia';
    } else {
        $subjectForTeacher = $teacherMapel;
    }

    $stmtSaved = $pdo->prepare("
        SELECT score, predikat, description 
        FROM academic_grades 
        WHERE student_id = :sid AND subject = :subj AND semester = :sem AND school_year = :sy 
        LIMIT 1
    ");
    $stmtSaved->execute([
        'sid' => $studentId, 
        'subj' => $subjectForTeacher, 
        'sem' => $semester, 
        'sy' => $schoolYear
    ]);
    $savedGrade = $stmtSaved->fetch();

    $initialRows[] = [
        'subject' => $subjectForTeacher,
        'score' => $savedGrade ? (string)$savedGrade['score'] : '',
        'predikat' => $savedGrade ? (string)($savedGrade['predikat'] ?: $defaultPredicate((string)$savedGrade['score'])) : '',
        'description' => $savedGrade ? (string)($savedGrade['description'] ?? '') : ''
    ];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $scores = $_POST['scores'] ?? [];
    $predikats = $_POST['predikats'] ?? [];
    $descriptions = $_POST['descriptions'] ?? [];

    try {
        $pdo->beginTransaction();

        $stmtInsert = $pdo->prepare("
            INSERT INTO academic_grades (student_id, subject, score, predikat, description, semester, school_year, created_at, updated_at) 
            VALUES (:sid, :subj, :score, :predikat, :desc, :sem, :sy, NOW(), NOW())
            ON DUPLICATE KEY UPDATE score = VALUES(score), predikat = VALUES(predikat), description = VALUES(description), updated_at = NOW()
        ");

        if ($isAdmin) {
            // Admin: simpan seluruh mapel dari form
            $subjects = $_POST['subjects'] ?? [];

            $stmtDel = $pdo->prepare("DELETE FROM academic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
            $stmtDel->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);

            for ($i = 0; $i < count($subjects); $i++) {
                $subj = trim((string)($subjects[$i] ?? ''));
                $scoreVal = trim((string)($scores[$i] ?? ''));
                $predikat = strtoupper(trim((string)($predikats[$i] ?? '')));
                $desc = trim((string)($descriptions[$i] ?? ''));

                if ($subj !== '' && $scoreVal !== '') {
                    if (!in_array($predikat, $predicateOptions, true)) {
                        throw new Exception("Predikat mata pelajaran '{$subj}' harus dipilih dari A sampai E.");
                    }
                    $scoreNum = (float)$scoreVal;
                    if ($scoreNum < 0 || $scoreNum > 100) {
                        throw new Exception("Nilai mata pelajaran '{$subj}' harus berada pada rentang 0 sampai 100.");
                    }

                    $stmtInsert->execute([
                        'sid' => $studentId,
                        'subj' => $subj,
                        'score' => $scoreNum,
                        'predikat' => $predikat,
                        'desc' => $desc ?: null,
                        'sem' => $semester,
                        'sy' => $schoolYear,
                    ]);
                }
            }
        } else {
            // Guru/Staff: Terkunci HANYA untuk mata pelajaran guru tersebut
            $subj = ($teacherMapel !== '') ? $teacherMapel : ($availableSubjects[0] ?? 'Bahasa Indonesia');
            $scoreVal = trim((string)($scores[0] ?? ''));
            $predikat = strtoupper(trim((string)($predikats[0] ?? '')));
            $desc = trim((string)($descriptions[0] ?? ''));

            if ($scoreVal !== '') {
                if (!in_array($predikat, $predicateOptions, true)) {
                    throw new Exception("Predikat mata pelajaran '{$subj}' harus dipilih dari A sampai E.");
                }
                $scoreNum = (float)$scoreVal;
                if ($scoreNum < 0 || $scoreNum > 100) {
                    throw new Exception("Nilai mata pelajaran '{$subj}' harus berada pada rentang 0 sampai 100.");
                }

                $stmtInsert->execute([
                    'sid' => $studentId,
                    'subj' => $subj,
                    'score' => $scoreNum,
                    'predikat' => $predikat,
                    'desc' => $desc ?: null,
                    'sem' => $semester,
                    'sy' => $schoolYear,
                ]);
            } else {
                // Jika nilai dikosongkan oleh guru, hapus nilai mapel tersebut
                $stmtDelSingle = $pdo->prepare("DELETE FROM academic_grades WHERE student_id = :sid AND subject = :subj AND semester = :sem AND school_year = :sy");
                $stmtDelSingle->execute([
                    'sid' => $studentId,
                    'subj' => $subj,
                    'sem' => $semester,
                    'sy' => $schoolYear,
                ]);
            }
        }

        // Cek kelengkapan nilai untuk update status rapor (ready / draft)
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

        $pdo->commit();

        $subjectMsg = $isAdmin ? "Nilai akademik" : "Nilai mata pelajaran {$teacherMapel}";
        set_flash('success', "{$subjectMsg} untuk {$student['nama']} berhasil disimpan.");
        redirect('/staff/nilai/index.php?semester=' . $semester . '&school_year=' . urlencode($schoolYear) . '&kelas=' . urlencode($student['kelas']));
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = $e->getMessage();
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Info Siswa Card Matching Tahfidh Style -->
<div class="picker-card" style="margin-bottom: 20px;">
    <div style="display: flex; align-items: center; gap: 14px; flex: 1;">
        <div style="width: 44px; height: 44px; border-radius: 10px; background: var(--green-100); color: var(--green-800); display: flex; align-items: center; justify-content: center; font-size: 20px;">
            📘
        </div>
        <div>
            <div style="font-size: 16px; font-weight: 800; color: var(--green-900);"><?= e($student['nama']) ?></div>
            <div style="font-size: 13px; color: var(--ink-soft); margin-top: 2px;">
                Kelas: <b><?= e($student['kelas']) ?></b> &nbsp;·&nbsp; 
                NISN: <b><?= e($student['nisn']) ?></b> &nbsp;·&nbsp; 
                Semester: <b><?= $semester === 1 ? '1 (Ganjil)' : '2 (Genap)' ?> <?= e($schoolYear) ?></b>
                <?php if (!$isAdmin && $teacherMapel !== ''): ?>
                    &nbsp;·&nbsp; Mapel: <b style="color: var(--green-900);"><?= e($teacherMapel) ?></b>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="<?= e(base_url('/staff/nilai/index.php?semester=' . $semester . '&school_year=' . urlencode($schoolYear) . '&kelas=' . urlencode($student['kelas']))) ?>" class="btn btn-ghost btn-sm">
            ← Kembali ke Daftar
        </a>
    </div>
</div>

<?php if (!$isAdmin && empty($teacherMapel)): ?>
    <div class="alert alert-warning" style="margin-bottom: 20px;">
        ⚠️ <strong>Perhatian:</strong> Akun Anda belum memiliki penugasan Mata Pelajaran spesifik. Nilai akan disimpan sebagai mapel default atau silakan hubungi Administrator untuk mengatur penugasan mata pelajaran Anda.
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="margin-bottom: 20px;">
        <ul style="margin:0; padding-left:20px;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Form Penilaian Akademik -->
<form method="POST" action="" class="card" id="formAkademik">
    <?= csrf_field() ?>

    <div class="panel-head">
        <div>
            <h2><?= $isAdmin ? 'Formulir Capaian Nilai Akademik Siswa' : 'Formulir Nilai Mata Pelajaran: ' . e($initialRows[0]['subject']) ?></h2>
            <p class="section-hint" style="margin: 2px 0 0;">
                <?= $isAdmin 
                    ? 'Masukkan nilai (skala 0–100) dan pilih predikat A–E. Klik <strong>+ Tambah Baris Mapel</strong> jika ingin menambah mata pelajaran.'
                    : 'Ketik nilai ujian/tugas mata pelajaran <strong>' . e($initialRows[0]['subject']) . '</strong> (skala 0–100) dan catatan capaian santri.' ?>
            </p>
        </div>

        <?php if ($isAdmin): ?>
            <button type="button" class="btn btn-primary" id="btnAddMapelRow" style="display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(21,122,66,0.25);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Tambah Baris Mapel</span>
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="responsive-stack" id="tableAkademik">
            <thead>
                <tr>
                    <th style="width: 45px;" class="num">No</th>
                    <th style="min-width: 250px;">Mata Pelajaran</th>
                    <th style="width: 120px;" class="num">Nilai (0–100)</th>
                    <th style="width: 105px; text-align: center;">Predikat (A-E)</th>
                    <th>Capaian Kompetensi / Catatan Guru</th>
                    <?php if ($isAdmin): ?>
                        <th style="width: 60px; text-align: center;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody id="akademikRowsBody">
                <tr id="emptyRowPlaceholder" style="<?= !empty($initialRows) ? 'display: none;' : '' ?>">
                    <td colspan="<?= $isAdmin ? '6' : '5' ?>" style="text-align: center; padding: 36px 20px; color: var(--ink-soft); background: #fafdfb; border: 1.5px dashed var(--line); border-radius: 8px;">
                        <div style="font-size: 28px; margin-bottom: 8px;">📘</div>
                        <div style="font-weight: 700; color: var(--green-900); font-size: 14.5px;">Belum ada mata pelajaran yang diinput</div>
                        <?php if ($isAdmin): ?>
                            <div style="font-size: 13px; margin-top: 4px; color: var(--ink-soft);">
                                Klik tombol hijau <strong>"+ Tambah Baris Mapel"</strong> di pojok kanan atas untuk menambahkan baris penilaian.
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>

                <?php 
                $no = 1;
                foreach ($initialRows as $index => $row): 
                    $valSubj = (string)$row['subject'];
                    $valScore = (string)$row['score'];
                    $valPredikat = (string)($row['predikat'] ?? '');
                    $valDesc = (string)$row['description'];

                    $pred = '–';
                    $predClass = '';
                    if ($valScore !== '') {
                        $sNum = (float)$valScore;
                        if ($sNum >= 91) { $pred = 'A'; $predClass = 'p-a'; }
                        elseif ($sNum >= 81) { $pred = 'B'; $predClass = 'p-b'; }
                        elseif ($sNum >= 71) { $pred = 'C'; $predClass = 'p-c'; }
                        else { $pred = 'D'; $predClass = 'p-d'; }
                    }
                ?>
                    <tr class="akademik-data-row">
                        <td class="num row-num" data-label="No"><?= $no++ ?></td>
                        <td data-label="Mata Pelajaran">
                            <?php if ($isAdmin): ?>
                                <select name="subjects[]" class="subject-select" required
                                        style="width: 100%; padding: 8px 12px; font-weight: 700; color: var(--green-900); border: 1px solid var(--line); border-radius: 8px; font-family: inherit;">
                                    <option value="">-- Pilih Mata Pelajaran --</option>
                                    <?php foreach ($availableSubjects as $subjOption): ?>
                                        <option value="<?= e($subjOption) ?>" <?= $valSubj === $subjOption ? 'selected' : '' ?>>
                                            <?= e($subjOption) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if (!in_array($valSubj, $availableSubjects, true) && $valSubj !== ''): ?>
                                        <option value="<?= e($valSubj) ?>" selected><?= e($valSubj) ?></option>
                                    <?php endif; ?>
                                </select>
                            <?php else: ?>
                                <input type="hidden" name="subjects[]" value="<?= e($valSubj) ?>">
                                <div style="display: flex; align-items: center; gap: 8px; padding: 6px 0;">
                                    <span style="font-size: 16px;">📘</span>
                                    <strong style="font-size: 14.5px; color: var(--green-900);"><?= e($valSubj) ?></strong>
                                    <span class="badge badge-success" style="font-size: 11px; margin-left: 6px;">Mapel Anda</span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="num" data-label="Nilai (0-100)">
                            <input type="number" step="0.1" min="0" max="100" name="scores[]" value="<?= e($valScore) ?>" placeholder="0 - 100"
                                   class="score-input" autofocus
                                   style="width: 100px; padding: 8px 10px; font-size: 14px; text-align: center; border: 1px solid var(--line); border-radius: 8px; font-weight: 700; font-family: inherit;">
                        </td>
                        <td data-label="Predikat" style="text-align: center;">
                            <select name="predikats[]" class="predicate-select" style="width: 78px; padding: 8px 6px; text-align: center; font-weight: 700; border: 1px solid var(--line); border-radius: 8px; font-family: inherit;">
                                <option value="">-</option>
                                <?php foreach ($predicateOptions as $option): ?>
                                    <option value="<?= $option ?>" <?= $valPredikat === $option ? 'selected' : '' ?>><?= $option ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td data-label="Catatan Guru">
                            <input type="text" name="descriptions[]" value="<?= e($valDesc) ?>" placeholder="Contoh: Sangat baik dalam memahami materi pembelajaran..." 
                                   style="width: 100%; padding: 8px 12px; font-size: 13.5px; border: 1px solid var(--line); border-radius: 8px; font-family: inherit;">
                        </td>
                        <?php if ($isAdmin): ?>
                            <td data-label="Aksi" style="text-align: center;">
                                <button type="button" class="btn btn-delete btn-sm btn-remove-row" title="Hapus baris ini" style="padding: 6px 10px; font-size: 12px;">
                                    🗑
                                </button>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions" style="margin-top: 24px;">
        <a href="<?= e(base_url('/staff/nilai/index.php?semester=' . $semester . '&school_year=' . urlencode($schoolYear) . '&kelas=' . urlencode($student['kelas']))) ?>" class="btn btn-ghost">Batal</a>
        <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-size: 14.5px;">
            💾 Simpan Nilai <?= $isAdmin ? 'Akademik' : e($initialRows[0]['subject']) ?>
        </button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('akademikRowsBody');
    const btnAdd = document.getElementById('btnAddMapelRow');
    const emptyPlaceholder = document.getElementById('emptyRowPlaceholder');
    const isAdmin = <?= json_encode($isAdmin) ?>;
    const availableSubjects = <?= json_encode($availableSubjects, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function calculatePredikat(scoreVal) {
        if (scoreVal === '' || isNaN(scoreVal)) {
            return { label: '–', cls: '' };
        }
        const num = Number(scoreVal);
        if (num >= 91) return { label: 'A', cls: 'p-a' };
        if (num >= 81) return { label: 'B', cls: 'p-b' };
        if (num >= 71) return { label: 'C', cls: 'p-c' };
        return { label: 'D', cls: 'p-d' };
    }

    function updateRowNumbers() {
        const rows = tbody.querySelectorAll('tr.akademik-data-row');
        if (rows.length === 0) {
            if (emptyPlaceholder) emptyPlaceholder.style.display = '';
        } else {
            if (emptyPlaceholder) emptyPlaceholder.style.display = 'none';
            rows.forEach((row, index) => {
                const numCell = row.querySelector('.row-num');
                if (numCell) numCell.textContent = index + 1;
            });
        }
    }

    if (isAdmin && btnAdd) {
        function createRow(subject = '', score = '', predikat = '', description = '') {
            const tr = document.createElement('tr');
            tr.className = 'akademik-data-row';

            let optionsHtml = '<option value="">-- Pilih Mata Pelajaran --</option>';
            let found = false;
            availableSubjects.forEach(s => {
                const isSelected = (s === subject) ? 'selected' : '';
                if (isSelected) found = true;
                optionsHtml += `<option value="${escapeHtml(s)}" ${isSelected}>${escapeHtml(s)}</option>`;
            });
            if (subject && !found) {
                optionsHtml += `<option value="${escapeHtml(subject)}" selected>${escapeHtml(subject)}</option>`;
            }

            const pred = calculatePredikat(score);

            tr.innerHTML = `
                <td class="num row-num" data-label="No">1</td>
                <td data-label="Mata Pelajaran">
                    <select name="subjects[]" class="subject-select" required
                            style="width: 100%; padding: 8px 12px; font-weight: 700; color: var(--green-900); border: 1px solid var(--line); border-radius: 8px; font-family: inherit;">
                        ${optionsHtml}
                    </select>
                </td>
                <td class="num" data-label="Nilai (0-100)">
                    <input type="number" step="0.1" min="0" max="100" name="scores[]" value="${escapeHtml(score)}" placeholder="0 - 100"
                           class="score-input"
                           style="width: 100px; padding: 8px 10px; font-size: 14px; text-align: center; border: 1px solid var(--line); border-radius: 8px; font-weight: 700; font-family: inherit;">
                </td>
                <td data-label="Predikat" style="text-align: center;">
                    <select name="predikats[]" class="predicate-select" style="width: 78px; padding: 8px 6px; text-align: center; font-weight: 700; border: 1px solid var(--line); border-radius: 8px; font-family: inherit;">
                        <option value="">-</option>
                        ${['A', 'B', 'C', 'D', 'E'].map(option => `<option value="${option}" ${option === predikat ? 'selected' : ''}>${option}</option>`).join('')}
                    </select>
                </td>
                <td data-label="Catatan Guru">
                    <input type="text" name="descriptions[]" value="${escapeHtml(description)}" placeholder="Contoh: Sangat baik dalam memahami materi pembelajaran..." 
                           style="width: 100%; padding: 8px 12px; font-size: 13.5px; border: 1px solid var(--line); border-radius: 8px; font-family: inherit;">
                </td>
                <td data-label="Aksi" style="text-align: center;">
                    <button type="button" class="btn btn-delete btn-sm btn-remove-row" title="Hapus baris ini" style="padding: 6px 10px; font-size: 12px;">
                        🗑
                    </button>
                </td>
            `;

            tbody.appendChild(tr);
            updateRowNumbers();
            tr.querySelector('.subject-select')?.focus();
        }

        btnAdd.addEventListener('click', () => {
            createRow();
        });

        tbody.addEventListener('click', (e) => {
            const btnDelete = e.target.closest('.btn-remove-row');
            if (btnDelete) {
                const row = btnDelete.closest('tr.akademik-data-row');
                if (row) {
                    row.remove();
                    updateRowNumbers();
                }
            }
        });
    }

    tbody.addEventListener('input', (e) => {
        if (e.target.classList.contains('score-input')) {
            const row = e.target.closest('tr.akademik-data-row');
            if (row) {
                const badge = row.querySelector('.predikat-badge');
                if (badge) {
                    const pred = calculatePredikat(e.target.value.trim());
                    badge.textContent = pred.label;
                    badge.className = `predikat-badge ${pred.cls}`;
                }
            }
        }
    });

    updateRowNumbers();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
