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

$pageTitle = 'Profil Siswa - ' . $student['nama'];
$contentTitle = 'Detail Profil Siswa';
$activeMenu = 'siswa';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="data-section">
    <div class="data-header">
        <h2><?= e($student['nama']) ?> (Kelas <?= e($student['kelas']) ?>)</h2>
        <div style="display:flex; gap:10px;">
            <a href="<?= e(base_url('/staff/siswa/edit.php?id=' . (int)$student['id'])) ?>" class="btn btn-primary">✏ Edit Profil</a>
            <a href="<?= e(base_url('/staff/nilai/input.php?student_id=' . (int)$student['id'])) ?>" class="btn btn-secondary">📝 Nilai Akademik</a>
            <a href="<?= e(base_url('/staff/tahfidh/input.php?student_id=' . (int)$student['id'])) ?>" class="btn btn-secondary">📖 Nilai Tahfidh</a>
            <a href="<?= e(base_url('/staff/siswa/index.php')) ?>" class="btn btn-secondary">Kembali</a>
        </div>
    </div>

    <!-- Info Grid -->
    <div class="form-container" style="box-shadow:none; padding:10px 0;">
        <div class="form-section">
            <h3>A. Data Pribadi Siswa</h3>
            <div class="form-grid">
                <div><strong>Nama Lengkap:</strong> <?= e($student['nama']) ?></div>
                <div><strong>Nomor Induk (NIS):</strong> <?= e($student['nis']) ?></div>
                <div><strong>NISN:</strong> <?= e($student['nisn']) ?></div>
                <div><strong>Jenis Kelamin:</strong> <?= $student['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></div>
                <div><strong>Tempat, Tanggal Lahir:</strong> <?= e($student['tempat_lahir'] . ', ' . date('d/m/Y', strtotime($student['tanggal_lahir']))) ?></div>
                <div><strong>Agama:</strong> <?= e($student['agama']) ?></div>
                <div><strong>Anak Ke / Status:</strong> <?= e((string)($student['anak_ke'] ?? '-')) ?> / <?= e($student['status_keluarga'] ?? '-') ?></div>
                <div class="full-width"><strong>Alamat Siswa:</strong> <?= e($student['alamat']) ?></div>
            </div>
        </div>

        <div class="form-section">
            <h3>B. Riwayat Sekolah</h3>
            <div class="form-grid">
                <div><strong>Diterima di Kelas:</strong> <?= e($student['kelas']) ?></div>
                <div><strong>Tanggal Diterima:</strong> <?= !empty($student['tanggal_diterima']) ? date('d/m/Y', strtotime($student['tanggal_diterima'])) : '-' ?></div>
                <div><strong>Nama Sekolah Asal:</strong> <?= e($student['sekolah_asal'] ?? '-') ?></div>
                <div><strong>Alamat Sekolah Asal:</strong> <?= e($student['alamat_sekolah_asal'] ?? '-') ?></div>
            </div>
        </div>

        <div class="form-section">
            <h3>C. Data Orang Tua & Wali</h3>
            <div class="form-grid">
                <div><strong>Nama Ayah:</strong> <?= e($student['nama_ayah'] ?? '-') ?></div>
                <div><strong>Pekerjaan Ayah:</strong> <?= e($student['pekerjaan_ayah'] ?? '-') ?></div>
                <div><strong>Nama Ibu:</strong> <?= e($student['nama_ibu'] ?? '-') ?></div>
                <div><strong>Pekerjaan Ibu:</strong> <?= e($student['pekerjaan_ibu'] ?? '-') ?></div>
                <div class="full-width"><strong>Alamat Orang Tua:</strong> <?= e($student['alamat_orang_tua'] ?? '-') ?></div>
                <?php if (!empty($student['nama_wali'])): ?>
                    <div><strong>Nama Wali:</strong> <?= e($student['nama_wali']) ?></div>
                    <div><strong>Pekerjaan Wali:</strong> <?= e($student['pekerjaan_wali'] ?? '-') ?></div>
                    <div class="full-width"><strong>Alamat Wali:</strong> <?= e($student['alamat_wali'] ?? '-') ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
