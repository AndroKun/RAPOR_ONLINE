<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Manajemen User';
require_once __DIR__ . '/../../includes/header.php';
?>
<main><h1>Manajemen User</h1><p>Modul pengelolaan akun staff akan diimplementasikan di tahap berikutnya.</p></main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
