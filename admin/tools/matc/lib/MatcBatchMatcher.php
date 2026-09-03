<?php
declare(strict_types=1);

require_once __DIR__ . '/MatcDb.php';

/**
 * Match in lot — suporta mii de linii (brand;cod) via tabela temporara + JOIN.
 */
final class MatcBatchMatcher
{
    private const CHUNK_SIZE = 1000;
    private const TEMP_TABLE = '_matc_batch_input';

    /**
     * @param iterable<array{brand:string,code:string,line?:int}> $rows
     * @return array{
     *   total:int,
     *   matched:int,
     *   unmatched:int,
     *   results:list<array<string,mixed>>,
     *   elapsed_ms:float
     * }
     */
    public static function match(iterable $rows, ?string $database = null): array
    {
        $t0 = microtime(true);
        $pdo = MatcDb::pdo($database);
        self::ensureTempTable($pdo);

        $total = 0;
        $matched = 0;
        $results = [];
        $buffer = [];

        $flush = static function () use (&$buffer, &$total, &$matched, &$results, $pdo): void {
            if ($buffer === []) {
                return;
            }

            $pdo->exec('TRUNCATE TABLE `' . self::TEMP_TABLE . '`');
            $insert = $pdo->prepare(
                'INSERT INTO `' . self::TEMP_TABLE . '` (line_no, brand_id, brand_name, code_norm, code_raw) VALUES (?,?,?,?,?)'
            );

            foreach ($buffer as $item) {
                $insert->execute([
                    $item['line'],
                    $item['brand_id'],
                    $item['brand_name'],
                    $item['code_norm'],
                    $item['code_raw'],
                ]);
            }

            $sql = 'SELECT t.line_no, t.brand_name, t.code_raw, t.code_norm, '
                . 'p.id AS product_id, p.art_code_1, p.art_code_2, p.art_name, p.ttc_art_id, '
                . 'p.compat_count, p.art_ean '
                . 'FROM `' . self::TEMP_TABLE . '` t '
                . 'LEFT JOIN product_codes pc ON pc.brand_id = t.brand_id '
                . 'AND pc.code_norm COLLATE utf8mb4_unicode_ci = t.code_norm COLLATE utf8mb4_unicode_ci '
                . 'LEFT JOIN products p ON p.id = pc.product_id '
                . 'ORDER BY t.line_no';

            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $isMatch = !empty($row['product_id']);
                if ($isMatch) {
                    ++$matched;
                }
                $results[] = [
                    'line' => (int) $row['line_no'],
                    'brand' => (string) $row['brand_name'],
                    'code' => (string) $row['code_raw'],
                    'code_norm' => (string) $row['code_norm'],
                    'matched' => $isMatch,
                    'product_id' => $isMatch ? (int) $row['product_id'] : null,
                    'art_code_1' => (string) ($row['art_code_1'] ?? ''),
                    'art_code_2' => (string) ($row['art_code_2'] ?? ''),
                    'art_name' => (string) ($row['art_name'] ?? ''),
                    'ttc_art_id' => (string) ($row['ttc_art_id'] ?? ''),
                    'compat_count' => (int) ($row['compat_count'] ?? 0),
                    'art_ean' => (string) ($row['art_ean'] ?? ''),
                ];
            }

            $buffer = [];
        };

        $brandCache = [];

        foreach ($rows as $row) {
            ++$total;
            $brand = MatcDb::normalizeBrand((string) ($row['brand'] ?? ''));
            $codeRaw = trim((string) ($row['code'] ?? ''));
            $codeNorm = MatcDb::normalizeCode($codeRaw);
            $line = (int) ($row['line'] ?? $total);

            if ($brand === '' || $codeNorm === '') {
                $results[] = [
                    'line' => $line,
                    'brand' => $brand,
                    'code' => $codeRaw,
                    'code_norm' => $codeNorm,
                    'matched' => false,
                    'product_id' => null,
                    'art_code_1' => '',
                    'art_code_2' => '',
                    'art_name' => '',
                    'ttc_art_id' => '',
                    'compat_count' => 0,
                    'art_ean' => '',
                    'error' => 'brand_or_code_empty',
                ];
                continue;
            }

            if (!isset($brandCache[$brand])) {
                $brandCache[$brand] = MatcDb::resolveBrandId($pdo, $brand);
            }
            $brandId = $brandCache[$brand];
            if ($brandId === null) {
                $results[] = [
                    'line' => $line,
                    'brand' => $brand,
                    'code' => $codeRaw,
                    'code_norm' => $codeNorm,
                    'matched' => false,
                    'product_id' => null,
                    'art_code_1' => '',
                    'art_code_2' => '',
                    'art_name' => '',
                    'ttc_art_id' => '',
                    'compat_count' => 0,
                    'art_ean' => '',
                    'error' => 'brand_not_in_db',
                ];
                continue;
            }

            $buffer[] = [
                'line' => $line,
                'brand_id' => $brandId,
                'brand_name' => $brand,
                'code_norm' => $codeNorm,
                'code_raw' => $codeRaw,
            ];

            if (count($buffer) >= self::CHUNK_SIZE) {
                $flush();
            }
        }

        $flush();
        $pdo->exec('DROP TEMPORARY TABLE IF EXISTS `' . self::TEMP_TABLE . '`');

        $matchedFinal = count(array_filter($results, static fn(array $r): bool => !empty($r['matched'])));

        return [
            'total' => $total,
            'matched' => $matchedFinal,
            'unmatched' => $total - $matchedFinal,
            'results' => $results,
            'elapsed_ms' => round((microtime(true) - $t0) * 1000, 2),
        ];
    }

    /**
     * @return iterable<array{brand:string,code:string,line:int}>
     */
    public static function readInputFile(string $path): iterable
    {
        if (!is_file($path)) {
            throw new RuntimeException('Fisier inexistent: ' . $path);
        }

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new RuntimeException('Nu pot deschide: ' . $path);
        }

        $lineNo = 0;
        while (($line = fgets($fh)) !== false) {
            ++$lineNo;
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $brand = '';
            $code = '';

            if (str_contains($line, ';')) {
                [$brand, $code] = array_map('trim', explode(';', $line, 2));
            } elseif (str_contains($line, "\t")) {
                [$brand, $code] = array_map('trim', explode("\t", $line, 2));
            } elseif (str_contains($line, ',')) {
                [$brand, $code] = array_map('trim', explode(',', $line, 2));
            } else {
                $code = $line;
            }

            yield [
                'brand' => $brand,
                'code' => $code,
                'line' => $lineNo,
            ];
        }

        fclose($fh);
    }

    private static function ensureTempTable(PDO $pdo): void
    {
        $pdo->exec('DROP TEMPORARY TABLE IF EXISTS `' . self::TEMP_TABLE . '`');
        $pdo->exec(
            'CREATE TEMPORARY TABLE `' . self::TEMP_TABLE . '` ('
            . 'line_no INT UNSIGNED NOT NULL, '
            . 'brand_id SMALLINT UNSIGNED NOT NULL, '
            . 'brand_name VARCHAR(64) NOT NULL, '
            . 'code_norm VARCHAR(64) NOT NULL, '
            . 'code_raw VARCHAR(128) NOT NULL, '
            . 'KEY idx_batch_lookup (brand_id, code_norm), '
            . 'KEY idx_line (line_no)'
            . ') ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
