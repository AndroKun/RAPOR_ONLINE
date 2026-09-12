<?php

declare(strict_types=1);

require_once __DIR__ . '/fpdf/fpdf.php';

class RaporTemplatePDF extends FPDF
{
    public function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = ''): void
    {
        $decodedImage = $this->decodePng($file);
        if ($decodedImage === null) {
            return;
        }

        [$imageWidth, $imageHeight, $rawData] = $decodedImage;

        $displayWidth = $w > 0 ? $w : $imageWidth / $this->k;
        $displayHeight = $h > 0 ? $h : $displayWidth * $imageHeight / $imageWidth;
        $imageName = 'I' . (count($this->images) + 1);
        $this->images[$imageName] = [
            'w' => $imageWidth,
            'h' => $imageHeight,
            'data' => gzcompress($rawData),
            'object' => 0,
        ];

        $x = $x ?? $this->x;
        $y = $y ?? $this->y;
        $this->_out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q',
            $displayWidth * $this->k,
            $displayHeight * $this->k,
            $x * $this->k,
            ($this->h - ($y + $displayHeight)) * $this->k,
            $imageName
        ));
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

    protected function _putresources()
    {
        $this->_putfonts();
        foreach ($this->images as &$image) {
            $image['object'] = $this->_newobj();
            $this->_out('<</Type /XObject /Subtype /Image');
            $this->_out('/Width ' . $image['w']);
            $this->_out('/Height ' . $image['h']);
            $this->_out('/ColorSpace /DeviceRGB /BitsPerComponent 8');
            $this->_out('/Filter /FlateDecode /Length ' . strlen($image['data']) . '>>');
            $this->_out('stream');
            $this->_out($image['data']);
            $this->_out('endstream');
            $this->_out('endobj');
        }
        unset($image);

        $this->offsets[2] = strlen($this->buffer);
        $this->_out('2 0 obj');
        $this->_out('<<');
        $this->_putresourcedict();
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putresourcedict()
    {
        parent::_putresourcedict();
        if ($this->images !== []) {
            $this->_out('/XObject <<');
            foreach ($this->images as $name => $image) {
                $this->_out('/' . $name . ' ' . $image['object'] . ' 0 R');
            }
            $this->_out('>>');
        }
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

function rapor_grade(float $score): string
{
    if ($score >= 91) return 'A';
    if ($score >= 81) return 'B';
    if ($score >= 71) return 'C';
    return 'D';
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
    return in_array($predicate, ['A', 'B', 'C', 'D', 'E'], true)
        ? $predicate
        : rapor_grade((float)($grade['score'] ?? 0));
}

function rapor_header(RaporTemplatePDF $pdf, string $title, bool $formal = false): void
{
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.45);
    $pdf->Image(__DIR__ . '/../resources/Logo_MTS.png', 18, 10, 30);
    $pdf->SetFont('Helvetica', 'B', 13);
    $pdf->SetXY(25, 12);
    $pdf->Cell(160, 7, "YAYASAN ROUDLOTUL QUR'AN AZ ZUHRI", 0, 1, 'C');
    $pdf->SetFont('Helvetica', 'B', 15);
    $pdf->Cell(190, 7, '" MTS ROUDLOTUL QUR\'AN "', 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(190, 5, 'Desa Ngampelsari Rt. 03 Ngampelsari, Candi, Sidoarjo', 0, 1, 'C');
    $pdf->Cell(190, 5, 'Email: mtsroudlotulquran@gmail.com  Telepon: 0821-4596-4013', 0, 1, 'C');
    $pdf->Cell(190, 5, 'SK KEMENKUMHAM Nomor AHU-0027813.AH.01.04. Tahun 2022', 0, 1, 'C');
    $pdf->Line(14, 44, 196, 44);
    $pdf->SetFont('Helvetica', 'B', $formal ? 15 : 13);
    $pdf->SetXY(14, 44);
    $pdf->Cell(182, 8, $title, 0, 1, 'C');
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

function rapor_biodata(RaporTemplatePDF $pdf, array $student): void
{
    rapor_header($pdf, 'DATA DIRI SISWA', true);
    $fields = [
        ['1. Nama Siswa', rapor_text($student['nama'])], ['2. Nomor Induk', rapor_text($student['nis'])],
        ['3. NIS Nasional', rapor_text($student['nisn'])], ['4. Jenis Kelamin', rapor_text($student['jenis_kelamin'])],
        ['5. Tempat dan Tgl Lahir', trim(rapor_text($student['tempat_lahir']) . ', ' . rapor_text($student['tanggal_lahir']))],
        ['6. Agama', rapor_text($student['agama'])], ['7. Anak Ke', rapor_text($student['anak_ke'])],
        ['8. Status di Keluarga', rapor_text($student['status_keluarga'])], ['9. Alamat Siswa', rapor_text($student['alamat'])],
        ['10. Diterima di sekolah ini', ''], ['   a. Di Kelas', rapor_text($student['kelas'])],
        ['   b. Pada Tanggal', rapor_text($student['tanggal_diterima'])], ['11. Sekolah Asal', ''],
        ['   a. Nama Sekolah', rapor_text($student['sekolah_asal'])], ['   b. Alamat Sekolah', rapor_text($student['alamat_sekolah_asal'])],
        ['12. Nama Orang Tua', ''], ['   a. Ayah', rapor_text($student['nama_ayah'])], ['   b. Ibu', rapor_text($student['nama_ibu'])],
        ['13. Alamat Orang Tua', rapor_text($student['alamat_orang_tua'])], ['14. Pekerjaan Orang Tua', ''],
        ['   a. Ayah', rapor_text($student['pekerjaan_ayah'])], ['   b. Ibu', rapor_text($student['pekerjaan_ibu'])],
        ['15. Nama Wali', rapor_text($student['nama_wali'])], ['16. Alamat Wali', rapor_text($student['alamat_wali'])],
        ['17. Pekerjaan', rapor_text($student['pekerjaan_wali'])],
    ];
    $pdf->SetFont('Helvetica', '', 9);
    $y = 58;
    foreach ($fields as $field) {
        $pdf->SetXY(20, $y); $pdf->Cell(62, 6, $field[0], 0, 0); $pdf->Cell(4, 6, ':', 0, 0); $pdf->Cell(108, 6, $field[1], 0, 1); $y += 6.6;
    }
    $pdf->SetFont('Helvetica', '', 8); $pdf->SetXY(125, 258); $pdf->Cell(65, 5, 'Sidoarjo, ' . date('d-m-Y'), 0, 1, 'C');
    $pdf->SetXY(125, 265); $pdf->Cell(65, 5, 'KEPALA MADRASAH', 0, 1, 'C'); $pdf->SetXY(125, 282); $pdf->Cell(65, 5, '(nama kepala sekolah)', 0, 1, 'C');
}

function rapor_academic_page(RaporTemplatePDF $pdf, array $student, array $report, array $grades, string $title): void
{
    rapor_header($pdf, $title); rapor_student_info($pdf, $student, $report, 57, true); $pdf->SetFont('Helvetica', 'B', 9); $pdf->SetFillColor(198, 235, 147);
    rapor_table_cell($pdf, 10, 9, 'No', 'C', true); rapor_table_cell($pdf, 62, 9, 'Mata Pelajaran', 'C', true); rapor_table_cell($pdf, 20, 9, 'Nilai', 'C', true); rapor_table_cell($pdf, 25, 9, 'Huruf', 'C', true); rapor_table_cell($pdf, 65, 9, 'Keterangan', 'C', true); $pdf->Ln();
    $pdf->SetFont('Helvetica', '', 8); $sum = 0.0; $count = min(count($grades), 18);
    foreach ($grades as $i => $grade) {
        if ($i >= 18) break; $score = (float)$grade['score']; $sum += $score;
        rapor_table_cell($pdf, 10, 7, (string)($i + 1), 'C'); rapor_table_cell($pdf, 62, 7, rapor_text($grade['subject'])); rapor_table_cell($pdf, 20, 7, number_format($score, 0), 'C'); rapor_table_cell($pdf, 25, 7, rapor_stored_grade($grade), 'C'); rapor_table_cell($pdf, 65, 7, rapor_text($grade['description'], rapor_description($score))); $pdf->Ln();
    }
    for ($i = $count; $i < 18; $i++) { rapor_table_cell($pdf, 10, 7, (string)($i + 1), 'C'); rapor_table_cell($pdf, 62, 7); rapor_table_cell($pdf, 20, 7); rapor_table_cell($pdf, 25, 7); rapor_table_cell($pdf, 65, 7); $pdf->Ln(); }
    $average = $count > 0 ? $sum / $count : 0; rapor_table_cell($pdf, 72, 8, 'Nilai rata-rata'); rapor_table_cell($pdf, 20, 8, number_format($average, 1), 'C'); rapor_table_cell($pdf, 25, 8, rapor_grade($average), 'C'); rapor_table_cell($pdf, 65, 8); $pdf->Ln();
    $pdf->SetFont('Helvetica', '', 8); $pdf->SetXY(16, 244); $pdf->Cell(80, 6, 'Catatan Wali Kelas :', 0, 0); $pdf->Cell(80, 6, 'Catatan Wali Murid :', 0, 1); $pdf->Rect(16, 250, 82, 20); $pdf->Rect(100, 250, 82, 20);
}

function rapor_tahfidh_page(RaporTemplatePDF $pdf, array $student, array $report, array $grades): void
{
    rapor_header($pdf, 'LAPORAN HASIL KEGIATAN TAHFIDH AL-QUR\'AN'); rapor_student_info($pdf, $student, $report, 57, false); $pdf->SetFont('Helvetica', 'B', 9);
    rapor_table_cell($pdf, 14, 9, 'NO', 'C'); rapor_table_cell($pdf, 50, 9, 'TARGET HAFALAN', 'C'); rapor_table_cell($pdf, 22, 9, 'Hafalan', 'C'); rapor_table_cell($pdf, 42, 9, 'Fashohah & Tajwid', 'C'); rapor_table_cell($pdf, 54, 9, 'Keterangan', 'C'); $pdf->Ln(); $pdf->SetFont('Helvetica', '', 8);
    $count = min(count($grades), 5);
    foreach ($grades as $i => $grade) { if ($i >= 5) break; $score = (float)$grade['score']; $predicate = rapor_stored_grade($grade); rapor_table_cell($pdf, 14, 8, (string)($i + 1), 'C'); rapor_table_cell($pdf, 50, 8, rapor_text($grade['memorization'])); rapor_table_cell($pdf, 22, 8, $predicate, 'C'); rapor_table_cell($pdf, 42, 8, $predicate, 'C'); rapor_table_cell($pdf, 54, 8, rapor_text($grade['description'], rapor_description($score))); $pdf->Ln(); }
    for ($i = $count; $i < 5; $i++) { rapor_table_cell($pdf, 14, 8, (string)($i + 1), 'C'); rapor_table_cell($pdf, 50, 8); rapor_table_cell($pdf, 22, 8); rapor_table_cell($pdf, 42, 8); rapor_table_cell($pdf, 54, 8); $pdf->Ln(); }
    rapor_table_cell($pdf, 64, 8, "TASMI' 5 JUZ", 'C'); rapor_table_cell($pdf, 22, 8); rapor_table_cell($pdf, 42, 8); rapor_table_cell($pdf, 54, 8); $pdf->Ln();
    $pdf->SetFont('Helvetica', 'B', 9); $pdf->SetXY(18, 151); $pdf->Cell(80, 6, 'GRADE  A : Melampaui Target', 0, 1); $pdf->SetX(18); $pdf->Cell(80, 6, '         B : Sesuai Target', 0, 1); $pdf->SetX(18); $pdf->Cell(80, 6, '         C : Belum sesuai target', 0, 1); $pdf->SetXY(115, 151); $pdf->Cell(70, 6, 'Diberikan di : Sidoarjo', 0, 1); $pdf->SetX(115); $pdf->Cell(70, 6, 'Tanggal : ' . date('d-m-Y'), 0, 1); $pdf->SetXY(115, 184); $pdf->Cell(70, 6, '(Nama)', 0, 1, 'C'); $pdf->SetXY(18, 205); $pdf->Cell(80, 6, 'Saran - saran :', 0, 1); for ($i = 1; $i <= 3; $i++) { $pdf->SetX(22); $pdf->Cell(80, 7, $i . '.', 0, 1); }
}

function rapor_arab_page(RaporTemplatePDF $pdf, array $student, array $report, array $grades): void
{
    rapor_header($pdf, 'LAPORAN HASIL PEMBELAJARAN BAHASA ARAB'); $pdf->SetFont('Helvetica', 'B', 12); $pdf->SetXY(14, 59); $pdf->Cell(182, 7, 'MTS ROUDLOTUL QUR\'AN', 0, 1, 'C'); $pdf->SetFont('Helvetica', 'B', 10); $pdf->SetXY(14, 68); $pdf->Cell(182, 7, 'TAHUN PELAJARAN ' . rapor_text($report['school_year'] ?? '', '2025/2026'), 0, 1, 'C'); rapor_student_info($pdf, $student, $report, 82, false); $pdf->SetY(111); $pdf->SetFont('Helvetica', 'B', 9);
    rapor_table_cell($pdf, 18, 9, 'NO', 'C'); rapor_table_cell($pdf, 62, 9, 'Elemen', 'C'); rapor_table_cell($pdf, 30, 9, 'Nilai', 'C'); rapor_table_cell($pdf, 72, 9, 'Keterangan', 'C'); $pdf->Ln(); $pdf->SetFont('Helvetica', '', 8); $count = min(count($grades), 4);
    foreach ($grades as $i => $grade) { if ($i >= 4) break; rapor_table_cell($pdf, 18, 8, (string)($i + 1), 'C'); rapor_table_cell($pdf, 62, 8, rapor_text($grade['subject'])); rapor_table_cell($pdf, 30, 8, number_format((float)$grade['score'], 0), 'C'); rapor_table_cell($pdf, 72, 8, rapor_text($grade['description'], rapor_description((float)$grade['score']))); $pdf->Ln(); }
    for ($i = $count; $i < 4; $i++) { rapor_table_cell($pdf, 18, 8, (string)($i + 1), 'C'); rapor_table_cell($pdf, 62, 8); rapor_table_cell($pdf, 30, 8); rapor_table_cell($pdf, 72, 8); $pdf->Ln(); }
    $pdf->SetFont('Helvetica', 'B', 9); $pdf->SetXY(18, 166); $pdf->Cell(80, 6, 'GRADE  A : Melampaui Target', 0, 1); $pdf->SetX(18); $pdf->Cell(80, 6, '         B : Sesuai Target', 0, 1); $pdf->SetX(18); $pdf->Cell(80, 6, '         C : Belum sesuai target', 0, 1); $pdf->SetXY(18, 205); $pdf->Cell(80, 6, 'Saran - saran :', 0, 1); for ($i = 1; $i <= 4; $i++) { $pdf->SetX(22); $pdf->Cell(80, 7, $i . '.', 0, 1); } $pdf->SetXY(125, 166); $pdf->Cell(60, 6, 'Diberikan di : Sidoarjo', 0, 1); $pdf->SetX(125); $pdf->Cell(60, 6, 'Tanggal : ' . date('d-m-Y'), 0, 1); $pdf->SetXY(125, 220); $pdf->Cell(60, 6, '(Nama Guru/Ustadz)', 0, 1, 'C');
}

function rapor_pengembangan_page(RaporTemplatePDF $pdf, array $student, array $report): void
{
    rapor_header($pdf, 'PENGEMBANGAN DIRI DAN PEMBIASAAN'); rapor_student_info($pdf, $student, $report, 57, false); $pdf->SetFont('Helvetica', 'B', 9);
    rapor_table_cell($pdf, 10, 8, 'NO', 'C'); rapor_table_cell($pdf, 75, 8, 'Jenis Kegiatan', 'C'); rapor_table_cell($pdf, 45, 8, 'Predikat', 'C'); rapor_table_cell($pdf, 52, 8, 'Keterangan', 'C'); $pdf->Ln(); $activities = ['Do\'a Harian, bacaan sholat, dan bacaan surat-surat pendek', 'Hafalan Asma\'ul Husna', 'Sholat Sunnah Dhuha Berjama\'ah', 'Sholat Fardhu Dhuhur Berjama\'ah', 'Salim kepada Bpk/Ibu guru', 'Hafal Tahlil dan Yasin', '']; $pdf->SetFont('Helvetica', '', 8);
    foreach ($activities as $i => $activity) { rapor_table_cell($pdf, 10, 9, (string)($i + 1), 'C'); rapor_table_cell($pdf, 75, 9, $activity); rapor_table_cell($pdf, 45, 9); rapor_table_cell($pdf, 52, 9); $pdf->Ln(); }
    $pdf->SetFont('Helvetica', 'B', 9); $pdf->SetXY(16, 157); $pdf->Cell(86, 8, 'Ketidakhadiran', 1, 1, 'C'); $pdf->SetFont('Helvetica', '', 8); foreach ([['S', 'Sakit'], ['I', 'Izin'], ['A', 'Tanpa Keterangan']] as $absence) { $pdf->SetX(16); rapor_table_cell($pdf, 15, 8, '(' . $absence[0] . ')', 'C'); rapor_table_cell($pdf, 50, 8, $absence[1], 'C'); rapor_table_cell($pdf, 21, 8, '-', 'C'); $pdf->Ln(); }
    $pdf->SetFont('Helvetica', 'B', 9); $pdf->SetXY(110, 157); $pdf->Cell(86, 8, 'Kepribadian', 1, 1, 'C'); foreach (['Perilaku', 'Kedisiplinan', 'Kerapian/Kerajinan', 'Kesehatan'] as $i => $personality) { $pdf->SetX(110); rapor_table_cell($pdf, 12, 8, (string)($i + 1), 'C'); rapor_table_cell($pdf, 52, 8, $personality); rapor_table_cell($pdf, 22, 8); $pdf->Ln(); }
    $pdf->SetFont('Helvetica', 'B', 9); $pdf->SetXY(16, 201); $pdf->Cell(180, 7, 'Catatan Wali Kelas :', 1, 1); $pdf->Rect(16, 208, 180, 22); $pdf->SetXY(16, 230); $pdf->Cell(180, 7, 'Catatan Wali Murid :', 1, 1); $pdf->Rect(16, 237, 180, 22); $pdf->SetFont('Helvetica', '', 8); $pdf->SetXY(18, 267); $pdf->Cell(55, 6, 'Orang Tua/Wali siswa', 0, 0, 'C'); $pdf->Cell(60, 6, 'Wali Kelas', 0, 0, 'C'); $pdf->Cell(60, 6, 'Kepala Madrasah', 0, 1, 'C'); $pdf->SetXY(18, 284); $pdf->Cell(55, 6, '(' . rapor_text($student['nama_wali'] ?? $student['nama_ayah'] ?? '', '........................') . ')', 0, 0, 'C'); $pdf->Cell(60, 6, '(Nama Wali Kelas)', 0, 0, 'C'); $pdf->Cell(60, 6, '(Nama Kepala Sekolah)', 0, 1, 'C');
}

function generate_rapor_pdf(array $student, array $academicGrades, array $tahfidhGrades, array $report, string $dest = 'I', string $filename = 'rapor.pdf'): string
{
    if (ob_get_level()) ob_end_clean();
    $pdf = new RaporTemplatePDF('P', 'mm', [215, 330]); $pdf->SetMargins(14, 10, 14); $pdf->SetAutoPageBreak(false);
    $pdf->AddPage(); rapor_biodata($pdf, $student);
    $pdf->AddPage(); rapor_academic_page($pdf, $student, $report, $academicGrades, 'LAPORAN HASIL BELAJAR SEMESTER');
    $pdf->AddPage(); rapor_academic_page($pdf, $student, $report, $academicGrades, 'HASIL SUMATIF TENGAH SEMESTER');
    $arabicGrades = array_values(array_filter($academicGrades, static fn(array $grade): bool => stripos((string)$grade['subject'], 'arab') !== false));
    $pdf->AddPage(); rapor_arab_page($pdf, $student, $report, $arabicGrades);
    $pdf->AddPage(); rapor_tahfidh_page($pdf, $student, $report, $tahfidhGrades);
    $pdf->AddPage(); rapor_pengembangan_page($pdf, $student, $report);
    if ($dest === 'I' || $dest === 'D') { if (!headers_sent()) { header('Content-Type: application/pdf'); header('Content-Disposition: ' . ($dest === 'D' ? 'attachment' : 'inline') . '; filename="' . $filename . '"'); header('Cache-Control: private, max-age=0, must-revalidate'); header('Pragma: public'); } }
    return $pdf->Output($dest, $filename);
}
