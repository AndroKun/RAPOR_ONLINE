<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'ID Siswa tidak valid.');
    redirect('/staff/siswa/index.php');
}

$stmt = $pdo->prepare("SELECT s.*, g.nama_ayah, g.nama_ibu, g.alamat_orang_tua, g.pekerjaan_ayah, g.pekerjaan_ibu, g.nama_wali, g.alamat_wali, g.pekerjaan_wali 
                       FROM students s 
                       LEFT JOIN guardians g ON g.student_id = s.id 
                       WHERE s.id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('danger', 'Data siswa tidak ditemukan.');
    redirect('/staff/siswa/index.php');
}

$pageTitle = 'Edit Data Siswa - ' . $student['nama'];
$contentTitle = 'Ubah Data Siswa';
$activeMenu = 'siswa';

$errors = [];

$nama = $student['nama'];
$nis = $student['nis'];
$nisn = $student['nisn'];
$jenis_kelamin = $student['jenis_kelamin'];
$tempat_lahir = $student['tempat_lahir'];
$tanggal_lahir = $student['tanggal_lahir'];
$agama = $student['agama'];
$anak_ke = $student['anak_ke'];
$status_keluarga = $student['status_keluarga'];
$alamat = $student['alamat'];
$kelas = $student['kelas'];
$tanggal_diterima = $student['tanggal_diterima'];
$sekolah_asal = $student['sekolah_asal'];
$alamat_sekolah_asal = $student['alamat_sekolah_asal'];

$nama_ayah = $student['nama_ayah'] ?? '';
$nama_ibu = $student['nama_ibu'] ?? '';
$alamat_orang_tua = $student['alamat_orang_tua'] ?? '';
$pekerjaan_ayah = $student['pekerjaan_ayah'] ?? '';
$pekerjaan_ibu = $student['pekerjaan_ibu'] ?? '';
$nama_wali = $student['nama_wali'] ?? '';
$alamat_wali = $student['alamat_wali'] ?? '';
$pekerjaan_wali = $student['pekerjaan_wali'] ?? '';

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

    if ($nama === '') $errors[] = 'Nama Siswa wajib diisi.';
    if ($nis === '') $errors[] = 'Nomor Induk (NIS) wajib diisi.';
    if ($nisn === '') $errors[] = 'NISN wajib diisi.';
    if ($tanggal_lahir === '') $errors[] = 'Tanggal Lahir wajib diisi.';
    if ($alamat === '') $errors[] = 'Alamat Siswa wajib diisi.';
    if ($kelas === '') $errors[] = 'Kelas wajib diisi.';

    // Check duplicate NIS/NISN with other students
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE (nis = :nis OR nisn = :nisn) AND id != :id LIMIT 1");
        $stmt->execute(['nis' => $nis, 'nisn' => $nisn, 'id' => $id]);
        if ($stmt->fetch()) {
            $errors[] = 'NIS atau NISN sudah digunakan oleh siswa lain.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmtUpdate = $pdo->prepare("UPDATE students SET 
                nis = :nis, nisn = :nisn, nama = :nama, jenis_kelamin = :jenis_kelamin,
                tempat_lahir = :tempat_lahir, tanggal_lahir = :tanggal_lahir, agama = :agama,
                anak_ke = :anak_ke, status_keluarga = :status_keluarga, alamat = :alamat,
                kelas = :kelas, tanggal_diterima = :tanggal_diterima, sekolah_asal = :sekolah_asal,
                alamat_sekolah_asal = :alamat_sekolah_asal WHERE id = :id");
            
            $stmtUpdate->execute([
                'id' => $id,
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

            // Update/Upsert Guardians
            $stmtGuard = $pdo->prepare("INSERT INTO guardians (student_id, nama_ayah, nama_ibu, alamat_orang_tua, pekerjaan_ayah, pekerjaan_ibu, nama_wali, alamat_wali, pekerjaan_wali)
                                        VALUES (:student_id, :nama_ayah, :nama_ibu, :alamat_orang_tua, :pekerjaan_ayah, :pekerjaan_ibu, :nama_wali, :alamat_wali, :pekerjaan_wali)
                                        ON DUPLICATE KEY UPDATE 
                                        nama_ayah = VALUES(nama_ayah), nama_ibu = VALUES(nama_ibu), alamat_orang_tua = VALUES(alamat_orang_tua),
                                        pekerjaan_ayah = VALUES(pekerjaan_ayah), pekerjaan_ibu = VALUES(pekerjaan_ibu),
                                        nama_wali = VALUES(nama_wali), alamat_wali = VALUES(alamat_wali), pekerjaan_wali = VALUES(pekerjaan_wali)");
            $stmtGuard->execute([
                'student_id' => $id,
                'nama_ayah' => $nama_ayah ?: null,
                'nama_ibu' => $nama_ibu ?: null,
                'alamat_orang_tua' => $alamat_orang_tua ?: null,
                'pekerjaan_ayah' => $pekerjaan_ayah ?: null,
                'pekerjaan_ibu' => $pekerjaan_ibu ?: null,
                'nama_wali' => $nama_wali ?: null,
                'alamat_wali' => $alamat_wali ?: null,
                'pekerjaan_wali' => $pekerjaan_wali ?: null,
            ]);

            $pdo->commit();

            set_flash('success', "Data siswa {$nama} berhasil diperbarui.");
            redirect('/staff/siswa/index.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Gagal memperbarui data: ' . $e->getMessage();
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
                    <input type="text" name="nama" value="<?= e($nama) ?>" required>
                </div>
                <div class="form-group">
                    <label>2. Nomor Induk (NIS) *</label>
                    <input type="text" name="nis" value="<?= e($nis) ?>" required>
                </div>
                <div class="form-group">
                    <label>3. NIS Nasional (NISN) *</label>
                    <input type="text" name="nisn" value="<?= e($nisn) ?>" required>
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
                    <input type="text" name="tempat_lahir" value="<?= e($tempat_lahir) ?>">
                </div>
                <div class="form-group">
                    <label>5b. Tanggal Lahir *</label>
                    <input type="date" name="tanggal_lahir" value="<?= e($tanggal_lahir) ?>" required>
                </div>
                <div class="form-group">
                    <label>6. Agama</label>
                    <input type="text" name="agama" value="<?= e($agama) ?>" readonly>
                </div>
                <div class="form-group">
                    <label>7. Anak Ke</label>
                    <input type="number" name="anak_ke" value="<?= e((string)$anak_ke) ?>">
                </div>
                <div class="form-group">
                    <label>8. Status di Keluarga</label>
                    <input type="text" name="status_keluarga" value="<?= e($status_keluarga) ?>">
                </div>
                <div class="form-group full-width">
                    <label>9. Alamat Lengkap Siswa *</label>
                    <textarea name="alamat" rows="2" required><?= e($alamat) ?></textarea>
                </div>
            </div>
        </div>

        <!-- BAGIAN 2: Riwayat Sekolah -->
        <div class="form-section">
            <h3>B. Riwayat Sekolah</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>10a. Diterima di Kelas *</label>
                    <input type="text" name="kelas" value="<?= e($kelas) ?>" required>
                </div>
                <div class="form-group">
                    <label>10b. Pada Tanggal</label>
                    <input type="date" name="tanggal_diterima" value="<?= e($tanggal_diterima) ?>">
                </div>
                <div class="form-group full-width">
                    <label>11a. Nama Sekolah Asal</label>
                    <input type="text" name="sekolah_asal" value="<?= e($sekolah_asal) ?>">
                </div>
                <div class="form-group full-width">
                    <label>11b. Alamat Sekolah Asal</label>
                    <textarea name="alamat_sekolah_asal" rows="2"><?= e($alamat_sekolah_asal) ?></textarea>
                </div>
            </div>
        </div>

        <!-- BAGIAN 3: Data Orang Tua & Wali -->
        <div class="form-section">
            <h3>C. Data Orang Tua & Wali</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>12a. Nama Ayah</label>
                    <input type="text" name="nama_ayah" value="<?= e($nama_ayah) ?>">
                </div>
                <div class="form-group">
                    <label>12b. Nama Ibu</label>
                    <input type="text" name="nama_ibu" value="<?= e($nama_ibu) ?>">
                </div>
                <div class="form-group full-width">
                    <label>13. Alamat Orang Tua</label>
                    <textarea name="alamat_orang_tua" rows="2"><?= e($alamat_orang_tua) ?></textarea>
                </div>
                <div class="form-group">
                    <label>14a. Pekerjaan Ayah</label>
                    <input type="text" name="pekerjaan_ayah" value="<?= e($pekerjaan_ayah) ?>">
                </div>
                <div class="form-group">
                    <label>14b. Pekerjaan Ibu</label>
                    <input type="text" name="pekerjaan_ibu" value="<?= e($pekerjaan_ibu) ?>">
                </div>
                <div class="form-group full-width" style="border-top: 1px dashed #ccc; padding-top: 15px; margin-top: 10px;">
                    <label>15. Nama Wali (Opsional)</label>
                    <input type="text" name="nama_wali" value="<?= e($nama_wali) ?>">
                </div>
                <div class="form-group full-width">
                    <label>16. Alamat Wali</label>
                    <textarea name="alamat_wali" rows="2"><?= e($alamat_wali) ?></textarea>
                </div>
                <div class="form-group">
                    <label>17. Pekerjaan Wali</label>
                    <input type="text" name="pekerjaan_wali" value="<?= e($pekerjaan_wali) ?>">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="<?= e(base_url('/staff/siswa/index.php')) ?>" class="btn-cancel">Batal</a>
            <button type="submit" class="btn-save">Simpan Perubahan</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
