<?php

namespace App\Support;

use DateTimeInterface;

/**
 * Membuat file Excel (.xlsx) sederhana tanpa pustaka tambahan: satu lembar berisi judul,
 * keterangan, baris judul kolom (dengan filter Excel), dan baris data.
 * File .xlsx adalah arsip zip berisi beberapa file XML; arsipnya disusun langsung di sini.
 */
final class Xlsx
{
    // Indeks gaya sel di styles.xml
    private const S_TITLE = 1, S_HEAD = 2, S_DATE = 3, S_WRAP = 4, S_TOP = 5, S_SUB = 6;

    /**
     * @param array<int, array{0:string,1:float,2?:bool}> $columns  [judul kolom, lebar, teks dibungkus?]
     * @param array<int, array<int, string|int|float|DateTimeInterface|null>> $rows
     */
    public static function build(string $sheet, string $title, string $subtitle, array $columns, array $rows): string
    {
        $last = self::col(count($columns) - 1);
        $headRow = 4;
        $lastRow = $headRow + max(count($rows), 1);
        $xml = [];

        $xml[] = self::row(1, [self::str('A1', $title, self::S_TITLE)]);
        $xml[] = self::row(2, [self::str('A2', $subtitle, self::S_SUB)]);
        $xml[] = self::row($headRow, array_map(fn ($c, $i) => self::str(self::col($i).$headRow, $c[0], self::S_HEAD), $columns, array_keys($columns)));
        foreach (array_values($rows) as $n => $cells) {
            $r = $headRow + 1 + $n;
            $out = [];
            foreach (array_values($cells) as $i => $v) {
                $ref = self::col($i).$r;
                if ($v === null || $v === '') {
                    continue;
                }
                if ($v instanceof DateTimeInterface) {
                    // Tanggal Excel = jumlah hari sejak 30 Des 1899, memakai jam lokal tanggal tersebut.
                    $serial = 25569 + ($v->getTimestamp() + $v->getOffset()) / 86400;
                    $out[] = '<c r="'.$ref.'" s="'.self::S_DATE.'"><v>'.round($serial, 6).'</v></c>';
                } elseif (is_int($v) || is_float($v)) {
                    $out[] = '<c r="'.$ref.'" s="'.self::S_TOP.'"><v>'.$v.'</v></c>';
                } else {
                    $out[] = self::str($ref, (string) $v, !empty($columns[$i][2]) ? self::S_WRAP : self::S_TOP);
                }
            }
            $xml[] = self::row($r, $out);
        }

        $cols = '';
        foreach ($columns as $i => $c) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$c[1].'" customWidth="1"/>';
        }
        $filter = "A{$headRow}:{$last}{$lastRow}";

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$headRow.'" topLeftCell="A'.($headRow + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<cols>'.$cols.'</cols>'
            .'<sheetData>'.implode('', $xml).'</sheetData>'
            .'<autoFilter ref="'.$filter.'"/>'
            .'<pageMargins left="0.5" right="0.5" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            .'<pageSetup orientation="landscape" paperSize="9"/>'
            .'</worksheet>';

        $sheetName = self::esc(mb_substr($sheet, 0, 31));
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$sheetName.'" sheetId="1" r:id="rId1"/></sheets>'
            .'<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\''.$sheetName.'\'!$A$'.$headRow.':$'.$last.'$'.$lastRow.'</definedName></definedNames>'
            .'</workbook>';

        return self::zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => $workbook,
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>',
            'xl/worksheets/sheet1.xml' => $sheetXml,
            'xl/styles.xml' => self::styles(),
        ]);
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="dd/mm/yyyy hh:mm"/></numFmts>'
            .'<fonts count="4">'
            .'<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="14"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><i/><sz val="10"/><color rgb="FF5B6770"/><name val="Calibri"/><family val="2"/></font>'
            .'</fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE5EEF3"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left/><right/><top/><bottom style="thin"><color rgb="FF17587A"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="left" vertical="top"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private static function row(int $r, array $cells): string
    {
        return '<row r="'.$r.'">'.implode('', $cells).'</row>';
    }

    private static function str(string $ref, string $text, int $style): string
    {
        return '<c r="'.$ref.'" t="inlineStr" s="'.$style.'"><is><t xml:space="preserve">'.self::esc($text).'</t></is></c>';
    }

    /** Teks aman untuk XML: karakter kontrol yang tidak diizinkan XML dibuang. */
    private static function esc(string $s): string
    {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';

        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** Nomor kolom (mulai 0) menjadi huruf kolom Excel: 0 = A, 25 = Z, 26 = AA. */
    private static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    /** Arsip zip tanpa kompresi (metode "stored"), cukup untuk dibuka Excel, LibreOffice, dan Google Sheets. */
    private static function zip(array $files): string
    {
        $t = getdate();
        $time = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
        $date = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
        $out = '';
        $central = '';
        foreach ($files as $name => $data) {
            $crc = crc32($data);
            $len = strlen($data);
            $offset = strlen($out);
            $out .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $time, $date, $crc, $len, $len, strlen($name), 0).$name.$data;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $time, $date, $crc, $len, $len, strlen($name), 0, 0, 0, 0, 0, $offset).$name;
        }

        return $out.$central.pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), strlen($out), 0);
    }
}
