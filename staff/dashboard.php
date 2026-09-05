<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Staf - MTs Roudlotul Qur'an</title>
    <link rel="stylesheet" href="desainstaff.css">
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">
            <h2>MTs Roudlotul Qur'an</h2>
            <p style="font-size: 12px; margin-top: 5px;">Portal Staf & Guru</p>
        </div>
        <a href="#" class="menu-item active">Dashboard Utama</a>
        <a href="inputsiswa.php" class="menu-item">Input Data Siswa</a>
        <a href="#" class="menu-item">Input Nilai Akademik</a>
        <a href="#" class="menu-item">Input Nilai Tahfidh</a>
        <a href="#" class="menu-item">Manajemen Cetak PDF</a>
        <a href="#" class="menu-item" style="margin-top: auto; border-top: 1px solid #134225;">Keluar (Logout)</a>
    </div>

    <div class="main-content">
        <div class="header-content">
            <h1>Database Rapor Genap 2025/2026</h1>
            <div class="user-profile">Halo, Admin Staf</div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Siswa Aktif</h3>
                <p>125 Siswa</p>
            </div>
            <div class="stat-card">
                <h3>Nilai Masuk (Progress)</h3>
                <p>85%</p>
            </div>
            <div class="stat-card">
                <h3>Rapor Siap Cetak (PDF)</h3>
                <p>42 Dokumen</p>
            </div>
        </div>

        <div class="data-section">
            <div class="data-header">
                <h2>Data Nilai Siswa Kelas VII</h2>
                <a href="inputsiswa.php" class="btn-add">+ Tambah Data Baru</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>NISN</th>
                        <th>Nama Lengkap</th>
                        <th>Kelas</th>
                        <th>Status Nilai</th>
                        <th>Aksi / Edit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>0136347734</td>
                        <td>Nalaa Qorin Al Faizin</td>
                        <td>VII</td>
                        <td><span style="color: green; font-weight: bold;">Lengkap</span></td>
                        <td>
                            <button class="action-btn btn-edit">Edit Nilai</button>
                            <button class="action-btn btn-pdf">Cetak PDF</button>
                        </td>
                    </tr>
                    <tr>
                        <td>0136347735</td>
                        <td>Contoh Nama Siswa 2</td>
                        <td>VII</td>
                        <td><span style="color: orange; font-weight: bold;">Tahfidh Kosong</span></td>
                        <td>
                            <button class="action-btn btn-edit">Edit Nilai</button>
                            <button class="action-btn btn-pdf" style="opacity: 0.5; cursor: not-allowed;">Cetak PDF</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>