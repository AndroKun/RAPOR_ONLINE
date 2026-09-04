# Implementation Plan - RAPOR ONLINE

## 1. Tujuan

Membangun aplikasi rapor online dari halaman statis yang tersedia saat ini dengan dua area akses:

- Area internal staff: hanya untuk user dengan role `staff` atau `admin`.
- Area publik: dapat diakses wali murid dan masyarakat tanpa login, tetapi hanya menampilkan rapor yang sudah dipublikasikan.

Implementasi menggunakan PHP 8.2+, MySQL/MariaDB, SQL, HTML, dan CSS. Tidak ada framework atau JavaScript sebagai ketergantungan aplikasi.

## 2. Kondisi Awal

- `Staff/Dashboard.html` berisi dashboard statis.
- `Staff/inputsiswa.html` berisi formulir data siswa statis.
- `Staff/desainstaff.css` berisi styling area staff dan formulir.
- `Wali dan Murid/Dashboardrapor.html` berisi portal pencarian rapor statis.
- `Wali dan Murid/desainrapor.css` berisi styling area publik.
- Belum ada database, backend, login, validasi server, penyimpanan data, atau generator PDF.
- Link dashboard pada `inputsiswa.html` perlu diarahkan ke halaman dashboard yang benar.

## 3. Struktur Target PHP Native

```text
RAPOR_ONLINE/
├── config/
│   └── koneksi.php
├── includes/
│   ├── auth.php
│   ├── fungsi.php
│   ├── header.php
│   └── footer.php
├── auth/
│   ├── login.php
│   └── logout.php
├── database/
│   ├── schema.sql
│   └── seed.sql
├── staff/
│   ├── index.php
│   ├── dashboard.php
│   ├── siswa/
│   ├── nilai/
│   ├── tahfidh/
│   ├── rapor/
│   └── users/
├── publik/
│   ├── index.php
│   ├── rapor.php
│   └── download.php
├── assets/
│   ├── css/
│   └── img/
├── uploads/
│   └── rapor/
├── index.php
├── Staff/
├── Wali dan Murid/
├── composer.json
└── IMPLEMENTATION_PLAN.md
```

`config/`, `includes/`, dan `database/` berisi file pendukung yang tidak boleh diakses langsung dari browser. Folder `staff/`, `publik/`, dan `auth/` berisi file PHP sebagai halaman sekaligus pemroses form sederhana. Folder `uploads/` harus dilindungi dari eksekusi script dan berisi PDF rapor dengan nama file acak.

Folder HTML lama dipertahankan sebagai referensi selama migrasi. Aplikasi dijalankan dari root project menggunakan PHP built-in server atau virtual host Apache; tidak ada front controller, namespace aplikasi, service container, atau struktur `app/` dan `public/` seperti Laravel.

### Aturan implementasi sederhana

- Gunakan `require_once` untuk memanggil `config/koneksi.php`, `includes/auth.php`, dan `includes/fungsi.php`.
- Gunakan PDO dan prepared statement untuk query MySQL.
- Proses form boleh berada di file halaman yang sama atau file `proses.php` pada folder fitur terkait.
- Gunakan session PHP bawaan untuk login dan pengecekan role.
- Gunakan Composer hanya bila library PDF diperlukan; Composer bukan framework aplikasi.

## 4. Model Data

Buat tabel berikut pada `database/schema.sql`:

### `users`

- `id`
- `username` unik
- `password_hash`
- `role` dengan nilai `admin` atau `staff`
- `is_active`
- `created_at`

### `students`

- `id`
- `nis` unik
- `nisn` unik
- `nama`
- `jenis_kelamin`
- `tempat_lahir`
- `tanggal_lahir`
- `agama`
- `anak_ke`
- `status_keluarga`
- `alamat`
- `kelas`
- `tanggal_diterima`
- `sekolah_asal`
- `alamat_sekolah_asal`
- `created_at`
- `updated_at`

### `guardians`

- `id`
- `student_id`
- `nama_ayah`
- `nama_ibu`
- `alamat_orang_tua`
- `pekerjaan_ayah`
- `pekerjaan_ibu`
- `nama_wali`
- `alamat_wali`
- `pekerjaan_wali`

### `academic_grades`

- `id`
- `student_id`
- `subject`
- `score`
- `description`
- `semester`
- `school_year`

### `tahfidh_grades`

- `id`
- `student_id`
- `memorization`
- `score`
- `description`
- `semester`
- `school_year`

### `reports`

- `id`
- `student_id`
- `semester`
- `school_year`
- `status` dengan nilai `draft`, `ready`, atau `published`
- `pdf_path`
- `published_at`
- `created_at`
- `updated_at`

Tambahkan foreign key, index pada `nisn`, `nama`, dan status rapor, serta constraint agar satu siswa tidak memiliki duplikasi rapor untuk semester dan tahun pelajaran yang sama.

## 5. Alur Akses

### Area publik

1. Pengunjung membuka `index.php` atau `publik/rapor.php`.
2. Pengunjung mengisi NISN atau nama siswa.
3. Backend hanya mencari data pada rapor dengan status `published`.
4. Sistem menampilkan nama, NISN, kelas, semester, tahun pelajaran, dan tombol unduh.
5. Data alamat, tanggal lahir, data orang tua, dan data internal tidak dikirim ke browser publik.
6. Unduh PDF hanya diberikan untuk rapor berstatus `published`.

### Area staff dan admin

1. User membuka `auth/login.php`.
2. Backend memvalidasi username dan password menggunakan `password_verify`.
3. User berhasil login lalu diarahkan ke `staff/dashboard.php`.
4. `includes/auth.php` memeriksa session dan role pada setiap halaman internal.
5. Staff mengelola siswa dan nilai.
6. Admin dapat melakukan semua tindakan staff, mengelola user, dan mempublikasikan rapor.
7. User keluar melalui `auth/logout.php`, lalu session dihancurkan.

## 6. Matriks Hak Akses

| Fitur | Publik | Staff | Admin |
|---|---:|---:|---:|
| Membuka portal publik | Ya | Ya | Ya |
| Mencari rapor published | Ya | Ya | Ya |
| Mengunduh rapor published | Ya | Ya | Ya |
| Melihat data siswa internal | Tidak | Ya | Ya |
| Membuat dan mengubah data siswa | Tidak | Ya | Ya |
| Mengubah nilai akademik | Tidak | Ya | Ya |
| Mengubah nilai tahfidh | Tidak | Ya | Ya |
| Mengubah status publikasi | Tidak | Tidak | Ya |
| Mengelola akun staff | Tidak | Tidak | Ya |
| Menghapus data | Tidak | Tidak | Ya |

Pemeriksaan role wajib dilakukan di backend. Menyembunyikan menu menggunakan HTML tidak dianggap sebagai proteksi akses.

## 7. Tahapan Eksekusi

### Tahap 0 - Persiapan

- [ ] Pastikan PHP 8.2+ dan ekstensi `pdo_mysql` tersedia.
- [ ] Buat struktur folder target.
- [ ] Buat konfigurasi environment tanpa menyimpan secret di repository.
- [ ] Buat `README.md` berisi cara menjalankan aplikasi lokal.

Validasi:

```bash
php -v
php -m | grep pdo_mysql
mysql --version
```

### Tahap 1 - Database dan seed

- [ ] Buat `database/schema.sql`.
- [ ] Buat `database/seed.sql` untuk akun admin awal dan data demo.
- [ ] Buat koneksi PDO pada `config/koneksi.php`.
- [ ] Gunakan prepared statements untuk semua query.
- [ ] Jangan menyimpan password asli; gunakan `password_hash`.

Validasi:

```bash
mysql -u USERNAME -p rapor_online < database/schema.sql
mysql -u USERNAME -p rapor_online < database/seed.sql
```

### Tahap 2 - Autentikasi dan pemeriksaan session

- [ ] Buat halaman login.
- [ ] Implementasikan session login.
- [ ] Buat fungsi `wajib_login()` pada `includes/auth.php` untuk semua halaman internal.
- [ ] Buat fungsi `wajib_role('admin')` untuk fitur admin.
- [ ] Regenerasi session ID setelah login.
- [ ] Tambahkan logout dan session timeout.
- [ ] Tambahkan CSRF token pada semua form yang mengubah data.

Validasi:

- [ ] Pengunjung anonim diarahkan ke login saat membuka halaman staff.
- [ ] Password salah ditolak.
- [ ] Staff tidak dapat membuka endpoint admin.
- [ ] Logout membuat halaman internal tidak dapat diakses kembali.

### Tahap 3 - Migrasi dashboard staff

- [ ] Ubah `Staff/Dashboard.html` menjadi dashboard dinamis.
- [ ] Tampilkan jumlah siswa aktif dari database.
- [ ] Hitung persentase kelengkapan nilai dari data rapor.
- [ ] Tampilkan daftar siswa dan status rapor.
- [ ] Hubungkan menu ke halaman aktual.
- [ ] Perbaiki link dashboard dari `index.html` menjadi route dashboard yang benar.

Validasi:

- [ ] Angka dashboard berubah setelah data ditambah.
- [ ] Tombol edit hanya membuka data yang dipilih.
- [ ] Rapor belum lengkap tidak dapat dicetak atau dipublikasikan.

### Tahap 4 - Modul data siswa

- [ ] Migrasikan `Staff/inputsiswa.html` ke form PHP.
- [ ] Beri `name` dan tipe input yang sesuai pada setiap field HTML.
- [ ] Tambahkan validasi wajib, format NISN, tanggal, dan angka.
- [ ] Simpan data siswa dan guardian dalam transaksi database.
- [ ] Buat halaman daftar, tambah, detail, edit, dan hapus siswa.
- [ ] Tampilkan error validasi tanpa menghilangkan input yang sudah benar.

Validasi:

- [ ] NIS dan NISN duplikat ditolak.
- [ ] Data invalid tidak masuk database.
- [ ] Data valid dapat diedit dan ditampilkan kembali.
- [ ] Penghapusan meminta konfirmasi dan dibatasi untuk admin.

### Tahap 5 - Modul nilai

- [ ] Buat halaman input nilai akademik.
- [ ] Buat halaman input nilai tahfidh.
- [ ] Batasi nilai pada rentang yang ditentukan sekolah.
- [ ] Tambahkan semester dan tahun pelajaran pada setiap nilai.
- [ ] Buat indikator kelengkapan nilai per siswa.
- [ ] Gunakan transaksi saat menyimpan beberapa nilai sekaligus.

Validasi:

- [ ] Nilai di luar rentang ditolak.
- [ ] Nilai tersimpan untuk siswa dan periode yang benar.
- [ ] Status rapor berubah sesuai kelengkapan nilai.

### Tahap 6 - Rapor dan publikasi

- [ ] Buat halaman detail rapor internal.
- [ ] Hitung status `draft`, `ready`, dan `published`.
- [ ] Hanya admin yang dapat mengubah status menjadi `published`.
- [ ] Buat helper atau file proses PDF menggunakan library PDF yang dipilih.
- [ ] Simpan PDF pada `uploads/rapor/` dan blokir eksekusi file script di folder tersebut.
- [ ] Buat endpoint unduh yang memeriksa session atau status publikasi.
- [ ] Gunakan nama file acak atau ID internal, bukan NISN langsung.

Validasi:

- [ ] Rapor incomplete tidak dapat dipublikasikan.
- [ ] Rapor published terlihat di portal publik.
- [ ] Rapor draft tidak muncul pada pencarian publik.
- [ ] File PDF dapat dibuka dan berisi data yang tepat.

### Tahap 7 - Migrasi portal publik

- [ ] Ubah `Wali dan Murid/Dashboardrapor.html` menjadi halaman publik dinamis.
- [ ] Tambahkan form pencarian dengan method `GET`.
- [ ] Tampilkan hasil hanya dari tabel `reports` berstatus `published`.
- [ ] Gunakan pesan umum saat data tidak ditemukan.
- [ ] Escape semua output HTML.
- [ ] Tambahkan rate limit berbasis PHP dan validasi panjang input.
- [ ] Tampilkan tombol unduh hanya saat rapor tersedia.

Validasi:

- [ ] Portal dapat dibuka tanpa login.
- [ ] Pencarian berdasarkan NISN bekerja.
- [ ] Pencarian nama tidak membocorkan data pribadi.
- [ ] Rapor unpublished tidak dapat ditemukan melalui query atau URL langsung.

### Tahap 8 - Penyempurnaan dan deployment

- [ ] Buat halaman error 403, 404, dan 500.
- [ ] Tambahkan logging error server tanpa membocorkan detail ke user.
- [ ] Nonaktifkan tampilan error detail pada production.
- [ ] Pastikan folder database dan storage tidak dapat di-list atau diunduh langsung.
- [ ] Uji responsif pada desktop dan mobile.
- [ ] Jalankan pemeriksaan HTML, CSS, PHP syntax, dan smoke test.
- [ ] Dokumentasikan akun demo, perintah lokal, backup database, dan deployment.

## 8. Keamanan Minimum

- Gunakan HTTPS pada production.
- Gunakan PDO prepared statements.
- Hash password dengan `password_hash`.
- Escape output menggunakan `htmlspecialchars`.
- Gunakan CSRF token pada POST, PUT, dan DELETE.
- Validasi role di server pada setiap route internal.
- Jangan menampilkan data wali, alamat, atau tanggal lahir pada portal publik.
- Jangan menaruh kredensial database atau PDF private di folder yang dapat diakses langsung.
- Tambahkan rate limit pada pencarian publik dan login.
- Catat aktivitas penting: login, perubahan nilai, publikasi, dan penghapusan.
- Lakukan backup database secara berkala.

## 9. Route Minimum

| Method | Route | Akses | Keterangan |
|---|---|---|---|
| GET | `/auth/login.php` | Publik | Form login |
| POST | `/auth/login.php` | Publik | Proses login |
| POST | `/auth/logout.php` | Login | Keluar |
| GET | `/staff/dashboard.php` | Staff/Admin | Dashboard |
| GET/POST | `/staff/siswa/` | Staff/Admin | Daftar dan tambah siswa |
| GET/POST | `/staff/siswa/edit.php` | Staff/Admin | Ubah siswa |
| GET/POST | `/staff/nilai/` | Staff/Admin | Nilai akademik |
| GET/POST | `/staff/tahfidh/` | Staff/Admin | Nilai tahfidh |
| GET | `/staff/rapor/` | Staff/Admin | Detail dan status rapor |
| POST | `/staff/rapor/publish.php` | Admin | Publikasi rapor |
| GET/POST | `/staff/users/` | Admin | Kelola akun |
| GET | `/publik/rapor.php` | Publik | Cari rapor |
| GET | `/publik/download.php` | Publik/Login | Unduh rapor yang diizinkan |

## 10. Definition of Done

Implementasi dianggap selesai apabila:

- [ ] Area staff tidak dapat dibuka tanpa login.
- [ ] Role `staff` dan `admin` memiliki hak akses yang berbeda sesuai matriks.
- [ ] Data siswa, nilai akademik, dan nilai tahfidh tersimpan di database.
- [ ] Admin dapat mempublikasikan rapor yang lengkap.
- [ ] Portal publik hanya menampilkan rapor dengan status `published`.
- [ ] PDF dapat dibuat dan diunduh sesuai hak akses.
- [ ] Input invalid, CSRF, SQL injection, dan akses URL langsung telah diuji.
- [ ] Aplikasi berjalan dari instruksi pada `README.md`.
- [ ] Tidak ada data demo atau password default yang aktif di production.

## 11. Urutan Pengerjaan Berikutnya

1. Buat struktur folder target dan file konfigurasi.
2. Implementasikan schema database serta seed data.
3. Implementasikan login, session, dan pemeriksaan role.
4. Migrasikan dashboard dan form siswa.
5. Implementasikan modul nilai dan status rapor.
6. Implementasikan publikasi serta PDF.
7. Migrasikan portal publik.
8. Jalankan pengujian keamanan dan smoke test.
