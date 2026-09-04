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
- `database/`: schema dan data awal untuk phpMyAdmin
- `uploads/rapor/`: file PDF rapor
- `Staff/` dan `Wali dan Murid/`: halaman HTML lama sebagai referensi migrasi

Detail tahapan pengembangan tersedia di [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md).

## Menjalankan Lokal

1. Pastikan MySQL/MariaDB dan ekstensi `pdo_mysql` aktif.
2. Buka phpMyAdmin, pilih menu **Import**, lalu import `database/schema.sql`.
3. Import `database/seed.sql` setelah schema berhasil.
4. Atur environment `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASSWORD` jika berbeda dari default.
5. Jalankan dari folder root project:

	```bash
	php -S localhost:8000 -t .
	```

6. Buka `http://localhost:8000/` pada browser.