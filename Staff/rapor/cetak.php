<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/pdf_helper.php';

require_login();

$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
if (!$studentId) {
    set_flash('danger', 'Siswa tidak ditemukan.');
    redirect('/staff/rapor/index.php');
}

$semester = (int)($_GET['semester'] ?? 2);
$schoolYear = trim($_GET['school_year'] ?? '2025/2026');

// Ambil data siswa & wali
$stmt = $pdo->prepare("SELECT s.*, g.nama_ayah, g.nama_ibu, g.nama_wali 
                       FROM students s 
                       LEFT JOIN guardians g ON g.student_id = s.id 
                       WHERE s.id = :id LIMIT 1");
$stmt->execute(['id' => $studentId]);
$student = $stmt->fetch();

if (!$student) {
    http_response_code(404);
    exit('Data siswa tidak ditemukan.');
}

// Ambil data rapor
$stmtRep = $pdo->prepare("SELECT * FROM reports WHERE student_id = :sid AND semester = :sem AND school_year = :sy LIMIT 1");
$stmtRep->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
$report = $stmtRep->fetch() ?: ['semester' => $semester, 'school_year' => $schoolYear, 'status' => 'draft'];

// Ambil nilai akademik
$stmtAcad = $pdo->prepare("SELECT * FROM academic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy ORDER BY id ASC");
$stmtAcad->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
$academicGrades = $stmtAcad->fetchAll();

// Ambil nilai tahfidh
$stmtTahfidh = $pdo->prepare("SELECT * FROM tahfidh_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy ORDER BY id ASC");
$stmtTahfidh->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
$tahfidhGrades = $stmtTahfidh->fetchAll();

// Generate PDF
$sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $student['nama']);
$filename = "Rapor_{$sanitizedName}_Semester_{$semester}.pdf";

generate_rapor_pdf($student, $academicGrades, $tahfidhGrades, $report, 'I', $filename);
exit;
