<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Data Siswa - MTs Roudlotul Qur'an</title>
    <link rel="stylesheet" href="desainstaff.css">
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">
            <h2>MTs Roudlotul Qur'an</h2>
            <p style="font-size: 12px; margin-top: 5px;">Portal Staf & Guru</p>
        </div>
        <a href="Dashboard.php" class="menu-item">Dashboard Utama</a>
        <a href="#" class="menu-item active">Input Data Siswa</a>
        <a href="#" class="menu-item">Input Nilai Akademik</a>
        <a href="#" class="menu-item">Input Nilai Tahfidh</a>
        <a href="#" class="menu-item">Manajemen Cetak PDF</a>
        <a href="#" class="menu-item" style="margin-top: auto; border-top: 1px solid #134225;">Keluar (Logout)</a>
    </div>

    <div class="main-content">
        <div class="header-content">
            <h1>Formulir Data Diri Siswa</h1>
            <div class="user-profile">Halo, Admin Staf</div>
        </div>

        <div class="form-container">
            <form action="inputsiswa.php" method="POST">
                
                <div class="form-section">
                    <h3>A. Data Pribadi Siswa</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>1. Nama Siswa</label>
                            <input type="text" placeholder="Contoh: Nalaa Qorin Al Faizin">
                        </div>
                        <div class="form-group">
                            <label>2. Nomor Induk</label>
                            <input type="text" placeholder="Contoh: 250012">
                        </div>
                        <div class="form-group">
                            <label>3. NIS Nasional</label>
                            <input type="text" placeholder="Contoh: 0136347734">
                        </div>
                        <div class="form-group">
                            <label>4. Jenis Kelamin</label>
                            <select>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>5a. Tempat Lahir</label>
                            <input type="text" placeholder="Contoh: Magetan">
                        </div>
                        <div class="form-group">
                            <label>5b. Tanggal Lahir</label>
                            <input type="date">
                        </div>
                        <div class="form-group">
                            <label>6. Agama</label>
                            <input type="text" value="ISLAM" readonly>
                        </div>
                        <div class="form-group">
                            <label>7. Anak Ke</label>
                            <input type="number" placeholder="Contoh: 1">
                        </div>
                        <div class="form-group">
                            <label>8. Status di Keluarga</label>
                            <input type="text" placeholder="Contoh: Anak Kandung">
                        </div>
                        <div class="form-group full-width">
                            <label>9. Alamat Siswa</label>
                            <textarea rows="2" placeholder="Contoh: Kali Tengah Rt 01 Rw 06 Tanggulangin Sidoarjo"></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>B. Riwayat Sekolah</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>10a. Diterima di Kelas</label>
                            <input type="text" placeholder="Contoh: VII (TUJUH)">
                        </div>
                        <div class="form-group">
                            <label>10b. Pada Tanggal</label>
                            <input type="date">
                        </div>
                        <div class="form-group full-width">
                            <label>11a. Nama Sekolah Asal</label>
                            <input type="text" placeholder="Contoh: SD Maarif Nu Ngaban">
                        </div>
                        <div class="form-group full-width">
                            <label>11b. Alamat Sekolah Asal</label>
                            <textarea rows="2" placeholder="Contoh: Ngaban Tanggulangin Sidoarjo"></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>C. Data Orang Tua & Wali</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>12a. Nama Ayah</label>
                            <input type="text" placeholder="Masukkan nama ayah">
                        </div>
                        <div class="form-group">
                            <label>12b. Nama Ibu</label>
                            <input type="text" placeholder="Masukkan nama ibu">
                        </div>
                        <div class="form-group full-width">
                            <label>13. Alamat Orang Tua</label>
                            <textarea rows="2" placeholder="Masukkan alamat lengkap orang tua"></textarea>
                        </div>
                        <div class="form-group">
                            <label>14a. Pekerjaan Ayah</label>
                            <input type="text" placeholder="Contoh: Wiraswasta">
                        </div>
                        <div class="form-group">
                            <label>14b. Pekerjaan Ibu</label>
                            <input type="text" placeholder="Contoh: Ibu Rumah Tangga">
                        </div>
                        <div class="form-group full-width" style="border-top: 1px dashed #ccc; padding-top: 15px; margin-top: 10px;">
                            <label>15. Nama Wali (Opsional)</label>
                            <input type="text" placeholder="Kosongkan jika sama dengan orang tua">
                        </div>
                        <div class="form-group full-width">
                            <label>16. Alamat Wali</label>
                            <textarea rows="2" placeholder="Alamat lengkap wali"></textarea>
                        </div>
                        <div class="form-group">
                            <label>17. Pekerjaan Wali</label>
                            <input type="text" placeholder="Pekerjaan wali">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn-cancel">Kosongkan Form</button>
                    <button type="submit" class="btn-save">Simpan Data Siswa</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>