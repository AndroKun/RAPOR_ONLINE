<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$pageTitle = 'Tambah Siswa Baru - MTs Roudlotul Qur\'an';
$contentTitle = 'Formulir Data Diri Siswa';
$activeMenu = 'siswa';

$errors = [];

// Form input variables
$nama = '';
$nis = '';
$nisn = '';
$jenis_kelamin = 'L';
$tempat_lahir = '';
$tanggal_lahir = '';
$agama = 'ISLAM';
$anak_ke = '';
$status_keluarga = 'Anak Kandung';
$alamat = '';
$kelas = 'VII';
$tanggal_diterima = date('Y-m-d');
$sekolah_asal = '';
$alamat_sekolah_asal = '';

$nama_ayah = '';
$nama_ibu = '';
$alamat_orang_tua = '';
$pekerjaan_ayah = '';
$pekerjaan_ibu = '';
$nama_wali = '';
$alamat_wali = '';
$pekerjaan_wali = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $nama = trim($_POST['nama'] ?? '');
    $nis = trim($_POST['nis'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? 'L';
    $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $agama = trim($_POST['agama'] ?? 'ISLAM');
    $anak_ke = trim($_POST['anak_ke'] ?? '');
    $status_keluarga = trim($_POST['status_keluarga'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $kelas = trim($_POST['kelas'] ?? 'VII');
    $tanggal_diterima = trim($_POST['tanggal_diterima'] ?? '');
    $sekolah_asal = trim($_POST['sekolah_asal'] ?? '');
    $alamat_sekolah_asal = trim($_POST['alamat_sekolah_asal'] ?? '');

    $nama_ayah = trim($_POST['nama_ayah'] ?? '');
    $nama_ibu = trim($_POST['nama_ibu'] ?? '');
    $alamat_orang_tua = trim($_POST['alamat_orang_tua'] ?? '');
    $pekerjaan_ayah = trim($_POST['pekerjaan_ayah'] ?? '');
    $pekerjaan_ibu = trim($_POST['pekerjaan_ibu'] ?? '');
    $nama_wali = trim($_POST['nama_wali'] ?? '');
    $alamat_wali = trim($_POST['alamat_wali'] ?? '');
    $pekerjaan_wali = trim($_POST['pekerjaan_wali'] ?? '');

    // Validasi
    if ($nama === '') $errors[] = 'Nama Siswa wajib diisi.';
    if ($nis === '') $errors[] = 'Nomor Induk (NIS) wajib diisi.';
    if ($nisn === '') $errors[] = 'NISN wajib diisi.';
    if ($tanggal_lahir === '') $errors[] = 'Tanggal Lahir wajib diisi.';
    if ($alamat === '') $errors[] = 'Alamat Siswa wajib diisi.';
    if ($kelas === '') $errors[] = 'Kelas wajib diisi.';

    // Check unique NIS and NISN
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE nis = :nis OR nisn = :nisn LIMIT 1");
        $stmt->execute(['nis' => $nis, 'nisn' => $nisn]);
        if ($stmt->fetch()) {
            $errors[] = 'NIS atau NISN sudah terdaftar di database. Silakan gunakan nomor yang berbeda.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Insert Student
            $stmt = $pdo->prepare("INSERT INTO students (nis, nisn, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, agama, anak_ke, status_keluarga, alamat, kelas, tanggal_diterima, sekolah_asal, alamat_sekolah_asal) 
                                   VALUES (:nis, :nisn, :nama, :jenis_kelamin, :tempat_lahir, :tanggal_lahir, :agama, :anak_ke, :status_keluarga, :alamat, :kelas, :tanggal_diterima, :sekolah_asal, :alamat_sekolah_asal)");
            
            $stmt->execute([
                'nis' => $nis,
                'nisn' => $nisn,
                'nama' => $nama,
                'jenis_kelamin' => $jenis_kelamin,
                'tempat_lahir' => $tempat_lahir,
                'tanggal_lahir' => $tanggal_lahir,
                'agama' => $agama,
                'anak_ke' => $anak_ke !== '' ? (int)$anak_ke : null,
                'status_keluarga' => $status_keluarga ?: null,
                'alamat' => $alamat,
                'kelas' => $kelas,
                'tanggal_diterima' => $tanggal_diterima ?: null,
                'sekolah_asal' => $sekolah_asal ?: null,
                'alamat_sekolah_asal' => $alamat_sekolah_asal ?: null,
            ]);

            $studentId = (int)$pdo->lastInsertId();

            // 2. Insert Guardians
            $stmtGuardian = $pdo->prepare("INSERT INTO guardians (student_id, nama_ayah, nama_ibu, alamat_orang_tua, pekerjaan_ayah, pekerjaan_ibu, nama_wali, alamat_wali, pekerjaan_wali) 
                                           VALUES (:student_id, :nama_ayah, :nama_ibu, :alamat_orang_tua, :pekerjaan_ayah, :pekerjaan_ibu, :nama_wali, :alamat_wali, :pekerjaan_wali)");
            $stmtGuardian->execute([
                'student_id' => $studentId,
                'nama_ayah' => $nama_ayah ?: null,
                'nama_ibu' => $nama_ibu ?: null,
                'alamat_orang_tua' => $alamat_orang_tua ?: null,
                'pekerjaan_ayah' => $pekerjaan_ayah ?: null,
                'pekerjaan_ibu' => $pekerjaan_ibu ?: null,
                'nama_wali' => $nama_wali ?: null,
                'alamat_wali' => $alamat_wali ?: null,
                'pekerjaan_wali' => $pekerjaan_wali ?: null,
            ]);

            // 3. Init Initial Report Period (Genap 2025/2026)
            $stmtReport = $pdo->prepare("INSERT INTO reports (student_id, semester, school_year, status) VALUES (:student_id, 2, '2025/2026', 'draft') ON DUPLICATE KEY UPDATE id=id");
            $stmtReport->execute(['student_id' => $studentId]);

            $pdo->commit();

            set_flash('success', "Data siswa {$nama} (NISN: {$nisn}) berhasil disimpan.");
            redirect('/staff/siswa/index.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Gagal menyimpan data ke database: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
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

<div class="form-container">
    <form action="" method="POST">
        <?= csrf_field() ?>

        <!-- BAGIAN 1: Identitas Siswa -->
        <div class="form-section">
            <h3>A. Data Pribadi Siswa</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>1. Nama Lengkap Siswa *</label>
                    <input type="text" name="nama" value="<?= e($nama) ?>" placeholder="Contoh: Nalaa Qorin Al Faizin" required>
                </div>
                <div class="form-group">
                    <label>2. Nomor Induk (NIS) *</label>
                    <input type="text" name="nis" value="<?= e($nis) ?>" placeholder="Contoh: 250012" required>
                </div>
                <div class="form-group">
                    <label>3. NIS Nasional (NISN) *</label>
                    <input type="text" name="nisn" value="<?= e($nisn) ?>" placeholder="Contoh: 0136347734" required>
                </div>
                <div class="form-group">
                    <label>4. Jenis Kelamin *</label>
                    <select name="jenis_kelamin" required>
                        <option value="L" <?= $jenis_kelamin === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                        <option value="P" <?= $jenis_kelamin === 'P' ? 'selected' : '' ?>>Perempuan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>5a. Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" value="<?= e($tempat_lahir) ?>" placeholder="Contoh: Magetan">
                </div>
                <div class="form-group">
                    <label>5b. Tanggal Lahir *</label>
                    <input type="date" name="tanggal_lahir" value="<?= e($tanggal_lahir) ?>" required>
                </div>
                <div class="form-group">
                    <label>6. Agama</label>
                    <input type="text" name="agama" value="ISLAM" readonly>
                </div>
                <div class="form-group">
                    <label>7. Anak Ke</label>
                    <input type="number" name="anak_ke" value="<?= e((string)$anak_ke) ?>" placeholder="Contoh: 1">
                </div>
                <div class="form-group">
                    <label>8. Status di Keluarga</label>
                    <input type="text" name="status_keluarga" value="<?= e($status_keluarga) ?>" placeholder="Contoh: Anak Kandung">
                </div>
                <div class="form-group full-width">
                    <label>9. Alamat Lengkap Siswa *</label>
                    <textarea name="alamat" rows="2" placeholder="Contoh: Kali Tengah Rt 01 Rw 06 Tanggulangin Sidoarjo" required><?= e($alamat) ?></textarea>
                </div>
            </div>
        </div>

        <!-- BAGIAN 2: Riwayat Sekolah -->
        <div class="form-section">
            <h3>B. Riwayat Sekolah</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>10a. Diterima di Kelas *</label>
                    <input type="text" name="kelas" value="<?= e($kelas) ?>" placeholder="Contoh: VII (TUJUH)" required>
                </div>
                <div class="form-group">
                    <label>10b. Pada Tanggal</label>
                    <input type="date" name="tanggal_diterima" value="<?= e($tanggal_diterima) ?>">
                </div>
                <div class="form-group full-width">
                    <label>11a. Nama Sekolah Asal</label>
                    <input type="text" name="sekolah_asal" value="<?= e($sekolah_asal) ?>" placeholder="Contoh: SD Maarif NU Ngaban">
                </div>
                <div class="form-group full-width">
                    <label>11b. Alamat Sekolah Asal</label>
                    <textarea name="alamat_sekolah_asal" rows="2" placeholder="Contoh: Ngaban Tanggulangin Sidoarjo"><?= e($alamat_sekolah_asal) ?></textarea>
                </div>
            </div>
        </div>

        <!-- BAGIAN 3: Data Orang Tua & Wali -->
        <div class="form-section">
            <h3>C. Data Orang Tua & Wali</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>12a. Nama Ayah</label>
                    <input type="text" name="nama_ayah" value="<?= e($nama_ayah) ?>" placeholder="Masukkan nama ayah">
                </div>
                <div class="form-group">
                    <label>12b. Nama Ibu</label>
                    <input type="text" name="nama_ibu" value="<?= e($nama_ibu) ?>" placeholder="Masukkan nama ibu">
                </div>
                <div class="form-group full-width">
                    <label>13. Alamat Orang Tua</label>
                    <textarea name="alamat_orang_tua" rows="2" placeholder="Masukkan alamat lengkap orang tua"><?= e($alamat_orang_tua) ?></textarea>
                </div>
                <div class="form-group">
                    <label>14a. Pekerjaan Ayah</label>
                    <input type="text" name="pekerjaan_ayah" value="<?= e($pekerjaan_ayah) ?>" placeholder="Contoh: Wiraswasta">
                </div>
                <div class="form-group">
                    <label>14b. Pekerjaan Ibu</label>
                    <input type="text" name="pekerjaan_ibu" value="<?= e($pekerjaan_ibu) ?>" placeholder="Contoh: Ibu Rumah Tangga">
                </div>
                <div class="form-group full-width" style="border-top: 1px dashed #ccc; padding-top: 15px; margin-top: 10px;">
                    <label>15. Nama Wali (Opsional)</label>
                    <input type="text" name="nama_wali" value="<?= e($nama_wali) ?>" placeholder="Kosongkan jika sama dengan orang tua">
                </div>
                <div class="form-group full-width">
                    <label>16. Alamat Wali</label>
                    <textarea name="alamat_wali" rows="2" placeholder="Alamat lengkap wali"><?= e($alamat_wali) ?></textarea>
                </div>
                <div class="form-group">
                    <label>17. Pekerjaan Wali</label>
                    <input type="text" name="pekerjaan_wali" value="<?= e($pekerjaan_wali) ?>" placeholder="Pekerjaan wali">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="<?= e(base_url('/staff/siswa/index.php')) ?>" class="btn-cancel">Batal</a>
            <button type="submit" class="btn-save">Simpan Data Siswa</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
