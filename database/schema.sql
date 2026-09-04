CREATE DATABASE IF NOT EXISTS raporonline
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE raporonline;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(30) NOT NULL UNIQUE,
    nisn VARCHAR(20) NOT NULL UNIQUE,
    nama VARCHAR(150) NOT NULL,
    jenis_kelamin ENUM('L', 'P') NOT NULL,
    tempat_lahir VARCHAR(100) NOT NULL,
    tanggal_lahir DATE NOT NULL,
    agama VARCHAR(30) NOT NULL DEFAULT 'ISLAM',
    anak_ke TINYINT UNSIGNED NULL,
    status_keluarga VARCHAR(50) NULL,
    alamat TEXT NOT NULL,
    kelas VARCHAR(30) NOT NULL,
    tanggal_diterima DATE NULL,
    sekolah_asal VARCHAR(150) NULL,
    alamat_sekolah_asal TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_students_name (nama)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS guardians (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL UNIQUE,
    nama_ayah VARCHAR(150) NULL,
    nama_ibu VARCHAR(150) NULL,
    alamat_orang_tua TEXT NULL,
    pekerjaan_ayah VARCHAR(100) NULL,
    pekerjaan_ibu VARCHAR(100) NULL,
    nama_wali VARCHAR(150) NULL,
    alamat_wali TEXT NULL,
    pekerjaan_wali VARCHAR(100) NULL,
    CONSTRAINT fk_guardians_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS academic_grades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    subject VARCHAR(100) NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    description TEXT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    school_year VARCHAR(9) NOT NULL,
    CONSTRAINT fk_academic_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY uq_academic_grade (student_id, subject, semester, school_year)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tahfidh_grades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    memorization VARCHAR(150) NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    description TEXT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    school_year VARCHAR(9) NOT NULL,
    CONSTRAINT fk_tahfidh_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    school_year VARCHAR(9) NOT NULL,
    status ENUM('draft', 'ready', 'published') NOT NULL DEFAULT 'draft',
    pdf_path VARCHAR(255) NULL,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reports_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY uq_student_report_period (student_id, semester, school_year),
    INDEX idx_reports_public (status, school_year)
) ENGINE=InnoDB;
