<?php

declare(strict_types=1);

require_once __DIR__ . '/fpdf/fpdf.php';

class RaporPDF extends FPDF
{
    public function Header()
    {
        // Kop Madrasah
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(26, 86, 50); // #1a5632
        $this->Cell(0, 7, "YAYASAN AZ ZUHRI", 0, 1, 'C');
        $this->SetFont('Helvetica', 'B', 16);
        $this->Cell(0, 8, "MTS TAHFIDH ROUDLOTUL QUR'AN", 0, 1, 'C');
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 5, "NSM: 121235150000 | NPSN: 69900000 | Akreditasi: B", 0, 1, 'C');
        $this->Cell(0, 5, "Alamat: Desa Ngampelsari Rt. 03 Rw. 01, Candi, Sidoarjo, Jawa Timur | Telp: 0812-3456-7890", 0, 1, 'C');
        
        // Garis Pembatas Kop
        $this->SetDrawColor(26, 86, 50);
        $this->SetLineWidth(0.8);
        $this->Line(15, 38, 195, 38);
        $this->SetLineWidth(0.2);
        $this->Line(15, 39, 195, 39);
        $this->Ln(8);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 10, "Rapor Elektronik MTs Roudlotul Qur'an - Dicetak pada: " . date('d/m/Y H:i') . " | Halaman " . $this->PageNo(), 0, 0, 'C');
    }
}

/**
 * Generate PDF binary string or stream for student report
 */
function generate_rapor_pdf(array $student, array $academicGrades, array $tahfidhGrades, array $report, string $dest = 'I', string $filename = 'rapor.pdf'): string
{
    $pdf = new RaporPDF('P', 'mm', 'A4');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->AddPage();

    // Judul Dokumen
    $pdf->SetFont('Helvetica', 'B', 13);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->Cell(0, 7, "LAPORAN HASIL BELAJAR SISWA (RAPOR)", 0, 1, 'C');
    $pdf->Ln(2);

    // Biodata Siswa Card
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetFillColor(245, 248, 246);
    $pdf->Rect(15, $pdf->GetY(), 180, 26, 'F');
    
    $semesterText = ((int)$report['semester'] === 1) ? '1 (Ganjil)' : '2 (Genap)';
    $schoolYear = $report['school_year'] ?? '2025/2026';

    $pdf->SetXY(18, $pdf->GetY() + 2);
    $pdf->Cell(35, 6, "Nama Siswa", 0, 0);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(65, 6, ": " . $student['nama'], 0, 0);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(30, 6, "Kelas", 0, 0);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(45, 6, ": " . $student['kelas'], 0, 1);

    $pdf->SetX(18);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(35, 6, "NIS / NISN", 0, 0);
    $pdf->Cell(65, 6, ": " . $student['nis'] . ' / ' . $student['nisn'], 0, 0);
    $pdf->Cell(30, 6, "Semester", 0, 0);
    $pdf->Cell(45, 6, ": " . $semesterText, 0, 1);

    $pdf->SetX(18);
    $pdf->Cell(35, 6, "Madrasah", 0, 0);
    $pdf->Cell(65, 6, ": MTs Roudlotul Qur'an", 0, 0);
    $pdf->Cell(30, 6, "Tahun Pelajaran", 0, 0);
    $pdf->Cell(45, 6, ": " . $schoolYear, 0, 1);

    $pdf->Ln(5);

    // A. CAPAIAN AKADEMIK
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(26, 86, 50);
    $pdf->Cell(0, 7, "A. PENCAPAIAN KOMPETENSI AKADEMIK", 0, 1, 'L');
    
    // Header Tabel Akademik
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(26, 86, 50);
    $pdf->SetDrawColor(200, 200, 200);

    $pdf->Cell(10, 7, "NO", 1, 0, 'C', true);
    $pdf->Cell(80, 7, "MATA PELAJARAN", 1, 0, 'L', true);
    $pdf->Cell(25, 7, "NILAI", 1, 0, 'C', true);
    $pdf->Cell(25, 7, "PREDIKAT", 1, 0, 'C', true);
    $pdf->Cell(40, 7, "KETERANGAN", 1, 1, 'L', true);

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(40, 40, 40);

    if (empty($academicGrades)) {
        $pdf->Cell(180, 7, "Belum ada data nilai akademik yang diinput.", 1, 1, 'C');
    } else {
        $no = 1;
        $totalScore = 0;
        foreach ($academicGrades as $grade) {
            $score = (float)$grade['score'];
            $totalScore += $score;
            
            // Tentukan predikat
            if ($score >= 90) $predikat = 'A (Sangat Baik)';
            elseif ($score >= 80) $predikat = 'B (Baik)';
            elseif ($score >= 70) $predikat = 'C (Cukup)';
            else $predikat = 'D (Perlu Bimbingan)';

            $desc = !empty($grade['description']) ? $grade['description'] : 'Tuntas';

            $pdf->Cell(10, 6, (string)$no++, 1, 0, 'C');
            $pdf->Cell(80, 6, " " . $grade['subject'], 1, 0, 'L');
            $pdf->Cell(25, 6, number_format($score, 1), 1, 0, 'C');
            $pdf->Cell(25, 6, $predikat, 1, 0, 'C');
            $pdf->Cell(40, 6, " " . $desc, 1, 1, 'L');
        }

        // Rata-rata
        $avg = count($academicGrades) > 0 ? $totalScore / count($academicGrades) : 0;
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(90, 6, "RATA-RATA NILAI AKADEMIK", 1, 0, 'R');
        $pdf->Cell(25, 6, number_format($avg, 2), 1, 0, 'C');
        $pdf->Cell(65, 6, "", 1, 1, 'C');
    }

    $pdf->Ln(4);

    // B. CAPAIAN TAHFIDH AL-QUR'AN
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(26, 86, 50);
    $pdf->Cell(0, 7, "B. PENCAPAIAN TAHFIDH AL-QUR'AN", 0, 1, 'L');

    // Header Tabel Tahfidh
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(26, 86, 50);

    $pdf->Cell(10, 7, "NO", 1, 0, 'C', true);
    $pdf->Cell(80, 7, "TARGET HAFALAN / SURAT / JUZ", 1, 0, 'L', true);
    $pdf->Cell(25, 7, "NILAI", 1, 0, 'C', true);
    $pdf->Cell(65, 7, "CATATAN KELANCARAN & TAJWID", 1, 1, 'L', true);

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(40, 40, 40);

    if (empty($tahfidhGrades)) {
        $pdf->Cell(180, 7, "Belum ada data nilai tahfidh yang diinput.", 1, 1, 'C');
    } else {
        $no = 1;
        foreach ($tahfidhGrades as $tahfidh) {
            $score = (float)$tahfidh['score'];
            $desc = !empty($tahfidh['description']) ? $tahfidh['description'] : 'Lancar, tajwid tartil';

            $pdf->Cell(10, 6, (string)$no++, 1, 0, 'C');
            $pdf->Cell(80, 6, " " . $tahfidh['memorization'], 1, 0, 'L');
            $pdf->Cell(25, 6, number_format($score, 1), 1, 0, 'C');
            $pdf->Cell(65, 6, " " . $desc, 1, 1, 'L');
        }
    }

    $pdf->Ln(8);

    // TANDA TANGAN
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(30, 30, 30);
    
    $todayDate = date('d F Y');
    $bulanIndo = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
        'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
        'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
        'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
    ];
    $formattedDate = strtr(date('d F Y'), $bulanIndo);

    $pdf->Cell(60, 5, "Mengetahui,", 0, 0, 'C');
    $pdf->Cell(60, 5, "", 0, 0, 'C');
    $pdf->Cell(60, 5, "Sidoarjo, " . $formattedDate, 0, 1, 'C');

    $pdf->Cell(60, 5, "Orang Tua / Wali Siswa", 0, 0, 'C');
    $pdf->Cell(60, 5, "Kepala Madrasah", 0, 0, 'C');
    $pdf->Cell(60, 5, "Wali Kelas " . $student['kelas'], 0, 1, 'C');

    $pdf->Ln(18);

    $pdf->SetFont('Helvetica', 'B', 9);
    $guardianName = !empty($student['nama_ayah']) ? $student['nama_ayah'] : (!empty($student['nama_ibu']) ? $student['nama_ibu'] : '...............................');
    $pdf->Cell(60, 5, "( " . $guardianName . " )", 0, 0, 'C');
    $pdf->Cell(60, 5, "( H. Ahmad Fauzi, S.Pd.I, M.Pd )", 0, 0, 'C');
    $pdf->Cell(60, 5, "( Ustadzah Siti Fatimah, S.Pd )", 0, 1, 'C');

    return $pdf->Output($dest, $filename);
}
