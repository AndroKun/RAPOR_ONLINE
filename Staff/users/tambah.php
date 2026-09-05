<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$pageTitle = 'Tambah Pengguna Baru - MTs Roudlotul Qur\'an';
$contentTitle = 'Tambah Akun Staf / Guru';
$activeMenu = 'users';

$errors = [];
$username = '';
$nama_lengkap = '';
$role = 'staff';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'staff';

    if ($username === '') $errors[] = 'Username wajib diisi.';
    if ($nama_lengkap === '') $errors[] = 'Nama Lengkap wajib diisi.';
    if (strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';
    if (!in_array($role, ['admin', 'staff'], true)) $errors[] = 'Role tidak valid.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
        $stmt->execute(['u' => $username]);
        if ($stmt->fetch()) {
            $errors[] = "Username '{$username}' sudah digunakan. Silakan pilih username lain.";
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, nama_lengkap, role, is_active) VALUES (:u, :p, :n, :r, 1)");
        $stmt->execute([
            'u' => $username,
            'p' => $hash,
            'n' => $nama_lengkap,
            'r' => $role,
        ]);

        set_flash('success', "Akun pengguna {$nama_lengkap} ({$username}) berhasil dibuat.");
        redirect('/staff/users/index.php');
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
            <input type="text" name="nama_lengkap" value="<?= e($nama_lengkap) ?>" placeholder="Contoh: Ustadz M. Zainuddin, S.Pd.I" required>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label>Username Login *</label>
            <input type="text" name="username" value="<?= e($username) ?>" placeholder="Contoh: zainuddin" required>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label>Kata Sandi (Password) *</label>
            <input type="password" name="password" placeholder="Minimal 6 karakter" required>
        </div>

        <div class="form-group" style="margin-bottom: 25px;">
            <label>Hak Akses / Role *</label>
            <select name="role" required>
                <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff / Guru (Input Siswa & Nilai)</option>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator (Akses Penuh & Publikasi)</option>
            </select>
        </div>

        <div class="form-actions">
            <a href="<?= e(base_url('/staff/users/index.php')) ?>" class="btn-cancel">Batal</a>
            <button type="submit" class="btn-save">Simpan Akun</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
