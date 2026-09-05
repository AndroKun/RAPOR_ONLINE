<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$pageTitle = 'Kategori & Target Tahfidh - MTs Roudlotul Qur\'an';
$contentTitle = 'Manajemen Target & Kategori Tahfidh';
$contentSubtitle = 'Kelola master target capaian hafalan Al-Qur\'an, doa, dan hadits untuk penilaian guru tahfidh.';
$activeMenu = 'kategori_tahfidh';

$errors = [];

// Handle Tambah Kategori Tahfidh
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    verify_csrf();

    $namaKategori = trim($_POST['nama_kategori'] ?? '');
    $kelompok = trim($_POST['kelompok'] ?? 'Hafalan Juz');

    if ($namaKategori === '') {
        $errors[] = 'Nama target/kategori tahfidh wajib diisi.';
    } else {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM tahfidh_categories WHERE LOWER(nama_kategori) = LOWER(:nm)");
        $stmtCheck->execute(['nm' => $namaKategori]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            $errors[] = "Target/kategori tahfidh '{$namaKategori}' sudah ada di database.";
        } else {
            // Hitung nomor urut otomatis
            $stmtUrt = $pdo->query("SELECT COALESCE(MAX(urutan), 0) + 1 FROM tahfidh_categories");
            $nextUrutan = (int)$stmtUrt->fetchColumn();

            $stmtInsert = $pdo->prepare("INSERT INTO tahfidh_categories (nama_kategori, kelompok, urutan) VALUES (:nm, :klp, :urt)");
            $stmtInsert->execute(['nm' => $namaKategori, 'klp' => $kelompok, 'urt' => $nextUrutan]);
            
            set_flash('success', "Target tahfidh '{$namaKategori}' berhasil ditambahkan.");
            redirect('/staff/tahfidh/kategori.php');
        }
    }
}

// Handle Hapus Kategori Tahfidh
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hapus') {
    verify_csrf();

    $kategoriId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($kategoriId) {
        $stmtGet = $pdo->prepare("SELECT nama_kategori FROM tahfidh_categories WHERE id = :id LIMIT 1");
        $stmtGet->execute(['id' => $kategoriId]);
        $kat = $stmtGet->fetch();

        if ($kat) {
            $stmtDel = $pdo->prepare("DELETE FROM tahfidh_categories WHERE id = :id");
            $stmtDel->execute(['id' => $kategoriId]);
            set_flash('success', "Target tahfidh '{$kat['nama_kategori']}' berhasil dihapus.");
            redirect('/staff/tahfidh/kategori.php');
        }
    }
}

// Ambil list semua kategori tahfidh beserta total penilaian tersimpan
$sql = "
    SELECT tc.*,
           (SELECT COUNT(*) FROM tahfidh_grades tg WHERE tg.memorization = tc.nama_kategori) as jumlah_nilai
    FROM tahfidh_categories tc
    ORDER BY tc.urutan ASC, tc.nama_kategori ASC
";
$categories = $pdo->query($sql)->fetchAll();

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
    <!-- Form Tambah Target Baru -->
    <div class="card" style="margin-bottom: 0;">
        <h2 class="section-title" style="margin-bottom: 6px;">+ Tambah Target Tahfidh</h2>
        <p class="section-hint" style="margin-bottom: 20px;">Target yang ditambahkan otomatis tersedia pada formulir penilaian Guru Tahfidh.</p>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="tambah">

            <div class="field" style="margin-bottom: 16px;">
                <label>Nama Target / Surat / Juz *</label>
                <input type="text" name="nama_kategori" placeholder="Contoh: Juz 28 (Al-Mujadilah s.d At-Tahrim)" required autofocus>
            </div>

            <div class="field" style="margin-bottom: 22px;">
                <label>Kelompok / Jenis Penilaian *</label>
                <select name="kelompok" required>
                    <option value="Hafalan Juz">Hafalan Juz (Al-Qur'an)</option>
                    <option value="Surat Pilihan">Surat Pilihan</option>
                    <option value="Doa & Dzikir">Doa Harian &amp; Dzikir</option>
                    <option value="Hadits Pilihan">Hadits-hadits Pilihan</option>
                    <option value="Tajwid & Makhraj">Tajwid, Fashahah &amp; Makhraj</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                💾 Tambah Target Tahfidh
            </button>
        </form>
    </div>

    <!-- Tabel Daftar Target Tahfidh -->
    <div class="panel-card" style="margin-bottom: 0;">
        <div class="panel-head">
            <h2>Daftar Target Penilaian Tahfidh (<?= count($categories) ?> Total)</h2>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 45px;" class="num">No</th>
                        <th>Target Hafalan / Materi</th>
                        <th>Kelompok</th>
                        <th class="num" style="width: 120px;">Penilaian Terisi</th>
                        <th style="width: 80px;" class="num">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: var(--ink-soft);">
                                Belum ada target tahfidh terdaftar. Silakan tambahkan formulir di sebelah kiri.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($categories as $cat): ?>
                            <tr>
                                <td class="num" data-label="No"><?= $no++ ?></td>
                                <td class="name" data-label="Target Hafalan">
                                    <strong>📖 <?= e($cat['nama_kategori']) ?></strong>
                                </td>
                                <td data-label="Kelompok">
                                    <?php if (str_contains($cat['kelompok'], 'Juz')): ?>
                                        <span class="badge badge-green"><?= e($cat['kelompok']) ?></span>
                                    <?php elseif (str_contains($cat['kelompok'], 'Surat')): ?>
                                        <span class="badge badge-amber"><?= e($cat['kelompok']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary"><?= e($cat['kelompok']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="num" data-label="Penilaian Terisi">
                                    <?php if ((int)$cat['jumlah_nilai'] > 0): ?>
                                        <span class="badge badge-success"><?= (int)$cat['jumlah_nilai'] ?> Nilai</span>
                                    <?php else: ?>
                                        <span style="color: var(--ink-soft); font-size: 13px;">Belum Ada</span>
                                    <?php endif; ?>
                                </td>
                                <td class="num" data-label="Aksi">
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus target \'<?= e($cat['nama_kategori']) ?>\'?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                        <button type="submit" class="btn btn-delete btn-sm" title="Hapus Target">Hapus</button>
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
