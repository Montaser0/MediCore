<?php

declare(strict_types=1);

namespace App\Core\Excel;

use RuntimeException;
use ZipArchive;

/**
 * Reads and writes a single-sheet workbook. Cells are stored as text so codes,
 * barcodes and national IDs keep their leading zeros.
 */
final class Xlsx
{
    /**
     * @param  iterable<int, list<string>>  $rows
     */
    public static function write(iterable $rows, string $sheetName): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('امتداد Zip غير متوفر على الخادم.');
        }

        $sheet = self::sheetXml($rows);
        $title = self::sheetTitle($sheetName);
        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($path === false) {
            throw new RuntimeException('تعذر إنشاء ملف الإكسل.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('تعذر إنشاء ملف الإكسل.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook($title));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        return $path;
    }

    /**
     * @return list<array{row: int, cells: list<string>}>
     */
    public static function read(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('امتداد Zip غير متوفر على الخادم.');
        }

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('الملف ليس جدول إكسل صالحاً. احفظه بصيغة xlsx.');
        }

        $shared = self::sharedStrings($zip->getFromName('xl/sharedStrings.xml') ?: '');
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');

        if ($sheet === false) {
            $sheet = self::firstSheet($zip);
        }

        $zip->close();

        if ($sheet === null || $sheet === '') {
            throw new RuntimeException('ملف الإكسل لا يحتوي على ورقة.');
        }

        return self::rows(self::plainXml($sheet), $shared);
    }

    /**
     * @param  iterable<int, list<string>>  $rows
     */
    private static function sheetXml(iterable $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $number = 1;

        foreach ($rows as $row) {
            $xml .= '<row r="'.$number.'">';
            foreach (array_values($row) as $index => $value) {
                $ref = self::columnName($index).$number;
                $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.self::xml((string) $value).'</t></is></c>';
            }
            $xml .= '</row>';
            $number++;
        }

        return $xml.'</sheetData></worksheet>';
    }

    /**
     * @param  list<string>  $shared
     * @return list<array{row: int, cells: list<string>}>
     */
    private static function rows(string $sheet, array $shared): array
    {
        $xml = simplexml_load_string($sheet);

        if ($xml === false) {
            throw new RuntimeException('تعذر قراءة ورقة الإكسل.');
        }

        $rows = [];
        $fallback = 1;
        $sheetData = $xml->sheetData ?? null;

        if ($sheetData === null) {
            return [];
        }

        foreach ($sheetData->row as $rowNode) {
            $number = (int) ($rowNode['r'] ?? 0);
            if ($number <= 0) {
                $number = $fallback;
            }
            $fallback = $number + 1;

            $indexed = [];
            $max = -1;

            foreach ($rowNode->c as $cell) {
                $column = self::columnIndex((string) ($cell['r'] ?? ''));
                $indexed[$column] = self::cellValue($cell, $shared);
                $max = max($max, $column);
            }

            $dense = [];
            for ($index = 0; $index <= $max; $index++) {
                $dense[] = $indexed[$index] ?? '';
            }

            $rows[] = ['row' => $number, 'cells' => $dense];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $shared
     */
    private static function cellValue(\SimpleXMLElement $cell, array $shared): string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            return self::textValue($cell->is ?? $cell);
        }

        $raw = trim((string) ($cell->v ?? ''));

        if ($type === 's') {
            return $shared[(int) $raw] ?? '';
        }

        if ($type === 'b') {
            return $raw === '1' ? 'نعم' : 'لا';
        }

        return $raw;
    }

    private static function textValue(\SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            $text = '';
            foreach ($node->t as $item) {
                $text .= (string) $item;
            }

            if ($text !== '') {
                return $text;
            }
        }

        $text = '';
        foreach ($node->children() as $child) {
            $text .= self::textValue($child);
        }

        return $text;
    }

    /**
     * @return list<string>
     */
    private static function sharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $document = simplexml_load_string(self::plainXml($xml));

        if ($document === false) {
            return [];
        }

        $strings = [];

        foreach ($document->si as $item) {
            $strings[] = self::textValue($item);
        }

        return $strings;
    }

    private static function plainXml(string $xml): string
    {
        $xml = preg_replace('/xmlns(:\w+)?="[^"]*"/', '', $xml) ?? $xml;
        $xml = preg_replace('/<(\/?)[A-Za-z0-9]+:/', '<$1', $xml) ?? $xml;

        return preg_replace('/\s[A-Za-z0-9]+:([A-Za-z0-9]+)=/', ' $1=', $xml) ?? $xml;
    }

    private static function firstSheet(ZipArchive $zip): ?string
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (is_string($name) && str_starts_with($name, 'xl/worksheets/sheet')) {
                $contents = $zip->getFromName($name);

                return $contents === false ? null : $contents;
            }
        }

        return null;
    }

    private static function columnIndex(string $reference): int
    {
        $letters = preg_replace('/\d/', '', strtoupper($reference)) ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $char) {
            $index = ($index * 26) + (ord($char) - 64);
        }

        return max(0, $index - 1);
    }

    private static function columnName(int $index): string
    {
        $name = '';
        $index++;

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private static function sheetTitle(string $name): string
    {
        $name = preg_replace('/[:\\\\\\/\\?\\*\\[\\]]/u', '', $name) ?? '';
        $name = trim($name);

        if ($name === '') {
            $name = 'Sheet1';
        }

        return mb_substr($name, 0, 31);
    }

    private static function xml(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbook(string $title): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::xml($title).'" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }
}
