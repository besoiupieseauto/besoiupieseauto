<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Liste ART_NAME de păstrat (magazin vs marketplace) + rezolvare tip articol per produs.
 */
final class ArtNameKeepListService
{
    public const TARGET_WEBSITE = 'website';
    public const TARGET_MARKETPLACE = 'marketplace';

    /** @var array<string, array<int, array{art_name:string,nr_linii:mixed,pastreaza:string,norm:string}>>|null */
    private static ?array $cache = null;

    /**
     * @return array<int, array{art_name:string,nr_linii:mixed,pastreaza:string,norm:string}>
     */
    public static function loadTarget(string $target, ?string $jsonPath = null): array
    {
        $all = self::loadAll($jsonPath);
        $key = self::normalizeTarget($target);
        if (!isset($all[$key])) {
            throw new \InvalidArgumentException('Target necunoscut: ' . $target . '. Foloseste website sau marketplace.');
        }

        return $all[$key];
    }

    /**
     * @return array<string, array<int, array{art_name:string,nr_linii:mixed,pastreaza:string,norm:string}>>
     */
    public static function loadAll(?string $jsonPath = null): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = $jsonPath ?: self::defaultJsonPath();
        if (!is_file($path)) {
            throw new \RuntimeException('Lista ART_NAME lipseste: ' . $path . '. Ruleaza import_art_name_keep_list.php.');
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('JSON invalid pentru lista ART_NAME: ' . $path);
        }

        $out = [];
        foreach ([self::TARGET_WEBSITE, self::TARGET_MARKETPLACE] as $target) {
            $rows = $decoded[$target] ?? [];
            if (!is_array($rows)) {
                $rows = [];
            }
            $out[$target] = self::normalizeRows($rows);
        }

        self::$cache = $out;

        return $out;
    }

    public static function defaultJsonPath(): string
    {
        return dirname(__DIR__, 2) . '/storage/keep_lists/art_name_keep_lists.json';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{art_name:string,nr_linii:mixed,pastreaza:string,norm:string}>
     */
    public static function normalizeRows(array $rows): array
    {
        self::bootImportHelpers();

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $artName = trim((string) ($row['art_name'] ?? $row['ART_NAME'] ?? ''));
            if ($artName === '') {
                continue;
            }
            $norm = self::normalizeArtNameKey($artName);
            if ($norm === '') {
                continue;
            }
            $out[] = [
                'art_name' => $artName,
                'nr_linii' => $row['nr_linii'] ?? $row['NR_LINII'] ?? null,
                'pastreaza' => strtoupper(trim((string) ($row['pastreaza'] ?? $row['PASTREAZA'] ?? 'DA'))),
                'norm' => $norm,
            ];
        }

        return $out;
    }

    /**
     * @param array<int, array{art_name:string,nr_linii:mixed,pastreaza:string,norm:string}> $rows
     * @return array{norm_set: array<string, true>, by_norm: array<string, array{art_name:string,nr_linii:mixed,pastreaza:string,norm:string}>}
     */
    public static function indexKeepRows(array $rows): array
    {
        $normSet = [];
        $byNorm = [];
        foreach ($rows as $row) {
            if (($row['pastreaza'] ?? 'DA') !== 'DA') {
                continue;
            }
            $norm = (string) ($row['norm'] ?? '');
            if ($norm === '') {
                continue;
            }
            $normSet[$norm] = true;
            $byNorm[$norm] = $row;
        }

        return ['norm_set' => $normSet, 'by_norm' => $byNorm];
    }

    public static function normalizeArtNameKey(string $value): string
    {
        self::bootImportHelpers();
        if (function_exists('import_base_normalize_name_key')) {
            return import_base_normalize_name_key($value);
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    /**
     * @param array<string, mixed> $product
     * @return array{art_name:string,norm:string,source:string}
     */
    public static function resolveProductArtName(array $product, ?\PDO $ttcPdo = null): array
    {
        self::bootImportHelpers();

        $candidates = [];

        $sub = trim((string) ($product['pSubcategory'] ?? ''));
        if ($sub !== '') {
            $candidates[] = ['art_name' => $sub, 'source' => 'pSubcategory'];
        }

        $name = trim((string) ($product['pName'] ?? ''));
        if ($name !== '' && function_exists('import_base_normalize_product_name')) {
            $normalizedName = import_base_normalize_product_name($name);
            if ($normalizedName !== '' && self::normalizeArtNameKey($normalizedName) !== self::normalizeArtNameKey($sub)) {
                $candidates[] = ['art_name' => $normalizedName, 'source' => 'pName_normalized'];
            }
        }

        $rawJsonArt = self::artNameFromRawJson($product);
        if ($rawJsonArt !== '') {
            $candidates[] = ['art_name' => $rawJsonArt, 'source' => 'raw_json'];
        }

        $ttcArt = self::artNameFromTtcIndex($product, $ttcPdo);
        if ($ttcArt !== '') {
            $candidates[] = ['art_name' => $ttcArt, 'source' => 'ttc_index'];
        }

        foreach ($candidates as $candidate) {
            $norm = self::normalizeArtNameKey((string) $candidate['art_name']);
            if ($norm !== '') {
                return [
                    'art_name' => (string) $candidate['art_name'],
                    'norm' => $norm,
                    'source' => (string) $candidate['source'],
                ];
            }
        }

        return ['art_name' => '', 'norm' => '', 'source' => 'unknown'];
    }

    /**
     * @param array<string, mixed> $product
     */
    public static function artNameFromRawJson(array $product): string
    {
        $raw = trim((string) ($product['raw_json'] ?? ''));
        if ($raw === '' || $raw === '{}' || $raw === 'null') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return '';
        }

        foreach (['tecdoc_file', 'product_summary'] as $section) {
            if (!isset($decoded[$section]) || !is_array($decoded[$section])) {
                continue;
            }
            $art = trim((string) ($decoded[$section]['art_name'] ?? ''));
            if ($art !== '') {
                return $art;
            }
        }

        $rows = $decoded['rows'] ?? null;
        if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
            $first = $rows[0];
            $art = trim((string) ($first['art_name'] ?? $first['art name'] ?? $first['ART_NAME'] ?? ''));
            if ($art !== '') {
                return $art;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $product
     */
    public static function artNameFromTtcIndex(array $product, ?\PDO $ttcPdo = null): string
    {
        if (!class_exists(TtcPozeCatalogIndex::class)) {
            return '';
        }

        try {
            $index = TtcPozeCatalogIndex::instance();
            if (!$index->isReady()) {
                return '';
            }

            $ctx = TtcPozeCatalogIndex::extractSearchContext($product);
            $codeNorm = trim((string) ($ctx['code_norm'] ?? ''));
            $brandNorm = trim((string) ($ctx['brand_norm'] ?? ''));
            if ($codeNorm === '') {
                return '';
            }

            $compact = $index->isCompactActive();
            $table = $compact ? 'ttc_code_brand' : 'ttc_catalog';
            $pdo = $ttcPdo;
            if (!$pdo instanceof \PDO) {
                $dbPath = $compact ? $index->compactDbPath() : $index->dbPath();
                if (!is_file($dbPath)) {
                    return '';
                }
                $pdo = new \PDO('sqlite:' . $dbPath);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            }

            if ($brandNorm !== '') {
                $stmt = $pdo->prepare("SELECT art_name FROM {$table} WHERE code_norm = :c AND brand_norm = :b AND art_name <> '' LIMIT 1");
                $stmt->execute([':c' => $codeNorm, ':b' => $brandNorm]);
                $art = trim((string) ($stmt->fetchColumn() ?: ''));
                if ($art !== '') {
                    return $art;
                }
            }

            $stmt = $pdo->prepare("SELECT art_name FROM {$table} WHERE code_norm = :c AND art_name <> '' LIMIT 1");
            $stmt->execute([':c' => $codeNorm]);

            return trim((string) ($stmt->fetchColumn() ?: ''));
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param array<string, true> $keepNormSet
     * @param array<string, mixed> $product
     */
    public static function shouldKeep(array $keepNormSet, array $product, ?\PDO $ttcPdo = null): bool
    {
        $resolved = self::resolveProductArtName($product, $ttcPdo);
        if ($resolved['norm'] === '') {
            return false;
        }

        return isset($keepNormSet[$resolved['norm']]);
    }

    public static function normalizeTarget(string $target): string
    {
        $target = strtolower(trim($target));
        if (in_array($target, ['web', 'site', 'magazin', 'magazin-online', 'website'], true)) {
            return self::TARGET_WEBSITE;
        }
        if (in_array($target, ['market', 'marketplace', 'pieseau', 'baselinker', 'pieseau-base'], true)) {
            return self::TARGET_MARKETPLACE;
        }

        return $target;
    }

    private static function bootImportHelpers(): void
    {
        static $booted = false;
        if ($booted) {
            return;
        }
        $baseLib = dirname(__DIR__) . '/Controllers/Produse/import_base_lib.php';
        if (is_file($baseLib)) {
            require_once $baseLib;
        }
        $booted = true;
    }
}
