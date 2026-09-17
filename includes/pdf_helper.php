<?php

declare(strict_types=1);

if (!defined('_SYSTEM_TTFONTS')) {
    define('_SYSTEM_TTFONTS', 'C:/Windows/Fonts/');
}
require_once __DIR__ . '/../vendor/autoload.php';

class RaporTemplatePDF extends tFPDF
{
    public function SetFont($family, $style = '', $size = 0)
    {
        parent::SetFont($family === 'Helvetica' ? 'Times' : $family, $style, $size);
    }

    public function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = ''): void
    {
        parent::Image($file, $x, $y, $w, $h, $type, $link);
    }

    public function MultiCell($w, $h, $txt, $border = 0, $align = 'L', $fill = false)
    {
        $startX = $this->x;
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin);
        $s = str_replace("\r", '', (string)$txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $this->x = $startX;
                $this->Cell($w, $h, substr($s, $j, $i - $j), $border, 2, $align, $fill);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
            }
            $l += $this->GetStringWidth($c);
            if ($l > $wmax) {
                $this->x = $startX;
                if ($sep == -1) {
                    if ($i == $j) $i++;
                    $this->Cell($w, $h, substr($s, $j, $i - $j), $border, 2, $align, $fill);
                } else {
                    $this->Cell($w, $h, substr($s, $j, $sep - $j), $border, 2, $align, $fill);
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
            } else {
                $i++;
            }
        }
        if ($i > $j) {
            $this->x = $startX;
            $this->Cell($w, $h, substr($s, $j, $i - $j), $border, 2, $align, $fill);
        }
    }

    protected function decodePng(string $file): ?array
    {
        $png = @file_get_contents($file);
        if ($png === false || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }

        $offset = 8;
        $idat = '';
        $palette = [];
        $transparency = [];
        $width = $height = $bitDepth = $colorType = $interlace = 0;
        while ($offset + 8 <= strlen($png)) {
            $length = unpack('N', substr($png, $offset, 4))[1];
            $type = substr($png, $offset + 4, 4);
            $data = substr($png, $offset + 8, $length);
            $offset += 12 + $length;
            if ($type === 'IHDR') {
                $header = unpack('Nwidth/Nheight/CbitDepth/CcolorType/Ccompression/Cfilter/Cinterlace', $data);
                $width = $header['width']; $height = $header['height'];
                $bitDepth = $header['bitDepth']; $colorType = $header['colorType']; $interlace = $header['interlace'];
            } elseif ($type === 'PLTE') {
                for ($i = 0; $i + 2 < strlen($data); $i += 3) $palette[] = [ord($data[$i]), ord($data[$i + 1]), ord($data[$i + 2])];
            } elseif ($type === 'tRNS') {
                for ($i = 0; $i < strlen($data); $i++) $transparency[$i] = ord($data[$i]);
            } elseif ($type === 'IDAT') {
                $idat .= $data;
            } elseif ($type === 'IEND') {
                break;
            }
        }
        if ($width < 1 || $height < 1 || $colorType !== 3 || $bitDepth !== 8 || $interlace !== 0 || $palette === []) return null;
        $decoded = @zlib_decode($idat);
        if ($decoded === false) return null;

        $stride = $width;
        $previous = array_fill(0, $stride, 0);
        $rawData = '';
        $position = 0;
        for ($row = 0; $row < $height; $row++) {
            $filter = ord($decoded[$position++]);
            $current = array_values(unpack('C*', substr($decoded, $position, $stride)));
            $position += $stride;
            for ($i = 0; $i < $stride; $i++) {
                $left = $i > 0 ? $current[$i - 1] : 0;
                $up = $previous[$i];
                $upLeft = $i > 0 ? $previous[$i - 1] : 0;
                if ($filter === 1) $current[$i] = ($current[$i] + $left) & 255;
                elseif ($filter === 2) $current[$i] = ($current[$i] + $up) & 255;
                elseif ($filter === 3) $current[$i] = ($current[$i] + intdiv($left + $up, 2)) & 255;
                elseif ($filter === 4) {
                    $predictor = $left + $up - $upLeft;
                    $distances = [abs($predictor - $left), abs($predictor - $up), abs($predictor - $upLeft)];
                    $predictor = [$left, $up, $upLeft][array_search(min($distances), $distances, true)];
                    $current[$i] = ($current[$i] + $predictor) & 255;
                } elseif ($filter !== 0) return null;
                $color = $palette[$current[$i]] ?? [255, 255, 255];
                $alpha = $transparency[$current[$i]] ?? 255;
                $rawData .= chr(intdiv($color[0] * $alpha + 255 * (255 - $alpha), 255));
                $rawData .= chr(intdiv($color[1] * $alpha + 255 * (255 - $alpha), 255));
                $rawData .= chr(intdiv($color[2] * $alpha + 255 * (255 - $alpha), 255));
            }
            $previous = $current;
        }
        return [$width, $height, $rawData];
    }

    public function Header(): void
    {
    }

    public function Footer(): void
    {
        $this->SetY(-10);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, 'Rapor MTs Roudlotul Qur\'an - Halaman ' . $this->PageNo(), 0, 0, 'C');
    }
}

function rapor_text(mixed $value, string $fallback = ''): string
{
    $text = trim((string)($value ?? ''));
    return $text !== '' ? $text : $fallback;
}

function rapor_arabic_text(string $text): string
{
    static $arabic;
    if ($arabic === null) {
        $arabic = new \ArPHP\I18N\Arabic();
    }
    return $arabic->utf8Glyphs($text, 100, true, true);
}

function rapor_grade(float $score): string
{
    if ($score >= 91) return 'A';
    if ($score >= 81) return 'B';
    if ($score >= 71) return 'C';
    return 'D';
}

function rapor_number_words(int $number): string
{
    $words = [
        'Nol', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan',
    ];

    if ($number < 0) return 'Minus ' . rapor_number_words(abs($number));
    if ($number < 10) return $words[$number];
    if ($number < 20) {
        return $number === 10 ? 'Sepuluh' : ($number === 11 ? 'Sebelas' : $words[$number - 10] . ' Belas');
    }
    if ($number < 100) {
        return $words[intdiv($number, 10)] . ' Puluh' . ($number % 10 ? ' ' . rapor_number_words($number % 10) : '');
    }
    if ($number < 200) return 'Seratus' . ($number > 100 ? ' ' . rapor_number_words($number - 100) : '');
    if ($number < 1000) {
        return $words[intdiv($number, 100)] . ' Ratus' . ($number % 100 ? ' ' . rapor_number_words($number % 100) : '');
    }
    if ($number < 2000) return 'Seribu' . ($number > 1000 ? ' ' . rapor_number_words($number - 1000) : '');
    if ($number < 1000000) {
        return rapor_number_words(intdiv($number, 1000)) . ' Ribu' . ($number % 1000 ? ' ' . rapor_number_words($number % 1000) : '');
    }

    return (string)$number;
}

function rapor_description(float $score): string
{
    if ($score >= 91) return 'Sangat Baik';
    if ($score >= 81) return 'Baik';
    if ($score >= 71) return 'Cukup';
    return 'Perlu Bimbingan';
}

function rapor_stored_grade(array $grade): string
{
    $predicate = strtoupper(trim((string)($grade['predikat'] ?? '')));
    return in_array($predicate, ['A+', 'A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'E'], true)
        ? $predicate
        : rapor_grade((float)($grade['score'] ?? 0));
}

function rapor_header(RaporTemplatePDF $pdf, string $title, bool $formal = false): void
{
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.25);
    $pdf->Image(__DIR__ . '/../resources/Logo_MTS.png', 18, 10, 30);
    $pdf->SetFont('Helvetica', 'B', 13);
    $pdf->SetXY(14, 12);
    $pdf->Cell(190, 7, "YAYASAN ROUDLOTUL QUR'AN AZ ZUHRI", 0, 1, 'C');
    $pdf->SetFont('Helvetica', 'B', 17);
    $pdf->SetXY(14, 19);
    $pdf->Cell(190, 7, '" MTS ROUDLOTUL QUR\'AN "', 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetXY(14, 26);
    $pdf->Cell(190, 5, 'Desa Ngampelsari Rt. 03 Ngampelsari, Candi, Sidoarjo', 0, 1, 'C');
    $pdf->SetXY(14, 31);
    $pdf->Cell(190, 5, 'Email: mtsroudlotulquran@gmail.com  Telepon: 0821-4596-4013', 0, 1, 'C');
    $pdf->SetXY(14, 36);
    $pdf->Cell(190, 5, 'SK KEMENKUMHAM Nomor AHU-0027813.AH.01.04. Tahun 2022', 0, 1, 'C');
    $pdf->Line(14, 42, 200, 42);
    $pdf->SetFont('Helvetica', 'B', $formal ? 15 : 13);
    $pdf->SetXY(14, 44);
    $pdf->Cell(187, 8, $title, 0, 1, 'C');
}

function rapor_student_info(RaporTemplatePDF $pdf, array $student, array $report, float $y = 58, bool $includeNisn = true): void
{
    $semester = ((int)($report['semester'] ?? 2) === 1) ? '1 (Ganjil)' : '2 (Genap)';
    $year = rapor_text($report['school_year'] ?? '', '2025/2026');
    $rows = [
        ['Nama Siswa', rapor_text($student['nama']), 'Kelas', rapor_text($student['kelas'])],
        ['Nomor Induk', rapor_text($student['nis']), 'Semester', $semester],
        [$includeNisn ? 'NISN' : 'Program', $includeNisn ? rapor_text($student['nisn']) : 'MTs Roudlotul Qur\'an', 'Tahun Ajaran', $year],
    ];
    $pdf->SetFont('Helvetica', 'B', 10);
    foreach ($rows as $row) {
        $pdf->SetXY(16, $y);
        $pdf->Cell(32, 7, $row[0], 0, 0);
        $pdf->Cell(60, 7, ': ' . $row[1], 0, 0);
        $pdf->Cell(32, 7, $row[2], 0, 0);
        $pdf->Cell(58, 7, ': ' . $row[3], 0, 1);
        $y += 8;
    }
}

function rapor_table_cell(RaporTemplatePDF $pdf, float $width, float $height, string $value = '', string $align = 'L', bool $fill = false): void
{
    $pdf->Cell($width, $height, $value, 1, 0, $align, $fill);
}

function rapor_biodata(RaporTemplatePDF $pdf, array $student, ?array $report = null): void
{
    rapor_header($pdf, 'DATA DIRI SISWA', true);
    $fields = [
        ['1. Nama Siswa', rapor_text($student['nama'])], ['2. Nomor Induk', rapor_text($student['nis'])],
        ['3. NIS Nasional', rapor_text($student['nisn'] ?? '')], ['4. Jenis Kelamin', rapor_text($student['jenis_kelamin'] ?? '')],
        ['5. Tempat dan Tgl Lahir', trim(rapor_text($student['tempat_lahir'] ?? '') . ', ' . rapor_text($student['tanggal_lahir'] ?? ''))],
        ['6. Agama', rapor_text($student['agama'] ?? '')], ['7. Anak Ke', rapor_text($student['anak_ke'] ?? '')],
        ['8. Status di Keluarga', rapor_text($student['status_keluarga'] ?? '')], ['9. Alamat Siswa', rapor_text($student['alamat'] ?? '')],
        ['10. Diterima di sekolah ini', ''], ['   a. Di Kelas', rapor_text($student['kelas'] ?? '')],
        ['   b. Pada Tanggal', rapor_text($student['tanggal_diterima'] ?? '')], ['11. Sekolah Asal', ''],
        ['   a. Nama Sekolah', rapor_text($student['sekolah_asal'] ?? '')], ['   b. Alamat Sekolah', rapor_text($student['alamat_sekolah_asal'] ?? '')],
        ['12. Nama Orang Tua', ''], ['   a. Ayah', rapor_text($student['nama_ayah'] ?? '')], ['   b. Ibu', rapor_text($student['nama_ibu'] ?? '')],
        ['13. Alamat Orang Tua', rapor_text($student['alamat_orang_tua'] ?? '')], ['14. Pekerjaan Orang Tua', ''],
        ['   a. Ayah', rapor_text($student['pekerjaan_ayah'] ?? '')], ['   b. Ibu', rapor_text($student['pekerjaan_ibu'] ?? '')],
        ['15. Nama Wali', rapor_text($student['nama_wali'] ?? ''), true], ['16. Alamat Wali', rapor_text($student['alamat_wali'] ?? '')],
        ['17. Pekerjaan', rapor_text($student['pekerjaan_wali'] ?? '')],
    ];
    $y = 58;
    foreach ($fields as $field) {
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetXY(20, $y); $pdf->Cell(62, 6, $field[0], 0, 0);
        $showColon = $field[2] ?? ($field[1] !== '');
        if ($showColon) {
            $pdf->Cell(4, 6, ':', 0, 0);
        }
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(108, 6, $field[1], 0, 1); $y += 6.6;
    }
    $namaKepala = !empty($student['nama_kepala_madrasah']) ? $student['nama_kepala_madrasah'] : (!empty($report['nama_kepala_madrasah']) ? $report['nama_kepala_madrasah'] : 'Nama Kepala Madrasah');
    $tglDiterima = !empty($student['tanggal_diterima']) ? date('d-m-Y', strtotime((string)$student['tanggal_diterima'])) : date('d-m-Y');
    $pdf->SetFont('Helvetica', '', 8); 
    $pdf->SetXY(125, 258); $pdf->Cell(65, 5, 'Sidoarjo, ' . $tglDiterima, 0, 1, 'C');
    $pdf->SetXY(125, 265); $pdf->Cell(65, 5, 'KEPALA MADRASAH', 0, 1, 'C');
    $pdf->SetXY(125, 290); $pdf->Cell(65, 5, '(' . $namaKepala . ')', 0, 1, 'C');
}

function rapor_academic_page(RaporTemplatePDF $pdf, array $student, array $report, array $grades, string $title): void
{
    rapor_header($pdf, $title);
    rapor_student_info($pdf, $student, $report, 57, true);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetFillColor(198, 235, 147);
    $sum = 0.0;
    $count = min(count($grades), 18);

    if ($title === 'LAPORAN HASIL BELAJAR SEMESTER') {
        
        $tableX = 6;
        $tableY = 88;
        $noWidth = 9;
        $subjectWidth = 62;
        $scoreWidth = 20;
        $gradeWidth = 78;
        $noteWidth = 31;
        $headerRowHeight = 6;
        $dataRowHeight = 7;
        $summaryLabelWidth = $noWidth + $subjectWidth;
        $summaryValueWidth = $scoreWidth + $gradeWidth;

        $pdf->Rect(4, 86, 207, 230);
        $headerX = $tableX;
        $headerY = $tableY;
        $pdf->SetXY($headerX, $headerY);
        $pdf->Cell($noWidth, $headerRowHeight * 3, 'No', 1, 0, 'C', true);
        $pdf->SetXY($headerX + $noWidth, $headerY);
        $pdf->Cell($subjectWidth, $headerRowHeight * 3, 'Mata Pelajaran', 1, 0, 'C', true);
        $pdf->SetXY($headerX + $summaryLabelWidth, $headerY);
        $pdf->Cell($scoreWidth + $gradeWidth + $noteWidth, $headerRowHeight, 'Hasil Belajar', 1, 0, 'C', true);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetXY($headerX + $summaryLabelWidth, $headerY + $headerRowHeight);
        $pdf->Cell($scoreWidth + $gradeWidth, $headerRowHeight, 'Nilai', 1, 0, 'C', true);
        $pdf->SetXY($headerX + $summaryLabelWidth + $scoreWidth + $gradeWidth, $headerY + $headerRowHeight);
        $pdf->Cell($noteWidth, $headerRowHeight * 2, 'Keterangan', 1, 0, 'C', true);
        $pdf->SetXY($headerX + $summaryLabelWidth, $headerY + ($headerRowHeight * 2));
        $pdf->Cell($scoreWidth, $headerRowHeight, 'Angka', 1, 0, 'C', true);
        $pdf->Cell($gradeWidth, $headerRowHeight, 'Huruf', 1, 0, 'C', true);
        $pdf->SetXY($tableX, $tableY + ($headerRowHeight * 3));

        $pdf->SetFont('Helvetica', '', 8);
        foreach ($grades as $i => $grade) {
            if ($i >= 18) break;
            $pdf->SetX($tableX);
            $score = (float)$grade['score'];
            $sum += $score;
            rapor_table_cell($pdf, $noWidth, $dataRowHeight, (string)($i + 1), 'C');
            rapor_table_cell($pdf, $subjectWidth, $dataRowHeight, rapor_text($grade['subject']));
            rapor_table_cell($pdf, $scoreWidth, $dataRowHeight, number_format($score, 0), 'C');
            rapor_table_cell($pdf, $gradeWidth, $dataRowHeight, rapor_number_words((int)round($score)), 'C');
            rapor_table_cell($pdf, $noteWidth, $dataRowHeight, rapor_text($grade['description'] ?? '', rapor_description($score)));
            $pdf->Ln();
        }
        for ($i = $count; $i < 18; $i++) {
            $pdf->SetX($tableX);
            rapor_table_cell($pdf, $noWidth, $dataRowHeight, (string)($i + 1), 'C');
            rapor_table_cell($pdf, $subjectWidth, $dataRowHeight);
            rapor_table_cell($pdf, $scoreWidth, $dataRowHeight);
            rapor_table_cell($pdf, $gradeWidth, $dataRowHeight);
            rapor_table_cell($pdf, $noteWidth, $dataRowHeight);
            $pdf->Ln();
        }

        $average = $count > 0 ? $sum / $count : 0;
        $pdf->SetX($tableX);
        $pdf->Cell($summaryLabelWidth, $dataRowHeight, 'Jumlah Prestasi Hasil Dasar', 1, 0, 'C');
        $pdf->Cell($summaryValueWidth, $dataRowHeight, (string)$count, 1, 0, 'C');
        $pdf->Cell($noteWidth, $dataRowHeight, '', 1, 1);
        $pdf->SetX($tableX);
        $pdf->Cell($summaryLabelWidth, $dataRowHeight, 'Nilai rata-rata', 1, 0, 'C');
        $pdf->Cell($summaryValueWidth, $dataRowHeight, number_format($average, 1), 1, 0, 'C');
        $pdf->Cell($noteWidth, $dataRowHeight, '', 1, 1);
        return;
    }

    $tableX = 16;
    $tableY = 88;
    $pdf->SetLineWidth(0);
    $pdf->Rect(14, 86, 186, 240);
    $pdf->SetLineWidth(0.25);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetXY($tableX, $tableY);
    $pdf->Cell(10, 14, 'No', 1, 0, 'C', true);
    $pdf->Cell(62, 14, 'Mata Pelajaran', 1, 0, 'C', true);
    $pdf->Cell(110, 14, 'Nilai', 1, 1, 'C', true);
    $pdf->SetXY($tableX, $tableY + 14);
    $pdf->SetFont('Helvetica', '', 8);
    foreach ($grades as $i => $grade) {
        if ($i >= 18) break; $pdf->SetX($tableX); $score = (float)$grade['score']; $sum += $score;
        rapor_table_cell($pdf, 10, 7, (string)($i + 1), 'C'); rapor_table_cell($pdf, 62, 7, rapor_text($grade['subject'])); rapor_table_cell($pdf, 20, 7, number_format($score, 0), 'C'); rapor_table_cell($pdf, 25, 7, rapor_stored_grade($grade), 'C'); rapor_table_cell($pdf, 65, 7, rapor_text($grade['description'] ?? '', rapor_description($score))); $pdf->Ln();
    }
    $count = min(count($grades), 18);
    for ($i = $count; $i < 18; $i++) { $pdf->SetX($tableX); rapor_table_cell($pdf, 10, 7, (string)($i + 1), 'C'); rapor_table_cell($pdf, 62, 7); rapor_table_cell($pdf, 20, 7); rapor_table_cell($pdf, 25, 7); rapor_table_cell($pdf, 65, 7); $pdf->Ln(); }
    $pdf->SetFillColor(198, 235, 147);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetXY(16, 230);
    $pdf->Cell(86, 7, 'Kepribadian', 1, 0, 'C', true);
    $pdf->SetXY(110, 230);
    $pdf->Cell(86, 7, 'Ketidakhadiran', 1, 1, 'C', true);

    $pdf->SetFont('Helvetica', '', 7);
    foreach (['Perilaku', 'Kedisiplinan', 'Kerapian/Kerajinan', 'Kesehatan'] as $i => $personality) {
        $rowY = 237 + ($i * 5.5);
        $pdf->SetXY(16, $rowY);
        rapor_table_cell($pdf, 12, 5.5, (string)($i + 1), 'C');
        rapor_table_cell($pdf, 52, 5.5, $personality);
        rapor_table_cell($pdf, 22, 5.5);
    }
    foreach ([['S', 'Sakit'], ['I', 'Izin'], ['A', 'Tanpa Keterangan']] as $i => $absence) {
        $rowY = 237 + ($i * 5.5);
        $pdf->SetXY(110, $rowY);
        rapor_table_cell($pdf, 15, 5.5, '(' . $absence[0] . ')', 'C');
        rapor_table_cell($pdf, 50, 5.5, $absence[1]);
        rapor_table_cell($pdf, 21, 5.5, '-');
    }

    $catatanWaliKelas = trim((string)($report['catatan_wali_kelas'] ?? ''));
    $catatanWaliMurid = trim((string)($report['catatan_wali_murid'] ?? ''));
    $pdf->Rect(16, 262, 180, 26);
    $pdf->Line(16, 275, 196, 275);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetXY(18, 264);
    $pdf->Cell(176, 5, 'Catatan Wali Kelas :', 0, 1);
    $pdf->SetXY(18, 277);
    $pdf->Cell(176, 5, 'Catatan Wali Murid :', 0, 1);
    if ($catatanWaliKelas !== '') {
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetXY(18, 270);
        $pdf->MultiCell(176, 4, $catatanWaliKelas);
    }
    if ($catatanWaliMurid !== '') {
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetXY(18, 282);
        $pdf->MultiCell(176, 4, $catatanWaliMurid);
    }

    $namaWaliKelas = rapor_text($report['nama_wali_kelas'] ?? '', 'Nama Walas');
    $namaKepala = rapor_text($report['nama_kepala_madrasah'] ?? ($student['nama_kepala_madrasah'] ?? ''), 'Nama Kepsek');
    $namaWaliSiswa = rapor_text($student['nama_wali'] ?? $student['nama_ayah'] ?? '', '........................');
    $tempatRapor = rapor_text($report['tempat_rapor'] ?? '', 'Sidoarjo');
    $pdf->SetFillColor(198, 235, 147);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetXY(16, 291);
    $pdf->Cell(180, 6, $tempatRapor . ', ' . date('d F Y'), 1, 1, 'C', true);
    $pdf->SetX(16);
    $pdf->Cell(60, 7, 'Orang Tua/Wali siswa', 1, 0, 'C');
    $pdf->Cell(60, 7, 'Wali Kelas', 1, 0, 'C');
    $pdf->Cell(60, 7, 'Kepala Madrasah', 1, 1, 'C');
    $pdf->SetX(16);
    $pdf->Cell(60, 17, '', 1, 0);
    $pdf->Cell(60, 17, '', 1, 0);
    $pdf->Cell(60, 17, '', 1, 1);
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->SetXY(16, 314);
    $pdf->Cell(60, 4, '(' . $namaWaliSiswa . ')', 0, 0, 'C');
    $pdf->SetXY(76, 314);
    $pdf->Cell(60, 4, '(' . $namaWaliKelas . ')', 0, 0, 'C');
    $pdf->SetXY(136, 314);
    $pdf->Cell(60, 4, '(' . $namaKepala . ')', 0, 0, 'C');
}

function rapor_tahfidh_page(RaporTemplatePDF $pdf, array $student, array $report, array $grades, array $notes = []): void
{
    rapor_header($pdf, 'LAPORAN HASIL KEGIATAN TAHFIDH AL-QUR\'AN');

    $pdf->AddFont('ArialUnicode', '', 'arial.ttf', true);
    $pdf->AddFont('ArialUnicode', 'B', 'arialbd.ttf', true);
    $pdf->SetFont('ArialUnicode', 'B', 18);
    $pdf->SetXY(14, 56);
    $pdf->Cell(187, 6, rapor_arabic_text('المدرسة الثانویة روضة القرآن'), 0, 1, 'C');

    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetXY(14, 63);
    $pdf->Cell(187, 6, 'TAHUN PELAJARAN (' . rapor_text($report['school_year'] ?? '', '2025/2026') . ')', 0, 1, 'C');

    $identityRows = [
        ['No. Induk', rapor_text($student['nis'] ?? '')],
        ['Nama', rapor_text($student['nama'] ?? '')],
        ['Program', "MTs Roudlotul Qur'an"],
    ];
    $pdf->SetFont('Helvetica', 'B', 10);
    foreach ($identityRows as $index => $identity) {
        $pdf->SetXY(16, 74 + ($index * 8));
        $pdf->Cell(32, 7, $identity[0], 0, 0);
        $pdf->Cell(90, 7, ': ' . $identity[1], 0, 1);
    }

    $tableX = 16;
    $tableY = 104;
    $noWidth = 14;
    $targetWidth = 50;
    $hafalanWidth = 22;
    $tajwidWidth = 42;
    $noteWidth = 54;
    $headerHeight = 9;
    $rowHeight = 8;

    $pdf->SetFillColor(198, 235, 147);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetXY($tableX, $tableY);
    $pdf->Cell($noWidth, $headerHeight * 2, 'NO', 1, 0, 'C', true);
    $pdf->Cell($targetWidth, $headerHeight * 2, 'TARGET HAFALAN', 1, 0, 'C', true);
    $pdf->Cell($hafalanWidth + $tajwidWidth, $headerHeight, 'Grade', 1, 0, 'C', true);
    $pdf->Cell($noteWidth, $headerHeight * 2, 'Keterangan', 1, 1, 'C', true);
    $pdf->SetXY($tableX + $noWidth + $targetWidth, $tableY + $headerHeight);
    $pdf->Cell($hafalanWidth, $headerHeight, 'Hafalan', 1, 0, 'C', true);
    $pdf->Cell($tajwidWidth, $headerHeight, 'Fashohah & Tajwid', 1, 1, 'C', true);

    $pdf->SetFont('Helvetica', '', 8);
    $count = min(count($grades), 5);
    for ($i = 0; $i < 5; $i++) {
        $grade = $grades[$i] ?? null;
        $score = $grade ? (float)($grade['score'] ?? 0) : 0;
        $predicate = $grade ? rapor_stored_grade($grade) : '';
        $description = $grade ? rapor_text($grade['description'] ?? '', rapor_description($score)) : '';
        $rowY = $tableY + ($headerHeight * 2) + ($i * $rowHeight);
        $pdf->SetXY($tableX, $rowY);
        rapor_table_cell($pdf, $noWidth, $rowHeight, (string)($i + 1), 'C');
        rapor_table_cell($pdf, $targetWidth, $rowHeight, $grade ? rapor_text($grade['memorization']) : '');
        rapor_table_cell($pdf, $hafalanWidth, $rowHeight, $predicate, 'C');
        rapor_table_cell($pdf, $tajwidWidth, $rowHeight, $predicate, 'C');
        rapor_table_cell($pdf, $noteWidth, $rowHeight, $description);
    }
    $tasmiY = $tableY + ($headerHeight * 2) + (5 * $rowHeight);
    $pdf->SetXY($tableX, $tasmiY);
    rapor_table_cell($pdf, $noWidth + $targetWidth, $rowHeight, "TASMI' 5 JUZ", 'C');
    rapor_table_cell($pdf, $hafalanWidth, $rowHeight);
    rapor_table_cell($pdf, $tajwidWidth, $rowHeight);
    rapor_table_cell($pdf, $noteWidth, $rowHeight);

    $diberikanDi = rapor_text($notes['diberikan_di'] ?? '', 'Sidoarjo');
    $tglRaw = $notes['tanggal'] ?? null;
    $dateValue = $tglRaw ? strtotime((string)$tglRaw) : time();
    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $tglTahfidh = date('d', $dateValue) . ' ' . $monthNames[(int)date('n', $dateValue)] . ' ' . date('Y', $dateValue);
    $namaGuru = rapor_text($notes['nama_guru'] ?? '', 'Nama Guru Tahfidh');

    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetXY(18, 180);
    $pdf->Cell(80, 6, 'GRADE  A : Melampaui Target', 0, 1);
    $pdf->SetX(18);
    $pdf->Cell(80, 6, '                B : Sesuai Target', 0, 1);
    $pdf->SetX(18);
    $pdf->Cell(80, 6, '                C : Belum sesuai target', 0, 1);
    $pdf->SetXY(150, 180);
    $pdf->Cell(70, 6, 'Diberikan di : ' . $diberikanDi, 0, 1);
    $pdf->SetX(150);
    $pdf->Cell(70, 6, 'Tanggal : ' . $tglTahfidh, 0, 1);

    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetXY(18, 215);
    $pdf->Cell(80, 6, 'Saran - saran :', 0, 1);
    for ($i = 1; $i <= 3; $i++) {
        $pdf->SetX(22);
        $pdf->Cell(165, 7, $i . '. ' . trim((string)($notes['saran_' . $i] ?? '')), 0, 1);
    }

    $pdf->SetFont('ArialUnicode', 'B', 17);
    $pdf->SetXY(120, 225);
    $pdf->Cell(65, 8, rapor_arabic_text('مدیر المعھد'), 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetXY(120, 255);
    $pdf->Cell(65, 5, '(' . $namaGuru . ')', 0, 1, 'C');
}

function rapor_arab_page(RaporTemplatePDF $pdf, array $student, array $report, array $grades = [], array $notes = []): void
{
    rapor_header($pdf, 'LAPORAN HASIL PEMBELAJARAN BAHASA ARAB');
    
    $pdf->AddFont('ArialUnicode', '', 'arial.ttf', true);
    $pdf->AddFont('ArialUnicode', 'B', 'arialbd.ttf', true);
    $pdf->SetXY(14, 56);
    $pdf->SetFont('ArialUnicode', 'B', 18);
    $pdf->Cell(187, 6, rapor_arabic_text("المدرسة الثانویة روضة القرآن"), 0, 1, 'C');
    
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetXY(14, 63);
    $pdf->Cell(187, 6, 'TAHUN PELAJARAN (' . rapor_text($report['school_year'] ?? '', '2025/2026') . ')', 0, 1, 'C');
    
    // Identitas mengikuti susunan template Bahasa Arab.
    $pdf->SetFont('Helvetica', 'B', 10);
    $identityRows = [
        ['No. Induk', rapor_text($student['nis'] ?? '')],
        ['Nama', rapor_text($student['nama'] ?? '')],
        ['Program', "MTs Roudlotul Qur'an"],
    ];
    $identityY = 74;
    foreach ($identityRows as $identity) {
        $pdf->SetXY(16, $identityY);
        $pdf->Cell(32, 7, $identity[0], 0, 0);
        $pdf->Cell(90, 7, ': ' . $identity[1], 0, 1);
        $identityY += 8;
    }
    
    // Tabel Penilaian
    $tableY = 104;
    $pdf->SetXY(16, $tableY);
    $pdf->SetFont('Helvetica', 'B', 9);
    rapor_table_cell($pdf, 16, 9, 'NO', 'C');
    rapor_table_cell($pdf, 72, 9, 'Elemen', 'C');
    rapor_table_cell($pdf, 28, 9, 'Nilai', 'C');
    rapor_table_cell($pdf, 68, 9, 'Keterangan', 'C');
    $pdf->Ln();
    
    $pdf->SetFont('Helvetica', '', 9);
    $defaultElements = [
        'Menyimak (Istima\')',
        'Berbicara (Kalam)',
        'Membaca (Qira\'ah)',
        'Menulis (Kitabah)',
    ];

    for ($i = 0; $i < 4; $i++) {
        $pdf->SetX(16);
        $grade = $grades[$i] ?? null;
        $elem = $grade ? ($grade['element'] ?? $grade['subject'] ?? $defaultElements[$i]) : $defaultElements[$i];
        
        $pred = '';
        if ($grade) {
            $pred = strtoupper(trim((string)($grade['predikat'] ?? '')));
            if (!in_array($pred, ['A', 'B', 'C'], true)) {
                $sc = (float)($grade['score'] ?? 0);
                $pred = $sc >= 90 ? 'A' : ($sc >= 80 ? 'B' : ($sc > 0 ? 'C' : ''));
            }
        }
        
        $desc = '';
        if ($grade) {
            $desc = trim((string)($grade['description'] ?? ''));
            if ($desc === '' && $pred !== '') {
                $desc = match($pred) {
                    'A' => 'Melampaui Target',
                    'B' => 'Sesuai Target',
                    'C' => 'Belum sesuai target',
                    default => ''
                };
            }
        }
        
        rapor_table_cell($pdf, 16, 9, (string)($i + 1), 'C');
        rapor_table_cell($pdf, 72, 9, '  ' . rapor_text($elem));
        rapor_table_cell($pdf, 28, 9, $pred, 'C');
        rapor_table_cell($pdf, 68, 9, '  ' . rapor_text($desc));
        $pdf->Ln();
    }
    
    // Legenda Grade & Titimangsa
    $legendY = 152;
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetXY(18, $legendY);
    $pdf->Cell(80, 5, 'GRADE  A : Melampaui Target', 0, 1);
    $pdf->SetX(18);
    $pdf->Cell(80, 5, '                B : Sesuai Target', 0, 1);
    $pdf->SetX(18);
    $pdf->Cell(80, 5, '                C : Belum sesuai target', 0, 1);
    
    $diberikanDi = rapor_text($notes['diberikan_di'] ?? '', 'Sidoarjo');
    $tglRaw = $notes['tanggal'] ?? null;
    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $dateValue = $tglRaw ? strtotime((string)$tglRaw) : time();
    $tglStr = date('d', $dateValue) . ' ' . $monthNames[(int)date('n', $dateValue)] . ' ' . date('Y', $dateValue);
    
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetXY(150, $legendY);
    $pdf->Cell(60, 5, 'Diberikan di : ' . $diberikanDi, 0, 1);
    $pdf->SetX(150);
    $pdf->Cell(60, 5, 'Tanggal : ' . $tglStr, 0, 1);
    
    // Saran - saran
    $saranY = 186;
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetXY(18, $saranY);
    $pdf->Cell(80, 6, 'Saran - saran :', 0, 1);
    
    for ($i = 1; $i <= 4; $i++) {
        $pdf->SetX(22);
        $saranText = trim((string)($notes['saran_' . $i] ?? ''));
        $pdf->Cell(165, 6, $i . '. ' . $saranText, 0, 1);
    }
    
    // Tanda Tangan Guru Pengampu
    $guruNama = rapor_text($notes['nama_guru'] ?? '', 'Nama Guru/Ustadz');
    $pdf->SetFont('ArialUnicode', 'B', 17);
    $pdf->SetXY(120, 205);
    $pdf->Cell(65, 8, rapor_arabic_text('معلم'), 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetXY(120, 225);
    $pdf->Cell(65, 5, '(' . $guruNama . ')', 0, 1, 'C');
}

function rapor_pengembangan_page(RaporTemplatePDF $pdf, array $student, array $report): void
{
    rapor_header($pdf, 'PENGEMBANGAN DIRI DAN PEMBIASAAN');
    rapor_student_info($pdf, $student, $report, 57, true);
    $green = [198, 235, 147];
    $activities = [
        'Do\'a Harian, sholat, dan surat-surat pendek',
        'Hafalan Asma\'ul Husna', 'Sholat Sunnah Dhuha Berjama\'ah',
        'Sholat Fardhu Dhuhur Berjama\'ah', 'Salim kepada Bpk/Ibu guru (datang & pulang)',
        'Hafal Tahlil dan Yasin', '',
    ];

    $pdf->SetFillColor(...$green);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetXY(16, 94);
    $pdf->Cell(76, 8, 'Pengembangan Diri', 1, 1, 'C', true);
    $pdf->SetXY(100, 94);
    $pdf->Cell(96, 8, 'Ketidakhadiran', 1, 1, 'C', true);
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->SetXY(16, 102);
    rapor_table_cell($pdf, 10, 8, 'NO', 'C');
    rapor_table_cell($pdf, 50, 8, 'Jenis Kegiatan', 'C');
    rapor_table_cell($pdf, 16, 8, 'Predikat', 'C');
    foreach ($activities as $i => $activity) {
        $pdf->SetXY(16, 110 + ($i * 7));
        rapor_table_cell($pdf, 10, 7, (string)($i + 1), 'C');
        rapor_table_cell($pdf, 50, 7, $activity);
        rapor_table_cell($pdf, 16, 7);
    }
    foreach ([['S', 'Sakit'], ['I', 'Izin'], ['A', 'Tanpa Keterangan']] as $i => $absence) {
        $pdf->SetXY(100, 102 + ($i * 8));
        rapor_table_cell($pdf, 30, 8, '(' . $absence[0] . ')', 'C');
        rapor_table_cell($pdf, 50, 8, $absence[1], 'C');
        rapor_table_cell($pdf, 16, 8, '-', 'C');
    }
    $pdf->SetFillColor(...$green);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetXY(100, 126);
    $pdf->Cell(96, 8, 'Kepribadian', 1, 1, 'C', true);
    $pdf->SetFont('Helvetica', '', 7);
    foreach (['Perilaku', 'Kedisiplinan', 'Kerapian/Kerajinan', 'Kesehatan'] as $i => $personality) {
        $pdf->SetXY(100, 134 + ($i * 7));
        rapor_table_cell($pdf, 15, 7, (string)($i + 1), 'C');
        rapor_table_cell($pdf, 65, 7, $personality);
        rapor_table_cell($pdf, 16, 7);
    }

    $pdf->SetFillColor(...$green);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetXY(16, 162);
    $pdf->Cell(12, 8, '', 1, 0, 'C', true);
    $pdf->Cell(90, 8, 'Kegiatan Belajar Pembiasaan', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Predikat', 1, 0, 'C', true);
    $pdf->Cell(43, 8, 'Keterangan', 1, 1, 'C', true);
    $pdf->SetFont('Helvetica', '', 7);
    foreach ($activities as $i => $activity) {
        $pdf->SetXY(16, 170 + ($i * 7));
        rapor_table_cell($pdf, 12, 7, $i < 6 ? chr(97 + $i) : '', 'C');
        rapor_table_cell($pdf, 90, 7, $activity);
        rapor_table_cell($pdf, 35, 7);
        rapor_table_cell($pdf, 43, 7);
    }

    $catatanWaliKelas = trim((string)($report['catatan_wali_kelas'] ?? ''));
    $catatanWaliMurid = trim((string)($report['catatan_wali_murid'] ?? ''));
    $pdf->Rect(16, 222, 180, 30);
    $pdf->Line(16, 237, 196, 237);
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetXY(18, 224);
    $pdf->Cell(176, 5, 'Catatan Wali Kelas :', 0, 1);
    $pdf->SetXY(18, 239);
    $pdf->Cell(176, 5, 'Catatan Wali Murid :', 0, 1);
    $pdf->SetFont('Helvetica', '', 7);
    if ($catatanWaliKelas !== '') { $pdf->SetXY(18, 229); $pdf->MultiCell(176, 3, $catatanWaliKelas); }
    if ($catatanWaliMurid !== '') { $pdf->SetXY(18, 244); $pdf->MultiCell(176, 3, $catatanWaliMurid); }

    $namaWk = rapor_text($report['nama_wali_kelas'] ?? '', 'Nama Wali Kelas');
    $namaKepala = rapor_text($report['nama_kepala_madrasah'] ?? ($student['nama_kepala_madrasah'] ?? ''), 'Nama Kepala Sekolah');
    $namaWaliSiswa = rapor_text($student['nama_wali'] ?? $student['nama_ayah'] ?? '', '........................');
    $tempatRapor = rapor_text($report['tempat_rapor'] ?? '', 'Sidoarjo');
    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetXY(16, 258);
    $pdf->Cell(60, 7, 'Orang Tua/Wali siswa', 0, 0, 'C');
    $pdf->SetXY(76, 258);
    $pdf->Cell(60, 7, 'Wali kelas', 0, 0, 'C');
    $pdf->SetXY(136, 258);
    $pdf->Cell(60, 5, $tempatRapor . ', ' . date('d F Y'), 0, 1, 'C');
    $pdf->SetXY(136, 263);
    $pdf->Cell(60, 5, 'Mengetahui Kepala MTs', 0, 1, 'C');
    $pdf->SetXY(136, 268);
    $pdf->Cell(60, 5, 'Rouldlotul Quran', 0, 0, 'C');
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->SetXY(16, 287);
    $pdf->Cell(60, 5, '(' . $namaWaliSiswa . ')', 0, 0, 'C');
    $pdf->Cell(60, 5, '(' . $namaWk . ')', 0, 0, 'C');
    $pdf->Cell(60, 5, '(' . $namaKepala . ')', 0, 1, 'C');
}

function generate_rapor_pdf(
    array $student,
    array $academicGrades,
    array $tahfidhGrades,
    array $report,
    string $dest = 'I',
    string $filename = 'rapor.pdf',
    array $arabicGrades = [],
    array $arabicNotes = [],
    array $tahfidhNotes = []
): string {
    // Pastikan nama_wali_kelas otomatis terisi sesuai kelas murid jika belum terisi
    if (empty($report['nama_wali_kelas']) && !empty($student['kelas'])) {
        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            $report['nama_wali_kelas'] = get_wali_kelas_by_class($pdo, (string)$student['kelas']);
        }
    }
    // Jika data arabic belum disediakan, coba query otomatis jika PDO tersedia
    if (empty($arabicGrades) && !empty($student['id'])) {
        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            $sem = (int)($report['semester'] ?? 2);
            $sy = (string)($report['school_year'] ?? '2025/2026');
            $stArab = $pdo->prepare("SELECT * FROM arabic_grades WHERE student_id = :sid AND semester = :sem AND school_year = :sy ORDER BY urutan ASC, id ASC");
            $stArab->execute(['sid' => $student['id'], 'sem' => $sem, 'sy' => $sy]);
            $arabicGrades = $stArab->fetchAll();

            if (empty($arabicNotes)) {
                $stNotes = $pdo->prepare("SELECT * FROM arabic_notes WHERE student_id = :sid AND semester = :sem AND school_year = :sy LIMIT 1");
                $stNotes->execute(['sid' => $student['id'], 'sem' => $sem, 'sy' => $sy]);
                $arabicNotes = $stNotes->fetch() ?: [];
            }
        }
    }

    // Jika data tahfidh_notes belum disediakan, coba query otomatis jika PDO tersedia
    if (empty($tahfidhNotes) && !empty($student['id'])) {
        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            $sem = (int)($report['semester'] ?? 2);
            $sy = (string)($report['school_year'] ?? '2025/2026');
            $stTNotes = $pdo->prepare("SELECT * FROM tahfidh_notes WHERE student_id = :sid AND semester = :sem AND school_year = :sy LIMIT 1");
            $stTNotes->execute(['sid' => $student['id'], 'sem' => $sem, 'sy' => $sy]);
            $tahfidhNotes = $stTNotes->fetch() ?: [];
        }
    }

    // Fallback bila tetap kosong: ambil dari academicGrades yang bermapel arab
    if (empty($arabicGrades)) {
        $arabicGrades = array_values(array_filter($academicGrades, static fn(array $grade): bool => stripos((string)$grade['subject'], 'arab') !== false));
    }

    if (empty($arabicNotes['nama_guru']) && !empty($tahfidhNotes['nama_guru'])) {
        $arabicNotes['nama_guru'] = $tahfidhNotes['nama_guru'];
    }

    if (ob_get_level()) ob_end_clean();
    $pdf = new RaporTemplatePDF('P', 'mm', [215, 330]); $pdf->SetMargins(14, 10, 14); $pdf->SetAutoPageBreak(false);
    $pdf->AddPage(); rapor_biodata($pdf, $student, $report);
    $pdf->AddPage(); rapor_academic_page($pdf, $student, $report, $academicGrades, 'LAPORAN HASIL BELAJAR SEMESTER');
    $pdf->AddPage(); rapor_academic_page($pdf, $student, $report, $academicGrades, 'HASIL SUMATIF TENGAH SEMESTER');
    $pdf->AddPage(); rapor_arab_page($pdf, $student, $report, $arabicGrades, $arabicNotes);
    $pdf->AddPage(); rapor_tahfidh_page($pdf, $student, $report, $tahfidhGrades, $tahfidhNotes);
    $pdf->AddPage(); rapor_pengembangan_page($pdf, $student, $report);
    if ($dest === 'I' || $dest === 'D') { if (!headers_sent()) { header('Content-Type: application/pdf'); header('Content-Disposition: ' . ($dest === 'D' ? 'attachment' : 'inline') . '; filename="' . $filename . '"'); header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); header('Pragma: no-cache'); header('Expires: 0'); } }
    return $pdf->Output($dest, $filename);
}

