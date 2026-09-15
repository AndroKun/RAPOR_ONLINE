<?php

declare(strict_types=1);

require_once __DIR__ . '/fungsi.php';
require_once __DIR__ . '/auth.php';

$activeMenu = $activeMenu ?? 'dashboard';
$user = current_user();
$userRole = $user['role'] ?? 'staff';
$isAdmin = ($userRole === 'admin');
$isWaliKelas = ($userRole === 'wali_kelas');
$isGuruTahfidh = ($userRole === 'guru_tahfidh');
$isGuruArab = ($userRole === 'guru_bahasa_arab');
$isGuruMapel = ($userRole === 'staff');
?>
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <img class="brand-logo" src="<?= e(base_url('/resources/Logo_MTS.png')) ?>" alt="Logo MTs Roudlotul Qur'an">
        <div>
            <div class="brand-name">Yayasan Roudlotul Qur'an Az Zuhri</div>
            <div class="brand-sub">Pon.Pes &amp; MTs Tahfidh Roudlotul Qur'an</div>
        </div>
        <button class="sidebar-close" id="sidebarClose" aria-label="Tutup menu">&times;</button>
    </div>
    
    <ul class="nav">
        <?php if ($isAdmin || $isWaliKelas || $isGuruTahfidh || $isGuruArab || $isGuruMapel === false): ?>
        <li>
            <a href="<?= e(base_url('/staff/dashboard.php')) ?>" class="<?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <rect x="3" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/>
                    <rect x="14" y="3" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/>
                    <rect x="3" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/>
                    <rect x="14" y="14" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.6"/>
                </svg>
                <span>Dashboard Utama</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($isAdmin || $isGuruMapel || $isWaliKelas): ?>
        <li>
            <a href="<?= e(base_url('/staff/nilai/index.php')) ?>" class="<?= in_array($activeMenu, ['nilai', 'inputnilai']) ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M4 5.5A2.5 2.5 0 016.5 3H19v16H6.5A2.5 2.5 0 004 16.5v-11z" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M8 8h7M8 12h7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <span>Input Nilai Akademik</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($isAdmin || $isGuruTahfidh || $isWaliKelas): ?>
        <li>
            <a href="<?= e(base_url('/staff/tahfidh/index.php')) ?>" class="<?= $activeMenu === 'tahfidh' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M14.5 5.5l4 4M5 19l3.5-.8L19.3 7.4a1.8 1.8 0 00-2.5-2.5L6 15.7 5 19z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                    <path d="M12 20H4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <span>Input Nilai Tahfidh</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($isAdmin || $isGuruArab || $isWaliKelas): ?>
        <li>
            <a href="<?= e(base_url('/staff/bahasa_arab/index.php')) ?>" class="<?= in_array($activeMenu, ['bahasa_arab', 'arab']) ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Input Nilai Bahasa Arab</span>
            </a>
        </li>
        <?php endif; ?>

        <li>
            <a href="<?= e(base_url('/staff/rapor/index.php')) ?>" class="<?= $activeMenu === 'rapor' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M6 9V4h12v5" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M6 18H4a1 1 0 01-1-1v-5a2 2 0 012-2h14a2 2 0 012 2v5a1 1 0 01-1 1h-2" stroke="currentColor" stroke-width="1.6"/>
                    <rect x="6" y="14" width="12" height="7" stroke="currentColor" stroke-width="1.6"/>
                </svg>
                <span>Manajemen Cetak PDF</span>
            </a>
        </li>
        <li>
            <a href="<?= e(base_url('/staff/riwayat/index.php')) ?>" class="<?= $activeMenu === 'riwayat' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M12 7v5l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Riwayat Pengisian</span>
            </a>
        </li>

        <?php if ($isAdmin || $isWaliKelas): ?>
        <li>
            <a href="<?= e(base_url('/staff/siswa/index.php')) ?>" class="<?= in_array($activeMenu, ['siswa', 'inputsiswa']) ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M12 12a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M4 20c0-3.5 3.5-6 8-6s8 2.5 8 6" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M19 8v4M17 10h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <span>Kelola Data Siswa</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <li>
            <a href="<?= e(base_url('/staff/mapel/index.php')) ?>" class="<?= $activeMenu === 'mapel' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M4 19.5A2.5 2.5 0 016.5 17H20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M9 6h7M9 10h7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <span>Mata Pelajaran</span>
            </a>
        </li>

        <li>
            <a href="<?= e(base_url('/staff/tahfidh/kategori.php')) ?>" class="<?= $activeMenu === 'kategori_tahfidh' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Target Tahfidh</span>
            </a>
        </li>

        <li>
            <a href="<?= e(base_url('/staff/users/index.php')) ?>" class="<?= $activeMenu === 'users' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="1.6"/>
                    <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="1.6"/>
                </svg>
                <span>Kelola Pengguna</span>
            </a>
        </li>
        <?php endif; ?>

        <li>
            <a href="<?= e(base_url('/publik/index.php')) ?>" target="_blank">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M3.6 9h16.8M3.6 15h16.8M12 3a15.3 15.3 0 014 9 15.3 15.3 0 01-4 9 15.3 15.3 0 01-4-9 15.3 15.3 0 014-9z" stroke="currentColor" stroke-width="1.6"/>
                </svg>
                <span>Portal Publik ↗</span>
            </a>
        </li>
    </ul>

    <div class="logout">
        <a href="<?= e(base_url('/auth/logout.php')) ?>">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
                <path d="M15 17l5-5-5-5M20 12H9M12 19H6a2 2 0 01-2-2V7a2 2 0 012-2h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Keluar (Logout)</span>
        </a>
    </div>
</aside>
