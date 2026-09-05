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
            <input type="password" name="password" placeholder="Minimal 6 karakter" required>
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
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
