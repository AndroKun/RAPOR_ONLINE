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
$stmtTahfidh = $pdo->prepare("SELECT memorization, score, description FROM tahfidh_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy ORDER BY id ASC");
$stmtTahfidh->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
$savedTahfidh = $stmtTahfidh->fetchAll();

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

        $stmtInsert = $pdo->prepare("INSERT INTO tahfidh_grades (student_id, memorization, score, description, semester, school_year, created_at, updated_at) 
                                     VALUES (:sid, :mem, :score, :desc, :sem, :sy, NOW(), NOW())");

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
            <p class="section-hint" style="margin: 2px 0 0;">Klik tombol <strong>+ Tambah Baris Penilaian</strong> di sebelah kanan untuk menambahkan baris target hafalan siswa.</p>
        </div>
        <button type="button" class="btn btn-primary" id="btnAddTahfidhRow" style="display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(21,122,66,0.25);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Tambah Baris Penilaian</span>
        </button>
    </div>

    <div class="table-responsive">
        <table class="responsive-stack" id="tableTahfidh">
            <thead>
                <tr>
                    <th style="width: 45px;" class="num">No</th>
                    <th style="min-width: 260px;">Target Hafalan / Surat / Juz</th>
                    <th style="width: 130px;" class="num">Nilai (0–100)</th>
                    <th>Catatan Fashahah, Kelancaran &amp; Tajwid</th>
                    <th style="width: 60px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody id="tahfidhRowsBody">
                <tr id="emptyRowPlaceholder" style="<?= !empty($savedTahfidh) ? 'display: none;' : '' ?>">
                    <td colspan="5" style="text-align: center; padding: 36px 20px; color: var(--ink-soft); background: #fafdfb; border: 1.5px dashed var(--line); border-radius: 8px;">
                        <div style="font-size: 28px; margin-bottom: 8px;">📖</div>
                        <div style="font-weight: 700; color: var(--green-900); font-size: 14.5px;">Belum ada target penilaian hafalan</div>
                        <div style="font-size: 13px; margin-top: 4px; color: var(--ink-soft);">
                            Klik tombol hijau <strong>"+ Tambah Baris Penilaian"</strong> di pojok kanan atas untuk memasukkan penilaian siswa.
                        </div>
                    </td>
                </tr>

                <?php 
                $no = 1;
                foreach ($savedTahfidh as $row): 
                    $valMem = (string)$row['memorization'];
                    $valScore = (string)$row['score'];
                    $valDesc = (string)($row['description'] ?? '');
                ?>
                    <tr class="tahfidh-data-row">
                        <td class="num row-num" data-label="No"><?= $no++ ?></td>
                        <td data-label="Target Hafalan">
                            <input type="text" name="memorizations[]" value="<?= e($valMem) ?>" placeholder="Contoh: Juz 30 / Surat An-Naba' / Fashahah" required
                                   style="width: 100%; padding: 8px 12px; font-weight: 700; color: var(--green-900); border: 1px solid var(--line); border-radius: 8px; font-family: inherit;">
                        </td>
                        <td class="num" data-label="Nilai (0-100)">
                            <input type="number" step="0.1" min="0" max="100" name="scores[]" value="<?= e($valScore) ?>" placeholder="0 - 100" required
                                   style="width: 110px; padding: 8px 12px; font-size: 14px; text-align: center; border: 1px solid var(--line); border-radius: 8px; font-weight: 700; font-family: inherit;">
                        </td>
                        <td data-label="Catatan Guru">
                            <input type="text" name="descriptions[]" value="<?= e($valDesc) ?>" placeholder="Contoh: Mutqin, fashahah makhraj baik, tartil..." 
                                   style="width: 100%; padding: 8px 12px; font-size: 13.5px; border: 1px solid var(--line); border-radius: 8px; font-family: inherit;">
                        </td>
                        <td data-label="Aksi" style="text-align: center;">
                            <button type="button" class="btn btn-delete btn-sm btn-remove-row" title="Hapus baris ini" style="padding: 6px 10px; font-size: 12px;">
                                🗑
                            </button>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('tahfidhRowsBody');
    const btnAdd = document.getElementById('btnAddTahfidhRow');
    const emptyPlaceholder = document.getElementById('emptyRowPlaceholder');

    function updateRowNumbers() {
        const rows = tbody.querySelectorAll('tr.tahfidh-data-row');
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

    function createRow(memorization = '', score = '', description = '') {
        const tr = document.createElement('tr');
        tr.className = 'tahfidh-data-row';
        tr.innerHTML = `
            <td class="num row-num" data-label="No">1</td>
            <td data-label="Target Hafalan">
                <input type="text" name="memorizations[]" value="${escapeHtml(memorization)}" placeholder="Contoh: Juz 30 / Surat An-Naba' / Fashahah" required
                       style="width: 100%; padding: 8px 12px; font-weight: 700; color: var(--green-900); border: 1px solid var(--line); border-radius: 8px; font-family: inherit;">
            </td>
            <td class="num" data-label="Nilai (0-100)">
                <input type="number" step="0.1" min="0" max="100" name="scores[]" value="${escapeHtml(score)}" placeholder="0 - 100" required
                       style="width: 110px; padding: 8px 12px; font-size: 14px; text-align: center; border: 1px solid var(--line); border-radius: 8px; font-weight: 700; font-family: inherit;">
            </td>
            <td data-label="Catatan Guru">
                <input type="text" name="descriptions[]" value="${escapeHtml(description)}" placeholder="Contoh: Mutqin, fashahah makhraj baik, tartil..." 
                       style="width: 100%; padding: 8px 12px; font-size: 13.5px; border: 1px solid var(--line); border-radius: 8px; font-family: inherit;">
            </td>
            <td data-label="Aksi" style="text-align: center;">
                <button type="button" class="btn btn-delete btn-sm btn-remove-row" title="Hapus baris ini" style="padding: 6px 10px; font-size: 12px;">
                    🗑
                </button>
            </td>
        `;

        // Attach remove handler
        tr.querySelector('.btn-remove-row').addEventListener('click', () => {
            tr.remove();
            updateRowNumbers();
        });

        tbody.appendChild(tr);
        updateRowNumbers();

        // Focus on the newly created memorization input
        const memInput = tr.querySelector('input[name="memorizations[]"]');
        if (memInput) memInput.focus();
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    if (btnAdd) {
        btnAdd.addEventListener('click', () => {
            createRow();
        });
    }

    // Attach remove handlers to pre-rendered rows
    tbody.querySelectorAll('.btn-remove-row').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const tr = e.target.closest('tr.tahfidh-data-row');
            if (tr) {
                tr.remove();
                updateRowNumbers();
            }
        });
    });

    updateRowNumbers();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
