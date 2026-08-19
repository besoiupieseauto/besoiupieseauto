<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Bibliotecă locală imagini TTC_ART_ID — mapare nume fișier (cifre) → URL public.
 * Sursă: folder Poze (brand/Poze/{TTC_ART_ID}.jpg) importat în uploads/products/ttc_library/.
 */
final class LocalTtcImageLibrary
{
    public const SOURCE_ID = 'local_ttc_poze';

    private const PUBLIC_PREFIX = '/uploads/products/ttc_library/';

    private static ?self $instance = null;

    private string $projectRoot;

    private string $storageDir;

    private string $manifestPath;

    /** @var array<string, mixed>|null */
    private ?array $manifestCache = null;

    public static function instance(?string $projectRoot = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($projectRoot);
        }

        return self::$instance;
    }

    private function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->storageDir = $this->projectRoot . '/uploads/products/ttc_library';
        $this->manifestPath = $this->projectRoot . '/admin/storage/ttc_image_library/manifest.json';
    }

    public function storageDir(): string
    {
        return $this->storageDir;
    }

    public function publicPrefix(): string
    {
        return self::PUBLIC_PREFIX;
    }

    public function normalizeTtcArtId(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '';
        }
        if (preg_match('/^\d+$/', $id)) {
            return $id;
        }

        return preg_replace('/[^\d]/', '', $id) ?? '';
    }

    public function publicUrlForId(string $ttcArtId): string
    {
        $id = $this->normalizeTtcArtId($ttcArtId);
        if ($id === '') {
            return '';
        }

        return self::PUBLIC_PREFIX . rawurlencode($id) . '.jpg';
    }

    /** Returnează URL public dacă fișierul există local, altfel string gol. */
    public function resolveUrl(string $ttcArtId): string
    {
        $path = $this->resolveAbsolutePath($ttcArtId);
        if ($path === null) {
            return '';
        }

        return $this->publicUrlForId($ttcArtId);
    }

    public function resolveAbsolutePath(string $ttcArtId): ?string
    {
        $id = $this->normalizeTtcArtId($ttcArtId);
        if ($id === '') {
            return null;
        }

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $path = $this->storageDir . '/' . $id . '.' . $ext;
            if (is_file($path) && (int) filesize($path) >= 512) {
                return $path;
            }
        }

        return null;
    }

    public function hasImage(string $ttcArtId): bool
    {
        return $this->resolveAbsolutePath($ttcArtId) !== null;
    }

    /**
     * @param array<string, mixed> $product
     * @return array{url: string, source: string, ttc_art_id: string, brand: string, query: string, match_score?: int, match_reason?: string, match_details?: array<string, mixed>}|null
     */
    public function lookupForProduct(array $product): ?array
    {
        $fromDb = $this->lookupImaginePozeDb($product);
        if ($fromDb !== null) {
            return $fromDb;
        }

        $ttcArtId = $this->extractTtcArtIdFromProduct($product);
        if ($ttcArtId !== '') {
            $url = $this->resolveUrl($ttcArtId);
            if ($url !== '') {
                $brand = $this->extractBrandFromProduct($product);
                require_once __DIR__ . '/TtcPozeCatalogIndex.php';
                $index = TtcPozeCatalogIndex::instance($this->projectRoot);
                if ($index->isReady()) {
                    $ctx = TtcPozeCatalogIndex::extractSearchContext($product);
                    $compactPath = $index->compactDbPath();
                    if ($index->isCompactActive() && is_file($compactPath)) {
                        $db = new \PDO('sqlite:' . $compactPath);
                        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                        $stmt = $db->prepare('SELECT * FROM ttc_code_brand WHERE ttc_art_id = :id LIMIT 1');
                        $stmt->execute([':id' => $ttcArtId]);
                        $catalogRow = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
                    } else {
                        $catalogRow = null;
                    }
                    if (is_array($catalogRow)) {
                        $validation = TtcPozeCatalogIndex::validatePozeGoldenRuleMatch($catalogRow, $ctx, 'code_brand');
                        if (empty($validation['accepted'])) {
                            return null;
                        }
                    }
                }

                return [
                    'url' => $url,
                    'source' => self::SOURCE_ID,
                    'ttc_art_id' => $ttcArtId,
                    'brand' => $brand,
                    'query' => $ttcArtId,
                    'match_score' => 100,
                    'match_reason' => 'TTC_ART_ID direct',
                ];
            }
        }

        require_once __DIR__ . '/TtcPozeCatalogIndex.php';
        $index = TtcPozeCatalogIndex::instance($this->projectRoot);
        if ($index->isReady()) {
            $hit = $index->lookupForProduct($product, $this);
            if (is_array($hit) && trim((string) ($hit['url'] ?? '')) !== '') {
                return $hit;
            }
        }

        return null;
    }

    /**
     * Lookup din baza imagine_poze (owner_code / ttc_art_id / aliasuri).
     *
     * @param array<string, mixed> $product
     * @return array<string, mixed>|null
     */
    private function lookupImaginePozeDb(array $product): ?array
    {
        require_once $this->projectRoot . '/app/Legacy/imagine-catalog-lookup.php';
        require_once $this->projectRoot . '/app/Legacy/product-code-normalize.php';
        $dbName = trim((string) ($_ENV['IMAGINE_POZE_DB'] ?? getenv('IMAGINE_POZE_DB') ?: 'imagine_poze'));
        $pdo = imagine_catalog_pdo($dbName);
        if (!$pdo instanceof \PDO) {
            return null;
        }

        $code = imagine_catalog_norm((string) ($product['pCode'] ?? ''));
        $brand = imagine_catalog_brand((string) ($product['pBrand'] ?? ''));
        $ttc = $this->extractTtcArtIdFromProduct($product);
        $row = null;
        if ($ttc !== '') {
            $stmt = $pdo->prepare('SELECT brand, code_norm, disk_name, rel_path, ttc_art_id FROM images WHERE ttc_art_id = :t AND original_path LIKE \'Poze/%\' ORDER BY disk_name LIMIT 1');
            $stmt->execute([':t' => $ttc]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }
        if (!is_array($row) && $code !== '') {
            if ($brand !== '') {
                $stmt = $pdo->prepare('SELECT brand, code_norm, disk_name, rel_path, ttc_art_id FROM images WHERE brand = :b AND code_norm = :c AND original_path LIKE \'Poze/%\' ORDER BY disk_name LIMIT 1');
                $stmt->execute([':b' => $brand, ':c' => $code]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            }
            if (!is_array($row)) {
                $stmt = $pdo->prepare('SELECT brand, code_norm, disk_name, rel_path, ttc_art_id FROM images WHERE code_norm = :c AND original_path LIKE \'Poze/%\' ORDER BY disk_name LIMIT 1');
                $stmt->execute([':c' => $code]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            }
        }
        if (!is_array($row) && $code !== '') {
            $stmt = $pdo->prepare(
                'SELECT i.brand, i.code_norm, i.disk_name, i.rel_path, i.ttc_art_id
                 FROM aliases a JOIN images i ON i.brand = a.brand AND i.code_norm = a.code_norm
                 WHERE a.alias_code_norm = :c AND i.original_path LIKE \'Poze/%\'
                 LIMIT 1'
            );
            $stmt->execute([':c' => $code]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }
        if (!is_array($row)) {
            return null;
        }

        $ren = trim((string) ($_ENV['TTC_POZE_RENAMED_DIR'] ?? getenv('TTC_POZE_RENAMED_DIR') ?: 'C:/laragon/www/besoiupieseimport/Poze_RENUMITE'));
        $origRoot = trim((string) ($_ENV['TTC_POZE_DIR'] ?? getenv('TTC_POZE_DIR') ?: 'C:/laragon/www/besoiupieseimport/Poze'));
        $abs = imagine_catalog_first_file([
            $ren . '/' . $row['brand'] . '/' . $row['disk_name'],
            dirname($ren) . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string) $row['rel_path']),
            $origRoot . '/' . str_replace(['Poze/', 'Poze\\'], '', (string) $row['rel_path']),
        ]);
        $url = imagine_catalog_publish($abs, $this->storageDir, self::PUBLIC_PREFIX, (string) $row['disk_name']);
        if ($url === '') {
            return null;
        }

        return [
            'url' => $url,
            'source' => self::SOURCE_ID,
            'ttc_art_id' => (string) ($row['ttc_art_id'] ?? ''),
            'brand' => (string) $row['brand'],
            'query' => (string) $row['code_norm'],
            'match_score' => 100,
            'match_reason' => 'imagine_poze ' . $row['brand'] . '-' . $row['code_norm'],
        ];
    }

    /**
     * @param array<string, mixed> $product
     */
    public function extractTtcArtIdFromProduct(array $product): string
    {
        if (function_exists('import_ttc_art_id_from_product')) {
            $id = trim((string) import_ttc_art_id_from_product($product));
            $normalized = $this->normalizeTtcArtId($id);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        $raw = json_decode((string) ($product['raw_json'] ?? '{}'), true);
        if (!is_array($raw)) {
            return '';
        }

        $tecdocFile = is_array($raw['tecdoc_file'] ?? null) ? $raw['tecdoc_file'] : [];
        $id = $this->normalizeTtcArtId(trim((string) ($tecdocFile['ttc_art_id'] ?? '')));
        if ($id !== '') {
            return $id;
        }

        foreach (['tecdoc_api', 'tecdoc_import_enrichment'] as $section) {
            $meta = $raw[$section] ?? null;
            if (!is_array($meta)) {
                continue;
            }
            $id = $this->normalizeTtcArtId(trim((string) ($meta['ttc_art_id'] ?? '')));
            if ($id !== '') {
                return $id;
            }
        }

        foreach (['rows', 'source_rows'] as $key) {
            foreach ((array) ($raw[$key] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (function_exists('import_base_row_get')) {
                    $id = $this->normalizeTtcArtId(import_base_row_get($row, 'ttc art id'));
                    if ($id !== '') {
                        return $id;
                    }
                }
                foreach (['ttc art id', 'TTC_ART_ID', 'ttc_art_id'] as $col) {
                    $id = $this->normalizeTtcArtId(trim((string) ($row[$col] ?? '')));
                    if ($id !== '') {
                        return $id;
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $product
     */
    private function extractBrandFromProduct(array $product): string
    {
        if (function_exists('import_ttc_art_brand_from_product')) {
            $brand = trim((string) import_ttc_art_brand_from_product($product));
            if ($brand !== '') {
                return $brand;
            }
        }

        return trim((string) ($product['pBrand'] ?? ''));
    }

    public function ensureStorageDir(): void
    {
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
        $manifestDir = dirname($this->manifestPath);
        if (!is_dir($manifestDir)) {
            mkdir($manifestDir, 0755, true);
        }
    }

    /** @return array<string, mixed> */
    public function loadManifest(): array
    {
        if (is_array($this->manifestCache)) {
            return $this->manifestCache;
        }

        if (!is_file($this->manifestPath)) {
            $this->manifestCache = $this->defaultManifest();

            return $this->manifestCache;
        }

        $decoded = json_decode((string) file_get_contents($this->manifestPath), true);
        $this->manifestCache = is_array($decoded) ? $decoded : $this->defaultManifest();

        return $this->manifestCache;
    }

    /** @param array<string, mixed> $manifest */
    public function saveManifest(array $manifest): void
    {
        $this->ensureStorageDir();
        $manifest['updated_at'] = date('c');
        file_put_contents(
            $this->manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $this->manifestCache = $manifest;
    }

    /** @return array<string, mixed> */
    private function defaultManifest(): array
    {
        return [
            'version' => 1,
            'source_path' => '',
            'indexed_count' => 0,
            'copied_count' => 0,
            'skipped_count' => 0,
            'error_count' => 0,
            'updated_at' => null,
        ];
    }

    public function countStoredImages(): int
    {
        $manifest = $this->loadManifest();
        $cached = (int) ($manifest['indexed_count'] ?? 0);
        if ($cached > 0) {
            return $cached;
        }

        if (!is_dir($this->storageDir)) {
            return 0;
        }

        // Evită glob() pe zeci/sute de mii de fișiere — alocă tot array-ul în RAM.
        $count = 0;
        try {
            $iterator = new \FilesystemIterator(
                $this->storageDir,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO
            );
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }
                $ext = strtolower($fileInfo->getExtension());
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    ++$count;
                }
            }
        } catch (\Throwable) {
            return 0;
        }

        return $count;
    }

    /**
     * Status combinat bibliotecă + index catalog (panou admin Import).
     *
     * @return array<string, mixed>
     */
    public function adminStatus(bool $forceRefresh = false): array
    {
        require_once __DIR__ . '/TtcPozeCatalogIndex.php';
        $index = TtcPozeCatalogIndex::instance($this->projectRoot);
        $manifest = $this->loadManifest();
        $indexSummary = $index->adminStatusSummary($forceRefresh, (string) ($manifest['source_path'] ?? ''));

        $pipeline = trim((string) ($_ENV['IMAGE_SEARCH_SOURCES'] ?? getenv('IMAGE_SEARCH_SOURCES') ?: ''));
        $sources = $pipeline !== '' ? array_values(array_filter(array_map('trim', explode(',', $pipeline)))) : [];

        return array_merge($indexSummary, [
            'images_stored' => $this->countStoredImages(),
            'source_path' => (string) ($manifest['source_path'] ?? ''),
            'last_image_import' => is_array($manifest['last_import'] ?? null) ? $manifest['last_import'] : null,
            'source_id' => self::SOURCE_ID,
            'pipeline_first' => $sources[0] ?? self::SOURCE_ID,
            'storage_public' => self::PUBLIC_PREFIX,
        ]);
    }

    /**
     * Importă imagini din arborele Poze (brand/Poze/{id}.jpg).
     *
     * @param array<string, mixed> $options source_root, limit, dry_run, resume, force, log callback
     * @return array<string, int|string>
     */
    public function importFromSourceTree(string $sourceRoot, array $options = []): array
    {
        $sourceRoot = rtrim(str_replace('\\', '/', $sourceRoot), '/');
        if ($sourceRoot === '' || !is_dir($sourceRoot)) {
            throw new \InvalidArgumentException('Folder sursă invalid: ' . $sourceRoot);
        }

        $this->ensureStorageDir();

        $limit = max(0, (int) ($options['limit'] ?? 0));
        $dryRun = !empty($options['dry_run']);
        $resume = !array_key_exists('resume', $options) || !empty($options['resume']);
        $force = !empty($options['force']);
        $log = $options['log'] ?? null;

        $stats = [
            'scanned' => 0,
            'copied' => 0,
            'skipped' => 0,
            'errors' => 0,
            'invalid_name' => 0,
        ];

        $logFn = static function (string $msg) use ($log): void {
            if (is_callable($log)) {
                $log($msg);
            }
        };

        $brandDirs = glob($sourceRoot . '/*', GLOB_ONLYDIR) ?: [];
        sort($brandDirs);

        foreach ($brandDirs as $brandDir) {
            $pozeDirs = $this->discoverPozeDirs($brandDir);
            foreach ($pozeDirs as $pozeDir) {
                $files = glob($pozeDir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];
                foreach ($files as $sourceFile) {
                    if ($limit > 0 && $stats['copied'] >= $limit) {
                        $logFn('Limită atinsă (' . $limit . ').');

                        return $this->finalizeImportStats($sourceRoot, $stats);
                    }

                    ++$stats['scanned'];
                    $baseName = pathinfo($sourceFile, PATHINFO_FILENAME);
                    $ttcId = $this->normalizeTtcArtId($baseName);
                    if ($ttcId === '') {
                        ++$stats['invalid_name'];
                        continue;
                    }

                    $dest = $this->storageDir . '/' . $ttcId . '.jpg';
                    if ($resume && !$force && is_file($dest) && (int) filesize($dest) >= 512) {
                        ++$stats['skipped'];
                        continue;
                    }

                    if ($dryRun) {
                        ++$stats['copied'];
                        continue;
                    }

                    if (!$this->copyImageToLibrary($sourceFile, $dest)) {
                        ++$stats['errors'];
                        $logFn('Eroare copiere: ' . $sourceFile);
                        continue;
                    }

                    ++$stats['copied'];
                    if ($stats['copied'] % 500 === 0) {
                        $logFn('Copiate ' . $stats['copied'] . ' imagini…');
                    }
                }
            }
        }

        return $this->finalizeImportStats($sourceRoot, $stats);
    }

    /** @return list<string> */
    private function discoverPozeDirs(string $brandDir): array
    {
        $dirs = [];
        $directPoze = $brandDir . '/Poze';
        if (is_dir($directPoze)) {
            $dirs[] = $directPoze;
        }

        foreach (glob($brandDir . '/*/Poze', GLOB_ONLYDIR) ?: [] as $nested) {
            $dirs[] = $nested;
        }

        $hasImages = glob($brandDir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];
        if ($hasImages !== [] && $dirs === []) {
            $dirs[] = $brandDir;
        }

        return array_values(array_unique($dirs));
    }

    private function copyImageToLibrary(string $source, string $dest): bool
    {
        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        if ($ext !== 'jpg') {
            if (!function_exists('imagecreatefromstring')) {
                return copy($source, $dest);
            }
            $blob = @file_get_contents($source);
            if ($blob === false || $blob === '') {
                return false;
            }
            $img = @imagecreatefromstring($blob);
            if ($img === false) {
                return false;
            }
            $ok = imagejpeg($img, $dest, 88);
            imagedestroy($img);

            return $ok;
        }

        return copy($source, $dest);
    }

    /**
     * @param array<string, int> $stats
     * @return array<string, int|string>
     */
    private function finalizeImportStats(string $sourceRoot, array $stats): array
    {
        $manifest = $this->loadManifest();
        $manifest['source_path'] = $sourceRoot;
        $manifest['indexed_count'] = $this->countStoredImages();
        $manifest['copied_count'] = (int) ($manifest['copied_count'] ?? 0) + $stats['copied'];
        $manifest['skipped_count'] = (int) ($manifest['skipped_count'] ?? 0) + $stats['skipped'];
        $manifest['error_count'] = (int) ($manifest['error_count'] ?? 0) + $stats['errors'];
        $manifest['last_import'] = [
            'scanned' => $stats['scanned'],
            'copied' => $stats['copied'],
            'skipped' => $stats['skipped'],
            'errors' => $stats['errors'],
            'invalid_name' => $stats['invalid_name'],
            'at' => date('c'),
        ];
        $this->saveManifest($manifest);

        return array_merge($stats, [
            'stored_total' => $manifest['indexed_count'],
            'manifest' => $this->manifestPath,
        ]);
    }
}
