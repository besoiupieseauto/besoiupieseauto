<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Scan;

/**
 * Extrage mărci/modele din CSV furnizor și le compară cu catalogul existent.
 */
final class SupplierCsvScanHelper
{
    private const SAMPLE_ROWS = 4000;

    private const MARCA_HEADERS = [
        'marca', 'car brand', 'make', 'brand auto', 'pmarca', 'car make', 'vehicle brand',
    ];

    private const MODEL_HEADERS = [
        'model', 'model name', 'nume model', 'pmodel', 'car model', 'vehicle model', 'model auto',
    ];

    /**
     * @return array{
     *   ok:bool,
     *   message:string,
     *   file:string,
     *   rows_sampled:int,
     *   price_column_found:bool,
     *   new_marci:array<int,string>,
     *   new_modele:array<int,array{marca:string,model:string,label:string}>,
     *   new_modele_count:int
     * }
     */
    public function analyzeFile(string $filePath, string $supplierCode, string $priceColumnsHint = ''): array
    {
        $empty = [
            'ok' => false,
            'message' => 'Fisier indisponibil.',
            'file' => basename($filePath),
            'rows_sampled' => 0,
            'price_column_found' => false,
            'new_marci' => [],
            'new_modele' => [],
            'new_modele_count' => 0,
        ];

        if (!is_file($filePath) || !is_readable($filePath)) {
            $empty['message'] = 'Fisierul nu poate fi citit.';

            return $empty;
        }

        $size = (int) (filesize($filePath) ?: 0);
        if ($size <= 0) {
            $empty['message'] = 'Fisier gol.';

            return $empty;
        }

        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            $empty['message'] = 'Nu s-a putut deschide CSV-ul.';

            return $empty;
        }

        $delimiter = $this->detectDelimiter($filePath);
        $headers = fgetcsv($handle, 0, $delimiter);
        if (!is_array($headers) || $headers === []) {
            fclose($handle);
            $empty['message'] = 'Antet CSV lipsa sau invalid.';

            return $empty;
        }

        $supplierType = $this->detectSupplierType($headers, basename($filePath), $supplierCode);
        $headerless = $supplierType !== null && $this->isHeaderlessSupplierRow($headers, $supplierType);
        $priceIdx = null;
        $marcaIdx = null;
        $modelIdx = null;

        if ($headerless) {
            rewind($handle);
            $priceIdx = $this->resolveHeaderlessPriceIndex($supplierType);
        } else {
            $headerMap = $this->mapHeaders($headers);
            $marcaIdx = $headerMap['marca'];
            $modelIdx = $headerMap['model'];
            $priceIdx = $this->resolvePriceColumnIndex($headers, $priceColumnsHint, $supplierType);
        }

        $pairs = [];
        $marciFromCsv = [];
        $rowCount = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false && $rowCount < self::SAMPLE_ROWS) {
            if (!is_array($row) || $row === []) {
                continue;
            }
            ++$rowCount;

            $marca = $marcaIdx !== null ? $this->cleanToken((string) ($row[$marcaIdx] ?? '')) : '';
            $model = $modelIdx !== null ? $this->cleanToken((string) ($row[$modelIdx] ?? '')) : '';

            if ($marca !== '') {
                $marciFromCsv[$marca] = true;
            }
            if ($model === '') {
                continue;
            }

            $key = mb_strtolower($marca . '|' . $model, 'UTF-8');
            if (!isset($pairs[$key])) {
                $pairs[$key] = ['marca' => $marca, 'model' => $model];
            }
        }

        fclose($handle);

        $knownMarci = $this->loadKnownMarci();
        $knownModele = $this->loadKnownModele();

        $newMarci = [];
        foreach (array_keys($marciFromCsv) as $marca) {
            if (!$this->isKnownMarca($marca, $knownMarci)) {
                $newMarci[] = $marca;
            }
        }
        sort($newMarci, SORT_NATURAL | SORT_FLAG_CASE);

        $newModele = [];
        foreach ($pairs as $pair) {
            $model = $pair['model'];
            $marca = $pair['marca'];
            if ($this->isKnownModel($model, $marca, $knownModele)) {
                continue;
            }
            $label = $marca !== '' ? ($marca . ' · ' . $model) : $model;
            $newModele[] = [
                'marca' => $marca,
                'model' => $model,
                'label' => $label,
            ];
        }

        usort($newModele, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));
        $newModele = array_slice($newModele, 0, 30);

        $ok = $rowCount > 0 && ($priceIdx !== null || $modelIdx !== null || $marcaIdx !== null || $headerless);
        $message = $ok
            ? ('Validat ' . number_format($rowCount, 0, ',', '.') . ' randuri esantion'
                . ($supplierType !== null ? ' (' . $supplierType . ($headerless ? ', fara antet' : '') . ')' : '') . '.')
            : 'CSV fara coloane recunoscute (pret / marca / model).';

        return [
            'ok' => $ok,
            'message' => $message,
            'file' => basename($filePath),
            'rows_sampled' => $rowCount,
            'price_column_found' => $priceIdx !== null,
            'new_marci' => $newMarci,
            'new_modele' => $newModele,
            'new_modele_count' => count($newModele),
        ];
    }

    /** @return array<int, string> */
    private function loadKnownMarci(): array
    {
        $known = [];
        try {
            $pdo = \Config\Database::getDB();
            foreach (['marca', 'model'] as $source) {
                $stmt = $pdo->query(
                    $source === 'marca'
                        ? "SELECT label FROM categorii WHERE type = 'marca' AND TRIM(label) <> ''"
                        : "SELECT DISTINCT TRIM(pMarca) AS label FROM produse WHERE TRIM(pMarca) <> '' AND status <> '0' LIMIT 5000"
                );
                if (!$stmt) {
                    continue;
                }
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                    $label = $this->cleanToken((string) ($row['label'] ?? ''));
                    if ($label !== '') {
                        $known[mb_strtolower($label, 'UTF-8')] = $label;
                    }
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $known;
    }

    /** @return array<string, true> */
    private function loadKnownModele(): array
    {
        $known = [];
        try {
            $pdo = \Config\Database::getDB();
            $queries = [
                "SELECT label FROM categorii WHERE type = 'model' AND TRIM(label) <> ''",
                "SELECT DISTINCT TRIM(pModel) AS label FROM produse WHERE TRIM(pModel) <> '' AND status <> '0' LIMIT 8000",
            ];
            foreach ($queries as $sql) {
                $stmt = $pdo->query($sql);
                if (!$stmt) {
                    continue;
                }
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                    $label = $this->cleanToken((string) ($row['label'] ?? ''));
                    if ($label !== '') {
                        $known[mb_strtolower($label, 'UTF-8')] = true;
                    }
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $known;
    }

    /** @param array<string, string> $knownMarci */
    private function isKnownMarca(string $marca, array $knownMarci): bool
    {
        $key = mb_strtolower($this->cleanToken($marca), 'UTF-8');

        return $key !== '' && isset($knownMarci[$key]);
    }

    /** @param array<string, true> $knownModele */
    private function isKnownModel(string $model, string $marca, array $knownModele): bool
    {
        $modelKey = mb_strtolower($this->cleanToken($model), 'UTF-8');
        if ($modelKey === '') {
            return true;
        }
        if (isset($knownModele[$modelKey])) {
            return true;
        }

        $combined = mb_strtolower($this->cleanToken($marca . ' ' . $model), 'UTF-8');

        return $combined !== '' && isset($knownModele[$combined]);
    }

    /** @param array<int, string> $headers */
    private function mapHeaders(array $headers): array
    {
        $marcaIdx = null;
        $modelIdx = null;

        foreach ($headers as $idx => $header) {
            $norm = mb_strtolower(trim((string) $header), 'UTF-8');
            if ($marcaIdx === null && in_array($norm, self::MARCA_HEADERS, true)) {
                $marcaIdx = (int) $idx;
            }
            if ($modelIdx === null && in_array($norm, self::MODEL_HEADERS, true)) {
                $modelIdx = (int) $idx;
            }
        }

        return ['marca' => $marcaIdx, 'model' => $modelIdx];
    }

    /** @param array<int, string> $headers */
    private function resolvePriceColumnIndex(array $headers, string $priceColumnsHint, string $supplierCode = ''): ?int
    {
        $hints = array_filter(array_map('trim', explode(',', $priceColumnsHint)));
        $normalizedHeaders = [];
        foreach ($headers as $idx => $header) {
            $normalizedHeaders[(int) $idx] = mb_strtolower(trim((string) $header), 'UTF-8');
        }

        foreach ($hints as $hint) {
            $hintLower = mb_strtolower($hint, 'UTF-8');
            foreach ($normalizedHeaders as $idx => $header) {
                if ($header === $hintLower || str_contains($header, $hintLower)) {
                    return $idx;
                }
            }
        }

        $supplierCode = strtoupper(trim($supplierCode));
        if ($supplierCode === 'AUTOPARTNER') {
            foreach ($normalizedHeaders as $idx => $header) {
                if (str_contains($header, 'purchase') || str_contains($header, 'pret')) {
                    return $idx;
                }
            }
        }

        foreach ($normalizedHeaders as $idx => $header) {
            if (preg_match('/pret|price|net|purchase/i', $header)) {
                return $idx;
            }
        }

        return null;
    }

    /** @param array<int, string> $firstRow */
    private function detectSupplierType(array $firstRow, string $filename, string $supplierCode): ?string
    {
        $importLib = dirname(__DIR__, 2) . '/Controllers/Produse/import_supplier_lib.php';
        if (is_file($importLib)) {
            require_once $importLib;
            if (function_exists('import_supplier_type_from_header_row')) {
                $fromHeader = import_supplier_type_from_header_row($firstRow);
                if ($fromHeader !== null) {
                    return $fromHeader;
                }
            }
            if (function_exists('import_detect_supplier_type')) {
                $detected = import_detect_supplier_type($firstRow, $filename);
                if ($detected !== null) {
                    return $detected;
                }
            }
        }

        $code = strtoupper(trim($supplierCode));

        return match ($code) {
            'AUTOPARTNER' => count($firstRow) >= 6 ? 'AUTOPARTNER' : null,
            'AUTONET' => count($firstRow) >= 5 ? 'AUTONET' : null,
            default => null,
        };
    }

    /** @param array<int, string> $firstRow */
    private function isHeaderlessSupplierRow(array $firstRow, string $supplierType): bool
    {
        $importLib = dirname(__DIR__, 2) . '/Controllers/Produse/import_supplier_lib.php';
        if (is_file($importLib)) {
            require_once $importLib;
            if (function_exists('import_supplier_type_from_header_row')) {
                if (import_supplier_type_from_header_row($firstRow) !== null) {
                    return false;
                }
            }
        }

        return in_array($supplierType, ['AUTOPARTNER', 'AUTONET'], true);
    }

    private function resolveHeaderlessPriceIndex(string $supplierType): ?int
    {
        return match (strtoupper($supplierType)) {
            'AUTOPARTNER' => 5,
            'AUTONET' => 4,
            default => null,
        };
    }

    private function detectDelimiter(string $filePath): string
    {
        $sample = (string) file_get_contents($filePath, false, null, 0, 4096);
        $counts = [
            ';' => substr_count($sample, ';'),
            ',' => substr_count($sample, ','),
            "\t" => substr_count($sample, "\t"),
        ];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function cleanToken(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value;
    }
}
