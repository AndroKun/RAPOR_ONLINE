# Implementation Plan - RAPOR ONLINE (MTs Roudlotul Qur'an)

## 1. Tujuan

Membangun aplikasi Rapor Online berbasis web dinamis untuk MTs Roudlotul Qur'an dari template statis yang tersedia, dengan membagi akses menjadi 2 area utama:

1. **Area Internal Staff & Admin** (Wajib Login):
   - Role `staff`: Mengelola data siswa, input nilai akademik, input hafalan/tahfidh, serta melihat status kelengkapan rapor.
   - Role `admin`: Memiliki semua hak akses staff ditambah manajemen akun pengguna (users), penghapusan data master, dan otorisasi publikasi rapor (`published`).
2. **Area Publik** (Tanpa Login):
   - Dapat diakses oleh wali murid dan masyarakat umum.
   - Pencarian berdasarkan NISN atau Nama Siswa.
   - **Hanya menampilkan dan mengizinkan unduh PDF** untuk rapor yang sudah berstatus `published`. Data pribadi sensitif (alamat lengkap, tanggal lahir, kontak orang tua/wali) tetap terlindungi dan tidak ditampilkan ke publik.

Aplikasi dibangun menggunakan **PHP 8.2+ Native**, **MySQL/MariaDB**, **PDO**, **HTML5**, dan **CSS murni** (berdasarkan `Staff/desainstaff.css` dan `Wali dan Murid/desainrapor.css`). Kompatibel dengan lingkungan XAMPP standar (`http://localhost/RAPOR_ONLINE/`) maupun virtual host / built-in PHP server (`http://localhost:8000/`).

---

## 2. Analisis Kondisi Awal & Masalah yang Diperbaiki

- **File Statis Eksisting**:
  - `Staff/Dashboard.html` & `Staff/desainstaff.css`: UI Dashboard internal dengan sidebar, stat card (Total Siswa, Progress Nilai, Rapor Siap Cetak), dan tabel data siswa.
  - `Staff/inputsiswa.html`: Formulir lengkap identitas siswa, riwayat sekolah, dan data orang tua/wali.
  - `Wali dan Murid/Dashboardrapor.html` & `Wali dan Murid/desainrapor.css`: Portal publik dengan header hijau, form pencarian NISN/Nama, dan result card rapor.
- **Masalah/Koreksi dari Plan Sebelumnya**:
  1. **Penanganan Base URL (XAMPP Subfolder vs Virtual Host)**: Plan sebelumnya menggunakan path absolut root (misal `/staff/dashboard.php`), yang menyebabkan error 404 pada instalasi default XAMPP (`http://localhost/RAPOR_ONLINE/`). Ditambahkan helper `base_url()` dan konstanta path yang dinamis.
  2. **PDF Generation Tanpa Dependensi CLI Composer**: Lingkungan lokal XAMPP tidak memiliki PHP Composer pada PATH bawaan. Digunakan standalone FPDF / library PDF mandiri (atau template printable HTML to PDF) yang diletakkan pada `includes/fpdf/` sehingga tidak memerlukan `composer require` dari terminal.
  3. **Penataan Struktur & Case-Sensitivity Folder**: Penyeragaman rute internal ke `staff/` (atau alias case-insensitive yang konsisten) dan pemindahan stylesheet ke `assets/css/` agar tata letak sidebar & branding MTs Roudlotul Qur'an tetap konsisten di semua halaman.
  4. **Konsistensi Penamaan Fungsi**: Penyeragaman fungsi autentikasi (`require_login()`, `require_role()`, `current_user()`) dan helper keamanan CSRF (`csrf_token()`, `csrf_field()`, `verify_csrf()`).
  5. **Kelengkapan Route & Rincian CRUD**: Menambahkan rute form tambah, edit, detail, hapus, bulk input nilai, preview rapor, dan manajemen user admin.
  6. **Integritas Constraint Database**: Penambahan indeks & unique key pada `tahfidh_grades` per periode agar tidak terjadi duplikasi hafalan untuk siswa dan semester yang sama.

---

## 3. Struktur Target Proyek

```text
RAPOR_ONLINE/
├── assets/
│   ├── css/
│   │   ├── desainstaff.css     # CSS untuk area Staff & Admin (Sidebar, Table, Form, Cards)
│   │   ├── desainrapor.css     # CSS untuk area Publik Portal Rapor
│   │   └── auth.css            # CSS untuk halaman Login & Error
│   └── img/
│       └── logo.png            # Logo & icon sekolah
├── auth/
│   ├── login.php               # Halaman & pemroses login staff/admin
│   └── logout.php              # Pemroses logout & penghancur session
├── config/
│   └── koneksi.php             # Koneksi database PDO MySQL (dukungan ENV & fallback XAMPP)
├── database/
│   ├── schema.sql              # Struktur tabel DDL lengkap
│   └── seed.sql                # Data awal (Akun Admin, Staff, & Data Siswa Demo)
├── includes/
│   ├── auth.php                # Session guard: require_login(), require_role(), current_user()
│   ├── fungsi.php              # Helper: e(), base_url(), redirect(), flash_message(), csrf_*()
│   ├── header.php              # Header & pemanggilan CSS
│   ├── sidebar.php             # Komponen sidebar navigasi staff/admin
│   ├── footer.php              # Footer template
│   └── fpdf/                   # Library generator PDF mandiri (tanpa Composer CLI)
├── publik/
│   ├── index.php               # Redirect atau landing page portal
│   ├── rapor.php               # Pencarian rapor siswa berstatus 'published'
│   └── download.php            # Endpoint aman pengunduhan PDF rapor
├── staff/
│   ├── index.php               # Redirect ke dashboard.php
│   ├── dashboard.php           # Dashboard statistik siswa, progress nilai, & kelengkapan rapor
│   ├── siswa/
│   │   ├── index.php           # Daftar master siswa & pagination/search
│   │   ├── tambah.php          # Form tambah data siswa & orang tua/wali
│   │   ├── edit.php            # Form edit data siswa
│   │   ├── detail.php          # Detail profil siswa lengkap
│   │   └── hapus.php           # Proses hapus siswa (Khusus Admin)
│   ├── nilai/
│   │   ├── index.php           # Daftar nilai akademik per kelas/semester
│   │   └── input.php           # Form input & update nilai mata pelajaran
│   ├── tahfidh/
│   │   ├── index.php           # Daftar hafalan & capaian tahfidh per kelas
│   │   └── input.php           # Form input capaian surat/juz & nilai tajwid/kelancaran
│   ├── rapor/
│   │   ├── index.php           # Manajemen status rapor (draft, ready, published)
│   │   ├── detail.php          # Pratinjau rapor lengkap siswa
│   │   ├── cetak.php           # Pembuatan / render PDF rapor
│   │   └── publish.php         # Aksi perubahan status publikasi (Khusus Admin)
│   └── users/
│       ├── index.php           # Manajemen pengguna staff & admin (Khusus Admin)
│       ├── tambah.php          # Tambah akun staff/guru baru
│       └── edit.php            # Edit role & status aktif akun
├── uploads/
│   └── rapor/
│       └── .htaccess           # Proteksi folder upload (Deny from all script execution)
├── .htaccess                   # Konfigurasi proteksi file sensitif & base URL Apache
├── index.php                   # Entry point root (redirect ke portal publik / dashboard)
├── README.md                   # Dokumentasi cara instalasi & akun default
└── IMPLEMENTATION_PLAN.md      # Rencana implementasi teknis
```

---

## 4. Skema Database (MySQL / MariaDB)

Database: `raporonline` (dapat dikonfigurasi via `config/koneksi.php`).

### Tabel `users`
Menyimpan akun pengguna internal.
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `username` VARCHAR(50) NOT NULL UNIQUE
- `password_hash` VARCHAR(255) NOT NULL
- `nama_lengkap` VARCHAR(100) NOT NULL
- `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff'
- `is_active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

### Tabel `students`
Menyimpan identitas pribadi dan riwayat sekolah siswa sesuai form `inputsiswa.html`.
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `nis` VARCHAR(30) NOT NULL UNIQUE
- `nisn` VARCHAR(20) NOT NULL UNIQUE
- `nama` VARCHAR(150) NOT NULL
- `jenis_kelamin` ENUM('L', 'P') NOT NULL
- `tempat_lahir` VARCHAR(100) NOT NULL
- `tanggal_lahir` DATE NOT NULL
- `agama` VARCHAR(30) NOT NULL DEFAULT 'ISLAM'
- `anak_ke` TINYINT UNSIGNED NULL
- `status_keluarga` VARCHAR(50) NULL
- `alamat` TEXT NOT NULL
- `kelas` VARCHAR(30) NOT NULL
- `tanggal_diterima` DATE NULL
- `sekolah_asal` VARCHAR(150) NULL
- `alamat_sekolah_asal` TEXT NULL
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- INDEX `idx_students_nama` (`nama`), INDEX `idx_students_kelas` (`kelas`)

### Tabel `guardians`
Menyimpan data orang tua kandung dan wali siswa (relasi 1-ke-1 dengan `students`).
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `student_id` INT UNSIGNED NOT NULL UNIQUE
- `nama_ayah` VARCHAR(150) NULL
- `nama_ibu` VARCHAR(150) NULL
- `alamat_orang_tua` TEXT NULL
- `pekerjaan_ayah` VARCHAR(100) NULL
- `pekerjaan_ibu` VARCHAR(100) NULL
- `nama_wali` VARCHAR(150) NULL
- `alamat_wali` TEXT NULL
- `pekerjaan_wali` VARCHAR(100) NULL
- FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE

### Tabel `academic_grades`
Menyimpan nilai per mata pelajaran per semester.
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `student_id` INT UNSIGNED NOT NULL
- `subject` VARCHAR(100) NOT NULL
- `score` DECIMAL(5,2) NOT NULL
- `description` TEXT NULL
- `semester` TINYINT UNSIGNED NOT NULL COMMENT '1=Ganjil, 2=Genap'
- `school_year` VARCHAR(9) NOT NULL COMMENT 'Contoh: 2025/2026'
- FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
- UNIQUE KEY `uq_academic_grade` (`student_id`, `subject`, `semester`, `school_year`)

### Tabel `tahfidh_grades`
Menyimpan penilaian tahfidh/hafalan Al-Qur'an.
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `student_id` INT UNSIGNED NOT NULL
- `memorization` VARCHAR(150) NOT NULL COMMENT 'Nama Surat / Juz / Target Hafalan'
- `score` DECIMAL(5,2) NOT NULL
- `description` TEXT NULL COMMENT 'Predikat / Catatan Kelancaran & Tajwid'
- `semester` TINYINT UNSIGNED NOT NULL
- `school_year` VARCHAR(9) NOT NULL
- FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
- UNIQUE KEY `uq_tahfidh_item` (`student_id`, `memorization`, `semester`, `school_year`)

### Tabel `reports`
Menyimpan ringkasan rapor, status kelengkapan, dan status publikasi.
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `student_id` INT UNSIGNED NOT NULL
- `semester` TINYINT UNSIGNED NOT NULL
- `school_year` VARCHAR(9) NOT NULL
- `status` ENUM('draft', 'ready', 'published') NOT NULL DEFAULT 'draft'
- `pdf_path` VARCHAR(255) NULL
- `published_at` TIMESTAMP NULL
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
- UNIQUE KEY `uq_student_report_period` (`student_id`, `semester`, `school_year`)
- INDEX `idx_reports_search` (`status`, `school_year`)

---

## 5. Matriks Hak Akses & Keamanan

| Fitur / Aksi | Publik | Staff | Admin |
|---|:---:|:---:|:---:|
| Akses Portal Pencarian Rapor | Ya | Ya | Ya |
| Cari & Lihat Ringkasan Rapor (`published`) | Ya | Ya | Ya |
| Unduh Dokumen PDF Rapor (`published`) | Ya | Ya | Ya |
| Akses Dashboard Internal & Statistik | Tidak | Ya | Ya |
| Tambah & Edit Data Siswa / Wali | Tidak | Ya | Ya |
| Input & Edit Nilai Akademik | Tidak | Ya | Ya |
| Input & Edit Nilai Tahfidh | Tidak | Ya | Ya |
| Pratinjau & Generate PDF Internal | Tidak | Ya | Ya |
| Ubah Status Publikasi (`published`/`draft`) | Tidak | Tidak | Ya |
| Hapus Data Siswa / Nilai | Tidak | Tidak | Ya |
| Kelola Akun Pengguna (Staff/Admin) | Tidak | Tidak | Ya |

### Standar Keamanan Wajib:
1. **PDO Prepared Statements**: Semua query SQL wajib menggunakan parameter binding untuk mencegah SQL Injection.
2. **Output Sanitization**: Semua data dinamis yang dirender ke HTML wajib di-escape menggunakan `e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`).
3. **CSRF Protection**: Form mutasi data (POST) wajib menyertakan token CSRF yang diverifikasi di sisi server.
4. **Session Hardening**: `session_regenerate_id(true)` saat login dan logout bersih.
5. **Proteksi File PDF & Upload**: PDF tersimpan di direktori `uploads/rapor/` dengan proteksi `.htaccess` agar tidak dapat dieksekusi script secara sembarangan, serta diakses melalui controller `download.php` / `cetak.php` yang memverifikasi hak akses.

---

## 6. Tahapan Eksekusi & Rencana Kerja

### Tahap 1 - Fondasi, Konfigurasi, dan Helper
- [ ] Buat `config/koneksi.php` yang mendukung konfigurasi default XAMPP (host: `127.0.0.1`, user: `root`, pass: ``, db: `raporonline`).
- [ ] Buat `includes/fungsi.php` berisi:
  - `e(?string $value)` untuk XSS escaping.
  - `base_url(string $path = '')` untuk resolusi URL otomatis (XAMPP subfolder / root host).
  - `redirect(string $path)` menggunakan `base_url`.
  - `csrf_token()`, `csrf_field()`, dan `verify_csrf()`.
  - `flash_message()` untuk alert sukses/gagal.
- [ ] Buat `includes/auth.php` berisi `require_login()`, `require_role($role)`, `is_logged_in()`, dan `current_user()`.
- [ ] Rapikan layout `includes/header.php`, `includes/sidebar.php`, dan `includes/footer.php` dengan menyematkan styling dari `desainstaff.css`.

### Tahap 2 - Database DDL & Data Seeder
- [ ] Tulis dan validasi `database/schema.sql` (tabel `users`, `students`, `guardians`, `academic_grades`, `tahfidh_grades`, `reports`).
- [ ] Buat `database/seed.sql` berisi:
  - Akun Admin default: `admin` / `admin123`.
  - Akun Staff default: `staff` / `staff123`.
  - Data demo siswa (misal: "Nalaa Qorin Al Faizin", kelas VII).
  - Nilai akademik dan hafalan tahfidh contoh.
  - Rapor sampel berstatus `published` dan `draft`.

### Tahap 3 - Modul Autentikasi
- [ ] Sempurnakan `auth/login.php` dengan UI yang rapi, pesan error yang informatif, dan proteksi brute-force sederhana.
- [ ] Buat `auth/logout.php` yang menghapus seluruh session dan mengarahkan kembali ke halaman login.

### Tahap 4 - Dashboard Staff Dinamis
- [ ] Migrasikan `Staff/Dashboard.html` ke `staff/dashboard.php`.
- [ ] Hubungkan komponen statistik:
  - Total siswa aktif (query count `students`).
  - Progress nilai masuk (% siswa yang sudah memiliki nilai lengkap).
  - Jumlah rapor siap cetak (`ready` / `published`).
- [ ] Tampilkan tabel ringkasan data siswa dengan indikator status nilai (Lengkap / Belum Lengkap) dan aksi edit / cetak.

### Tahap 5 - Modul Data Siswa (CRUD Lengkap)
- [ ] `staff/siswa/index.php`: Daftar seluruh siswa dengan fitur filter kelas dan pencarian nama/NISN.
- [ ] `staff/siswa/tambah.php`: Migrasi form dari `Staff/inputsiswa.html` untuk menyimpan identitas siswa, riwayat sekolah, dan data wali dalam satu transaksi database (`beginTransaction`, `commit`, `rollBack`).
- [ ] `staff/siswa/edit.php`: Form edit data siswa dan wali.
- [ ] `staff/siswa/detail.php`: Tampilan detail profil siswa.
- [ ] `staff/siswa/hapus.php`: Hapus siswa beserta relasinya (Khusus role Admin).

### Tahap 6 - Modul Nilai Akademik & Tahfidh
- [ ] `staff/nilai/index.php` & `staff/nilai/input.php`:
  - Input nilai mata pelajaran (PAI, Bahasa Arab, Matematika, IPA, IPS, Bahasa Indonesia, Bahasa Inggris, dll.).
  - Validasi rentang nilai (0 - 100).
- [ ] `staff/tahfidh/index.php` & `staff/tahfidh/input.php`:
  - Input penilaian hafalan surat/juz, nilai kelancaran/tajwid, dan catatan ustadz/guru pembimbing.
- [ ] Pembaruan otomatis status rapor pada tabel `reports` menjadi `ready` jika seluruh komponen nilai telah terisi.

### Tahap 7 - Generator PDF & Manajemen Rapor
- [ ] Sediakan engine PDF mandiri pada `includes/fpdf/`.
- [ ] `staff/rapor/index.php`: Daftar status rapor per periode.
- [ ] `staff/rapor/detail.php`: Pratinjau rapor akademik & tahfidh di browser.
- [ ] `staff/rapor/cetak.php`: Generate dokumen PDF rapor resmi MTs Roudlotul Qur'an dengan kop surat dan tanda tangan.
- [ ] `staff/rapor/publish.php`: Otorisasi publikasi rapor oleh Admin agar dapat diakses publik.

### Tahap 8 - Portal Publik Rapor
- [ ] Migrasikan `Wali dan Murid/Dashboardrapor.html` ke `publik/rapor.php`.
- [ ] Formulir pencarian berdasarkan NISN atau Nama Siswa.
- [ ] Tampilkan hasil pencarian hanya jika terdapat rapor dengan status `published`.
- [ ] Tombol "Unduh PDF Rapor" mengarahkan ke `publik/download.php?id=...`.
- [ ] `publik/download.php`: Validasi keberadaan file PDF dan status published sebelum stream file ke user.

### Tahap 9 - Manajemen Pengguna (Admin) & Polishing
- [ ] `staff/users/index.php`: Kelola akun staff & admin (tambah akun, ganti role, nonaktifkan user).
- [ ] Buat penanganan error kustom (403, 404, 500).
- [ ] Uji responsivitas UI dan validasi seluruh alur (Smoke Test).

---

## 7. Rute Aplikasi Lengkap

| Method | URL / Path | Hak Akses | Keterangan |
|---|---|---|---|
| GET | `/` | Publik | Redirect ke `/publik/index.php` |
| GET | `/publik/index.php` | Publik | Portal pencarian rapor |
| GET | `/publik/rapor.php` | Publik | Pencarian rapor berstatus `published` |
| GET | `/publik/download.php` | Publik | Unduh PDF rapor yang dipublikasikan |
| GET/POST | `/auth/login.php` | Publik | Form dan proses login staff/admin |
| GET/POST | `/auth/logout.php` | Login | Proses logout |
| GET | `/staff/dashboard.php` | Staff / Admin | Dashboard statistik & ringkasan |
| GET | `/staff/siswa/index.php` | Staff / Admin | Daftar master siswa |
| GET/POST | `/staff/siswa/tambah.php` | Staff / Admin | Form input siswa baru |
| GET/POST | `/staff/siswa/edit.php` | Staff / Admin | Form edit siswa |
| GET | `/staff/siswa/detail.php` | Staff / Admin | Detail lengkap siswa |
| POST | `/staff/siswa/hapus.php` | Admin | Hapus siswa |
| GET | `/staff/nilai/index.php` | Staff / Admin | Daftar nilai akademik |
| GET/POST | `/staff/nilai/input.php` | Staff / Admin | Input/edit nilai akademik |
| GET | `/staff/tahfidh/index.php`| Staff / Admin | Daftar nilai tahfidh |
| GET/POST | `/staff/tahfidh/input.php`| Staff / Admin | Input/edit nilai tahfidh |
| GET | `/staff/rapor/index.php` | Staff / Admin | Monitoring status rapor |
| GET | `/staff/rapor/detail.php`| Staff / Admin | Preview rapor siswa |
| GET | `/staff/rapor/cetak.php` | Staff / Admin | Cetak / Generate PDF rapor |
| POST | `/staff/rapor/publish.php`| Admin | Publish / Unpublish rapor |
| GET | `/staff/users/index.php` | Admin | Manajemen user |
| GET/POST | `/staff/users/tambah.php`| Admin | Tambah akun staff/admin |
| GET/POST | `/staff/users/edit.php` | Admin | Edit akun user |

---

## 8. Definition of Done (Kriteria Keberhasilan)

1. [ ] Pengunjung anonim tidak dapat mengakses halaman `/staff/*`.
2. [ ] Role `staff` dapat mengelola siswa & nilai, tetapi dibatasi dari publish rapor, hapus siswa, dan kelola user.
3. [ ] Role `admin` dapat melakukan seluruh fungsi termasuk manajemen user dan publikasi rapor.
4. [ ] Portal publik hanya menampilkan data siswa dan tombol unduh PDF untuk rapor berstatus `published`.
5. [ ] Tampilan antarmuka mengadopsi penuh desain hijau khas MTs Roudlotul Qur'an dari file template asli.
6. [ ] PDF rapor dapat digenerate dan diunduh secara rapi.
7. [ ] Aplikasi berjalan lancar di lingkungan XAMPP tanpa memerlukan Composer CLI atau ekstensi tambahan non-standar.
