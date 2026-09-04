CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role TEXT NOT NULL DEFAULT 'staff' CHECK (role IN ('admin', 'staff')),
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE students (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nis TEXT NOT NULL UNIQUE,
    nisn TEXT NOT NULL UNIQUE,
    nama TEXT NOT NULL,
    jenis_kelamin TEXT NOT NULL CHECK (jenis_kelamin IN ('L', 'P')),
    tempat_lahir TEXT NOT NULL,
    tanggal_lahir TEXT NOT NULL,
    agama TEXT NOT NULL DEFAULT 'ISLAM',
    anak_ke INTEGER NULL,
    status_keluarga TEXT NULL,
    alamat TEXT NOT NULL,
    kelas TEXT NOT NULL,
    tanggal_diterima TEXT NULL,
    sekolah_asal TEXT NULL,
    alamat_sekolah_asal TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_students_name ON students (nama);

CREATE TABLE guardians (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL UNIQUE,
    nama_ayah TEXT NULL,
    nama_ibu TEXT NULL,
    alamat_orang_tua TEXT NULL,
    pekerjaan_ayah TEXT NULL,
    pekerjaan_ibu TEXT NULL,
    nama_wali TEXT NULL,
    alamat_wali TEXT NULL,
    pekerjaan_wali TEXT NULL,
    CONSTRAINT fk_guardians_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE academic_grades (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL,
    subject TEXT NOT NULL,
    score REAL NOT NULL,
    description TEXT NULL,
    semester INTEGER NOT NULL,
    school_year TEXT NOT NULL,
    CONSTRAINT fk_academic_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE (student_id, subject, semester, school_year)
);

CREATE TABLE tahfidh_grades (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL,
    memorization TEXT NOT NULL,
    score REAL NOT NULL,
    description TEXT NULL,
    semester INTEGER NOT NULL,
    school_year TEXT NOT NULL,
    CONSTRAINT fk_tahfidh_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL,
    semester INTEGER NOT NULL,
    school_year TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'ready', 'published')),
    pdf_path TEXT NULL,
    published_at TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reports_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE (student_id, semester, school_year)
);

CREATE INDEX idx_reports_public ON reports (status, school_year);
