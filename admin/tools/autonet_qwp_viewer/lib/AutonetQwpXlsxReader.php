<?php
declare(strict_types=1);

/**
 * Citește coloanele A, B, C din primul sheet XLSX.
 * ZipArchive dacă e disponibil, altfel fallback PowerShell (Windows).
 */
final class AutonetQwpXlsxReader
{
    private const COLS = ['A', 'B', 'C'];

    /**
     * @return array{
     *   headers: array{a:string,b:string,c:string},
     *   rows: list<array{row:int,a:string,b:string,c:string}>
     * }
     */
    public static function load(string $path): array
    {
        $path = str_replace('\\', '/', $path);
        if (!is_file($path) || !is_readable($path)) {
            return ['headers' => self::defaultHeaders(), 'rows' => []];
        }

        if (class_exists(ZipArchive::class)) {
            return self::readViaZip($path);
        }

        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            return self::readViaPowerShell($path);
        }

        return ['headers' => self::defaultHeaders(), 'rows' => []];
    }

    /** @return list<array{row:int,a:string,b:string,c:string}> */
    public static function readAll(string $path): array
    {
        return self::load($path)['rows'];
    }

    /** @return array{a:string,b:string,c:string} */
    private static function defaultHeaders(): array
    {
        return ['a' => 'Coloana A', 'b' => 'Coloana B', 'c' => 'Coloana C'];
    }

    /** @return array{headers: array{a:string,b:string,c:string}, rows: list<array{row:int,a:string,b:string,c:string}>} */
    private static function readViaZip(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return ['headers' => self::defaultHeaders(), 'rows' => []];
        }

        $shared = self::loadSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!is_string($sheetXml)) {
            return ['headers' => self::defaultHeaders(), 'rows' => []];
        }

        return self::parseSheet($sheetXml, $shared);
    }

    /** @return list<string> */
    private static function loadSharedStrings(ZipArchive $zip): array
    {
        $shared = [];
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (!is_string($xml)) {
            return $shared;
        }

        $sx = @simplexml_load_string($xml);
        if (!$sx) {
            preg_match_all('/<t[^>]*>([^<]*)/', $xml, $m);
            return $m[1] ?? [];
        }

        foreach ($sx->si as $si) {
            $text = '';
            if (isset($si->t)) {
                $text = (string) $si->t;
            } elseif (isset($si->r)) {
                foreach ($si->r as $r) {
                    $text .= (string) $r->t;
                }
            }
            $shared[] = $text;
        }

        return $shared;
    }

    /**
     * @param list<string> $shared
     * @return array{headers: array{a:string,b:string,c:string}, rows: list<array{row:int,a:string,b:string,c:string}>}
     */
    private static function parseSheet(string $sheetXml, array $shared): array
    {
        $grid = [];
        preg_match_all(
            '/<c r="([A-Z]+)(\d+)"([^>]*)>(?:<v>([^<]*)<\/v>)?/',
            $sheetXml,
            $cells,
            PREG_SET_ORDER
        );

        foreach ($cells as $cell) {
            $col = $cell[1];
            $rowNum = (int) $cell[2];
            if (!in_array($col, self::COLS, true)) {
                continue;
            }

            $val = $cell[4] ?? '';
            if (str_contains($cell[3], 't="s"') && $val !== '' && isset($shared[(int) $val])) {
                $val = $shared[(int) $val];
            }

            $grid[$rowNum][$col] = trim(html_entity_decode($val, ENT_QUOTES | ENT_XML1, 'UTF-8'));
        }

        if ($grid === []) {
            return ['headers' => self::defaultHeaders(), 'rows' => []];
        }

        ksort($grid);
        $headerRow = min(array_keys($grid));
        $headerCells = $grid[$headerRow] ?? [];
        $headers = [
            'a' => ($headerCells['A'] ?? '') !== '' ? $headerCells['A'] : 'Coloana A',
            'b' => ($headerCells['B'] ?? '') !== '' ? $headerCells['B'] : 'Coloana B',
            'c' => ($headerCells['C'] ?? '') !== '' ? $headerCells['C'] : 'Coloana C',
        ];
        $rows = [];

        foreach ($grid as $rowNum => $cols) {
            if ($rowNum === $headerRow) {
                continue;
            }
            $rows[] = [
                'row' => $rowNum,
                'a' => $cols['A'] ?? '',
                'b' => $cols['B'] ?? '',
                'c' => $cols['C'] ?? '',
            ];
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** @return array{headers: array{a:string,b:string,c:string}, rows: list<array{row:int,a:string,b:string,c:string}>} */
    private static function readViaPowerShell(string $path): array
    {
        $script = dirname(__DIR__) . '/read_abc.ps1';
        if (!is_file($script)) {
            return ['headers' => self::defaultHeaders(), 'rows' => []];
        }

        $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File '
            . escapeshellarg($script) . ' ' . escapeshellarg($path) . ' 2>nul';

        $json = shell_exec($cmd);
        if (!is_string($json) || trim($json) === '') {
            return ['headers' => self::defaultHeaders(), 'rows' => []];
        }

        $decoded = json_decode(trim($json), true);
        if (!is_array($decoded)) {
            return ['headers' => self::defaultHeaders(), 'rows' => []];
        }

        $headers = self::defaultHeaders();
        if (isset($decoded['headers']) && is_array($decoded['headers'])) {
            $headers = [
                'a' => (string) ($decoded['headers']['a'] ?? $headers['a']),
                'b' => (string) ($decoded['headers']['b'] ?? $headers['b']),
                'c' => (string) ($decoded['headers']['c'] ?? $headers['c']),
            ];
        }

        $rows = [];
        $items = $decoded['rows'] ?? $decoded;
        if (!is_array($items)) {
            return ['headers' => $headers, 'rows' => []];
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rows[] = [
                'row' => (int) ($item['row'] ?? 0),
                'a' => (string) ($item['a'] ?? ''),
                'b' => (string) ($item['b'] ?? ''),
                'c' => (string) ($item['c'] ?? ''),
            ];
        }

        return ['headers' => $headers, 'rows' => $rows];
    }
}
