<?php
/*******************************************************************************
* FPDF - Free PDF generator class for PHP                                      *
* Version: 1.86                                                                *
*******************************************************************************/

define('FPDF_VERSION', '1.86');

class FPDF
{
    protected $page;               // current page number
    protected $n;                  // current object number
    protected $offsets;            // array of object offsets
    protected $buffer;             // buffer holding in-memory PDF
    protected $pages;              // array containing pages
    protected $state;              // current document state
    protected $compress;           // compression flag
    protected $k;                  // scale factor (number of points in user unit)
    protected $DefOrientation;     // default orientation
    protected $CurOrientation;     // current orientation
    protected $StdPageSizes;       // standard page sizes
    protected $DefPageSize;        // default page size
    protected $CurPageSize;        // current page size
    protected $CurRotation;        // current page rotation
    protected $PageInfo;           // page-related data
    protected $wPt, $hPt;          // dimensions of current page in points
    protected $w, $h;              // dimensions of current page in user units
    protected $lMargin;            // left margin
    protected $tMargin;            // top margin
    protected $rMargin;            // right margin
    protected $bMargin;            // page break margin
    protected $cMargin;            // cell margin
    protected $x, $y;              // current position in user units
    protected $lasth;              // height of last printed cell
    protected $LineWidth;          // line width in user units
    protected $fontpath;           // path containing fonts
    protected $CoreFonts;          // array of core font names
    protected $fonts;              // array of used fonts
    protected $FontFiles;          // array of font files
    protected $encodings;          // array of encodings
    protected $cmaps;              // array of ToUnicode CMaps
    protected $FontFamily;         // current font family
    protected $FontStyle;          // current font style
    protected $underline;          // underlining flag
    protected $CurrentFont;        // current font info
    protected $FontSizePt;         // current font size in points
    protected $FontSize;           // current font size in user units
    protected $DrawColor;          // commands for drawing color
    protected $FillColor;          // commands for filling color
    protected $TextColor;          // commands for text color
    protected $ColorFlag;          // indicates whether fill and text colors are different
    protected $WithAlpha;          // indicates whether alpha channel is used
    protected $ws;                 // word spacing
    protected $images;             // array of used images
    protected $PageLinks;          // array of links in pages
    protected $links;              // array of internal links
    protected $AutoPageBreak;      // automatic page breaking
    protected $PageBreakTrigger;   // threshold used to trigger page breaks
    protected $InHeader;           // flag set when processing header
    protected $InFooter;           // flag set when processing footer
    protected $AliasNbPages;       // alias for total number of pages
    protected $ZoomMode;           // zoom mode
    protected $LayoutMode;         // layout mode
    protected $metadata;           // document properties
    protected $PDFVersion;         // PDF version number

    public function __construct($orientation='P', $unit='mm', $size='A4')
    {
        $this->state = 0;
        $this->page = 0;
        $this->n = 2;
        $this->buffer = '';
        $this->pages = [];
        $this->PageInfo = [];
        $this->fonts = [];
        $this->FontFiles = [];
        $this->encodings = [];
        $this->cmaps = [];
        $this->images = [];
        $this->links = [];
        $this->offsets = [];
        $this->InHeader = false;
        $this->InFooter = false;
        $this->lasth = 0;
        $this->FontFamily = '';
        $this->FontStyle = '';
        $this->FontSizePt = 12;
        $this->underline = false;
        $this->DrawColor = '0 G';
        $this->FillColor = '0 g';
        $this->TextColor = '0 g';
        $this->ColorFlag = false;
        $this->WithAlpha = false;
        $this->ws = 0;
        $this->fontpath = '';
        $this->CoreFonts = ['courier', 'helvetica', 'times', 'symbol', 'zapfdingbats'];

        if ($unit == 'pt') $this->k = 1;
        elseif ($unit == 'mm') $this->k = 72/25.4;
        elseif ($unit == 'cm') $this->k = 72/2.54;
        elseif ($unit == 'in') $this->k = 72;
        else $this->Error('Incorrect unit: '.$unit);

        $this->StdPageSizes = [
            'a3' => [841.89, 1190.55],
            'a4' => [595.28, 841.89],
            'a5' => [420.94, 595.28],
            'letter' => [612, 792],
            'legal' => [612, 1008]
        ];
        $size = $this->_getpagesize($size);
        $this->DefPageSize = $size;
        $this->CurPageSize = $size;

        $orientation = strtolower($orientation);
        if ($orientation == 'p' || $orientation == 'portrait') {
            $this->DefOrientation = 'P';
            $this->w = $size[0];
            $this->h = $size[1];
        } elseif ($orientation == 'l' || $orientation == 'landscape') {
            $this->DefOrientation = 'L';
            $this->w = $size[1];
            $this->h = $size[0];
        } else {
            $this->Error('Incorrect orientation: '.$orientation);
        }
        $this->CurOrientation = $this->DefOrientation;
        $this->wPt = $this->w * $this->k;
        $this->hPt = $this->h * $this->k;

        $this->CurRotation = 0;
        $margin = 28.35 / $this->k;
        $this->SetMargins($margin, $margin);
        $this->cMargin = $margin / 10;
        $this->LineWidth = .567 / $this->k;
        $this->SetAutoPageBreak(true, 2 * $margin);
        $this->SetDisplayMode('default');
        $this->SetCompression(true);
        $this->PDFVersion = '1.3';
    }

    public function SetMargins($left, $top, $right=null)
    {
        $this->lMargin = $left;
        $this->tMargin = $top;
        if ($right === null) $right = $left;
        $this->rMargin = $right;
    }

    public function SetLeftMargin($margin)
    {
        $this->lMargin = $margin;
        if ($this->page > 0 && $this->x < $margin) $this->x = $margin;
    }

    public function SetTopMargin($margin)
    {
        $this->tMargin = $margin;
    }

    public function SetRightMargin($margin)
    {
        $this->rMargin = $margin;
    }

    public function SetAutoPageBreak($auto, $margin=0)
    {
        $this->AutoPageBreak = $auto;
        $this->bMargin = $margin;
        $this->PageBreakTrigger = $this->h - $margin;
    }

    public function SetDisplayMode($zoom, $layout='default')
    {
        if ($zoom=='fullpage' || $zoom=='fullwidth' || $zoom=='real' || $zoom=='default' || !is_string($zoom))
            $this->ZoomMode = $zoom;
        else
            $this->Error('Incorrect zoom display mode: '.$zoom);
        if ($layout=='single' || $layout=='continuous' || $layout=='two' || $layout=='default')
            $this->LayoutMode = $layout;
        else
            $this->Error('Incorrect layout display mode: '.$layout);
    }

    public function SetCompression($compress)
    {
        if (function_exists('gzcompress'))
            $this->compress = $compress;
        else
            $this->compress = false;
    }

    public function SetTitle($title, $isUTF8=false)
    {
        $this->metadata['Title'] = $isUTF8 ? $title : utf8_encode($title);
    }

    public function SetAuthor($author, $isUTF8=false)
    {
        $this->metadata['Author'] = $isUTF8 ? $author : utf8_encode($author);
    }

    public function Error($msg)
    {
        throw new Exception('FPDF error: '.$msg);
    }

    public function Open()
    {
        $this->state = 1;
    }

    public function Close()
    {
        if ($this->state == 3) return;
        if ($this->page == 0) $this->AddPage();
        $this->InFooter = true;
        $this->Footer();
        $this->InFooter = false;
        $this->_endpage();
        $this->_enddoc();
    }

    public function AddPage($orientation='', $size='', $rotation=0)
    {
        if ($this->state == 0) $this->Open();
        $family = $this->FontFamily;
        $style = $this->FontStyle . ($this->underline ? 'U' : '');
        $fontsize = $this->FontSizePt;
        $lw = $this->LineWidth;
        $dc = $this->DrawColor;
        $fc = $this->FillColor;
        $tc = $this->TextColor;
        $cf = $this->ColorFlag;
        if ($this->page > 0) {
            $this->InFooter = true;
            $this->Footer();
            $this->InFooter = false;
            $this->_endpage();
        }
        $this->_beginpage($orientation, $size, $rotation);
        $this->_out('2 J');
        $this->LineWidth = $lw;
        $this->_out(sprintf('%.2F w', $lw * $this->k));
        if ($family) $this->SetFont($family, $style, $fontsize);
        $this->DrawColor = $dc;
        if ($dc != '0 G') $this->_out($dc);
        $this->FillColor = $fc;
        if ($fc != '0 g') $this->_out($fc);
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;
        $this->InHeader = true;
        $this->Header();
        $this->InHeader = false;
        if ($this->LineWidth != $lw) {
            $this->LineWidth = $lw;
            $this->_out(sprintf('%.2F w', $lw * $this->k));
        }
        if ($family) $this->SetFont($family, $style, $fontsize);
        if ($this->DrawColor != $dc) {
            $this->DrawColor = $dc;
            $this->_out($dc);
        }
        if ($this->FillColor != $fc) {
            $this->FillColor = $fc;
            $this->_out($fc);
        }
        $this->TextColor = $tc;
        $this->ColorFlag = $cf;
    }

    public function Header() {}
    public function Footer() {}
    public function PageNo() { return $this->page; }

    public function SetDrawColor($r, $g=null, $b=null)
    {
        if (($r==0 && $g==0 && $b==0) || $g===null)
            $this->DrawColor = sprintf('%.3F G', $r/255);
        else
            $this->DrawColor = sprintf('%.3F %.3F %.3F RG', $r/255, $g/255, $b/255);
        if ($this->page > 0) $this->_out($this->DrawColor);
    }

    public function SetFillColor($r, $g=null, $b=null)
    {
        if (($r==0 && $g==0 && $b==0) || $g===null)
            $this->FillColor = sprintf('%.3F g', $r/255);
        else
            $this->FillColor = sprintf('%.3F %.3F %.3F rg', $r/255, $g/255, $b/255);
        $this->ColorFlag = ($this->FillColor != $this->TextColor);
        if ($this->page > 0) $this->_out($this->FillColor);
    }

    public function SetTextColor($r, $g=null, $b=null)
    {
        if (($r==0 && $g==0 && $b==0) || $g===null)
            $this->TextColor = sprintf('%.3F g', $r/255);
        else
            $this->TextColor = sprintf('%.3F %.3F %.3F rg', $r/255, $g/255, $b/255);
        $this->ColorFlag = ($this->FillColor != $this->TextColor);
    }

    public function GetStringWidth($s)
    {
        $s = (string)$s;
        $cw = $this->CurrentFont['cw'];
        $w = 0;
        $l = strlen($s);
        for ($i=0; $i<$l; $i++) $w += $cw[$s[$i]] ?? 500;
        return $w * $this->FontSize / 1000;
    }

    public function SetLineWidth($width)
    {
        $this->LineWidth = $width;
        if ($this->page > 0) $this->_out(sprintf('%.2F w', $width * $this->k));
    }

    public function Line($x1, $y1, $x2, $y2)
    {
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F l S', $x1 * $this->k, ($this->h - $y1) * $this->k, $x2 * $this->k, ($this->h - $y2) * $this->k));
    }

    public function Rect($x, $y, $w, $h, $style='')
    {
        if ($style=='F') $op = 'f';
        elseif ($style=='FD' || $style=='DF') $op = 'B';
        else $op = 'S';
        $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s', $x * $this->k, ($this->h - $y) * $this->k, $w * $this->k, -$h * $this->k, $op));
    }

    public function SetFont($family, $style='', $size=0)
    {
        if ($family=='') $family = $this->FontFamily;
        else $family = strtolower($family);
        $style = strtoupper($style);
        if (strpos($style, 'U') !== false) {
            $this->underline = true;
            $style = str_replace('U', '', $style);
        } else {
            $this->underline = false;
        }
        if ($style == 'IB') $style = 'BI';
        if ($size == 0) $size = $this->FontSizePt;
        if ($this->FontFamily == $family && $this->FontStyle == $style && $this->FontSizePt == $size) return;

        // Core font setup
        if ($family == 'arial') $family = 'helvetica';
        if (!in_array($family, $this->CoreFonts)) $family = 'helvetica';

        $fontkey = $family . $style;
        if (!isset($this->fonts[$fontkey])) {
            $i = count($this->fonts) + 1;
            $name = ($family == 'helvetica') ? 'Helvetica' : (($family == 'times') ? 'Times-Roman' : 'Courier');
            if ($style == 'B') $name .= '-Bold';
            if ($style == 'I') $name .= '-Oblique';
            if ($style == 'BI') $name .= '-BoldOblique';
            
            // Standard char widths estimate
            $cw = [];
            for ($c=0; $c<256; $c++) $cw[chr($c)] = 600;
            
            $this->fonts[$fontkey] = [
                'i' => $i,
                'type' => 'core',
                'name' => $name,
                'up' => -100,
                'ut' => 50,
                'cw' => $cw
            ];
        }

        $this->FontFamily = $family;
        $this->FontStyle = $style;
        $this->FontSizePt = $size;
        $this->FontSize = $size / $this->k;
        $this->CurrentFont = $this->fonts[$fontkey];
        if ($this->page > 0) $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
    }

    public function SetFontSize($size)
    {
        if ($this->FontSizePt == $size) return;
        $this->FontSizePt = $size;
        $this->FontSize = $size / $this->k;
        if ($this->page > 0 && isset($this->CurrentFont))
            $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->CurrentFont['i'], $this->FontSizePt));
    }

    public function SetXY($x, $y)
    {
        $this->SetX($x);
        $this->SetY($y, false);
    }

    public function SetX($x)
    {
        if ($x >= 0) $this->x = $x;
        else $this->x = $this->w + $x;
    }

    public function SetY($y, $resetX=true)
    {
        if ($y >= 0) $this->y = $y;
        else $this->y = $this->h + $y;
        if ($resetX) $this->x = $this->lMargin;
    }

    public function GetX() { return $this->x; }
    public function GetY() { return $this->y; }

    public function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='')
    {
        $k = $this->k;
        if ($this->y + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AutoPageBreak) {
            $x = $this->x;
            $ws = $this->ws;
            if ($ws > 0) {
                $this->ws = 0;
                $this->_out('0 Tw');
            }
            $this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation);
            $this->x = $x;
            if ($ws > 0) {
                $this->ws = $ws;
                $this->_out(sprintf('%.3F Tw', $ws * $k));
            }
        }
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $s = '';
        if ($fill || $border == 1) {
            if ($fill) $op = ($border == 1) ? 'B' : 'f';
            else $op = 'S';
            $s .= sprintf('%.2F %.2F %.2F %.2F re %s ', $this->x * $k, ($this->h - $this->y) * $k, $w * $k, -$h * $k, $op);
        }
        if (is_string($border)) {
            $x = $this->x;
            $y = $this->y;
            if (strpos($border, 'L') !== false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x*$k, ($this->h-$y)*$k, $x*$k, ($this->h-($y+$h))*$k);
            if (strpos($border, 'T') !== false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x*$k, ($this->h-$y)*$k, ($x+$w)*$k, ($this->h-$y)*$k);
            if (strpos($border, 'R') !== false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', ($x+$w)*$k, ($this->h-$y)*$k, ($x+$w)*$k, ($this->h-($y+$h))*$k);
            if (strpos($border, 'B') !== false) $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x*$k, ($this->h-($y+$h))*$k, ($x+$w)*$k, ($this->h-($y+$h))*$k);
        }
        if ($txt !== '') {
            if ($align == 'R') $dx = $w - $this->cMargin - $this->GetStringWidth($txt);
            elseif ($align == 'C') $dx = ($w - $this->GetStringWidth($txt)) / 2;
            else $dx = $this->cMargin;
            if ($this->ColorFlag) $s .= 'q ' . $this->TextColor . ' ';
            $txt2 = str_replace(')', '\\)', str_replace('(', '\\(', str_replace('\\', '\\\\', $txt)));
            $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET', ($this->x + $dx) * $k, ($this->h - ($this->y + 0.5 * $h + 0.3 * $this->FontSize)) * $k, $txt2);
            if ($this->underline) $s .= ' ' . $this->_dounderline($this->x + $dx, $this->y + 0.5 * $h + 0.3 * $this->FontSize, $txt);
            if ($this->ColorFlag) $s .= ' Q';
        }
        if ($s) $this->_out($s);
        $this->lasth = $h;
        if ($ln > 0) {
            $this->y += $h;
            if ($ln == 1) $this->x = $this->lMargin;
        } else {
            $this->x += $w;
        }
    }

    public function Ln($h=null)
    {
        $this->x = $this->lMargin;
        if ($h === null) $this->y += $this->lasth;
        else $this->y += $h;
    }

    public function Output($dest='', $name='', $isUTF8=false)
    {
        $this->Close();
        if (strlen($name) == 0) {
            $name = 'doc.pdf';
            $dest = 'I';
        }
        $dest = strtoupper($dest);
        if ($dest == 'I') {
            $this->_checkoutput();
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="'.$name.'"');
            echo $this->buffer;
        } elseif ($dest == 'D') {
            $this->_checkoutput();
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="'.$name.'"');
            echo $this->buffer;
        } elseif ($dest == 'F') {
            $f = fopen($name, 'wb');
            if (!$f) $this->Error('Unable to create output file: '.$name);
            fwrite($f, $this->buffer, strlen($this->buffer));
            fclose($f);
        } elseif ($dest == 'S') {
            return $this->buffer;
        }
        return '';
    }

    protected function _checkoutput()
    {
        if (PHP_SAPI != 'cli') {
            if (headers_sent($file, $line)) {
                $this->Error("Some data has already been output, can't send PDF file (output started at $file:$line)");
            }
        }
    }

    protected function _getpagesize($size)
    {
        if (is_string($size)) {
            $size = strtolower($size);
            if (!isset($this->StdPageSizes[$size])) $this->Error('Unknown page size: '.$size);
            $a = $this->StdPageSizes[$size];
            return [$a[0]/$this->k, $a[1]/$this->k];
        } else {
            if ($size[0] > $size[1]) return [$size[1], $size[0]];
            else return [$size[0], $size[1]];
        }
    }

    protected function _beginpage($orientation, $size, $rotation)
    {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->PageInfo[$this->page] = [];
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->FontFamily = '';
        if ($orientation == '') $orientation = $this->DefOrientation;
        else $orientation = strtoupper($orientation[0]);
        if ($size == '') $size = $this->DefPageSize;
        else $size = $this->_getpagesize($size);
        if ($orientation != $this->CurOrientation || $size[0] != $this->CurPageSize[0] || $size[1] != $this->CurPageSize[1]) {
            if ($orientation == 'P') {
                $this->w = $size[0];
                $this->h = $size[1];
            } else {
                $this->w = $size[1];
                $this->h = $size[0];
            }
            $this->wPt = $this->w * $this->k;
            $this->hPt = $this->h * $this->k;
            $this->PageBreakTrigger = $this->h - $this->bMargin;
            $this->CurOrientation = $orientation;
            $this->CurPageSize = $size;
        }
        $this->PageInfo[$this->page]['size'] = [$this->wPt, $this->hPt];
    }

    protected function _endpage()
    {
        $this->state = 1;
    }

    protected function _dounderline($x, $y, $txt)
    {
        $up = $this->CurrentFont['up'];
        $ut = $this->CurrentFont['ut'];
        $w = $this->GetStringWidth($txt) + $this->ws * substr_count($txt, ' ');
        return sprintf('%.2F %.2F %.2F %.2F re f', $x * $this->k, ($this->h - ($y - $up / 1000 * $this->FontSize)) * $this->k, $w * $this->k, -$ut / 1000 * $this->FontSizePt);
    }

    protected function _out($s)
    {
        if ($this->state == 2) $this->pages[$this->page] .= $s . "\n";
        elseif ($this->state == 1) $this->_put($s);
        elseif ($this->state == 0) $this->Error('No page has been added yet');
        elseif ($this->state == 3) $this->Error('The document is closed');
    }

    protected function _put($s)
    {
        $this->buffer .= $s . "\n";
    }

    protected function _newobj($n=null)
    {
        if ($n === null) $n = ++$this->n;
        $this->offsets[$n] = strlen($this->buffer);
        $this->_out($n . ' 0 obj');
        return $n;
    }

    protected function _putpages()
    {
        $nb = $this->page;
        for ($n=1; $n<=$nb; $n++) {
            $this->_newobj();
            $this->_out('<</Type /Page');
            $this->_out('/Parent 1 0 R');
            $this->_out(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->PageInfo[$n]['size'][0], $this->PageInfo[$n]['size'][1]));
            $this->_out('/Resources 2 0 R');
            $this->_out('/Contents ' . ($this->n + 1) . ' 0 R>>');
            $this->_out('endobj');
            
            // Content stream
            $p = $this->pages[$n];
            $this->_newobj();
            $this->_out('<</Length ' . strlen($p) . '>>');
            $this->_out('stream');
            $this->_out($p);
            $this->_out('endstream');
            $this->_out('endobj');
        }
        $this->offsets[1] = strlen($this->buffer);
        $this->_out('1 0 obj');
        $this->_out('<</Type /Pages');
        $kids = '/Kids [';
        for ($i=0; $i<$nb; $i++) $kids .= (3 + 2 * $i) . ' 0 R ';
        $this->_out($kids . ']');
        $this->_out('/Count ' . $nb);
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putfonts()
    {
        foreach ($this->fonts as $k => $font) {
            $this->_newobj();
            $this->_out('<</Type /Font');
            $this->_out('/BaseFont /' . $font['name']);
            $this->_out('/Subtype /Type1');
            $this->_out('/Encoding /WinAnsiEncoding');
            $this->_out('>>');
            $this->_out('endobj');
        }
    }

    protected function _putresourcedict()
    {
        $this->_out('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->_out('/Font <<');
        foreach ($this->fonts as $font) $this->_out('/F' . $font['i'] . ' ' . (3 + 2 * $this->page + $font['i'] - 1) . ' 0 R');
        $this->_out('>>');
    }

    protected function _putresources()
    {
        $this->_putfonts();
        $this->offsets[2] = strlen($this->buffer);
        $this->_out('2 0 obj');
        $this->_out('<<');
        $this->_putresourcedict();
        $this->_out('>>');
        $this->_out('endobj');
    }

    protected function _putinfo()
    {
        $this->metadata['Producer'] = 'FPDF ' . FPDF_VERSION;
        $this->metadata['CreationDate'] = 'D:' . @date('YmdHis');
        foreach ($this->metadata as $key => $value) {
            $this->_out('/' . $key . ' (' . str_replace(')', '\\)', str_replace('(', '\\(', str_replace('\\', '\\\\', $value))) . ')');
        }
    }

    protected function _puttrailer()
    {
        $this->_out('/Size ' . ($this->n + 1));
        $this->_out('/Root ' . ($this->n) . ' 0 R');
        $this->_out('/Info ' . ($this->n - 1) . ' 0 R');
    }

    protected function _enddoc()
    {
        $this->_putpages();
        $this->_putresources();
        
        // Info
        $this->_newobj();
        $this->_out('<<');
        $this->_putinfo();
        $this->_out('>>');
        $this->_out('endobj');
        
        // Catalog
        $this->_newobj();
        $this->_out('<<');
        $this->_out('/Type /Catalog');
        $this->_out('/Pages 1 0 R');
        $this->_out('>>');
        $this->_out('endobj');
        
        // Cross-ref
        $o = strlen($this->buffer);
        $this->_out('xref');
        $this->_out('0 ' . ($this->n + 1));
        $this->_out('0000000000 65535 f ');
        for ($i=1; $i<=$this->n; $i++) {
            $this->_out(sprintf('%010d 00000 n ', $this->offsets[$i]));
        }
        
        // Trailer
        $this->_out('trailer');
        $this->_out('<<');
        $this->_puttrailer();
        $this->_out('>>');
        $this->_out('startxref');
        $this->_out($o);
        $this->_out('%%EOF');
        $this->state = 3;
    }
}
