<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$pageTitle = 'Kelola Mata Pelajaran - MTs Roudlotul Qur\'an';
$contentTitle = 'Manajemen Master Mata Pelajaran';
$contentSubtitle = 'Tambah, ubah, dan kelola mata pelajaran kurikulum madrasah.';
$activeMenu = 'mapel';

$errors = [];

// Handle Tambah Mapel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    verify_csrf();

    $namaMapel = trim($_POST['nama_mapel'] ?? '');
    $kelompok = trim($_POST['kelompok'] ?? 'Kelompok B (Umum)');
    $urutan = (int)($_POST['urutan'] ?? 0);

    if ($namaMapel === '') {
        $errors[] = 'Nama mata pelajaran wajib diisi.';
    } else {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE LOWER(nama_mapel) = LOWER(:nm)");
        $stmtCheck->execute(['nm' => $namaMapel]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            $errors[] = "Mata pelajaran '{$namaMapel}' sudah ada di database.";
        } else {
            $stmtUrt = $pdo->query("SELECT COALESCE(MAX(urutan), 0) + 1 FROM subjects");
            $urutan = (int)$stmtUrt->fetchColumn();

            $stmtInsert = $pdo->prepare("INSERT INTO subjects (nama_mapel, kelompok, urutan) VALUES (:nm, :klp, :urt)");
            $stmtInsert->execute(['nm' => $namaMapel, 'klp' => $kelompok, 'urt' => $urutan]);
            set_flash('success', "Mata pelajaran '{$namaMapel}' berhasil ditambahkan ke sistem.");
            redirect('/staff/mapel/index.php');
        }
    }
}

// Handle Hapus Mapel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hapus') {
    verify_csrf();

    $mapelId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($mapelId) {
        $stmtGet = $pdo->prepare("SELECT nama_mapel FROM subjects WHERE id = :id LIMIT 1");
        $stmtGet->execute(['id' => $mapelId]);
        $mapel = $stmtGet->fetch();

        if ($mapel) {
            $stmtDel = $pdo->prepare("DELETE FROM subjects WHERE id = :id");
            $stmtDel->execute(['id' => $mapelId]);
            set_flash('success', "Mata pelajaran '{$mapel['nama_mapel']}' berhasil dihapus.");
            redirect('/staff/mapel/index.php');
        }
    }
}

// Ambil list semua mata pelajaran beserta jumlah guru yang mengampu
$sql = "
    SELECT s.*, 
           (SELECT COUNT(*) FROM users u WHERE u.mata_pelajaran = s.nama_mapel AND u.role = 'staff') as jumlah_guru
    FROM subjects s
    ORDER BY s.urutan ASC, s.nama_mapel ASC
";
$subjects = $pdo->query($sql)->fetchAll();

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

<div style="display: grid; grid-template-columns: 360px 1fr; gap: 24px; align-items: start;">
    <!-- Form Tambah Mata Pelajaran Baru -->
    <div class="card" style="margin-bottom: 0;">
        <h2 class="section-title" style="margin-bottom: 6px;">+ Tambah Mata Pelajaran</h2>
        <p class="section-hint" style="margin-bottom: 20px;">Mata pelajaran baru otomatis muncul di formulir penilaian &amp; akun guru.</p>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="tambah">

            <div class="field" style="margin-bottom: 16px;">
                <label>Nama Mata Pelajaran *</label>
                <input type="text" name="nama_mapel" placeholder="Contoh: Bahasa Sunda / Informatika" required autofocus>
            </div>

            <div class="field" style="margin-bottom: 22px;">
                <label>Kelompok Kurikulum *</label>
                <select name="kelompok" required>
                    <option value="Kelompok A (Agama)">Kelompok A (Pendidikan Agama Islam)</option>
                    <option value="Kelompok B (Umum)" selected>Kelompok B (Mata Pelajaran Umum)</option>
                    <option value="Muatan Lokal">Muatan Lokal / Keterampilan</option>
                    <option value="Tahfidh">Tahfidh / Keagamaan Khusus</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                💾 Tambah Mata Pelajaran
            </button>
        </form>
    </div>

    <!-- Tabel Daftar Mata Pelajaran -->
    <div class="panel-card" style="margin-bottom: 0;">
        <div class="panel-head">
            <h2>Daftar Mata Pelajaran (<?= count($subjects) ?> Total)</h2>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 45px;" class="num">No</th>
                        <th>Nama Mata Pelajaran</th>
                        <th>Kelompok</th>
                        <th class="num" style="width: 110px;">Guru Pengajar</th>
                        <th style="width: 80px;" class="num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: var(--ink-soft);">
                                Belum ada mata pelajaran terdaftar.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($subjects as $sb): ?>
                            <tr>
                                <td class="num" data-label="No"><?= $no++ ?></td>
                                <td class="name" data-label="Mata Pelajaran">
                                    <strong><?= e($sb['nama_mapel']) ?></strong>
                                </td>
                                <td data-label="Kelompok">
                                    <?php if (str_contains($sb['kelompok'], 'Agama')): ?>
                                        <span class="badge badge-green"><?= e($sb['kelompok']) ?></span>
                                    <?php elseif (str_contains($sb['kelompok'], 'Muatan')): ?>
                                        <span class="badge badge-amber"><?= e($sb['kelompok']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?= e($sb['kelompok']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="num" data-label="Guru Pengajar">
                                    <?php if ((int)$sb['jumlah_guru'] > 0): ?>
                                        <span class="badge badge-success"><?= (int)$sb['jumlah_guru'] ?> Guru</span>
                                    <?php else: ?>
                                        <span style="color: var(--ink-soft); font-size: 13px;">–</span>
                                    <?php endif; ?>
                                </td>
                                <td class="num" data-label="Aksi">
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus mata pelajaran \'<?= e($sb['nama_mapel']) ?>\'?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id" value="<?= (int)$sb['id'] ?>">
                                        <button type="submit" class="btn btn-delete btn-sm" title="Hapus Mapel">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media (max-width: 900px) {
    div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
