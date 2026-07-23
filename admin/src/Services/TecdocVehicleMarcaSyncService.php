<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Categorii\CategoriiModel;
use Config\Database;
use PDO;
use RuntimeException;

/**
 * Sincronizează mărcile auto (type=marca) din catalogul TecDoc în tabela categorii.
 *
 * Surse (în ordine):
 * 1. API RapidAPI manufacturers/list (dacă e permis)
 * 2. Cache JSON local (storage/catalog/tecdoc-vehicle-manufacturers.json)
 * 3. DISTINCT car_brand din tecdoc_product_compatibilities (shop + legacy)
 * 4. Mapă de referință MFA (ID-uri verificate din admin)
 */
final class TecdocVehicleMarcaSyncService
{
    public const CACHE_JSON = 'tecdoc-vehicle-manufacturers.json';

    /** @var array<string, int> label normalizat => MFA TecDoc */
    private const REFERENCE_MFA_IDS = [
        'ALFA ROMEO' => 2,
        'AUDI' => 5,
        'BMW' => 16,
        'CHEVROLET' => 23,
        'CHRYSLER' => 24,
        'CITROEN' => 21,
        'CITROËN' => 21,
        'DACIA' => 139,
        'DAEWOO' => 1856,
        'DS' => 448,
        'FIAT' => 35,
        'FORD' => 36,
        'HONDA' => 45,
        'HYUNDAI' => 183,
        'ISUZU' => 54,
        'IVECO' => 55,
        'JAGUAR' => 56,
        'JEEP' => 882,
        'KIA' => 184,
        'LANCIA' => 64,
        'LAND ROVER' => 1820,
        'LEXUS' => 842,
        'MAZDA' => 72,
        'MERCEDES-BENZ' => 74,
        'MG' => 411,
        'MINI' => 1827,
        'MITSUBISHI' => 771,
        'NISSAN' => 80,
        'OPEL' => 84,
        'PEUGEOT' => 88,
        'PORSCHE' => 92,
        'RENAULT' => 93,
        'SEAT' => 104,
        'SKODA' => 106,
        'SMART' => 113,
        'SSANGYONG' => 175,
        'SUBARU' => 107,
        'SUZUKI' => 109,
        'TESLA' => 3322,
        'TOYOTA' => 111,
        'VOLVO' => 120,
        'VW' => 121,
    ];

    private CategoriiModel $model;
    private TaxonomySyncService $taxonomySync;

    public function __construct(?CategoriiModel $model = null, ?TaxonomySyncService $taxonomySync = null)
    {
        $this->model = $model ?? new CategoriiModel();
        $this->taxonomySync = $taxonomySync ?? new TaxonomySyncService($this->model);
    }

    public static function cachePath(): string
    {
        return TaxonomySyncService::storageDir() . '/' . self::CACHE_JSON;
    }

    /**
     * @return array{
     *   ok:bool,
     *   source:string,
     *   imported:int,
     *   updated:int,
     *   skipped:int,
     *   total_sources:int,
     *   total_db:int,
     *   api_used:bool,
     *   message:string
     * }
     */
    public function sync(bool $allowApi = true, bool $updateExisting = true): array
    {
        $manufacturers = $this->collectManufacturers($allowApi);
        if ($manufacturers === []) {
            throw new RuntimeException(
                'Nu am găsit mărci TecDoc. Verifică conexiunea API (Armez API live) sau tabelele tecdoc_product_compatibilities.'
            );
        }

        $existing = $this->indexExistingMarci();
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $sortOrder = $this->nextSortOrder();

        foreach ($manufacturers as $entry) {
            $label = trim((string) ($entry['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $tecdocId = (int) ($entry['tecdoc_id'] ?? 0);
            $norm = $this->normalizeLabel($label);
            $match = null;

            if ($tecdocId > 0 && isset($existing['by_tecdoc'][$tecdocId])) {
                $match = $existing['by_tecdoc'][$tecdocId];
            } elseif (isset($existing['by_label'][$norm])) {
                $match = $existing['by_label'][$norm];
            }

            if ($match !== null) {
                $existingTecdoc = (int) ($match['tecdoc_id'] ?? 0);
                if ($updateExisting && $tecdocId > 0 && $existingTecdoc !== $tecdocId) {
                    $this->model->update((int) $match['id'], [
                        'tecdoc_id' => $tecdocId,
                        'meta' => json_encode([
                            'source' => 'tecdoc',
                            'synced_at' => date('c'),
                            'sync_source' => (string) ($entry['source'] ?? 'tecdoc'),
                        ], JSON_UNESCAPED_UNICODE),
                    ]);
                    ++$updated;
                } else {
                    ++$skipped;
                }
                continue;
            }

            $payload = [
                'label' => $label,
                'slug' => $this->slugify($label),
                'type' => 'marca',
                'parent_id' => null,
                'is_active' => 1,
                'sort_order' => $sortOrder,
                'tecdoc_id' => $tecdocId > 0 ? $tecdocId : null,
                'meta' => json_encode([
                    'source' => 'tecdoc',
                    'synced_at' => date('c'),
                    'sync_source' => (string) ($entry['source'] ?? 'tecdoc'),
                ], JSON_UNESCAPED_UNICODE),
            ];

            $payload = $this->taxonomySync->enrichPayload($payload);
            if (!$this->model->insert($payload)) {
                continue;
            }

            $newId = (int) Database::getDB()->lastInsertId();
            if ($tecdocId > 0) {
                $existing['by_tecdoc'][$tecdocId] = ['id' => $newId, 'tecdoc_id' => $tecdocId, 'label' => $label];
            }
            $existing['by_label'][$norm] = ['id' => $newId, 'tecdoc_id' => $tecdocId, 'label' => $label];
            $sortOrder += 10;
            ++$imported;
        }

        if ($imported > 0 || $updated > 0) {
            $this->taxonomySync->syncAll(null);
        }

        $totalDb = (int) Database::getDB()->query("SELECT COUNT(*) FROM categorii WHERE type = 'marca'")->fetchColumn();
        $apiUsed = !empty($manufacturers[0]['api_batch']);

        return [
            'ok' => true,
            'source' => $apiUsed ? 'tecdoc_api+cache+db' : 'tecdoc_db+cache',
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'total_sources' => count($manufacturers),
            'total_db' => $totalDb,
            'api_used' => $apiUsed,
            'message' => sprintf(
                'Sincronizare mărci TecDoc: %d noi, %d actualizate, %d existente (%d surse → %d în DB).',
                $imported,
                $updated,
                $skipped,
                count($manufacturers),
                $totalDb
            ),
        ];
    }

    /**
     * @return list<array{label:string,tecdoc_id:?int,source:string,api_batch?:bool}>
     */
    public function collectManufacturers(bool $allowApi = true): array
    {
        $merged = [];
        $apiUsed = false;

        if ($allowApi) {
            $fromApi = $this->fetchFromApi();
            if ($fromApi !== []) {
                $apiUsed = true;
                $this->writeCache($fromApi);
                foreach ($fromApi as $row) {
                    $this->mergeManufacturer($merged, $row, true);
                }
            }
        }

        if ($merged === []) {
            foreach ($this->readCache() as $row) {
                $this->mergeManufacturer($merged, $row, false);
            }
        }

        foreach ($this->fetchFromCompatTables() as $row) {
            $this->mergeManufacturer($merged, $row, false);
        }

        if ($merged === []) {
            return [];
        }

        $result = array_values($merged);
        if ($apiUsed && $result !== []) {
            $result[0]['api_batch'] = true;
        }

        usort($result, static fn (array $a, array $b): int => strcasecmp((string) $a['label'], (string) $b['label']));

        return $result;
    }

    /**
     * @return list<array{label:string,tecdoc_id:int,source:string}>
     */
    private function fetchFromApi(): array
    {
        if (!defined('BESOIU_LEGACY')) {
            return [];
        }

        $legacyFile = BESOIU_LEGACY . '/tecdoc_stock.php';
        if (!is_file($legacyFile)) {
            return [];
        }

        require_once $legacyFile;

        if (
            function_exists('besoiu_api_automation_blocks_tecdoc_live')
            && besoiu_api_automation_blocks_tecdoc_live()
        ) {
            return [];
        }

        if (!function_exists('tecdoc_catalog_lang_id') || !function_exists('tecdoc_cached_response')) {
            return [];
        }

        $lang = tecdoc_catalog_lang_id();
        $country = function_exists('tecdoc_catalog_country_id') ? tecdoc_catalog_country_id() : 63;
        $host = defined('BESOiu_TECDOC_HOST') ? BESOiu_TECDOC_HOST : 'auto-parts-catalog.p.rapidapi.com';
        $url = "https://{$host}/manufacturers/list/type-id/1/lang-id/{$lang}/country-filter-id/{$country}";

        $body = tecdoc_cached_response($url, 86400 * 30);
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['error']) || (isset($decoded['message']) && !isset($decoded['manufacturers']))) {
            return [];
        }

        return $this->parseApiManufacturers($decoded);
    }

    /**
     * @param array<string, mixed> $decoded
     * @return list<array{label:string,tecdoc_id:int,source:string}>
     */
    private function parseApiManufacturers(array $decoded): array
    {
        $rows = $decoded['manufacturers'] ?? $decoded['data'] ?? [];
        if (is_array($rows) && isset($rows['array']) && is_array($rows['array'])) {
            $rows = $rows['array'];
        }
        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['manuId'] ?? $row['manu_id'] ?? $row['id'] ?? 0);
            $name = trim((string) ($row['manuName'] ?? $row['manu_name'] ?? $row['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $items[] = [
                'label' => $name,
                'tecdoc_id' => $id,
                'source' => 'tecdoc_api',
            ];
        }

        return $items;
    }

    /**
     * @return list<array{label:string,tecdoc_id:?int,source:string}>
     */
    private function fetchFromCompatTables(): array
    {
        $labels = [];

        try {
            $pdo = Database::getDB();
            if ($this->tableExists($pdo, 'tecdoc_product_compatibilities')) {
                $stmt = $pdo->query(
                    "SELECT DISTINCT TRIM(car_brand) AS label
                     FROM tecdoc_product_compatibilities
                     WHERE car_brand IS NOT NULL AND TRIM(car_brand) <> ''"
                );
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $label = trim((string) ($row['label'] ?? ''));
                    if ($label !== '') {
                        $labels[$this->normalizeLabel($label)] = $label;
                    }
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        try {
            if (function_exists('besoiupieseimport_tecdoc_legacy_pdo')) {
                $legacy = besoiupieseimport_tecdoc_legacy_pdo();
                if ($this->tableExists($legacy, 'product_compatibilities')) {
                    $stmt = $legacy->query(
                        "SELECT DISTINCT TRIM(car_brand) AS label
                         FROM product_compatibilities
                         WHERE car_brand IS NOT NULL AND TRIM(car_brand) <> ''"
                    );
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $label = trim((string) ($row['label'] ?? ''));
                        if ($label !== '') {
                            $labels[$this->normalizeLabel($label)] = $label;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        $items = [];
        foreach ($labels as $norm => $label) {
            $tecdocId = self::REFERENCE_MFA_IDS[$norm] ?? self::REFERENCE_MFA_IDS[strtoupper($label)] ?? null;
            $items[] = [
                'label' => $label,
                'tecdoc_id' => $tecdocId,
                'source' => 'tecdoc_mysql_compat',
            ];
        }

        return $items;
    }

    /**
     * @param array<string, array{label:string,tecdoc_id:?int,source:string}> $merged
     * @param array{label:string,tecdoc_id:?int,source:string} $row
     */
    private function mergeManufacturer(array &$merged, array $row, bool $preferIncoming): void
    {
        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '') {
            return;
        }

        $norm = $this->normalizeLabel($label);
        $tecdocId = (int) ($row['tecdoc_id'] ?? 0);
        if ($tecdocId <= 0) {
            $tecdocId = (int) (self::REFERENCE_MFA_IDS[$norm] ?? self::REFERENCE_MFA_IDS[strtoupper($label)] ?? 0);
        }

        $entry = [
            'label' => $label,
            'tecdoc_id' => $tecdocId > 0 ? $tecdocId : null,
            'source' => (string) ($row['source'] ?? 'tecdoc'),
        ];

        if (!isset($merged[$norm])) {
            $merged[$norm] = $entry;

            return;
        }

        $current = $merged[$norm];
        $currentId = (int) ($current['tecdoc_id'] ?? 0);
        if ($preferIncoming || ($entry['tecdoc_id'] !== null && $currentId <= 0)) {
            $merged[$norm] = $entry;
        }
    }

    /** @return list<array{label:string,tecdoc_id:int,source:string}> */
    private function readCache(): array
    {
        $path = self::cachePath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = $decoded['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            $id = (int) ($item['tecdoc_id'] ?? 0);
            if ($label === '' || $id <= 0) {
                continue;
            }
            $result[] = [
                'label' => $label,
                'tecdoc_id' => $id,
                'source' => 'tecdoc_cache',
            ];
        }

        return $result;
    }

    /** @param list<array{label:string,tecdoc_id:int,source:string}> $items */
    private function writeCache(array $items): void
    {
        $dir = TaxonomySyncService::storageDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            self::cachePath(),
            json_encode([
                'title' => 'TecDoc vehicle manufacturers (MFA)',
                'synced_at' => date('c'),
                'count' => count($items),
                'items' => $items,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /** @return array{by_tecdoc:array<int,array<string,mixed>>,by_label:array<string,array<string,mixed>>} */
    private function indexExistingMarci(): array
    {
        $byTecdoc = [];
        $byLabel = [];

        foreach ($this->model->findAll() as $row) {
            if ((string) ($row['type'] ?? '') !== 'marca') {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $tecdocId = (int) ($row['tecdoc_id'] ?? 0);
            $entry = ['id' => $id, 'tecdoc_id' => $tecdocId, 'label' => $label];
            if ($tecdocId > 0) {
                $byTecdoc[$tecdocId] = $entry;
            }
            if ($label !== '') {
                $byLabel[$this->normalizeLabel($label)] = $entry;
            }
        }

        return ['by_tecdoc' => $byTecdoc, 'by_label' => $byLabel];
    }

    private function nextSortOrder(): int
    {
        try {
            $max = (int) Database::getDB()->query(
                "SELECT COALESCE(MAX(sort_order), 0) FROM categorii WHERE type = 'marca'"
            )->fetchColumn();

            return $max + 10;
        } catch (\Throwable) {
            return 100;
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $safe = preg_replace('/[^a-z0-9_]/i', '', $table) ?? '';
        if ($safe === '') {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
            );
            $stmt->execute([$safe]);

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeLabel(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');
        $value = str_replace(
            ['Ă', 'Â', 'Î', 'Ș', 'Ş', 'Ț', 'Ţ'],
            ['A', 'A', 'I', 'S', 'S', 'T', 'T'],
            $value
        );

        return preg_replace('/[^A-Z0-9]+/', '', $value) ?? $value;
    }

    private function slugify(string $label): string
    {
        $slug = mb_strtolower($label, 'UTF-8');
        $slug = str_replace(
            ['ă', 'â', 'î', 'ș', 'ş', 'ț', 'ţ'],
            ['a', 'a', 'i', 's', 's', 't', 't'],
            $slug
        );
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;

        return trim($slug, '-') ?: 'marca';
    }
}
