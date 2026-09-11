<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$pageTitle = 'Tambah Pengguna Baru - MTs Roudlotul Qur\'an';
$contentTitle = 'Tambah Akun Staf / Guru';
$activeMenu = 'users';

$availableSubjects = get_all_subjects($pdo);

$errors = [];
$username = '';
$nama_lengkap = '';
$role = 'staff';
$mata_pelajaran = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'staff';
    $mata_pelajaran = trim($_POST['mata_pelajaran'] ?? '');

    if ($username === '') $errors[] = 'Username wajib diisi.';
    if ($nama_lengkap === '') $errors[] = 'Nama Lengkap wajib diisi.';
    if (strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';
    if (!in_array($role, ['admin', 'staff', 'guru_tahfidh'], true)) $errors[] = 'Hak akses / role tidak valid.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
        $stmt->execute(['u' => $username]);
        if ($stmt->fetch()) {
            $errors[] = "Username '{$username}' sudah digunakan. Silakan pilih username lain.";
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $mapelToSave = null;
        if ($role === 'staff' && $mata_pelajaran !== '') {
            $mapelToSave = $mata_pelajaran;
        } elseif ($role === 'guru_tahfidh') {
            $mapelToSave = 'Tahfidh Al-Qur\'an';
        }

        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, nama_lengkap, role, mata_pelajaran, is_active) VALUES (:u, :p, :n, :r, :m, 1)");
        $stmt->execute([
            'u' => $username,
            'p' => $hash,
            'n' => $nama_lengkap,
            'r' => $role,
            'm' => $mapelToSave,
        ]);

        set_flash('success', "Akun pengguna {$nama_lengkap} ({$username}) berhasil dibuat dan langsung aktif.");
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

<div class="form-container" style="max-width: 620px;">
    <form action="" method="POST">
        <?= csrf_field() ?>

        <div class="field" style="margin-bottom: 20px;">
            <label>Nama Lengkap Guru (beserta Gelar) *</label>
            <input type="text" name="nama_lengkap" value="<?= e($nama_lengkap) ?>" placeholder="Contoh: Ustadzah Siti Fatimah, S.Pd" required>
        </div>

        <div class="field" style="margin-bottom: 20px;">
            <label>Username Login *</label>
            <input type="text" name="username" value="<?= e($username) ?>" placeholder="Contoh: guru_tahfidh / guru_bahasa" required>
        </div>

        <div class="field" style="margin-bottom: 20px;">
            <label>Kata Sandi (Password) *</label>
            <div style="position: relative; display: flex; align-items: center;">
                <input type="password" id="inputPassword" name="password" placeholder="Minimal 6 karakter" required style="padding-right: 44px; width: 100%;">
                <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility('inputPassword', this)" title="Lihat Kata Sandi" aria-label="Lihat Kata Sandi" style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: var(--ink-soft); padding: 6px; display: flex; align-items: center; justify-content: center; outline: none; border-radius: 6px;">
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

        <div class="field" id="mapelGroup" style="margin-bottom: 25px; <?= $role !== 'staff' ? 'display:none;' : '' ?>">
            <label>Mata Pelajaran yang Diajar (Khusus Guru Pelajaran) *</label>
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

        <div class="form-actions">
            <a href="<?= e(base_url('/staff/users/index.php')) ?>" class="btn btn-ghost">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Akun Guru</button>
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
