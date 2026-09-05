<?php

declare(strict_types=1);

require_once __DIR__ . '/fungsi.php';
require_once __DIR__ . '/auth.php';

$activeMenu = $activeMenu ?? 'dashboard';
$user = current_user();
$userRole = $user['role'] ?? 'staff';
$isAdmin = ($userRole === 'admin');
$isGuruTahfidh = ($userRole === 'guru_tahfidh');
$isGuruMapel = ($userRole === 'staff');
?>
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div style="display: flex; align-items: center; gap: 11px;">
            <div style="width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.15); border: 1.5px solid var(--gold-500); display: flex; align-items: center; justify-content: center; color: var(--gold-500); font-size: 16px; flex-shrink: 0;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <rect x="4" y="4" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </div>
            <div>
                <div class="brand-name" style="font-size: 14.5px; font-weight: 800; color: #FFFFFF; line-height: 1.25;">MTs Tahfidh<br>Roudlotul Qur'an</div>
                <div class="brand-sub" style="font-size: 11px; color: #A7E0BE; margin-top: 2px;">Portal Staf &amp; Guru</div>
            </div>
        </div>
        <button class="sidebar-close" id="sidebarClose" aria-label="Tutup menu">&times;</button>
    </div>
    
    <ul class="nav">
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

        <?php if ($isAdmin || $isGuruMapel): ?>
        <li>
            <a href="<?= e(base_url('/staff/nilai/input.php')) ?>" class="<?= in_array($activeMenu, ['nilai', 'inputnilai']) ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M4 5.5A2.5 2.5 0 016.5 3H19v16H6.5A2.5 2.5 0 004 16.5v-11z" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M8 8h7M8 12h7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <span>Input Nilai Akademik</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($isAdmin || $isGuruTahfidh): ?>
        <li>
            <a href="<?= e(base_url('/staff/tahfidh/index.php')) ?>" class="<?= $activeMenu === 'tahfidh' ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M12 3a3 3 0 013 3v6a3 3 0 01-6 0V6a3 3 0 013-3z" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M6 11a6 6 0 0012 0M12 17v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <span>Input Nilai Tahfidh</span>
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

        <?php if ($isAdmin): ?>
        <li>
            <a href="<?= e(base_url('/staff/inputsiswa.php')) ?>" class="<?= in_array($activeMenu, ['siswa', 'inputsiswa']) ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M12 12a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M4 20c0-3.5 3.5-6 8-6s8 2.5 8 6" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M19 8v4M17 10h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                <span>Input Data Siswa</span>
            </a>
        </li>
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
