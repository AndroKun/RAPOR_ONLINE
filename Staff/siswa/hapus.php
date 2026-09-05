<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $pdo->prepare("SELECT nama FROM students WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $st = $stmt->fetch();

        if ($st) {
            $stmtDel = $pdo->prepare("DELETE FROM students WHERE id = :id");
            $stmtDel->execute(['id' => $id]);
            set_flash('success', "Data siswa {$st['nama']} berhasil dihapus.");
        } else {
            set_flash('danger', 'Data siswa tidak ditemukan.');
        }
    }
}

redirect('/staff/siswa/index.php');
