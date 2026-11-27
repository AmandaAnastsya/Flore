<?php

namespace App\Support;

/**
 * Penulis file Excel .xlsx minimal (satu sheet) tanpa library & tanpa ekstensi PHP zip.
 * File .xlsx = arsip ZIP berisi XML; di sini arsip ditulis manual dengan metode "stored" (tanpa kompresi).
 * Angka disimpan sebagai angka (bisa dijumlah di Excel), teks sebagai inline string.
 */
class SimpleXlsx
{
    /** @param iterable<array> $rows */
    public static function build(string $sheetName, array $header, iterable $rows): string
    {
        $xmlRows = [self::row(1, $header, true)];
        $r = 2;
        foreach ($rows as $row) {
            $xmlRows[] = self::row($r++, array_values((array) $row));
        }

        $cols = '';
        foreach ($header as $i => $_) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="18" customWidth="1"/>';
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . "<cols>{$cols}</cols><sheetData>" . implode('', $xmlRows) . '</sheetData></worksheet>';

        $name = htmlspecialchars(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $sheetName), 0, 31), ENT_XML1);

        return self::zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
                . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
                . '<fill><patternFill patternType="solid"><fgColor rgb="FF4A2A30"/><bgColor indexed="64"/></patternFill></fill></fills>'
                . '<borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
                . '<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
                . '</styleSheet>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ]);
    }

    private static function row(int $r, array $values, bool $header = false): string
    {
        $cells = '';
        foreach ($values as $i => $v) {
            $ref = self::col($i) . $r;
            if (! $header && (is_int($v) || is_float($v) || (is_string($v) && is_numeric($v) && ! preg_match('/^0\d/', $v)))) {
                $style = (float) $v == (int) $v && abs((float) $v) >= 1000 ? ' s="2"' : '';
                $cells .= "<c r=\"{$ref}\"{$style}><v>" . (0 + $v) . '</v></c>';
            } elseif ($v !== null && $v !== '') {
                $text = htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= "<c r=\"{$ref}\" t=\"inlineStr\"" . ($header ? ' s="1"' : '') . "><is><t xml:space=\"preserve\">{$text}</t></is></c>";
            }
        }

        return "<row r=\"{$r}\">{$cells}</row>";
    }

    private static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }

        return $s;
    }

    /** Arsip ZIP "stored" (tanpa kompresi) — format yang dibaca Excel, LibreOffice & Google Sheets. */
    private static function zip(array $files): string
    {
        $data = $central = '';
        $offset = 0;
        [$time, $date] = self::dosTime();

        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $size = strlen($content);
            $len = strlen($name);

            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $time, $date, $crc, $size, $size, $len, 0) . $name;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $time, $date, $crc, $size, $size, $len, 0, 0, 0, 0, 0, $offset) . $name;
            $data .= $local . $content;
            $offset += strlen($local) + $size;
        }

        return $data . $central
            . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), $offset, 0);
    }

    private static function dosTime(): array
    {
        $t = getdate();

        return [
            ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2),
            (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'],
        ];
    }
}
