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

$availableSubjects = get_all_subjects($pdo);

$errors = [];
$nama_lengkap = $userTarget['nama_lengkap'];
$username = $userTarget['username'];
$role = $userTarget['role'];
$mata_pelajaran = $userTarget['mata_pelajaran'] ?? '';
$is_active = (int)$userTarget['is_active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'staff';
    $mata_pelajaran = trim($_POST['mata_pelajaran'] ?? '');
    $is_active = (int)($_POST['is_active'] ?? 1);

    if ($username === '') $errors[] = 'Username wajib diisi.';
    if ($nama_lengkap === '') $errors[] = 'Nama Lengkap wajib diisi.';
    if (!in_array($role, ['admin', 'staff', 'guru_tahfidh'], true)) $errors[] = 'Hak akses / role tidak valid.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u AND id != :id LIMIT 1");
        $stmt->execute(['u' => $username, 'id' => $id]);
        if ($stmt->fetch()) {
            $errors[] = "Username '{$username}' sudah digunakan oleh akun lain.";
        }
    }

    if (empty($errors)) {
        $mapelValue = null;
        if ($role === 'staff' && $mata_pelajaran !== '') {
            $mapelValue = $mata_pelajaran;
        } elseif ($role === 'guru_tahfidh') {
            $mapelValue = 'Tahfidh Al-Qur\'an';
        }

        if ($password !== '') {
            if (strlen($password) < 6) {
                $errors[] = 'Password baru minimal 6 karakter.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtUpdate = $pdo->prepare("UPDATE users SET username = :u, password_hash = :p, nama_lengkap = :n, role = :r, mata_pelajaran = :m, is_active = :a WHERE id = :id");
                $stmtUpdate->execute(['u' => $username, 'p' => $hash, 'n' => $nama_lengkap, 'r' => $role, 'm' => $mapelValue, 'a' => $is_active, 'id' => $id]);
            }
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE users SET username = :u, nama_lengkap = :n, role = :r, mata_pelajaran = :m, is_active = :a WHERE id = :id");
            $stmtUpdate->execute(['u' => $username, 'n' => $nama_lengkap, 'r' => $role, 'm' => $mapelValue, 'a' => $is_active, 'id' => $id]);
        }

        if (empty($errors)) {
            // Update session if editing self
            if ($id === (int)($_SESSION['user']['id'] ?? 0)) {
                $_SESSION['user']['nama_lengkap'] = $nama_lengkap;
                $_SESSION['user']['username'] = $username;
                $_SESSION['user']['role'] = $role;
                $_SESSION['user']['mata_pelajaran'] = $mapelValue;
            }

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

<div class="form-container" style="max-width: 620px;">
    <form action="" method="POST">
        <?= csrf_field() ?>

        <div class="field" style="margin-bottom: 20px;">
            <label>Nama Lengkap (beserta Gelar) *</label>
            <input type="text" name="nama_lengkap" value="<?= e($nama_lengkap) ?>" required>
        </div>

        <div class="field" style="margin-bottom: 20px;">
            <label>Username Login *</label>
            <input type="text" name="username" value="<?= e($username) ?>" required>
        </div>

        <div class="field" style="margin-bottom: 20px;">
            <label>Kata Sandi Baru (Kosongkan jika tidak diubah)</label>
            <input type="password" name="password" placeholder="Biarkan kosong jika tidak ingin ganti password">
        </div>

        <div class="field" style="margin-bottom: 20px;">
            <label>Hak Akses / Role *</label>
            <select name="role" id="roleSelect" onchange="toggleMapelField(this.value)" required>
                <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Guru Mata Pelajaran (Input Nilai Akademik)</option>
                <option value="guru_tahfidh" <?= $role === 'guru_tahfidh' ? 'selected' : '' ?>>Guru Tahfidh Al-Qur'an (Input Nilai Tahfidh)</option>
                <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator (Akses Penuh Semua Menu &amp; Publikasi)</option>
            </select>
        </div>

        <div class="field" id="mapelGroup" style="margin-bottom: 20px; <?= $role !== 'staff' ? 'display:none;' : '' ?>">
            <label>Mata Pelajaran yang Diajar (Khusus Guru Pelajaran)</label>
            <select name="mata_pelajaran">
                <option value="">— Pilih Mata Pelajaran yang Diampu —</option>
                <?php foreach ($availableSubjects as $sub): ?>
                    <option value="<?= e($sub) ?>" <?= $mata_pelajaran === $sub ? 'selected' : '' ?>>
                        <?= e($sub) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="opt" style="font-size: 12.5px; color: var(--ink-soft); margin-top: 4px;">
                Guru pelajaran hanya dapat mengisi nilai mata pelajaran yang dipilih dan tidak dapat melihat menu Tahfidh.
            </span>
        </div>

        <div class="field" style="margin-bottom: 25px;">
            <label>Status Akun *</label>
            <select name="is_active" required>
                <option value="1" <?= $is_active === 1 ? 'selected' : '' ?>>Aktif (Dapat Login)</option>
                <option value="0" <?= $is_active === 0 ? 'selected' : '' ?>>Nonaktif (Blokir Akses)</option>
            </select>
        </div>

        <div class="form-actions">
            <a href="<?= e(base_url('/staff/users/index.php')) ?>" class="btn btn-ghost">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </div>
    </form>
</div>

<script>
function toggleMapelField(role) {
    const mapelGroup = document.getElementById('mapelGroup');
    if (mapelGroup) {
        mapelGroup.style.display = (role === 'staff') ? 'flex' : 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
