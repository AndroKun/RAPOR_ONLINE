<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';

require_role('admin');

$pageTitle = 'Kelola Pengguna - MTs Roudlotul Qur\'an';
$contentTitle = 'Manajemen Akun Staf & Guru';
$activeMenu = 'users';

$stmt = $pdo->query("SELECT id, username, nama_lengkap, role, mata_pelajaran, is_active, created_at FROM users ORDER BY role ASC, nama_lengkap ASC");
$users = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="data-section">
    <div class="data-header">
        <h2>Daftar Akun Pengguna Internal</h2>
        <a href="<?= e(base_url('/staff/users/tambah.php')) ?>" class="btn btn-add">+ Tambah Akun Baru</a>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Lengkap</th>
                    <th>Username</th>
                    <th>Hak Akses / Role</th>
                    <th>Mata Pelajaran yang Diajar</th>
                    <th>Status Akun</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($users as $u): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><strong><?= e($u['nama_lengkap']) ?></strong></td>
                        <td><code><?= e($u['username']) ?></code></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge badge-danger">ADMINISTRATOR</span>
                            <?php elseif ($u['role'] === 'guru_tahfidh'): ?>
                                <span class="badge badge-success">GURU TAHFIDH</span>
                            <?php else: ?>
                                <span class="badge badge-info">GURU MAPEL</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($u['mata_pelajaran'])): ?>
                                <span class="badge badge-success">📘 <?= e($u['mata_pelajaran']) ?></span>
                            <?php elseif ($u['role'] === 'admin'): ?>
                                <span class="badge badge-secondary">Semua Mapel (Admin)</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Belum Diatur</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$u['is_active'] === 1): ?>
                                <span class="badge badge-success">AKTIF</span>
                            <?php else: ?>
                                <span class="badge badge-danger">NONAKTIF</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= e(base_url('/staff/users/edit.php?id=' . (int)$u['id'])) ?>" class="action-btn btn-edit">Edit Akun</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
