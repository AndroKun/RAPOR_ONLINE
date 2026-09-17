<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/fungsi.php';
require_once __DIR__ . '/../includes/pdf_helper.php';

$reportId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$studentId = isset($_GET['student_id']) && is_numeric($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$semester = isset($_GET['semester']) && is_numeric($_GET['semester']) ? (int)$_GET['semester'] : 2;
$schoolYear = trim($_GET['school_year'] ?? '2025/2026');

$data = null;

if ($reportId) {
    $stmt = $pdo->prepare("SELECT r.*, s.*, s.id AS student_id,
                                  g.nama_ayah, g.nama_ibu, g.alamat_orang_tua, g.pekerjaan_ayah, g.pekerjaan_ibu,
                                  g.nama_wali, g.alamat_wali, g.pekerjaan_wali
                           FROM reports r
                           INNER JOIN students s ON s.id = r.student_id
                           LEFT JOIN guardians g ON g.student_id = s.id
                           WHERE r.id = :id 
                           LIMIT 1");
    $stmt->execute(['id' => $reportId]);
    $data = $stmt->fetch();
} elseif ($studentId) {
    $stmt = $pdo->prepare("SELECT s.*, s.id AS student_id, g.nama_ayah, g.nama_ibu, g.alamat_orang_tua, g.pekerjaan_ayah, g.pekerjaan_ibu,
                                  g.nama_wali, g.alamat_wali, g.pekerjaan_wali,
                                  r.id AS report_id, r.status, r.published_at,
                                  r.nama_wali_kelas, r.nama_kepala_madrasah, r.catatan_wali_kelas, r.catatan_wali_murid,
                                  r.tempat_rapor, r.tanggal_rapor
                           FROM students s
                           LEFT JOIN guardians g ON g.student_id = s.id
                           LEFT JOIN reports r ON r.student_id = s.id AND r.semester = :sem AND r.school_year = :sy
                           WHERE s.id = :sid 
                           LIMIT 1");
    $stmt->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
    $data = $stmt->fetch();
    if ($data) {
        $data['semester'] = $semester;
        $data['school_year'] = $schoolYear;
    }
}

if (!$data) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Rapor Tidak Tersedia</title>';
    echo '<link rel="stylesheet" href="' . e(base_url('/assets/css/desainrapor.css')) . '">';
    echo '</head><body><div class="container" style="text-align:center; margin-top:80px;">';
    echo '<div class="result-card" style="border-left-color:#e74c3c;">';
    echo '<h3 style="color:#c0392b;">Rapor Belum Tersedia / Tidak Ditemukan</h3>';
    echo '<p>Rapor ini belum dipublikasikan secara resmi oleh pihak madrasah atau data siswa tidak valid.</p>';
    echo '<a href="' . e(base_url('/publik/rapor.php')) . '" class="download-btn" style="background:#1a5632; color:white;">Kembali ke Pencarian</a>';
    echo '</div></div></body></html>';
    exit;
}

$student = $data;

$report = [
    'semester' => (int)$data['semester'],
    'school_year' => $data['school_year'],
    'status' => 'published',
    'nama_wali_kelas' => !empty($data['nama_wali_kelas']) ? $data['nama_wali_kelas'] : get_wali_kelas_by_class($pdo, (string)($student['kelas'] ?? '')),
    'nama_kepala_madrasah' => $data['nama_kepala_madrasah'] ?? null,
    'catatan_wali_kelas' => $data['catatan_wali_kelas'] ?? null,
    'catatan_wali_murid' => $data['catatan_wali_murid'] ?? null,
    'tempat_rapor' => $data['tempat_rapor'] ?? 'Sidoarjo',
    'tanggal_rapor' => $data['tanggal_rapor'] ?? null,
];

// Ambil nilai akademik
$stmtAcad = $pdo->prepare("SELECT * FROM academic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy ORDER BY id ASC");
$stmtAcad->execute(['sid' => $data['student_id'], 'sem' => $data['semester'], 'sy' => $data['school_year']]);
$academicGrades = $stmtAcad->fetchAll();

// Ambil nilai tahfidh
$stmtTah = $pdo->prepare("SELECT * FROM tahfidh_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy ORDER BY id ASC");
$stmtTah->execute(['sid' => $data['student_id'], 'sem' => $data['semester'], 'sy' => $data['school_year']]);
$tahfidhGrades = $stmtTah->fetchAll();

// Ambil catatan tahfidh
$stmtTNotes = $pdo->prepare("SELECT * FROM tahfidh_notes WHERE student_id = :sid AND semester = :sem AND school_year = :sy LIMIT 1");
$stmtTNotes->execute(['sid' => $data['student_id'], 'sem' => $data['semester'], 'sy' => $data['school_year']]);
$tahfidhNotes = $stmtTNotes->fetch() ?: [];

// Ambil nilai bahasa arab
$stmtArab = $pdo->prepare("SELECT * FROM arabic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy ORDER BY urutan ASC, id ASC");
$stmtArab->execute(['sid' => $data['student_id'], 'sem' => $data['semester'], 'sy' => $data['school_year']]);
$arabicGrades = $stmtArab->fetchAll();

// Ambil catatan saran bahasa arab
$stmtNotes = $pdo->prepare("SELECT * FROM arabic_notes WHERE student_id = :sid AND semester = :sem AND school_year = :sy LIMIT 1");
$stmtNotes->execute(['sid' => $data['student_id'], 'sem' => $data['semester'], 'sy' => $data['school_year']]);
$arabicNotes = $stmtNotes->fetch() ?: [];

$sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $data['nama']);
$filename = "Rapor_{$sanitizedName}_Semester_{$data['semester']}.pdf";

generate_rapor_pdf($student, $academicGrades, $tahfidhGrades, $report, 'I', $filename, $arabicGrades, $arabicNotes, $tahfidhNotes);
exit;
