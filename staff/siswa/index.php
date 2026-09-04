<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_login();

$pageTitle = 'Data Siswa';
require_once __DIR__ . '/../../includes/header.php';
?>
<main><h1>Data Siswa</h1><p>Modul daftar, tambah, dan edit siswa akan diimplementasikan di tahap berikutnya.</p></main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
