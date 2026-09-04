# RAPOR ONLINE

Aplikasi rapor online sederhana berbasis PHP native dan SQLite. Proyek ini tidak menggunakan Laravel atau framework PHP lain.

## Teknologi

- PHP 8.2 atau lebih baru
- SQLite
- PDO dengan ekstensi `pdo_sqlite`
- HTML dan CSS
- Composer hanya bila library PDF diperlukan

## Struktur Folder

- `config/`: koneksi database
- `includes/`: autentikasi, helper, dan bagian HTML bersama
- `auth/`: login dan logout
- `staff/`: dashboard serta pengelolaan siswa, nilai, dan rapor
- `publik/`: pencarian dan unduh rapor yang sudah dipublikasikan
- `database/`: schema, data awal, setup, dan panel database lokal
- `uploads/rapor/`: file PDF rapor
- `Staff/` dan `Wali dan Murid/`: halaman HTML lama sebagai referensi migrasi

Detail tahapan pengembangan tersedia di [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md).

## Menjalankan Lokal

1. Pastikan ekstensi `pdo_sqlite` aktif.
2. Buat database dan tabel dengan perintah `php database/setup.php`.
3. Jalankan dari folder root project:
4. Jalankan dari folder root project:

	```bash
	php -S localhost:8000 -t .
	```

4. Buka `http://localhost:8000/` pada browser.
5. Panel database lokal tersedia di `http://localhost:8000/database/`.