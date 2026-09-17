<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../includes/fungsi.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/pdf_helper.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $semester = (int)($_POST['semester'] ?? 2);
    $schoolYear = trim($_POST['school_year'] ?? '2025/2026');
    $action = trim($_POST['action'] ?? 'publish');

    if ($studentId) {
        $stmtS = $pdo->prepare("SELECT s.*, g.nama_ayah, g.nama_ibu, g.nama_wali 
                                FROM students s 
                                LEFT JOIN guardians g ON g.student_id = s.id 
                                WHERE s.id = :id LIMIT 1");
        $stmtS->execute(['id' => $studentId]);
        $student = $stmtS->fetch();

        if ($student) {
            if ($action === 'publish') {
                // Pastikan folder uploads/rapor ada
                $uploadDir = __DIR__ . '/../../uploads/rapor';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Ambil nilai akademik & tahfidh
                $stmtAcad = $pdo->prepare("SELECT * FROM academic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
                $stmtAcad->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
                $academicGrades = $stmtAcad->fetchAll();

                $stmtTah = $pdo->prepare("SELECT * FROM tahfidh_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
                $stmtTah->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
                $tahfidhGrades = $stmtTah->fetchAll();

                $stmtArab = $pdo->prepare("SELECT * FROM arabic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy ORDER BY urutan ASC, id ASC");
                $stmtArab->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
                $arabicGrades = $stmtArab->fetchAll();

                $stmtNotes = $pdo->prepare("SELECT * FROM arabic_notes WHERE student_id = :sid AND semester = :sem AND school_year = :sy LIMIT 1");
                $stmtNotes->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
                $arabicNotes = $stmtNotes->fetch() ?: [];

                // Ambil catatan tahfidh
                $stmtTNotes = $pdo->prepare("SELECT * FROM tahfidh_notes WHERE student_id = :sid AND semester = :sem AND school_year = :sy LIMIT 1");
                $stmtTNotes->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
                $tahfidhNotes = $stmtTNotes->fetch() ?: [];

                // Ambil data laporan existing agar tanda tangan dan catatan tersimpan tetap utuh
                $stmtRep = $pdo->prepare("SELECT * FROM reports WHERE student_id = :sid AND semester = :sem AND school_year = :sy LIMIT 1");
                $stmtRep->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);
                $tempReport = $stmtRep->fetch() ?: [];
                $tempReport['semester'] = $semester;
                $tempReport['school_year'] = $schoolYear;
                $tempReport['status'] = 'published';
                if (empty($tempReport['nama_wali_kelas']) && !empty($student['kelas'])) {
                    $tempReport['nama_wali_kelas'] = get_wali_kelas_by_class($pdo, (string)$student['kelas']);
                }

                // Simpan PDF fisik dengan hash unik
                $uniqueHash = hash('sha256', "student_{$studentId}_sem_{$semester}_sy_{$schoolYear}");
                $pdfFileName = "rapor_{$studentId}_{$semester}_{$uniqueHash}.pdf";
                $pdfFullPath = $uploadDir . '/' . $pdfFileName;

                generate_rapor_pdf($student, $academicGrades, $tahfidhGrades, $tempReport, 'F', $pdfFullPath, $arabicGrades, $arabicNotes, $tahfidhNotes);

                // Update database
                $stmtUp = $pdo->prepare("INSERT INTO reports (student_id, semester, school_year, status, pdf_path, published_at) 
                                         VALUES (:sid, :sem, :sy, 'published', :pdf, NOW())
                                         ON DUPLICATE KEY UPDATE status = 'published', pdf_path = VALUES(pdf_path), published_at = NOW()");
                $stmtUp->execute([
                    'sid' => $studentId,
                    'sem' => $semester,
                    'sy' => $schoolYear,
                    'pdf' => 'uploads/rapor/' . $pdfFileName,
                ]);

                set_flash('success', "Rapor {$student['nama']} berhasil dipublikasikan ke portal publik.");
            } else {
                // Unpublish
                $stmtUp = $pdo->prepare("UPDATE reports SET status = 'ready', published_at = NULL WHERE student_id = :sid AND semester = :sem AND school_year = :sy");
                $stmtUp->execute(['sid' => $studentId, 'sem' => $semester, 'sy' => $schoolYear]);

                set_flash('info', "Rapor {$student['nama']} telah ditarik dari publik (status diubah menjadi Ready/Internal).");
            }
        }
    }
}

redirect('/staff/rapor/index.php');
