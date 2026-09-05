# RAPOR ONLINE - MTs ROUDLOTUL QUR'AN

Aplikasi Rapor Online berbasis **PHP Native** dan **MySQL/MariaDB** dengan generator PDF mandiri, sistem autentikasi multi-role (Admin & Staff), dan portal pencarian publik untuk wali murid.

---

## Fitur Utama

1. **Area Publik (Wali Murid & Siswa)**:
   - Pencarian rapor berdasarkan NISN atau Nama Siswa.
   - Hanya menampilkan dan mengizinkan unduh PDF untuk rapor yang berstatus **Published**.
   - Privasi data pribadi terlindungi (alamat dan data orang tua tidak bocor ke publik).
2. **Area Internal (Staff / Guru)**:
   - Dashboard statistik: Total Siswa Aktif, Progress Nilai Masuk, dan Rapor Siap Cetak.
   - Master Data Siswa & Orang Tua/Wali (Form lengkap identitas & riwayat sekolah).
   - Input & Kelola Nilai Akademik per mata pelajaran.
   - Input & Kelola Nilai Tahfidh Al-Qur'an (Surat/Juz, Skor, Catatan Tajwid & Fashahah).
   - Pratinjau (*Preview*) Rapor & Cetak PDF.
3. **Area Administrator**:
   - Semua hak akses staff.
   - Otorisasi Publikasi Rapor (**Publish** / **Unpublish**).
   - Hapus Data Siswa & Nilai.
   - Manajemen Akun Pengguna (Tambah & Kelola Akun Staff/Guru).

---

## Akun Login Bawaan (Default)

| Role | Username | Password | Keterangan |
|---|---|---|---|
| **Administrator** | `admin` | `admin123` | Akses penuh & publikasi |
| **Staff / Guru** | `staff` | `staff123` | Kelola data siswa & nilai |

---

## Cara Instalasi & Menjalankan di XAMPP

1. **Nyalakan Apache & MySQL** di panel XAMPP Control Panel.
2. Buka **phpMyAdmin** (`http://localhost/phpmyadmin/`).
3. Buat database baru bernama `raporonline` (atau database otomatis dibuat saat import).
4. Pilih menu **Import**, lalu pilih file [database/schema.sql](database/schema.sql).
5. Lakukan import berikutnya untuk file [database/seed.sql](database/seed.sql) untuk mengisi data awal.
6. Buka aplikasi di browser:
   - **Melalui XAMPP Apache**: `http://localhost/RAPOR_ONLINE/`
   - **Melalui PHP Built-in Server**:
     ```bash
     php -S localhost:8000 -t .
     ```
     Lalu buka `http://localhost:8000/`.

---

## Struktur Folder

- `assets/css/`: File stylesheet master (`desainstaff.css`, `desainrapor.css`, `auth.css`)
- `auth/`: Halaman login dan pemroses logout
- `config/`: Koneksi PDO MySQL (`koneksi.php`)
- `database/`: Skema DDL (`schema.sql`) dan data seeder (`seed.sql`)
- `includes/`: Autentikasi guard, helper, template header/sidebar/footer, dan FPDF mandiri
- `publik/`: Portal pencarian rapor dan pengunduhan PDF publik
- `staff/`: Dashboard internal serta submodul siswa, nilai, tahfidh, rapor, dan users
- `uploads/rapor/`: Direktori penyimpanan berkas PDF rapor terproteksi