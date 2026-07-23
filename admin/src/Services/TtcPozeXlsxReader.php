<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Citește rânduri din XLSX TecDoc (Poze) — coloane A–W standard.
 */
final class TtcPozeXlsxReader
{
    /** @return \Generator<int, array<string, string>> */
    public static function readRows(string $xlsxPath): \Generator
    {
        $xlsxPath = str_replace('\\', '/', $xlsxPath);
        if (!is_file($xlsxPath)) {
            return;
        }

        if (class_exists(\ZipArchive::class)) {
            yield from self::readRowsFromZip($xlsxPath);

            return;
        }

        yield from self::readRowsViaPowerShell($xlsxPath);
    }

    /** @return \Generator<int, array<string, string>> */
    private static function readRowsFromZip(string $xlsxPath): \Generator
    {
        $zip = new \ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            return;
        }

        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if (is_string($ssXml)) {
            preg_match_all('/<t[^>]*>([^<]*)/', $ssXml, $m);
            $sharedStrings = $m[1] ?? [];
        }

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (!is_string($sheet)) {
            return;
        }

        yield from self::parseSheetXml($sheet, $sharedStrings);
    }

    /**
     * @param list<string> $sharedStrings
     * @return \Generator<int, array<string, string>>
     */
    private static function parseSheetXml(string $sheet, array $sharedStrings): \Generator
    {
        $colMap = [
            'A' => 'ttc_art_id',
            'B' => 'art_brand',
            'C' => 'art_code_1',
            'D' => 'art_code_2',
            'E' => 'art_title',
            'F' => 'art_name',
            'G' => 'art_description',
            'H' => 'art_ean',
            'J' => 'parts_info',
            'K' => 'art_cross',
            'N' => 'car_brand',
            'O' => 'car_model',
            'P' => 'car_typ',
            'Q' => 'car_body',
            'R' => 'car_of_year',
            'S' => 'car_to_year',
            'T' => 'car_kw',
            'U' => 'car_pm',
            'V' => 'car_cc',
        ];

        $rows = [];
        preg_match_all('/<c r="([A-Z]+)(\d+)"([^>]*)>(?:<v>([^<]*)<\/v>)?/', $sheet, $cells, PREG_SET_ORDER);
        foreach ($cells as $c) {
            $col = $c[1];
            $rowNum = (int) $c[2];
            if ($rowNum < 2) {
                continue;
            }
            $val = $c[4] ?? '';
            if (str_contains($c[3], 't="s"') && $val !== '' && isset($sharedStrings[(int) $val])) {
                $val = $sharedStrings[(int) $val];
            }
            if (!isset($colMap[$col])) {
                continue;
            }
            $rows[$rowNum][$colMap[$col]] = trim($val);
        }

        foreach ($rows as $row) {
            if (trim((string) ($row['ttc_art_id'] ?? '')) === '') {
                continue;
            }
            yield $row;
        }
    }

    /** @return \Generator<int, array<string, string>> */
    private static function readRowsViaPowerShell(string $xlsxPath): \Generator
    {
        if (stripos(PHP_OS_FAMILY, 'Windows') === false) {
            return;
        }

        $script = dirname(__DIR__, 2) . '/tools/_read_poze_xlsx.ps1';
        if (!is_file($script)) {
            return;
        }

        $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File '
            . escapeshellarg($script) . ' ' . escapeshellarg($xlsxPath) . ' 2>nul';
        $handle = popen($cmd, 'r');
        if (!is_resource($handle)) {
            return;
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = json_decode($line, true);
            if (is_array($row) && trim((string) ($row['ttc_art_id'] ?? '')) !== '') {
                yield $row;
            }
        }
        pclose($handle);
    }
}
