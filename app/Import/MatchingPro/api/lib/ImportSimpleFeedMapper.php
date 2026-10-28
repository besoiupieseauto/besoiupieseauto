<?php
declare(strict_types=1);

final class ImportSimpleFeedMapper
{
    private const MAX_SCAN_ROWS = 2500;

    /** @var array<string, string> */
    private const BRAND_ALIASES = [
        'val' => 'valeo',
        'feb' => 'febibilstein',
        'febi' => 'febibilstein',
        'dep' => 'abakus',
        'depo' => 'abakus',
        'lor' => 'abakus',
        'loro' => 'abakus',
        'aba' => 'abakus',
        'abakusdepo' => 'abakus',
    ];

    /** @return array<string, mixed> */
    public function mapFile(string $supplier, string $filename, string $path, int $offset, int $limit): array
    {
        $offset = max(0, min(500000, $offset));
        $limit = max(1, min(50, $limit));
        $pdo = $this->tecdocPdo();
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 4);
        $pdo->exec('SET SESSION MAX_EXECUTION_TIME=2500');

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new RuntimeException('Fișierul nu poate fi citit.');
        }
        $sample = (string) fread($fh, 4096);
        rewind($fh);
        $delimiter = $this->detectDelimiter($sample);
        $header = fgetcsv($fh, 0, $delimiter, '"', '\\');
        if (!is_array($header)) {
            fclose($fh);
            throw new RuntimeException('Fișierul nu are antet CSV.');
        }
        $columns = $this->resolveColumns($supplier, $header);
        if ($columns['brand'] === null || $columns['code'] === null) {
            fclose($fh);
            throw new RuntimeException('Nu am găsit coloanele brand și cod în fișier.');
        }

        $rowNo = 0;
        while ($rowNo < $offset && fgetcsv($fh, 0, $delimiter, '"', '\\') !== false) {
            $rowNo++;
        }

        $matcTables = $this->matcTables($pdo);
        $indexed = $this->indexedSlugs($pdo);
        $houseBrands = $this->houseBrands($pdo);
        $cards = [];
        $stats = [
            'scanned' => 0,
            'mapped' => 0,
            'missing_code' => 0,
            'no_catalog' => 0,
            'indexing' => 0,
        ];
        $scannedThisCall = 0;
        $deadline = microtime(true) + 9.0;
        while ($scannedThisCall < self::MAX_SCAN_ROWS && count($cards) < $limit && microtime(true) < $deadline) {
            $row = fgetcsv($fh, 0, $delimiter, '"', '\\');
            if ($row === false) {
                break;
            }
            $rowNo++;
            $scannedThisCall++;
            $stats['scanned']++;
            $brand = $this->cell($row, $columns['brand']);
            $code = $this->cell($row, $columns['code']);
            if ($brand === '' || $code === '') {
                $stats['missing_code']++;
                continue;
            }
            $priceRaw = $this->cell($row, $columns['price']);
            $priceNet = $this->feedPrice($priceRaw, $supplier);
            $base = [
                'status' => 'missing_code',
                'sourceSupplier' => $supplier,
                'supplier' => strtoupper($supplier),
                'sourceFile' => strtolower($supplier) . '/' . basename($filename),
                'supplierSku' => $code,
                'file_brand' => $brand,
                'file_code' => $code,
                'file_name' => $this->cell($row, $columns['name']),
                'price' => $priceRaw,
                'priceCsv' => $priceNet,
                'priceNet' => $priceNet,
            ];
            $mapped = null;
            if (isset($houseBrands[strtoupper($brand)])) {
                $mapped = $this->mapHouseBrand($pdo, $base, $brand, $code, $this->cell($row, $columns['itemkey']));
            } else {
                $mapped = $this->mapCatalog($pdo, $base, $matcTables, $indexed, $brand, $code);
                if ($mapped === null) {
                    $echivBrand = $this->cell($row, $columns['echiv_brand']);
                    $echivCode = $this->cell($row, $columns['echiv_code']);
                    if ($echivBrand !== '' && $echivCode !== '') {
                        $mapped = $this->mapCatalog($pdo, $base, $matcTables, $indexed, $echivBrand, $echivCode);
                    }
                }
            }
            if ($mapped === null) {
                $slug = $this->brandSlug($brand);
                $stats[isset($matcTables[$slug]) ? 'missing_code' : 'no_catalog']++;
                continue;
            }
            $status = (string) ($mapped['status'] ?? 'missing_code');
            if ($status === 'mapped') {
                $stats['mapped']++;
                $cards[] = import_motor_normalize_card_purchase_prices($mapped);
            } elseif ($status === 'indexing') {
                $stats['indexing']++;
            } elseif ($status === 'no_catalog') {
                $stats['no_catalog']++;
            } else {
                $stats['missing_code']++;
            }
        }
        $peek = fgetcsv($fh, 0, $delimiter, '"', '\\');
        $done = $peek === false;
        fclose($fh);

        return [
            'supplier' => $supplier,
            'filename' => basename($filename),
            'offset' => $offset,
            'next_offset' => $rowNo,
            'done' => $done,
            'cards' => $cards,
            'stats' => $stats,
        ];
    }

    /** @param array<string, mixed> $base @param array<string, string> $matcTables @param array<string, bool> $indexed @return array<string, mixed>|null */
    private function mapCatalog(PDO $pdo, array $base, array $matcTables, array $indexed, string $brand, string $code): ?array
    {
        $slug = $this->brandSlug($brand);
        if (!isset($matcTables[$slug])) {
            return null;
        }
        if (empty($indexed[$slug])) {
            return $base + ['status' => 'indexing', 'brand' => $brand, 'reason' => 'Catalogul are nevoie de index pe cod.'];
        }
        $table = $matcTables[$slug];
        $entries = $this->findEntries($pdo, $table, $code);
        if ($entries === []) {
            return null;
        }
        $canonicalBrand = trim((string) ($entries[0]['ART_BRAND'] ?? $brand));
        $code1 = trim((string) ($entries[0]['ART_CODE_1'] ?? $code));
        $artName = trim((string) ($entries[0]['ART_NAME'] ?? ''));
        if ($artName === '') {
            return null;
        }
        $displayBrand = $canonicalBrand !== '' ? $canonicalBrand : $brand;
        $displayCode = $code1 !== '' ? $code1 : $code;
        $compatText = $this->compatText($entries);
        $card = array_merge($base, [
            'status' => 'mapped',
            'sku' => (string) $base['supplierSku'],
            'brand' => $displayBrand,
            'tecdocSku' => $displayCode,
            'matchedTecdocBrand' => $displayBrand,
            'matchedTecdocSku' => $displayCode,
            'name' => $artName,
            'matchedName' => $artName,
            'title' => trim($artName . ' ' . $displayBrand),
            'ean' => trim((string) ($entries[0]['ART_EAN'] ?? '')),
            'artCross' => trim((string) ($entries[0]['ART_CROSS'] ?? '')),
            'art_cross' => trim((string) ($entries[0]['ART_CROSS'] ?? '')),
            'parameters' => $this->parameters((string) ($entries[0]['PARTS_INFO'] ?? '')),
            'termsOfUse' => str_replace('|', '; ', (string) ($entries[0]['TERMS_OF_USE'] ?? '')),
            'compatText' => $compatText,
            'compatCount' => count($entries),
            'sourceRows' => $entries,
            'hasImage' => false,
            'imageUrl' => '',
            'imageDisplayUrl' => '',
        ]);
        $card['sourceSupplier'] = (string) $base['sourceSupplier'];
        $card['sourceFile'] = (string) $base['sourceFile'];
        $card['supplierSku'] = (string) $base['supplierSku'];
        $card['file_brand'] = (string) $base['file_brand'];
        $card['file_code'] = (string) $base['file_code'];
        $card['file_name'] = (string) $base['file_name'];
        $card['price'] = $base['price'];
        $card['priceCsv'] = $base['priceCsv'];
        $card['tecdocSku'] = trim((string) ($card['tecdocSku'] ?? $code1));
        if ($card['tecdocSku'] === '') {
            $card['tecdocSku'] = $code1 !== '' ? $code1 : $code;
        }
        $card['matchedTecdocBrand'] = (string) ($card['brand'] ?? $canonicalBrand);
        $card['matchedTecdocSku'] = (string) ($card['tecdocSku'] ?? $card['sku'] ?? $code1);
        $card['matchStatus'] = 'exact';
        $card['matchMethod'] = 'simple_brand_code';

        return $card;
    }

    /** @param list<array<string, string>> $entries */
    private function compatText(array $entries): string
    {
        $out = [];
        foreach (array_slice($entries, 0, 80) as $entry) {
            $brand = trim((string) ($entry['CAR_BRAND'] ?? ''));
            $model = trim((string) ($entry['CAR_MODEL'] ?? ''));
            $type = trim((string) ($entry['CAR_TYP'] ?? ''));
            if ($brand === '' && $model === '' && $type === '') {
                continue;
            }
            $out[] = trim($brand . ' ' . $model . ' ' . $type);
        }

        return implode('; ', array_values(array_unique($out)));
    }

    /** @return list<array{key:string,value:string}> */
    private function parameters(string $partsInfo): array
    {
        $out = [];
        foreach (preg_split('/\s*\|\s*/', trim($partsInfo)) ?: [] as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            if (str_contains($chunk, '::')) {
                [$key, $value] = array_map('trim', explode('::', $chunk, 2));
            } elseif (str_contains($chunk, ':')) {
                [$key, $value] = array_map('trim', explode(':', $chunk, 2));
            } else {
                $key = $chunk;
                $value = '';
            }
            $out[] = ['key' => $key, 'value' => $value];
            if (count($out) >= 24) {
                break;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $base @return array<string, mixed>|null */
    private function mapHouseBrand(PDO $pdo, array $base, string $brand, string $code, string $itemkey): ?array
    {
        $st = $pdo->prepare('SELECT ART_NAME, borrowed_brand, borrowed_code_1, borrowed_art_name, matc_table FROM branduri_autototal WHERE itemkey = ? OR (sup_brand = ? AND ART_CODE_1 = ?) LIMIT 1');
        $st->execute([$itemkey !== '' ? $itemkey : $code, $brand, $code]);
        $hit = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($hit)) {
            return null;
        }
        $borrowedBrand = trim((string) ($hit['borrowed_brand'] ?? ''));
        $borrowedCode = trim((string) ($hit['borrowed_code_1'] ?? ''));
        if ($borrowedCode === '2147483647') {
            $borrowedCode = '';
        }
        $name = trim((string) (($hit['borrowed_art_name'] ?? '') !== '' ? $hit['borrowed_art_name'] : ($hit['ART_NAME'] ?? '')));
        if ($borrowedBrand === '' || $borrowedCode === '' || $name === '') {
            return null;
        }
        $card = array_merge($base, [
            'status' => 'mapped',
            'brand' => $borrowedBrand,
            'sku' => $borrowedCode,
            'tecdocSku' => $borrowedCode,
            'matchedTecdocBrand' => $borrowedBrand,
            'matchedTecdocSku' => $borrowedCode,
            'name' => $name,
            'matchedName' => $name,
            'title' => $name . ' ' . $borrowedBrand,
            'ean' => '',
            'matchStatus' => 'exact',
            'matchMethod' => 'branduri_autototal',
            'compatText' => '',
            'sourceRows' => [],
            'hasImage' => false,
        ]);

        return $card;
    }

    /** @return list<array<string, string>> */
    private function findEntries(PDO $pdo, string $table, string $code): array
    {
        if (!preg_match('/^matc_[a-z0-9_]+$/', $table)) {
            return [];
        }
        $variants = $this->codeVariants($code);
        $sql = 'SELECT TTC_ART_ID, ART_BRAND, ART_CODE_1, ART_CODE_2, ART_NAME, ART_EAN, PARTS_INFO, TERMS_OF_USE, ART_CROSS, TTC_TYP_ID, CAR_BRAND, CAR_MODEL, CAR_TYP, CAR_BODY, CAR_OF_YEAR, CAR_TO_YEAR, CAR_KW, CAR_PM, CAR_CC FROM `' . $table . '` WHERE ART_CODE_2 = ? OR ART_CODE_1 = ? LIMIT 40';
        $st = $pdo->prepare($sql);
        foreach ($variants as $variant) {
            try {
                $st->execute([$variant, $variant]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable) {
                return [];
            }
            if (is_array($rows) && $rows !== []) {
                return array_map(static fn(array $r): array => array_map('strval', $r), $rows);
            }
        }

        return [];
    }

    /** @return array<string, string> */
    private function matcTables(PDO $pdo): array
    {
        $out = [];
        $res = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'matc\\\\_%'");
        while ($name = $res->fetchColumn()) {
            $name = (string) $name;
            $out[substr($name, 5)] = $name;
        }

        return $out;
    }

    /** @return array<string, bool> */
    private function indexedSlugs(PDO $pdo): array
    {
        $out = [];
        $sql = "SELECT TABLE_NAME FROM information_schema.statistics WHERE table_schema = DATABASE() AND TABLE_NAME LIKE 'csv\\\\_%' AND INDEX_NAME = 'idx_feed_code_1' AND COLUMN_NAME = 'ART_CODE_1'";
        $res = $pdo->query($sql);
        while ($table = $res->fetchColumn()) {
            if (preg_match('/^csv_([a-z0-9]+)_/i', (string) $table, $m) === 1) {
                $out[strtolower($m[1])] = true;
            }
        }

        return $out;
    }

    /** @return array<string, bool> */
    private function houseBrands(PDO $pdo): array
    {
        $out = [];
        try {
            $res = $pdo->query('SELECT sup_brand FROM autototal_brands');
            while ($brand = $res->fetchColumn()) {
                $key = strtoupper(trim((string) $brand));
                if ($key !== '') {
                    $out[$key] = true;
                }
            }
        } catch (Throwable) {
        }

        return $out;
    }

    /** @param list<string|null> $header @return array<string, int|null> */
    private function resolveColumns(string $supplier, array $header): array
    {
        $map = [];
        foreach ($header as $i => $name) {
            $key = $this->headerKey((string) $name);
            if ($key !== '') {
                $map[$key] = (int) $i;
            }
        }
        $cfg = $this->supplierConfig($supplier);
        $cols = is_array($cfg['columns'] ?? null) ? $cfg['columns'] : [];
        $pick = function (array $names) use ($map): ?int {
            foreach ($names as $name) {
                $key = $this->headerKey((string) $name);
                if (array_key_exists($key, $map)) {
                    return $map[$key];
                }
            }

            return null;
        };

        return [
            'brand' => $pick(array_merge((array) ($cols['brand'] ?? []), ['SUP_BRAND', 'SUP BRAND', 'BRAND', 'PRODUCATOR'])),
            'code' => $pick(array_merge((array) ($cols['sku'] ?? []), ['ART_ARTICLE_NR', 'COD ARTICOL TECDOC', 'COD NPF', 'INDEX TECDOC'])),
            'name' => $pick(array_merge((array) ($cols['name'] ?? []), ['DENUMIRE', 'ART_NAME', 'NAME'])),
            'price' => $pick(array_merge((array) ($cols['price'] ?? []), ['PRET', 'NET PRICE', 'PURCHASE PRICE'])),
            'itemkey' => $pick(['ITEMKEY']),
            'echiv_brand' => $pick(['SUP_BRAND_ECHIV', 'SUP BRAND ECHIV', 'REFERENCEBRAND']),
            'echiv_code' => $pick(array_merge((array) ($cfg['extra_codes'] ?? []), ['CODE_ECHIV', 'CODE ECHIV', 'REFNR'])),
        ];
    }

    /** @return array<string, mixed> */
    private function supplierConfig(string $supplier): array
    {
        $path = dirname(__DIR__, 2) . '/config/suppliers.json';
        $json = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($json[$supplier] ?? null) ? $json[$supplier] : [];
    }

    private function detectDelimiter(string $sample): string
    {
        $counts = [';' => substr_count($sample, ';'), ',' => substr_count($sample, ','), "\t" => substr_count($sample, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /** @param array<int, mixed> $row */
    private function cell(array $row, ?int $idx): string
    {
        return $idx === null ? '' : trim((string) ($row[$idx] ?? ''));
    }

    private function headerKey(string $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]+/i', '', trim($value)));
    }

    private function brandSlug(string $brand): string
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', trim($brand)));

        return self::BRAND_ALIASES[$slug] ?? $slug;
    }

    /** @return list<string> */
    private function codeVariants(string $code): array
    {
        $raw = trim($code);
        $norm = str_replace([' ', '-', '/', '.'], '', strtoupper($raw));

        return array_values(array_unique(array_filter([$raw, strtoupper($raw), $norm], static fn(string $v): bool => $v !== '')));
    }

    private function feedPrice(string $raw, string $supplier): float
    {
        $price = null;
        if (function_exists('import_motor_apply_feed_price')) {
            $price = import_motor_apply_feed_price($raw, $supplier);
        }
        if ($price === null && function_exists('import_parse_supplier_price')) {
            $price = import_parse_supplier_price($raw);
        }
        if ($price === null) {
            $normalized = str_replace([' ', ','], ['', '.'], $raw);
            $price = is_numeric($normalized) ? (float) $normalized : 0.0;
        }

        return round(max(0.0, (float) $price), 2);
    }

    private function tecdocPdo(): PDO
    {
        $cfg = function_exists('import_tecdoc_mysql_cfg')
            ? import_tecdoc_mysql_cfg()
            : ['host' => '127.0.0.1', 'port' => 3308, 'user' => 'root', 'pass' => '', 'db' => 'tecdoc_rebuild_1998_20261010'];
        $dbNames = array_values(array_unique([
            (string) ($cfg['db'] ?? ''),
            'tecdoc_rebuild_1998_20261010',
        ]));
        $users = [
            [(string) ($cfg['user'] ?? 'root'), (string) ($cfg['pass'] ?? '')],
            ['root', ''],
        ];
        $ports = array_values(array_unique([(int) ($cfg['port'] ?? 3308), 3308]));
        $hosts = array_values(array_unique([(string) ($cfg['host'] ?? '127.0.0.1'), '192.168.1.3', '127.0.0.1']));
        $last = null;
        foreach ($dbNames as $db) {
            if ($db === '') {
                continue;
            }
            foreach ($hosts as $host) {
            foreach ($ports as $port) {
            foreach ($users as [$user, $pass]) {
            try {
                return new PDO(
                    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db),
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_TIMEOUT => 8,
                    ]
                );
            } catch (PDOException $e) {
                $last = $e;
            }
            }
            }
            }
        }
        throw $last ?? new RuntimeException('Conexiunea TecDoc nu poate fi deschisă.');
    }
}
