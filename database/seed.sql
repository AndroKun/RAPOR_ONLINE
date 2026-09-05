-- ============================================================================
-- DATA SEEDER AWAL RAPOR ONLINE - MTS ROUDLOTUL QUR'AN
-- ============================================================================

USE `raporonline`;

-- 1. Users Default
-- admin : admin123
-- staff : staff123
INSERT INTO `users` (`id`, `username`, `password_hash`, `nama_lengkap`, `role`, `mata_pelajaran`, `is_active`) VALUES
(1, 'admin', '$2y$10$TH9wLS.qG.bTlAod7Xb1y.IlVYfvrDjNZSljBKvdEi6Lb/2CatL/K', 'Administrator Madrasah', 'admin', NULL, 1),
(2, 'staff', '$2y$10$AonUmdGRni/R9ux63OrD8.UR3nLO..AK9nJ58eKk1YCHPszfHi1hi', 'Ustadzah Siti Fatimah, S.Pd', 'staff', 'Bahasa Indonesia', 1)
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`);

-- 2. Data Siswa Contoh
INSERT INTO `students` (`id`, `nis`, `nisn`, `nama`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `agama`, `anak_ke`, `status_keluarga`, `alamat`, `kelas`, `tanggal_diterima`, `sekolah_asal`, `alamat_sekolah_asal`) VALUES
(1, '250012', '0136347734', 'Nalaa Qorin Al Faizin', 'L', 'Magetan', '2013-05-12', 'ISLAM', 1, 'Anak Kandung', 'Kali Tengah Rt 01 Rw 06 Tanggulangin Sidoarjo', 'VII', '2025-07-14', 'SD Maarif NU Ngaban', 'Ngaban Tanggulangin Sidoarjo'),
(2, '250013', '0136347735', 'Ahmad Dani Prasetya', 'L', 'Sidoarjo', '2013-08-20', 'ISLAM', 2, 'Anak Kandung', 'Desa Ngampelsari Rt 03 Candi Sidoarjo', 'VII', '2025-07-14', 'MI Roudlotul Ulum', 'Candi Sidoarjo'),
(3, '250014', '0136347736', 'Aisyah Nur Rahmah', 'P', 'Surabaya', '2012-09-15', 'ISLAM', 1, 'Anak Kandung', 'Perum Candi Asri Blok B2 Sidoarjo', 'VIII', '2024-07-15', 'SDIT Al-Hikmah', 'Sidoarjo')
ON DUPLICATE KEY UPDATE `nisn`=VALUES(`nisn`);

-- 3. Data Orang Tua & Wali
INSERT INTO `guardians` (`id`, `student_id`, `nama_ayah`, `nama_ibu`, `alamat_orang_tua`, `pekerjaan_ayah`, `pekerjaan_ibu`, `nama_wali`, `alamat_wali`, `pekerjaan_wali`) VALUES
(1, 1, 'Bpk. Ahmad Subhan', 'Ibu Nur Khasanah', 'Kali Tengah Rt 01 Rw 06 Tanggulangin Sidoarjo', 'Wiraswasta', 'Ibu Rumah Tangga', NULL, NULL, NULL),
(2, 2, 'Bpk. Hendra Gunawan', 'Ibu Siti Aminah', 'Desa Ngampelsari Rt 03 Candi Sidoarjo', 'PNS / Guru', 'Wiraswasta', NULL, NULL, NULL),
(3, 3, 'Bpk. M. Ridwan', 'Ibu Lailatul Fitriyah', 'Perum Candi Asri Blok B2 Sidoarjo', 'Karyawan Swasta', 'Pegawai BUMN', NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE `student_id`=VALUES(`student_id`);

-- 4. Nilai Akademik Siswa 1 (Nalaa Qorin Al Faizin - Semester 2 / Genap 2025/2026)
INSERT INTO `academic_grades` (`student_id`, `subject`, `score`, `description`, `semester`, `school_year`) VALUES
(1, 'Al-Qur\'an Hadits', 92.00, 'Sangat baik dalam memahami dan menghafal hadits pilihan', 2, '2025/2026'),
(1, 'Aqidah Akhlak', 90.00, 'Memiliki pemahaman aqidah yang kuat dan berakhlakul karimah', 2, '2025/2026'),
(1, 'Fiqih', 88.00, 'Menguasai tata cara ibadah fardhu dan sunnah dengan baik', 2, '2025/2026'),
(1, 'Sejarah Kebudayaan Islam (SKI)', 86.00, 'Memahami sejarah perkembangan Islam periode Khulafaur Rasyidin', 2, '2025/2026'),
(1, 'Bahasa Arab', 85.00, 'Cakap dalam mufradat dan percakapan dasar bahasa Arab', 2, '2025/2026'),
(1, 'Bahasa Indonesia', 88.00, 'Mampu menulis paragraf deskriptif dan membaca puisi', 2, '2025/2026'),
(1, 'Matematika', 84.00, 'Baik dalam memahami aljabar dan bangun ruang', 2, '2025/2026'),
(1, 'Ilmu Pengetahuan Alam (IPA)', 87.00, 'Aktif dan teliti dalam praktikum dan teori IPA', 2, '2025/2026'),
(1, 'Ilmu Pengetahuan Sosial (IPS)', 85.00, 'Memahami dinamika kependudukan dan interaksi sosial', 2, '2025/2026'),
(1, 'Bahasa Inggris', 85.00, 'Mampu berkomunikasi dalam greeting dan simple dialogue', 2, '2025/2026')
ON DUPLICATE KEY UPDATE `score`=VALUES(`score`);

-- 5. Nilai Tahfidh Siswa 1
INSERT INTO `tahfidh_grades` (`student_id`, `memorization`, `score`, `description`, `semester`, `school_year`) VALUES
(1, 'Juz 30 (An-Naba s.d An-Nas)', 95.00, 'Mutqin, makharijul huruf fasih, tajwid sangat baik', 2, '2025/2026'),
(1, 'Juz 29 (Al-Mulk s.d Al-Mursalat)', 90.00, 'Lancar, terus tingkatkan ketartilan bacaan', 2, '2025/2026'),
(1, 'Surat Pilihan (Surat Yasin & Al-Waqi\'ah)', 92.00, 'Lancar dengan irama tartil yang baik', 2, '2025/2026')
ON DUPLICATE KEY UPDATE `score`=VALUES(`score`);

-- 6. Status Rapor Siswa 1 (Published)
INSERT INTO `reports` (`student_id`, `semester`, `school_year`, `status`, `published_at`) VALUES
(1, 2, '2025/2026', 'published', NOW()),
(2, 2, '2025/2026', 'draft', NULL),
(3, 2, '2025/2026', 'ready', NULL)
ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);
