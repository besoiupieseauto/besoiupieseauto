<?php

declare(strict_types=1);

namespace Besoiu\Modules\Furnizori\Service;

use PDO;
use RuntimeException;

/**
 * Formează cartela din fișierul furnizorului: brand + cod în matc_{brand}.
 * Echivalentul se caută doar dacă brandul lui are tabel matc_.
 */
final class SupplierFeedCardFormationService
{
    private const MAX_OFFSET = 500000;
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

    /** @param array<string, mixed> $furnizor @return array<string, mixed> */
    public function formBatch(array $furnizor, string $filename, int $offset, int $limit): array
    {
        $filename = basename(str_replace('\\', '/', trim($filename)));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw new RuntimeException('Lipsește numele fișierului.');
        }
        $code = trim((string) ($furnizor['code'] ?? ''));
        $randomnId = (int) ($furnizor['randomn_id'] ?? 0);
        $path = '';
        foreach ((new SupplierFeedFolderService())->listFiles($code, $randomnId) as $entry) {
            if (strcasecmp((string) ($entry['name'] ?? ''), $filename) === 0) {
                $path = (string) ($entry['local_path'] ?? '');
                break;
            }
        }
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException('Fișierul nu este în folderul furnizorului.');
        }

        return $this->formFile($path, $filename, $offset, $limit);
    }

    /** @return array<string, mixed> */
    public function formFile(string $path, string $filename, int $offset, int $limit): array
    {
        $offset = max(0, min(self::MAX_OFFSET, $offset));
        $limit = max(1, min(20, $limit));
        $pdo = $this->pdo();
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 4);
        $pdo->exec('SET SESSION MAX_EXECUTION_TIME=2000');

        $matc = $this->matcTables($pdo);
        $indexed = $this->indexedBrands($pdo);
        $houseBrands = $this->houseBrands($pdo);
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new RuntimeException('Fișierul nu poate fi citit.');
        }
        $header = fgetcsv($fh, 0, ';', '"', '\\');
        if (!is_array($header)) {
            fclose($fh);
            throw new RuntimeException('Fișierul nu are antet CSV.');
        }
        $columns = $this->columns($header);
        if ($columns['brand'] === null || $columns['code'] === null) {
            fclose($fh);
            throw new RuntimeException('Antetul nu are brand și cod.');
        }

        $rowNo = 0;
        while ($rowNo < $offset && ($row = fgetcsv($fh, 0, ';', '"', '\\')) !== false) {
            $rowNo++;
        }

        $cards = [];
        $stats = [
            'scanned' => 0,
            'formed' => 0,
            'missing_code' => 0,
            'no_catalog' => 0,
            'indexing' => 0,
        ];
        $deadline = microtime(true) + 8.0;
        $scannedThisCall = 0;
        while ($scannedThisCall < self::MAX_SCAN_ROWS && count($cards) < $limit && microtime(true) < $deadline) {
            $row = fgetcsv($fh, 0, ';', '"', '\\');
            if ($row === false) {
                break;
            }
            $rowNo++;
            $scannedThisCall++;
            $stats['scanned']++;
            $brand = trim((string) ($row[$columns['brand']] ?? ''));
            $code = trim((string) ($row[$columns['code']] ?? ''));
            if ($brand === '' || $code === '') {
                continue;
            }
            $card = $this->mapRow($pdo, $matc, $indexed, $houseBrands, $columns, $row, $brand, $code);
            $status = (string) ($card['status'] ?? '');
            if ($status === 'formed') {
                $stats['formed']++;
                $cards[] = $card;
            } elseif ($status === 'indexing') {
                $stats['indexing']++;
            } elseif ($status === 'no_catalog') {
                $stats['no_catalog']++;
            } else {
                $stats['missing_code']++;
            }
        }
        $peek = fgetcsv($fh, 0, ';', '"', '\\');
        $done = $peek === false;
        fclose($fh);

        return [
            'filename' => $filename,
            'offset' => $offset,
            'next_offset' => $rowNo,
            'done' => $done,
            'cards' => $cards,
            'stats' => $stats,
        ];
    }

    /**
     * @param array<string, string> $matc
     * @param array<string, bool> $indexed
     * @param array<string, bool> $houseBrands
     * @param array<string, int|null> $columns
     * @param array<int, string|null> $row
     * @return array<string, string>
     */
    private function mapRow(PDO $pdo, array $matc, array $indexed, array $houseBrands, array $columns, array $row, string $brand, string $code): array
    {
        $base = [
            'file_brand' => $brand,
            'file_code' => $code,
            'file_name' => $this->cell($row, $columns['name']),
            'price' => $this->cell($row, $columns['price']),
            'card_brand' => '',
            'card_code' => '',
            'card_name' => '',
            'ean' => '',
            'cross' => '',
            'vehicle' => '',
            'status' => 'missing_code',
        ];
        if (isset($houseBrands[strtoupper($brand)])) {
            $house = $this->houseCard($pdo, $this->cell($row, $columns['item']), $brand, $code);
            if ($house !== null) {
                return array_merge($base, $house, ['status' => 'formed']);
            }

            return $base;
        }
        $slug = $this->slug($brand);
        $direct = $this->catalogCard($pdo, $matc, $indexed, $slug, $brand, $code);
        if ($direct !== null && ($direct['status'] ?? '') === 'formed') {
            return array_merge($base, $direct);
        }
        if ($direct !== null && ($direct['status'] ?? '') === 'indexing') {
            return array_merge($base, $direct);
        }
        $echivBrand = $this->cell($row, $columns['echiv_brand']);
        $echivCode = $this->cell($row, $columns['echiv_code']);
        $echivSlug = $this->slug($echivBrand);
        if ($echivBrand !== '' && $echivCode !== '' && $echivSlug !== $slug && isset($matc[$echivSlug])) {
            $equiv = $this->catalogCard($pdo, $matc, $indexed, $echivSlug, $echivBrand, $echivCode);
            if ($equiv !== null && ($equiv['status'] ?? '') === 'formed') {
                $equiv['match_via'] = 'echiv';

                return array_merge($base, $equiv);
            }
            if ($equiv !== null && ($equiv['status'] ?? '') === 'indexing') {
                return array_merge($base, $equiv);
            }
        }
        if (!isset($matc[$slug]) && !isset($matc[$echivSlug])) {
            $base['status'] = 'no_catalog';
        }

        return $base;
    }

    /** @param array<string, string> $matc @param array<string, bool> $indexed @return array<string, string>|null */
    private function catalogCard(PDO $pdo, array $matc, array $indexed, string $slug, string $brand, string $code): ?array
    {
        if (!isset($matc[$slug])) {
            return null;
        }
        if (empty($indexed[$slug])) {
            return ['status' => 'indexing', 'card_brand' => $brand];
        }
        $table = $matc[$slug];
        if (!preg_match('/^matc_[a-z0-9_]+$/', $table)) {
            return null;
        }
        $hit = $this->findCode($pdo, $table, $code);
        if ($hit === null) {
            return ['status' => 'missing_code', 'card_brand' => $brand];
        }
        $vehicle = trim((string) ($hit['CAR_BRAND'] ?? '') . ' ' . (string) ($hit['CAR_MODEL'] ?? '') . ' ' . (string) ($hit['CAR_TYP'] ?? ''));
        $years = trim((string) ($hit['CAR_OF_YEAR'] ?? '') . '-' . (string) ($hit['CAR_TO_YEAR'] ?? ''), '-');
        if ($years !== '') {
            $vehicle = trim($vehicle . ' ' . $years);
        }

        return [
            'status' => 'formed',
            'card_brand' => (string) ($hit['ART_BRAND'] ?? $brand),
            'card_code' => (string) (($hit['ART_CODE_2'] ?? '') !== '' ? $hit['ART_CODE_2'] : ($hit['ART_CODE_1'] ?? '')),
            'card_name' => (string) ($hit['ART_NAME'] ?? ''),
            'ean' => (string) ($hit['ART_EAN'] ?? ''),
            'cross' => (string) ($hit['ART_CROSS'] ?? ''),
            'vehicle' => $vehicle,
            'match_via' => 'brand_code',
        ];
    }

    /** @return array<string, string>|null */
    private function findCode(PDO $pdo, string $table, string $code): ?array
    {
        $raw = trim($code);
        $norm = str_replace([' ', '-', '/', '.'], '', strtoupper($raw));
        $variants = array_values(array_unique(array_filter([$raw, strtoupper($raw), $norm], static fn (string $v): bool => $v !== '')));
        $sql = 'SELECT ART_BRAND, ART_CODE_1, ART_CODE_2, ART_NAME, ART_EAN, LEFT(ART_CROSS, 220) AS ART_CROSS, CAR_BRAND, CAR_MODEL, CAR_TYP, CAR_OF_YEAR, CAR_TO_YEAR FROM `' . $table . '` WHERE ART_CODE_2 = ? OR ART_CODE_1 = ? LIMIT 1';
        $st = $pdo->prepare($sql);
        foreach ($variants as $variant) {
            try {
                $st->execute([$variant, $variant]);
                $hit = $st->fetch(PDO::FETCH_ASSOC);
            } catch (\PDOException) {
                return null;
            }
            if (is_array($hit)) {
                return $hit;
            }
        }

        return null;
    }

    /** @return array<string, string>|null */
    private function houseCard(PDO $pdo, string $itemkey, string $brand, string $code): ?array
    {
        $sql = 'SELECT sup_brand, ART_BRAND, ART_CODE_1, ART_NAME, borrowed_brand, borrowed_code_1, borrowed_art_name, matc_table, MATCH_TYPE FROM branduri_autototal WHERE itemkey = ? OR (sup_brand = ? AND ART_CODE_1 = ?) LIMIT 1';
        try {
            $st = $pdo->prepare($sql);
            $st->execute([$itemkey !== '' ? $itemkey : $code, $brand, $code]);
            $hit = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            return null;
        }
        if (!is_array($hit)) {
            return null;
        }
        $borrowed = trim((string) ($hit['borrowed_brand'] ?? ''));
        $borrowedCode = trim((string) ($hit['borrowed_code_1'] ?? ''));
        if ($borrowedCode === '2147483647') {
            $borrowedCode = '';
        }

        return [
            'card_brand' => $borrowed !== '' ? $borrowed : (string) ($hit['ART_BRAND'] ?? $brand),
            'card_code' => $borrowedCode !== '' ? $borrowedCode : (string) ($hit['ART_CODE_1'] ?? $code),
            'card_name' => (string) (($hit['borrowed_art_name'] ?? '') !== '' ? $hit['borrowed_art_name'] : ($hit['ART_NAME'] ?? '')),
            'ean' => '',
            'cross' => '',
            'vehicle' => '',
            'match_via' => 'branduri_autototal',
        ];
    }

    /** @return array<string, bool> */
    private function houseBrands(PDO $pdo): array
    {
        $brands = [];
        try {
            $res = $pdo->query('SELECT sup_brand FROM autototal_brands');
        } catch (\PDOException) {
            return $brands;
        }
        if ($res === false) {
            return $brands;
        }
        while ($name = $res->fetchColumn()) {
            $key = strtoupper(trim((string) $name));
            if ($key !== '') {
                $brands[$key] = true;
            }
        }

        return $brands;
    }

    /** @return array<string, string> */
    private function matcTables(PDO $pdo): array
    {
        $tables = [];
        $res = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'matc\\_%'");
        if ($res === false) {
            return $tables;
        }
        while ($name = $res->fetchColumn()) {
            $name = (string) $name;
            $tables[substr($name, 5)] = $name;
        }

        return $tables;
    }

    /** @return array<string, bool> */
    private function indexedBrands(PDO $pdo): array
    {
        $indexed = [];
        $sql = "SELECT TABLE_NAME AS table_name FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND TABLE_NAME LIKE 'csv\\_%' AND COLUMN_NAME IN ('ART_CODE_1', 'ART_CODE_2') AND INDEX_NAME LIKE 'idx_feed_code%'";
        $res = $pdo->query($sql);
        if ($res === false) {
            return $indexed;
        }
        while ($name = $res->fetchColumn()) {
            if (preg_match('/^csv_([a-z0-9]+)_/i', (string) $name, $m) === 1) {
                $indexed[strtolower($m[1])] = true;
            }
        }

        return $indexed;
    }

    /** @param array<int, string|null> $header @return array<string, int|null> */
    private function columns(array $header): array
    {
        $map = [];
        foreach ($header as $index => $name) {
            $key = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', (string) $name));
            if ($key !== '') {
                $map[$key] = (int) $index;
            }
        }
        $pick = static function (array $map, array $names): ?int {
            foreach ($names as $name) {
                $key = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $name));
                if (isset($map[$key])) {
                    return $map[$key];
                }
            }

            return null;
        };

        return [
            'brand' => $pick($map, ['SUP_BRAND', 'SUP BRAND', 'BRAND', 'PRODUCATOR', 'LKQ BRAND NAME']),
            'code' => $pick($map, ['ART_ARTICLE_NR', 'ART ARTICLE NR', 'COD ARTICOL TECDOC', 'COD NPF', 'INDEX TECDOC']),
            'name' => $pick($map, ['DENUMIRE', 'ART_NAME', 'NAME']),
            'price' => $pick($map, ['PRET', 'NET PRICE', 'PURCHASE PRICE']),
            'item' => $pick($map, ['ITEMKEY']),
            'echiv_brand' => $pick($map, ['SUP_BRAND_ECHIV', 'SUP BRAND ECHIV']),
            'echiv_code' => $pick($map, ['CODE_ECHIV', 'CODE ECHIV']),
        ];
    }

    /** @param array<int, string|null> $row */
    private function cell(array $row, ?int $index): string
    {
        if ($index === null) {
            return '';
        }

        return trim((string) ($row[$index] ?? ''));
    }

    private function slug(string $brand): string
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $brand));

        return self::BRAND_ALIASES[$slug] ?? $slug;
    }

    private function pdo(): PDO
    {
        $connect = dirname(__DIR__, 4) . '/app/Import/MatchingPro/api/lib/ImportTecdocMysqlConnect.php';
        if (!is_file($connect)) {
            $connect = '/var/www/besoiupieseauto.ro/app/Import/MatchingPro/api/lib/ImportTecdocMysqlConnect.php';
        }
        require_once $connect;

        return import_tecdoc_mysql_pdo();
    }
}
