<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$pageTitle = 'Data Siswa - MTs Roudlotul Qur\'an';
$contentTitle = 'Manajemen Master Data Siswa';
$activeMenu = 'siswa';

$search = trim($_GET['q'] ?? '');
$kelasFilter = trim($_GET['kelas'] ?? '');

$sql = "SELECT s.*, g.nama_ayah, g.nama_ibu FROM students s 
        LEFT JOIN guardians g ON g.student_id = s.id WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (s.nama LIKE :q OR s.nis LIKE :q OR s.nisn LIKE :q)";
    $params['q'] = "%{$search}%";
}

if ($kelasFilter !== '') {
    $sql .= " AND s.kelas = :kelas";
    $params['kelas'] = $kelasFilter;
}

$sql .= " ORDER BY s.kelas ASC, s.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// List kelas
$stmtKelas = $pdo->query("SELECT DISTINCT kelas FROM students ORDER BY kelas ASC");
$kelasList = $stmtKelas->fetchAll(PDO::FETCH_COLUMN);

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="data-section">
    <div class="data-header">
        <h2>Daftar Siswa Terdaftar</h2>
        <a href="<?= e(base_url('/staff/siswa/tambah.php')) ?>" class="btn btn-add">+ Tambah Siswa Baru</a>
    </div>

    <!-- Filter & Search Bar -->
    <form method="GET" action="" class="filter-bar">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari NISN / NIS / Nama Siswa..." style="min-width: 250px;">
        <select name="kelas" onchange="this.form.submit()">
            <option value="">-- Semua Kelas --</option>
            <?php foreach ($kelasList as $k): ?>
                <option value="<?= e($k) ?>" <?= $kelasFilter === $k ? 'selected' : '' ?>>Kelas <?= e($k) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary">Cari</button>
        <?php if ($search !== '' || $kelasFilter !== ''): ?>
            <a href="<?= e(base_url('/staff/siswa/index.php')) ?>" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>NIS</th>
                    <th>NISN</th>
                    <th>Nama Lengkap</th>
                    <th>L/P</th>
                    <th>Kelas</th>
                    <th>Nama Orang Tua / Wali</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 25px; color: #777;">
                            Tidak ditemukan data siswa.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($students as $st): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= e($st['nis']) ?></td>
                            <td><strong><?= e($st['nisn']) ?></strong></td>
                            <td><?= e($st['nama']) ?></td>
                            <td><?= e($st['jenis_kelamin']) ?></td>
                            <td><span class="badge badge-secondary"><?= e($st['kelas']) ?></span></td>
                            <td>
                                <?= e($st['nama_ayah'] ?: ($st['nama_ibu'] ?: '-')) ?>
                            </td>
                            <td>
                                <a href="<?= e(base_url('/staff/siswa/detail.php?id=' . (int)$st['id'])) ?>" class="action-btn btn-detail">Detail</a>
                                <a href="<?= e(base_url('/staff/siswa/edit.php?id=' . (int)$st['id'])) ?>" class="action-btn btn-edit">Edit</a>
                                <?php if ($isAdmin): ?>
                                    <form method="POST" action="<?= e(base_url('/staff/siswa/hapus.php')) ?>" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data siswa <?= e($st['nama']) ?> beserta seluruh nilainya?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$st['id'] ?>">
                                        <button type="submit" class="action-btn btn-delete">Hapus</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
