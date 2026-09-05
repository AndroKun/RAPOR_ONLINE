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
    $stmt = $pdo->prepare("SELECT r.*, s.nama, s.nis, s.nisn, s.kelas, s.tempat_lahir, s.tanggal_lahir,
                                  g.nama_ayah, g.nama_ibu, g.nama_wali 
                           FROM reports r
                           INNER JOIN students s ON s.id = r.student_id
                           LEFT JOIN guardians g ON g.student_id = s.id
                           WHERE r.id = :id 
                           LIMIT 1");
    $stmt->execute(['id' => $reportId]);
    $data = $stmt->fetch();
} elseif ($studentId) {
    $stmt = $pdo->prepare("SELECT s.*, s.id AS student_id, g.nama_ayah, g.nama_ibu, g.nama_wali,
                                  r.id AS report_id, r.status, r.published_at 
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

$student = [
    'nama' => $data['nama'],
    'nis' => $data['nis'],
    'nisn' => $data['nisn'],
    'kelas' => $data['kelas'],
    'nama_ayah' => $data['nama_ayah'],
    'nama_ibu' => $data['nama_ibu'],
    'nama_wali' => $data['nama_wali'],
];

$report = [
    'semester' => (int)$data['semester'],
    'school_year' => $data['school_year'],
    'status' => 'published',
];

// Ambil nilai akademik
$stmtAcad = $pdo->prepare("SELECT * FROM academic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy ORDER BY id ASC");
$stmtAcad->execute(['sid' => $data['student_id'], 'sem' => $data['semester'], 'sy' => $data['school_year']]);
$academicGrades = $stmtAcad->fetchAll();

// Ambil nilai tahfidh
$stmtTah = $pdo->prepare("SELECT * FROM tahfidh_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy ORDER BY id ASC");
$stmtTah->execute(['sid' => $data['student_id'], 'sem' => $data['semester'], 'sy' => $data['school_year']]);
$tahfidhGrades = $stmtTah->fetchAll();

$sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $data['nama']);
$filename = "Rapor_{$sanitizedName}_Semester_{$data['semester']}.pdf";

generate_rapor_pdf($student, $academicGrades, $tahfidhGrades, $report, 'I', $filename);
exit;
