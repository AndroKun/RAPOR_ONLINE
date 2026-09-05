<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../includes/fungsi.php';
require_once __DIR__ . '/../includes/pdf_helper.php';

$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$reportId) {
    http_response_code(404);
    exit('Rapor tidak ditemukan.');
}

// Hanya ambil rapor yang berstatus 'published'
$stmt = $pdo->prepare("SELECT r.*, s.nama, s.nis, s.nisn, s.kelas, s.tempat_lahir, s.tanggal_lahir,
                              g.nama_ayah, g.nama_ibu, g.nama_wali 
                       FROM reports r
                       INNER JOIN students s ON s.id = r.student_id
                       LEFT JOIN guardians g ON g.student_id = s.id
                       WHERE r.id = :id AND r.status = 'published' 
                       LIMIT 1");
$stmt->execute(['id' => $reportId]);
$data = $stmt->fetch();

if (!$data) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Rapor Tidak Tersedia</title>';
    echo '<link rel="stylesheet" href="' . e(base_url('/assets/css/desainrapor.css')) . '">';
    echo '</head><body><div class="container" style="text-align:center; margin-top:80px;">';
    echo '<div class="result-card" style="border-left-color:#e74c3c;">';
    echo '<h3 style="color:#c0392b;">Rapor Belum Tersedia / Tidak Ditemukan</h3>';
    echo '<p>Rapor ini belum dipublikasikan secara resmi oleh pihak madrasah atau ID rapor tidak valid.</p>';
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
