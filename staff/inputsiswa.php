<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/fungsi.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pageTitle = 'Input Data Siswa - MTs Roudlotul Qur\'an';
$contentTitle = 'Formulir Data Diri Siswa';
$contentSubtitle = 'Lengkapi data pribadi, riwayat sekolah, dan data keluarga siswa secara bertahap.';
$activeMenu = 'inputsiswa';

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old = $_POST;

    // 1. Data Pribadi
    $nama = trim($_POST['nama'] ?? '');
    $nis = trim($_POST['nis'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $jenisKelamin = trim($_POST['jenis_kelamin'] ?? '');
    $tempatLahir = trim($_POST['tempat_lahir'] ?? '');
    $tanggalLahir = trim($_POST['tanggal_lahir'] ?? '');
    $agama = trim($_POST['agama'] ?? 'ISLAM');
    $anakKe = !empty($_POST['anak_ke']) ? (int)$_POST['anak_ke'] : null;
    $statusKeluarga = trim($_POST['status_keluarga'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    // 2. Riwayat Sekolah
    $kelas = trim($_POST['kelas'] ?? 'VII');
    $tanggalDiterima = !empty($_POST['tanggal_diterima']) ? $_POST['tanggal_diterima'] : null;
    $sekolahAsal = trim($_POST['sekolah_asal'] ?? '');
    $alamatSekolahAsal = trim($_POST['alamat_sekolah_asal'] ?? '');

    // 3. Orang Tua & Wali
    $namaAyah = trim($_POST['nama_ayah'] ?? '');
    $namaIbu = trim($_POST['nama_ibu'] ?? '');
    $alamatOrangTua = trim($_POST['alamat_orang_tua'] ?? '');
    $pekerjaanAyah = trim($_POST['pekerjaan_ayah'] ?? '');
    $pekerjaanIbu = trim($_POST['pekerjaan_ibu'] ?? '');
    $namaWali = trim($_POST['nama_wali'] ?? '');
    $alamatWali = trim($_POST['alamat_wali'] ?? '');
    $pekerjaanWali = trim($_POST['pekerjaan_wali'] ?? '');

    // Validasi Dasar
    if ($nama === '') $errors[] = 'Nama lengkap siswa wajib diisi.';
    if ($nis === '') $errors[] = 'Nomor Induk Siswa (NIS) wajib diisi.';
    if ($nisn === '') $errors[] = 'Nomor Induk Siswa Nasional (NISN) wajib diisi.';
    if (!in_array($jenisKelamin, ['L', 'P'], true)) $errors[] = 'Jenis kelamin harus dipilih (Laki-laki / Perempuan).';
    if ($tempatLahir === '') $errors[] = 'Tempat lahir wajib diisi.';
    if ($tanggalLahir === '') $errors[] = 'Tanggal lahir wajib diisi.';
    if ($alamat === '') $errors[] = 'Alamat siswa wajib diisi.';
    if ($kelas === '') $errors[] = 'Kelas siswa wajib diisi.';

    // Cek duplikasi NIS / NISN
    if (empty($errors)) {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM students WHERE nis = :nis OR nisn = :nisn");
        $stmtCheck->execute(['nis' => $nis, 'nisn' => $nisn]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            $errors[] = 'NIS atau NISN sudah terdaftar pada sistem.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert Student
            $stmtStudent = $pdo->prepare("
                INSERT INTO students (
                    nis, nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, 
                    agama, anak_ke, status_keluarga, alamat, kelas, 
                    tanggal_diterima, sekolah_asal, alamat_sekolah_asal
                ) VALUES (
                    :nis, :nisn, :nama, :jenis_kelamin, :tempat_lahir, :tanggal_lahir, 
                    :agama, :anak_ke, :status_keluarga, :alamat, :kelas, 
                    :tanggal_diterima, :sekolah_asal, :alamat_sekolah_asal
                )
            ");

            $stmtStudent->execute([
                'nis' => $nis,
                'nisn' => $nisn,
                'nama' => $nama,
                'jenis_kelamin' => $jenisKelamin,
                'tempat_lahir' => $tempatLahir,
                'tanggal_lahir' => $tanggalLahir,
                'agama' => $agama,
                'anak_ke' => $anakKe,
                'status_keluarga' => $statusKeluarga ?: null,
                'alamat' => $alamat,
                'kelas' => $kelas,
                'tanggal_diterima' => $tanggalDiterima,
                'sekolah_asal' => $sekolahAsal ?: null,
                'alamat_sekolah_asal' => $alamatSekolahAsal ?: null,
            ]);

            $studentId = (int)$pdo->lastInsertId();

            // Insert Guardians
            $stmtGuardian = $pdo->prepare("
                INSERT INTO guardians (
                    student_id, nama_ayah, nama_ibu, alamat_orang_tua, 
                    pekerjaan_ayah, pekerjaan_ibu, nama_wali, alamat_wali, pekerjaan_wali
                ) VALUES (
                    :student_id, :nama_ayah, :nama_ibu, :alamat_orang_tua, 
                    :pekerjaan_ayah, :pekerjaan_ibu, :nama_wali, :alamat_wali, :pekerjaan_wali
                )
            ");

            $stmtGuardian->execute([
                'student_id' => $studentId,
                'nama_ayah' => $namaAyah ?: null,
                'nama_ibu' => $namaIbu ?: null,
                'alamat_orang_tua' => $alamatOrangTua ?: null,
                'pekerjaan_ayah' => $pekerjaanAyah ?: null,
                'pekerjaan_ibu' => $pekerjaanIbu ?: null,
                'nama_wali' => $namaWali ?: null,
                'alamat_wali' => $alamatWali ?: null,
                'pekerjaan_wali' => $pekerjaanWali ?: null,
            ]);

            $pdo->commit();

            set_flash('success', "Data siswa {$nama} berhasil ditambahkan ke database.");
            redirect('/staff/dashboard.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul style="margin:0; padding-left:20px;">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Multi-Step Stepper -->
<div class="stepper">
    <button type="button" class="step is-active" data-step="1">
        <span class="step-badge">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M12 12a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="1.7"/>
                <path d="M4 20c0-3.5 3.5-6 8-6s8 2.5 8 6" stroke="currentColor" stroke-width="1.7"/>
            </svg>
        </span>
        <span class="step-label">
            <span class="k">Langkah 1</span><br>
            <span class="v">Data Pribadi</span>
        </span>
    </button>
    <div class="step-connector" data-conn="1"></div>
    <button type="button" class="step" data-step="2">
        <span class="step-badge">
            <svg viewBox="0 0 24 24" fill="none">
                <path d="M12 3l9 5-9 5-9-5 9-5z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                <path d="M5 10.5V16c0 1.5 3 3 7 3s7-1.5 7-3v-5.5" stroke="currentColor" stroke-width="1.7"/>
            </svg>
        </span>
        <span class="step-label">
            <span class="k">Langkah 2</span><br>
            <span class="v">Riwayat Sekolah</span>
        </span>
    </button>
    <div class="step-connector" data-conn="2"></div>
    <button type="button" class="step" data-step="3">
        <span class="step-badge">
            <svg viewBox="0 0 24 24" fill="none">
                <circle cx="8" cy="8" r="2.6" stroke="currentColor" stroke-width="1.7"/>
                <circle cx="16" cy="8" r="2.6" stroke="currentColor" stroke-width="1.7"/>
                <path d="M3 19c0-2.8 2.3-4.6 5-4.6s5 1.8 5 4.6M11 19c0-2.8 2.3-4.6 5-4.6s5 1.8 5 4.6" stroke="currentColor" stroke-width="1.7"/>
            </svg>
        </span>
        <span class="step-label">
            <span class="k">Langkah 3</span><br>
            <span class="v">Orang Tua &amp; Wali</span>
        </span>
    </button>
</div>

<!-- Form Card -->
<form id="siswaForm" class="card" method="POST" action="" novalidate>
    <?= csrf_field() ?>

    <!-- PANEL 1: DATA PRIBADI -->
    <section class="panel is-active" data-panel="1">
        <h2 class="section-title">A. Data Pribadi Siswa</h2>
        <p class="section-hint">Isi identitas dasar siswa sesuai dokumen resmi.</p>
        <div class="grid">
            <div class="field">
                <label>1. Nama Siswa <span style="color:var(--rose-500)">*</span></label>
                <input type="text" name="nama" value="<?= e($old['nama'] ?? '') ?>" placeholder="Contoh: Nalaa Qorin Al Faizin" required>
            </div>
            <div class="field">
                <label>2. Nomor Induk (NIS) <span style="color:var(--rose-500)">*</span></label>
                <input type="text" name="nis" value="<?= e($old['nis'] ?? '') ?>" placeholder="Contoh: 250012" required>
            </div>
            <div class="field">
                <label>3. NIS Nasional (NISN) <span style="color:var(--rose-500)">*</span></label>
                <input type="text" name="nisn" value="<?= e($old['nisn'] ?? '') ?>" placeholder="Contoh: 0136347734" required>
            </div>
            <div class="field">
                <label>4. Jenis Kelamin <span style="color:var(--rose-500)">*</span></label>
                <select name="jenis_kelamin" required>
                    <option value="">Pilih jenis kelamin</option>
                    <option value="L" <?= ($old['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                    <option value="P" <?= ($old['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                </select>
            </div>
            <div class="field">
                <label>5a. Tempat Lahir <span style="color:var(--rose-500)">*</span></label>
                <input type="text" name="tempat_lahir" value="<?= e($old['tempat_lahir'] ?? '') ?>" placeholder="Contoh: Magetan" required>
            </div>
            <div class="field">
                <label>5b. Tanggal Lahir <span style="color:var(--rose-500)">*</span></label>
                <input type="date" name="tanggal_lahir" value="<?= e($old['tanggal_lahir'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label>6. Agama</label>
                <input type="text" name="agama" value="<?= e($old['agama'] ?? 'ISLAM') ?>" readonly>
            </div>
            <div class="field">
                <label>7. Anak Ke</label>
                <input type="number" name="anak_ke" value="<?= e($old['anak_ke'] ?? '') ?>" placeholder="Contoh: 1">
            </div>
            <div class="field">
                <label>8. Status di Keluarga</label>
                <input type="text" name="status_keluarga" value="<?= e($old['status_keluarga'] ?? 'Anak Kandung') ?>" placeholder="Contoh: Anak Kandung">
            </div>
            <div class="field full">
                <label>9. Alamat Siswa <span style="color:var(--rose-500)">*</span></label>
                <textarea name="alamat" placeholder="Contoh: Kali Tengah Rt 01 Rw 06 Tanggulangin Sidoarjo" required><?= e($old['alamat'] ?? '') ?></textarea>
            </div>
        </div>
    </section>

    <!-- PANEL 2: RIWAYAT SEKOLAH -->
    <section class="panel" data-panel="2">
        <h2 class="section-title">B. Riwayat Sekolah</h2>
        <p class="section-hint">Informasi penerimaan dan asal sekolah siswa.</p>
        <div class="grid">
            <div class="field">
                <label>10a. Diterima di Kelas <span style="color:var(--rose-500)">*</span></label>
                <input type="text" name="kelas" value="<?= e($old['kelas'] ?? 'VII') ?>" placeholder="Contoh: VII" required>
            </div>
            <div class="field">
                <label>10b. Pada Tanggal</label>
                <input type="date" name="tanggal_diterima" value="<?= e($old['tanggal_diterima'] ?? '') ?>">
            </div>
            <div class="field full">
                <label>11a. Nama Sekolah Asal</label>
                <input type="text" name="sekolah_asal" value="<?= e($old['sekolah_asal'] ?? '') ?>" placeholder="Contoh: SD Maarif NU Ngaban">
            </div>
            <div class="field full">
                <label>11b. Alamat Sekolah Asal</label>
                <textarea name="alamat_sekolah_asal" placeholder="Contoh: Ngaban Tanggulangin Sidoarjo"><?= e($old['alamat_sekolah_asal'] ?? '') ?></textarea>
            </div>
        </div>
    </section>

    <!-- PANEL 3: ORANG TUA & WALI -->
    <section class="panel" data-panel="3">
        <h2 class="section-title">C. Data Orang Tua &amp; Wali</h2>
        <p class="section-hint">Kolom wali bisa dikosongkan jika sama dengan orang tua.</p>
        <div class="grid">
            <div class="field">
                <label>12a. Nama Ayah</label>
                <input type="text" name="nama_ayah" value="<?= e($old['nama_ayah'] ?? '') ?>" placeholder="Masukkan nama ayah">
            </div>
            <div class="field">
                <label>12b. Nama Ibu</label>
                <input type="text" name="nama_ibu" value="<?= e($old['nama_ibu'] ?? '') ?>" placeholder="Masukkan nama ibu">
            </div>
            <div class="field full">
                <label>13. Alamat Orang Tua</label>
                <textarea name="alamat_orang_tua" placeholder="Masukkan alamat lengkap orang tua"><?= e($old['alamat_orang_tua'] ?? '') ?></textarea>
            </div>
            <div class="field">
                <label>14a. Pekerjaan Ayah</label>
                <input type="text" name="pekerjaan_ayah" value="<?= e($old['pekerjaan_ayah'] ?? '') ?>" placeholder="Contoh: Wiraswasta">
            </div>
            <div class="field">
                <label>14b. Pekerjaan Ibu</label>
                <input type="text" name="pekerjaan_ibu" value="<?= e($old['pekerjaan_ibu'] ?? '') ?>" placeholder="Contoh: Ibu Rumah Tangga">
            </div>
            <hr class="divider">
            <div class="field full">
                <label>15. Nama Wali <span class="opt">(Opsional)</span></label>
                <input type="text" name="nama_wali" value="<?= e($old['nama_wali'] ?? '') ?>" placeholder="Kosongkan jika sama dengan orang tua">
            </div>
            <div class="field full">
                <label>16. Alamat Wali</label>
                <textarea name="alamat_wali" placeholder="Alamat lengkap wali"><?= e($old['alamat_wali'] ?? '') ?></textarea>
            </div>
            <div class="field">
                <label>17. Pekerjaan Wali</label>
                <input type="text" name="pekerjaan_wali" value="<?= e($old['pekerjaan_wali'] ?? '') ?>" placeholder="Pekerjaan wali">
            </div>
        </div>
    </section>

    <!-- FORM ACTIONS -->
    <div class="form-actions">
        <button type="button" class="btn btn-ghost" id="backBtn" disabled>Kembali</button>
        <div>
            <button type="button" class="btn btn-ghost" id="clearBtn">Kosongkan Form</button>
            <button type="button" class="btn btn-primary" id="nextBtn">Lanjut</button>
            <button type="submit" class="btn btn-gold" id="saveBtn" style="display:none;">Simpan Data Siswa</button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const steps = Array.from(document.querySelectorAll('.step'));
    const panels = Array.from(document.querySelectorAll('.panel'));
    const connectors = Array.from(document.querySelectorAll('.step-connector'));
    const backBtn = document.getElementById('backBtn');
    const nextBtn = document.getElementById('nextBtn');
    const saveBtn = document.getElementById('saveBtn');
    let current = 1;
    let furthestVisited = 1;

    function render() {
        panels.forEach(p => p.classList.toggle('is-active', Number(p.dataset.panel) === current));
        steps.forEach(s => {
            const n = Number(s.dataset.step);
            s.classList.toggle('is-active', n === current);
            s.classList.toggle('is-done', n < furthestVisited || (n < current));
            s.disabled = n > furthestVisited;
        });
        connectors.forEach(c => c.classList.toggle('is-done', Number(c.dataset.conn) < current));
        backBtn.disabled = (current === 1);
        nextBtn.style.display = (current === 3) ? 'none' : 'inline-flex';
        saveBtn.style.display = (current === 3) ? 'inline-flex' : 'none';
    }

    function goTo(n) {
        current = Math.min(3, Math.max(1, n));
        furthestVisited = Math.max(furthestVisited, current);
        render();
        const card = document.querySelector('.card');
        if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    nextBtn.addEventListener('click', () => {
        const activePanel = document.querySelector('.panel.is-active');
        const required = activePanel.querySelectorAll('[required]');
        for (const field of required) {
            if (!field.value.trim()) {
                field.focus();
                field.style.borderColor = 'var(--rose-500)';
                showToast('Harap lengkapi kolom yang bertanda bintang (*).');
                return;
            }
            field.style.borderColor = '';
        }
        goTo(current + 1);
    });

    backBtn.addEventListener('click', () => goTo(current - 1));

    steps.forEach(s => s.addEventListener('click', () => {
        if (!s.disabled) goTo(Number(s.dataset.step));
    }));

    document.getElementById('clearBtn').addEventListener('click', () => {
        if (confirm('Apakah Anda yakin ingin mengosongkan seluruh isian formulir?')) {
            document.getElementById('siswaForm').reset();
            goTo(1);
            furthestVisited = 1;
        }
    });

    render();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>