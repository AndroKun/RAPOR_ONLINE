<?php

declare(strict_types=1);

require_once __DIR__ . '/fungsi.php';
require_once __DIR__ . '/auth.php';

$activeMenu = $activeMenu ?? 'dashboard';
$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h2>MTs Roudlotul Qur'an</h2>
        <p>Portal Staf & Guru</p>
    </div>
    <div class="sidebar-menu">
        <a href="<?= e(base_url('/staff/dashboard.php')) ?>" class="menu-item <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
            <span>🏠 Dashboard Utama</span>
        </a>
        <a href="<?= e(base_url('/staff/siswa/index.php')) ?>" class="menu-item <?= $activeMenu === 'siswa' ? 'active' : '' ?>">
            <span>👨‍🎓 Data Siswa</span>
        </a>
        <a href="<?= e(base_url('/staff/nilai/index.php')) ?>" class="menu-item <?= $activeMenu === 'nilai' ? 'active' : '' ?>">
            <span>📝 Nilai Akademik</span>
        </a>
        <a href="<?= e(base_url('/staff/tahfidh/index.php')) ?>" class="menu-item <?= $activeMenu === 'tahfidh' ? 'active' : '' ?>">
            <span>📖 Nilai Tahfidh</span>
        </a>
        <a href="<?= e(base_url('/staff/rapor/index.php')) ?>" class="menu-item <?= $activeMenu === 'rapor' ? 'active' : '' ?>">
            <span>📄 Manajemen Rapor (PDF)</span>
        </a>
        <?php if ($isAdmin): ?>
            <a href="<?= e(base_url('/staff/users/index.php')) ?>" class="menu-item <?= $activeMenu === 'users' ? 'active' : '' ?>">
                <span>👥 Kelola Pengguna</span>
            </a>
        <?php endif; ?>
        <a href="<?= e(base_url('/publik/index.php')) ?>" target="_blank" class="menu-item">
            <span>🌐 Lihat Portal Publik ↗</span>
        </a>
        <a href="<?= e(base_url('/auth/logout.php')) ?>" class="menu-item menu-logout">
            <span>🚪 Keluar (Logout)</span>
        </a>
    </div>
</div>
