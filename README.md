# RAPOR ONLINE

Aplikasi rapor online sederhana berbasis PHP native dan MySQL/MariaDB. Proyek ini tidak menggunakan Laravel atau framework PHP lain.

## Teknologi

- PHP 8.2 atau lebih baru
- MySQL/MariaDB
- PDO dengan ekstensi `pdo_mysql`
- HTML dan CSS
- Composer hanya bila library PDF diperlukan

## Struktur Folder

- `config/`: koneksi database
- `includes/`: autentikasi, helper, dan bagian HTML bersama
- `auth/`: login dan logout
- `staff/`: dashboard serta pengelolaan siswa, nilai, dan rapor
- `publik/`: pencarian dan unduh rapor yang sudah dipublikasikan
- `database/`: schema dan data awal MySQL
- `uploads/rapor/`: file PDF rapor
- `Staff/` dan `Wali dan Murid/`: halaman HTML lama sebagai referensi migrasi

Detail tahapan pengembangan tersedia di [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md).

## Menjalankan Lokal

1. Buat database `rapor_online` pada MySQL/MariaDB.
2. Import `database/schema.sql`, lalu `database/seed.sql` setelah file tersebut dibuat.
3. Salin konfigurasi koneksi database ke `config/koneksi.php` dan jangan menyimpan password production di repository.
4. Jalankan dari folder root project:

	```bash
	php -S localhost:8000
	```

5. Buka `http://localhost:8000/` pada browser.