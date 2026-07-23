<?php
declare(strict_types=1);

/**
 * Citire CSV furnizor cu detectare delimiter (; , tab).
 */
final class ImportShowcaseCsvReader
{
    /**
     * @return array{handle: resource, delimiter: string}|null
     */
    public static function open(string $path): ?array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return null;
        }

        $delimiter = function_exists('import_csv_delimiter')
            ? import_csv_delimiter($firstLine)
            : self::detectDelimiter($firstLine);

        rewind($handle);

        return ['handle' => $handle, 'delimiter' => $delimiter];
    }

    /**
     * @return list<string|null>|false
     */
    public static function readHeader($handle, string $delimiter)
    {
        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false) {
            return false;
        }

        if (function_exists('import_utf8_sanitize_string')) {
            return array_map(
                static fn ($col): string => import_utf8_sanitize_string(trim((string) $col)),
                $header
            );
        }

        return array_map(static fn ($col): string => trim((string) $col), $header);
    }

    /**
     * @return list<string|null>|false
     */
    public static function readRow($handle, string $delimiter)
    {
        return fgetcsv($handle, 0, $delimiter);
    }

    public static function detectDelimiter(string $sampleLine): string
    {
        if (function_exists('import_csv_delimiter')) {
            return import_csv_delimiter($sampleLine);
        }

        $semicolons = substr_count($sampleLine, ';');
        $commas = substr_count($sampleLine, ',');
        $tabs = substr_count($sampleLine, "\t");
        $best = max($semicolons, $commas, $tabs);
        if ($best <= 0) {
            return ',';
        }
        if ($tabs === $best) {
            return "\t";
        }
        if ($commas === $best) {
            return ',';
        }

        return ';';
    }
}
