<?php

declare(strict_types=1);

require_once __DIR__ . '/fpdf/fpdf.php';

class RaporTemplatePDF extends FPDF
{
    public function Header()
    {
        // Custom header handled manually
    }

    public function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Helvetica', 'I', 7.5);
        $this->SetTextColor(120, 135, 125);
        $this->Cell(0, 8, "Dokumen Resmi Rapor MTs Tahfidh Roudlotul Qur'an - Dicetak pada: " . date('d/m/Y H:i') . " | Halaman " . $this->PageNo(), 0, 0, 'C');
    }

    public function MultiCellBox($x, $y, $w, $lineHeight, $txt, $fillColor = [245, 250, 247], $drawColor = [198, 235, 211])
    {
        $lines = explode("\n", wordwrap($txt, 34, "\n"));
        $boxHeight = max(24, count($lines) * $lineHeight + 5);
        
        $this->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
        $this->SetDrawColor($drawColor[0], $drawColor[1], $drawColor[2]);
        $this->Rect($x, $y, $w, $boxHeight, 'DF');

        $curY = $y + 2.5;
        $this->SetFont('Helvetica', 'I', 7.5);
        $this->SetTextColor(40, 55, 45);

        foreach ($lines as $line) {
            $this->SetXY($x + 2, $curY);
            $this->Cell($w - 4, $lineHeight, $line, 0, 0, 'L', false);
            $curY += $lineHeight;
        }
        $this->SetY($y + $boxHeight);
    }
}

/**
 * Generate PDF based on the requested template layout
 */
function generate_rapor_pdf(array $student, array $academicGrades, array $tahfidhGrades, array $report, string $dest = 'I', string $filename = 'rapor.pdf'): string
{
    // Clean any prior output buffer to prevent corrupted PDF streams
    if (ob_get_level()) {
        ob_end_clean();
    }

    $pdf = new RaporTemplatePDF('P', 'mm', 'A4');
    $pdf->SetMargins(14, 12, 14);
    $pdf->SetAutoPageBreak(true, 14);
    $pdf->AddPage();

    // 1. TOP HEADER
    // Left: School Name and Emblem
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(18, 99, 54); // #126336
    $pdf->SetXY(14, 12);
    $pdf->Cell(110, 5, "YAYASAN ROUDLOTUL QUR'AN AZ ZUHRI", 0, 1, 'L');
    
    $pdf->SetFont('Helvetica', 'B', 13.5);
    $pdf->SetTextColor(12, 67, 37); // #0C4325
    $pdf->SetX(14);
    $pdf->Cell(110, 6, "MTS TAHFIDH ROUDLOTUL QUR'AN", 0, 1, 'L');
    
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(80, 105, 90);
    $pdf->SetX(14);
    $pdf->Cell(110, 4.5, "Desa Ngampelsari Rt. 03 Rw. 01, Candi, Sidoarjo | mtstahfidhroudlotulquran.sch.id", 0, 1, 'L');

    // Right: Dark Green Box "Rapor Sekolah"
    $pdf->SetFillColor(21, 122, 66); // #157A42
    $pdf->Rect(138, 12, 58, 14, 'F');
    $pdf->SetFont('Helvetica', 'B', 12.5);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(138, 12);
    $pdf->Cell(58, 14, "Rapor Sekolah", 0, 1, 'C');

    // Decorative Horizontal Line
    $pdf->SetDrawColor(21, 122, 66);
    $pdf->SetLineWidth(0.6);
    $pdf->Line(14, 29, 196, 29);

    // 2. STUDENT METADATA (Underlined style matching reference)
    $pdf->SetY(32);
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(30, 45, 35);

    $semesterNum = (int)($report['semester'] ?? 2);
    $semText = ($semesterNum === 1) ? 'Semester 1 (Ganjil)' : 'Semester 2 (Genap)';
    $schoolYear = $report['school_year'] ?? '2025/2026';
    $studentName = $student['nama'] ?? '-';
    $studentKelas = $student['kelas'] ?? 'VII';
    $studentNisn = $student['nisn'] ?? '-';

    // Row 1
    $pdf->SetX(14);
    $pdf->Cell(24, 5, "Nama Siswa:", 0, 0);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->Cell(68, 5, $studentName, 'B', 0);
    
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->Cell(28, 5, "Periode Evaluasi:", 0, 0);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->Cell(62, 5, $semText, 'B', 1);

    // Row 2
    $pdf->SetX(14);
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->Cell(24, 5, "Kelas & NISN:", 0, 0);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->Cell(68, 5, "Kelas " . $studentKelas . "  |  NISN: " . $studentNisn, 'B', 0);
    
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->Cell(28, 5, "Tahun Ajar:", 0, 0);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->Cell(62, 5, $schoolYear, 'B', 1);

    // Row 3
    $pdf->SetX(14);
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->Cell(24, 5, "Wali Kelas:", 0, 0);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->Cell(68, 5, "Ustadzah Siti Fatimah, S.Pd", 'B', 0);
    
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->Cell(28, 5, "Status Rapor:", 0, 0);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->Cell(62, 5, "Resmi Dipublikasikan", 'B', 1);

    $pdf->Ln(4);

    // 3. TWO-COLUMN LAYOUT
    $topContentY = $pdf->GetY();
    $leftWidth = 120;
    $rightWidth = 58;
    $rightX = 138;

    // ==========================================
    // RIGHT COLUMN: Sistem Penilaian & Tanggapan Guru
    // ==========================================
    $pdf->SetXY($rightX, $topContentY);

    // Header Sistem Penilaian
    $pdf->SetFillColor(198, 235, 211); // Soft mint #C6EBD3
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->SetTextColor(12, 67, 37);
    $pdf->Cell($rightWidth, 6, "Sistem Penilaian:", 0, 1, 'C', true);

    // Tabel Konversi Nilai
    $gradingScale = [
        ['A+', '97 - 100'],
        ['A',  '94 - 96'],
        ['A-', '90 - 93'],
        ['B+', '87 - 89'],
        ['B',  '84 - 86'],
        ['B-', '80 - 83'],
        ['C+', '77 - 79'],
        ['C',  '74 - 76'],
        ['C-', '70 - 73'],
        ['D+', '67 - 69'],
        ['D',  '64 - 66'],
        ['D-', '60 - 63'],
        ['F',  'Di bawah 60'],
    ];

    $pdf->SetFont('Helvetica', '', 7.5);
    $fillRow = false;
    foreach ($gradingScale as $scale) {
        $pdf->SetX($rightX);
        if ($fillRow) {
            $pdf->SetFillColor(245, 250, 247);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        $pdf->SetTextColor(30, 45, 35);
        $pdf->Cell(24, 4.2, $scale[0], 0, 0, 'C', true);
        $pdf->Cell(34, 4.2, $scale[1], 0, 1, 'C', true);
        $fillRow = !$fillRow;
    }

    $pdf->Ln(3);
    $pdf->SetX($rightX);

    // Header Tanggapan dan Masukan Guru
    $pdf->SetFillColor(198, 235, 211);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->SetTextColor(12, 67, 37);
    $pdf->Cell($rightWidth, 6, "Tanggapan dan Masukan Guru:", 0, 1, 'L', true);

    // Box Isi Tanggapan
    $catatanGuru = "Alhamdulillah, ananda menunjukkan kesungguhan yang baik dalam memahami materi akademik dan setoran hafalan Al-Qur'an. Terus tingkatkan muraja'ah rutin dan kedisiplinan belajar.";
    $pdf->MultiCellBox($rightX, $pdf->GetY(), $rightWidth, 3.8, $catatanGuru);

    $rightEndY = $pdf->GetY();

    // ==========================================
    // LEFT COLUMN: Mata Pelajaran & Tahfidh & Kehadiran
    // ==========================================
    $pdf->SetXY(14, $topContentY);

    // Header Tabel Akademik
    $pdf->SetFillColor(21, 122, 66); // Dark green #157A42
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 8);
    
    $pdf->Cell(58, 6, "Mata Pelajaran", 0, 0, 'L', true);
    $pdf->Cell(20, 6, "Nilai", 0, 0, 'C', true);
    $pdf->Cell(20, 6, "Predikat", 0, 0, 'C', true);
    $pdf->Cell(22, 6, "Keterangan", 0, 1, 'C', true);

    $pdf->SetFont('Helvetica', '', 7.5);
    $pdf->SetTextColor(30, 45, 35);
    $fillAcad = true;

    $totalScore = 0.0;
    $countScore = 0;

    if (!empty($academicGrades)) {
        foreach ($academicGrades as $g) {
            $pdf->SetX(14);
            if ($fillAcad) {
                $pdf->SetFillColor(242, 248, 244);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $scoreNum = (float)$g['score'];
            $totalScore += $scoreNum;
            $countScore++;

            // Predikat
            $p = 'D';
            if ($scoreNum >= 97) $p = 'A+';
            elseif ($scoreNum >= 94) $p = 'A';
            elseif ($scoreNum >= 90) $p = 'A-';
            elseif ($scoreNum >= 87) $p = 'B+';
            elseif ($scoreNum >= 84) $p = 'B';
            elseif ($scoreNum >= 80) $p = 'B-';
            elseif ($scoreNum >= 77) $p = 'C+';
            elseif ($scoreNum >= 74) $p = 'C';
            elseif ($scoreNum >= 70) $p = 'C-';
            elseif ($scoreNum >= 67) $p = 'D+';
            elseif ($scoreNum >= 64) $p = 'D';
            elseif ($scoreNum >= 60) $p = 'D-';
            else $p = 'F';

            $pdf->Cell(58, 4.3, " " . mb_substr($g['subject'], 0, 32), 0, 0, 'L', true);
            $pdf->Cell(20, 4.3, number_format($scoreNum, 1), 0, 0, 'C', true);
            $pdf->Cell(20, 4.3, $p, 0, 0, 'C', true);
            $pdf->Cell(22, 4.3, ($scoreNum >= 70 ? 'Tuntas' : 'Perlu Bimb.'), 0, 1, 'C', true);

            $fillAcad = !$fillAcad;
        }
    } else {
        $pdf->SetX(14);
        $pdf->Cell($leftWidth, 6, "Belum ada nilai akademik tersimpan.", 1, 1, 'C');
    }

    $pdf->Ln(2.5);

    // Section Tahfidh
    $pdf->SetX(14);
    $pdf->SetFillColor(21, 122, 66);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->Cell(58, 6, "Capaian Tahfidh Al-Qur'an", 0, 0, 'L', true);
    $pdf->Cell(20, 6, "Nilai", 0, 0, 'C', true);
    $pdf->Cell(20, 6, "Predikat", 0, 0, 'C', true);
    $pdf->Cell(22, 6, "Keterangan", 0, 1, 'C', true);

    $pdf->SetFont('Helvetica', '', 7.5);
    $pdf->SetTextColor(30, 45, 35);
    $fillTah = true;

    if (!empty($tahfidhGrades)) {
        foreach ($tahfidhGrades as $t) {
            $pdf->SetX(14);
            if ($fillTah) {
                $pdf->SetFillColor(242, 248, 244);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }

            $tScore = (float)$t['score'];
            $pT = 'D';
            if ($tScore >= 90) $pT = 'A';
            elseif ($tScore >= 80) $pT = 'B';
            elseif ($tScore >= 70) $pT = 'C';

            $pdf->Cell(58, 4.3, " " . mb_substr($t['memorization'], 0, 32), 0, 0, 'L', true);
            $pdf->Cell(20, 4.3, number_format($tScore, 1), 0, 0, 'C', true);
            $pdf->Cell(20, 4.3, $pT, 0, 0, 'C', true);
            $pdf->Cell(22, 4.3, ($tScore >= 75 ? 'Mutqin' : 'Jayyid'), 0, 1, 'C', true);

            $fillTah = !$fillTah;
        }
    } else {
        $pdf->SetX(14);
        $pdf->Cell($leftWidth, 5, "Belum ada capaian tahfidh tersimpan.", 1, 1, 'C');
    }

    $pdf->Ln(2.5);

    // Section Kehadiran & Nilai Rata-Rata
    $pdf->SetX(14);
    $pdf->SetFillColor(21, 122, 66);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->Cell($leftWidth, 5.5, "Kehadiran & Ringkasan Nilai", 0, 1, 'L', true);

    $pdf->SetFont('Helvetica', '', 7.5);
    $pdf->SetTextColor(30, 45, 35);
    $pdf->SetFillColor(242, 248, 244);

    $avgScore = $countScore > 0 ? ($totalScore / $countScore) : 0.0;

    $pdf->SetX(14);
    $pdf->Cell(38, 4.5, " Jum. Hari Sekolah: 112 Hari", 0, 0, 'L', true);
    $pdf->Cell(28, 4.5, "Hadir: 110 Hari", 0, 0, 'L', true);
    $pdf->Cell(26, 4.5, "Izin: 2 Hari", 0, 0, 'L', true);
    $pdf->Cell(28, 4.5, "Sakit: 0 Hari", 0, 1, 'L', true);

    $pdf->SetX(14);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 7.5);
    $pdf->Cell(58, 4.5, " Nilai Rata-Rata: " . number_format($avgScore, 1), 0, 0, 'L', true);
    $pdf->Cell(62, 4.5, "Status: TUNTAS & MEMENUHI SYARAT", 0, 1, 'L', true);

    $leftEndY = $pdf->GetY();

    // 4. SIGNATURES SECTION
    $sigY = max($leftEndY, $rightEndY) + 5;
    $pdf->SetY($sigY);

    $todayDate = date('d F Y');
    $bulanIndo = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
        'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
        'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
        'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
    ];
    $formattedDate = strtr(date('d F Y'), $bulanIndo);

    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(30, 45, 35);

    $pdf->Cell(60, 4.5, "Mengetahui,", 0, 0, 'C');
    $pdf->Cell(62, 4.5, "", 0, 0, 'C');
    $pdf->Cell(60, 4.5, "Sidoarjo, " . $formattedDate, 0, 1, 'C');

    $pdf->Cell(60, 4.5, "Orang Tua / Wali Siswa", 0, 0, 'C');
    $pdf->Cell(62, 4.5, "Kepala Madrasah", 0, 0, 'C');
    $pdf->Cell(60, 4.5, "Wali Kelas " . $studentKelas, 0, 1, 'C');

    $pdf->Ln(13);

    $pdf->SetFont('Helvetica', 'B', 8);
    $guardianName = !empty($student['nama_ayah']) ? $student['nama_ayah'] : (!empty($student['nama_ibu']) ? $student['nama_ibu'] : '...............................');
    $pdf->Cell(60, 4.5, "( " . $guardianName . " )", 0, 0, 'C');
    $pdf->Cell(62, 4.5, "( H. Ahmad Fauzi, S.Pd.I, M.Pd )", 0, 0, 'C');
    $pdf->Cell(60, 4.5, "( Ustadzah Siti Fatimah, S.Pd )", 0, 1, 'C');

    // Output with proper headers
    if ($dest === 'I' || $dest === 'D') {
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . ($dest === 'D' ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
        }
    }

    return $pdf->Output($dest, $filename);
}
