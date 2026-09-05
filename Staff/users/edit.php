<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('danger', 'ID Pengguna tidak valid.');
    redirect('/staff/users/index.php');
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$userTarget = $stmt->fetch();

if (!$userTarget) {
    set_flash('danger', 'Pengguna tidak ditemukan.');
    redirect('/staff/users/index.php');
}

$pageTitle = 'Edit Pengguna - ' . $userTarget['nama_lengkap'];
$contentTitle = 'Ubah Akun Staf / Guru';
$activeMenu = 'users';

$errors = [];
$nama_lengkap = $userTarget['nama_lengkap'];
$username = $userTarget['username'];
$role = $userTarget['role'];
$is_active = (int)$userTarget['is_active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'staff';
    $is_active = (int)($_POST['is_active'] ?? 1);

    if ($username === '') $errors[] = 'Username wajib diisi.';
    if ($nama_lengkap === '') $errors[] = 'Nama Lengkap wajib diisi.';
    if (!in_array($role, ['admin', 'staff'], true)) $errors[] = 'Role tidak valid.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u AND id != :id LIMIT 1");
        $stmt->execute(['u' => $username, 'id' => $id]);
        if ($stmt->fetch()) {
            $errors[] = "Username '{$username}' sudah digunakan oleh akun lain.";
        }
    }

    if (empty($errors)) {
        if ($password !== '') {
            if (strlen($password) < 6) {
                $errors[] = 'Password baru minimal 6 karakter.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtUpdate = $pdo->prepare("UPDATE users SET username = :u, password_hash = :p, nama_lengkap = :n, role = :r, is_active = :a WHERE id = :id");
                $stmtUpdate->execute(['u' => $username, 'p' => $hash, 'n' => $nama_lengkap, 'r' => $role, 'a' => $is_active, 'id' => $id]);
            }
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE users SET username = :u, nama_lengkap = :n, role = :r, is_active = :a WHERE id = :id");
            $stmtUpdate->execute(['u' => $username, 'n' => $nama_lengkap, 'r' => $role, 'a' => $is_active, 'id' => $id]);
        }

        if (empty($errors)) {
            set_flash('success', "Akun pengguna {$nama_lengkap} berhasil diperbarui.");
            redirect('/staff/users/index.php');
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

<div class="form-container" style="max-width: 600px;">
    <form action="" method="POST">
        <?= csrf_field() ?>

        <div class="form-group" style="margin-bottom: 20px;">
            <label>Nama Lengkap (beserta Gelar) *</label>
            <input type="text" name="nama_lengkap" value="<?= e($nama_lengkap) ?>" required>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label>Username Login *</label>
            <input type="text" name="username" value="<?= e($username) ?>" required>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label>Kata Sandi Baru (Kosongkan jika tidak diubah)</label>
            <input type="password" name="password" placeholder="Biarkan kosong jika tidak ingin ganti password">
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label>Hak Akses / Role *</label>
            <select name="role" required>
                <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff / Guru (Input Siswa & Nilai)</option>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator (Akses Penuh & Publikasi)</option>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 25px;">
            <label>Status Akun *</label>
            <select name="is_active" required>
                <option value="1" <?= $is_active === 1 ? 'selected' : '' ?>>Aktif (Dapat Login)</option>
                <option value="0" <?= $is_active === 0 ? 'selected' : '' ?>>Nonaktif (Blokir Akses)</option>
            </select>
        </div>

        <div class="form-actions">
            <a href="<?= e(base_url('/staff/users/index.php')) ?>" class="btn-cancel">Batal</a>
            <button type="submit" class="btn-save">Simpan Perubahan</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
