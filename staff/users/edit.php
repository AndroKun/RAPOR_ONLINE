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
            <div style="position: relative; display: flex; align-items: center;">
                <input type="password" id="inputPasswordEdit" name="password" placeholder="Biarkan kosong jika tidak ingin ganti password" style="padding-right: 44px; width: 100%;">
                <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility('inputPasswordEdit', this)" title="Lihat Kata Sandi" aria-label="Lihat Kata Sandi" style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: var(--ink-soft); padding: 6px; display: flex; align-items: center; justify-content: center; outline: none; border-radius: 6px;">
                    <svg class="icon-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="icon-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                </button>
            </div>
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
            <label>Status Akun</label>
            <select name="is_active">
                <option value="1" <?= $is_active === 1 ? 'selected' : '' ?>>Aktif (Bisa Login)</option>
                <option value="0" <?= $is_active === 0 ? 'selected' : '' ?>>Nonaktif (Tidak Bisa Login)</option>
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

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';

    const eyeIcon = btn.querySelector('.icon-eye');
    const eyeOffIcon = btn.querySelector('.icon-eye-off');
    if (eyeIcon && eyeOffIcon) {
        eyeIcon.style.display = isPassword ? 'none' : 'block';
        eyeOffIcon.style.display = isPassword ? 'block' : 'none';
    }
    btn.setAttribute('title', isPassword ? 'Sembunyikan Kata Sandi' : 'Lihat Kata Sandi');
    btn.setAttribute('aria-label', isPassword ? 'Sembunyikan Kata Sandi' : 'Lihat Kata Sandi');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
