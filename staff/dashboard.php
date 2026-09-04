<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Dashboard Staff';
require_once __DIR__ . '/../includes/header.php';
?>
<main>
    <h1>Dashboard Staff</h1>
    <p>Kerangka dashboard internal. Statistik dan data siswa akan dihubungkan ke database.</p>
    <nav>
        <a href="/staff/siswa/">Data Siswa</a>
        <a href="/staff/nilai/">Nilai Akademik</a>
        <a href="/staff/tahfidh/">Nilai Tahfidh</a>
        <a href="/staff/rapor/">Manajemen Rapor</a>
        <a href="/auth/logout.php">Keluar</a>
    </nav>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
