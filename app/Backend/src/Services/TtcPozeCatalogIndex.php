<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Index SQLite din Excel-urile Poze — căutare imagine după cod, brand, OEM.
 *
 * Mod compact (recomandat): doar mapări cod+brand→TTC și OEM→TTC (~50–100 MB).
 * Mod full (legacy): fiecare rând Excel inclusiv compatibilități vehicul (poate depăși 1 GB).
 */
final class TtcPozeCatalogIndex
{
    private string $storageDir;

    /** @deprecated legacy — mod full, foarte mare */
    private string $dbPath;

    private string $compactDbPath;

    private static ?self $instance = null;

    private ?\PDO $pdoCache = null;

    private ?string $pdoCachePath = null;

    /** @var array<string, mixed>|null */
    private static ?array $lastMatchDebug = null;

    public static function instance(?string $projectRoot = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($projectRoot);
        }

        return self::$instance;
    }

    /**
     * Rădăcină arbore Poze (Excel + imagini) — regula de aur: mereu din Excel intern.
     */
    public static function resolveSourceRoot(?string $override = null): string
    {
        $candidates = [];
        if ($override !== null && trim($override) !== '') {
            $candidates[] = trim($override);
        }
        $env = trim((string) ($_ENV['TTC_IMAGE_LIBRARY_SOURCE'] ?? getenv('TTC_IMAGE_LIBRARY_SOURCE') ?: ''));
        if ($env !== '') {
            $candidates[] = $env;
        }
        $candidates[] = 'F:/Poze';
        $candidates[] = 'G:/Project/besoiupieseauto/Images_work/Poze';

        $manifestPath = dirname(__DIR__, 2) . '/admin/storage/ttc_image_library/manifest.json';
        if (is_file($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (is_array($manifest) && trim((string) ($manifest['source_path'] ?? '')) !== '') {
                $candidates[] = (string) $manifest['source_path'];
            }
        }

        foreach ($candidates as $path) {
            $path = rtrim(str_replace('\\', '/', trim($path)), '/');
            if ($path !== '' && is_dir($path)) {
                return $path;
            }
        }

        return '';
    }

    private function __construct(?string $projectRoot = null)
    {
        $root = $projectRoot ?? dirname(__DIR__, 3);
        $dir = $root . '/admin/storage/ttc_image_library';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->storageDir = $dir;
        $this->dbPath = $dir . '/catalog.sqlite';
        $this->compactDbPath = $dir . '/catalog_compact.sqlite';
    }

    public function dbPath(): string
    {
        return $this->activeDbPath();
    }

    public function compactDbPath(): string
    {
        return $this->compactDbPath;
    }

    public function isCompactActive(): bool
    {
        return is_file($this->compactDbPath) && $this->compactCodeBrandCount() > 0;
    }

    private function activeDbPath(): string
    {
        if ($this->isCompactActive()) {
            return $this->compactDbPath;
        }

        return $this->dbPath;
    }

    private function compactProgressPath(): string
    {
        return $this->storageDir . '/index_compact_progress.json';
    }

    public function isReady(): bool
    {
        return $this->isCompactActive() || (is_file($this->dbPath) && $this->fullRowCount() > 0);
    }

    public function countRows(): int
    {
        if ($this->isCompactActive()) {
            return $this->compactCodeBrandCount() + $this->compactOemCount();
        }

        return $this->fullRowCount();
    }

    private function fullRowCount(): int
    {
        if (!is_file($this->dbPath)) {
            return 0;
        }
        $pdo = $this->pdoForPath($this->dbPath);
        if ($pdo === null) {
            return 0;
        }

        return (int) $pdo->query('SELECT COUNT(*) FROM ttc_catalog')->fetchColumn();
    }

    public function compactCodeBrandCount(): int
    {
        if (!is_file($this->compactDbPath)) {
            return 0;
        }
        $pdo = $this->pdoForPath($this->compactDbPath);
        if ($pdo === null) {
            return 0;
        }

        try {
            return (int) $pdo->query('SELECT COUNT(*) FROM ttc_code_brand')->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function compactOemCount(): int
    {
        if (!is_file($this->compactDbPath)) {
            return 0;
        }
        $pdo = $this->pdoForPath($this->compactDbPath);
        if ($pdo === null) {
            return 0;
        }

        try {
            return (int) $pdo->query('SELECT COUNT(*) FROM ttc_oem')->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function countDistinctTtc(): int
    {
        if ($this->isCompactActive()) {
            $pdo = $this->pdoForPath($this->compactDbPath);
            if ($pdo === null) {
                return 0;
            }

            return (int) $pdo->query('
                SELECT COUNT(DISTINCT ttc_art_id) FROM (
                    SELECT ttc_art_id FROM ttc_code_brand
                    UNION
                    SELECT ttc_art_id FROM ttc_oem
                )
            ')->fetchColumn();
        }

        if (!is_file($this->dbPath)) {
            return 0;
        }
        $pdo = $this->pdoForPath($this->dbPath);
        if ($pdo === null) {
            return 0;
        }

        return (int) $pdo->query('SELECT COUNT(DISTINCT ttc_art_id) FROM ttc_catalog')->fetchColumn();
    }

    /** @return list<string> */
    public function indexedSourceFiles(): array
    {
        if ($this->isCompactActive()) {
            return $this->loadCompactProgressFiles();
        }

        if (!is_file($this->dbPath)) {
            return [];
        }
        $pdo = $this->pdoForPath($this->dbPath);
        if ($pdo === null) {
            return [];
        }

        $rows = $pdo->query('SELECT DISTINCT source_file FROM ttc_catalog WHERE source_file IS NOT NULL AND source_file != \'\' ORDER BY source_file')->fetchAll(\PDO::FETCH_COLUMN);

        return is_array($rows) ? array_values(array_filter(array_map('strval', $rows))) : [];
    }

    /** @return list<string> */
    private function loadCompactProgressFiles(): array
    {
        $path = $this->compactProgressPath();
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data['completed_files'] ?? null)
            ? array_values(array_filter(array_map('strval', $data['completed_files'])))
            : [];
    }

    /** @param list<string> $files */
    private function saveCompactProgressFiles(array $files): void
    {
        file_put_contents($this->compactProgressPath(), json_encode([
            'completed_files' => array_values(array_unique($files)),
            'updated_at' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function countIndexedSourceFiles(): int
    {
        return count($this->indexedSourceFiles());
    }

    /**
     * Rezumat pentru panoul admin (cu cache scurt — COUNT pe SQLite e lent).
     *
     * @return array<string, mixed>
     */
    public function adminStatusSummary(bool $forceRefresh = false, ?string $sourceRoot = null): array
    {
        $storageDir = dirname($this->dbPath);
        $cachePath = $storageDir . '/status_cache.json';
        $indexingActive = self::detectIndexingActive($storageDir);
        $lastLogLine = self::readLastIndexLogLine($storageDir);

        if (!$forceRefresh && is_file($cachePath)) {
            $cached = json_decode((string) file_get_contents($cachePath), true);
            if (is_array($cached) && (time() - (int) ($cached['cached_at'] ?? 0)) < 120) {
                $cached['indexing_active'] = $indexingActive;
                $cached['last_log_line'] = $lastLogLine;

                return $cached;
            }
        }

        $catMeta = is_file($storageDir . '/catalog_meta.json')
            ? json_decode((string) file_get_contents($storageDir . '/catalog_meta.json'), true)
            : null;

        $rows = $this->countRows();
        $distinctTtc = $this->countDistinctTtc();
        $filesIndexed = $this->countIndexedSourceFiles();
        $filesTotal = (int) ($catMeta['files_total'] ?? 0);
        $sourceRoot = trim((string) ($sourceRoot ?? ''));
        if ($sourceRoot === '') {
            $sourceRoot = self::resolveSourceRoot();
        }
        if ($filesTotal <= 0 && $sourceRoot !== '' && is_dir($sourceRoot)) {
            $filesTotal = self::countPozeXlsxFiles($sourceRoot);
        }

        $activePath = $this->activeDbPath();
        $summary = [
            'cached_at' => time(),
            'index_mode' => $this->isCompactActive() ? 'compact' : 'full',
            'db_path' => $activePath,
            'db_size_mb' => is_file($activePath) ? round(filesize($activePath) / 1048576, 1) : 0,
            'legacy_db_size_mb' => is_file($this->dbPath) ? round(filesize($this->dbPath) / 1048576, 1) : 0,
            'index_rows' => $rows,
            'code_brand_rows' => $this->compactCodeBrandCount(),
            'oem_rows' => $this->compactOemCount(),
            'index_ttc_unique' => $distinctTtc,
            'files_indexed' => $filesIndexed,
            'files_total' => $filesTotal,
            'index_ready' => $rows > 0,
            'index_complete' => $filesTotal > 0 && $filesIndexed >= $filesTotal,
            'indexing_active' => $indexingActive,
            'last_log_line' => $lastLogLine,
            'catalog_built_at' => is_array($catMeta) ? ($catMeta['built_at'] ?? null) : null,
            'progress_pct' => $filesTotal > 0 ? min(100, round(($filesIndexed / $filesTotal) * 100, 1)) : null,
        ];

        file_put_contents($cachePath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $summary;
    }

    public static function countPozeXlsxFiles(string $sourceRoot): int
    {
        $sourceRoot = rtrim(str_replace('\\', '/', $sourceRoot), '/');
        $files = glob($sourceRoot . '/*/*.xlsx') ?: [];
        $files = array_merge($files, glob($sourceRoot . '/*/*/*.xlsx') ?: []);
        $files = array_values(array_unique(array_filter(
            $files,
            static fn (string $path): bool => !self::shouldSkipXlsxFile($path)
        )));

        return count($files);
    }

    private static function detectIndexingActive(string $storageDir): bool
    {
        foreach (['index_full.log', 'index_resume.log', 'index_compact.log'] as $name) {
            $path = $storageDir . '/' . $name;
            if (!is_file($path)) {
                continue;
            }
            $mtime = filemtime($path);
            if ($mtime === false || (time() - $mtime) > 180) {
                continue;
            }
            $tail = self::readFileTail($path, 4096);
            if ($tail !== '' && !preg_match('/Total fișiere indexate:/u', $tail)) {
                return true;
            }
        }

        return false;
    }

    private static function readLastIndexLogLine(string $storageDir): string
    {
        $line = '';
        foreach (['index_full.log', 'index_resume.log', 'index_compact.log'] as $name) {
            $path = $storageDir . '/' . $name;
            if (!is_file($path)) {
                continue;
            }
            $candidate = self::readFileTail($path, 16384);
            if ($candidate === '') {
                continue;
            }
            $candidate = preg_replace('/\x00/u', '', $candidate) ?? $candidate;
            $parts = preg_split('/\R/u', trim($candidate)) ?: [];
            for ($i = count($parts) - 1; $i >= 0; --$i) {
                $row = trim((string) $parts[$i]);
                if ($row === '' || str_starts_with($row, 'Fatal error') || str_starts_with($row, 'Stack trace')) {
                    continue;
                }
                if (preg_match('/^\[\d{2}:\d{2}:\d{2}\]/', $row)) {
                    $line = $row;

                    break 2;
                }
                if ($line === '' && strlen($row) < 200) {
                    $line = $row;
                }
            }
        }

        return $line;
    }

    private static function readFileTail(string $path, int $bytes = 4096): string
    {
        $size = filesize($path);
        if ($size === false || $size <= 0) {
            return '';
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }
        $readFrom = max(0, $size - $bytes);
        fseek($handle, $readFrom);
        $chunk = (string) fread($handle, min($bytes, $size));
        fclose($handle);

        return $chunk;
    }

    private function pdo(): ?\PDO
    {
        return $this->pdoForPath($this->activeDbPath());
    }

    private function pdoForPath(string $path): ?\PDO
    {
        if ($this->pdoCache instanceof \PDO && $this->pdoCachePath === $path) {
            return $this->pdoCache;
        }

        if (!is_file($path) && !str_ends_with($path, '.sqlite')) {
            return null;
        }

        try {
            $pdo = new \PDO('sqlite:' . $path);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA busy_timeout = 60000');
            try {
                $pdo->exec('PRAGMA journal_mode = WAL');
            } catch (\Throwable) {
            }
            $this->pdoCache = $pdo;
            $this->pdoCachePath = $path;
            if ($path === $this->compactDbPath) {
                $this->migrateCompactSchema($pdo);
            }

            return $pdo;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $params */
    private function executeWithRetry(\PDO $pdo, \PDOStatement &$insert, array $params, int $maxAttempts = 10): void
    {
        for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
            try {
                $insert->execute($params);

                return;
            } catch (\PDOException $e) {
                $msg = $e->getMessage();
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
                if (str_contains($msg, 'locked') || str_contains($msg, 'misuse')) {
                    usleep(150000 * $attempt);
                    $insert = $this->prepareCatalogInsert($pdo);

                    continue;
                }
                throw $e;
            }
        }
    }

    private function prepareCatalogInsert(\PDO $pdo): \PDOStatement
    {
        return $pdo->prepare('
            INSERT INTO ttc_catalog (
                ttc_art_id, art_brand, art_code_1, art_code_2, art_name, art_ean, parts_info, art_cross,
                car_brand, car_model, car_typ, car_body, car_of_year, car_to_year, car_kw,
                code_norm, brand_norm, car_brand_norm, car_model_norm, car_typ_norm, source_file
            ) VALUES (
                :ttc, :brand, :code1, :code2, :name, :ean, :parts, :cross,
                :cbrand, :cmodel, :ctyp, :cbody, :cyfrom, :cyto, :ckw,
                :cnorm, :bnorm, :cbnorm, :cmnorm, :ctnorm, :src
            )
        ');
    }

    private function commitWithRetry(\PDO $pdo, int $maxAttempts = 10): void
    {
        for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
            try {
                $pdo->commit();

                return;
            } catch (\PDOException $e) {
                if ($attempt >= $maxAttempts || !str_contains($e->getMessage(), 'locked')) {
                    throw $e;
                }
                usleep(150000 * $attempt);
            }
        }
    }

    public function ensureSchema(\PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS ttc_catalog (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ttc_art_id TEXT NOT NULL,
                art_brand TEXT,
                art_code_1 TEXT,
                art_code_2 TEXT,
                art_name TEXT,
                art_ean TEXT,
                parts_info TEXT,
                art_cross TEXT,
                car_brand TEXT,
                car_model TEXT,
                car_typ TEXT,
                car_body TEXT,
                car_of_year TEXT,
                car_to_year TEXT,
                car_kw TEXT,
                code_norm TEXT,
                brand_norm TEXT,
                car_brand_norm TEXT,
                car_model_norm TEXT,
                car_typ_norm TEXT,
                source_file TEXT
            )
        ');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ttc ON ttc_catalog(ttc_art_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_code_brand ON ttc_catalog(code_norm, brand_norm)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_car ON ttc_catalog(car_brand_norm, car_model_norm)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_oem ON ttc_catalog(art_cross)');
    }

    public function ensureCompactSchema(\PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS ttc_code_brand (
                code_norm TEXT NOT NULL,
                brand_norm TEXT NOT NULL,
                ttc_art_id TEXT NOT NULL,
                art_code_1 TEXT,
                art_brand TEXT,
                art_name TEXT,
                source_file TEXT,
                PRIMARY KEY (code_norm, brand_norm)
            )
        ');
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS ttc_oem (
                oem_norm TEXT NOT NULL,
                ttc_art_id TEXT NOT NULL,
                code_norm TEXT,
                brand_norm TEXT,
                PRIMARY KEY (oem_norm, ttc_art_id)
            )
        ');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_code_brand_code ON ttc_code_brand(code_norm)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_oem_norm ON ttc_oem(oem_norm)');
        $this->migrateCompactSchema($pdo);
    }

    private function migrateCompactSchema(\PDO $pdo): void
    {
        $cols = $pdo->query('PRAGMA table_info(ttc_code_brand)')->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $names = array_column($cols, 'name');
        if (!in_array('art_title', $names, true)) {
            $pdo->exec('ALTER TABLE ttc_code_brand ADD COLUMN art_title TEXT');
        }
        if (!in_array('art_description', $names, true)) {
            $pdo->exec('ALTER TABLE ttc_code_brand ADD COLUMN art_description TEXT');
        }
    }

    /**
     * @param array<string, mixed> $options source_root, limit_files, resume, log, compact
     * @return array<string, int|string|bool>
     */
    public function buildFromPozeTree(string $sourceRoot, array $options = []): array
    {
        if (!empty($options['compact'])) {
            return $this->buildCompactFromPozeTree($sourceRoot, $options);
        }

        return $this->buildFullFromPozeTree($sourceRoot, $options);
    }

    /**
     * Index compact — doar cod+brand→TTC și OEM→TTC (fără rânduri vehicul).
     *
     * @param array<string, mixed> $options
     * @return array<string, int|string|bool>
     */
    public function buildCompactFromPozeTree(string $sourceRoot, array $options = []): array
    {
        $sourceRoot = rtrim(str_replace('\\', '/', $sourceRoot), '/');
        $pdo = $this->pdoForPath($this->compactDbPath);
        if ($pdo === null) {
            throw new \RuntimeException('SQLite compact indisponibil.');
        }

        $resume = !empty($options['resume']);
        $this->ensureCompactSchema($pdo);
        if (!$resume) {
            $pdo->exec('DELETE FROM ttc_code_brand');
            $pdo->exec('DELETE FROM ttc_oem');
            $this->saveCompactProgressFiles([]);
        }

        $limitFiles = max(0, (int) ($options['limit_files'] ?? 0));
        $log = $options['log'] ?? null;
        $filesProcessed = 0;
        $filesSkipped = 0;
        $codeBrandUpserts = 0;
        $oemInserts = 0;
        $completedFiles = $this->loadCompactProgressFiles();
        $alreadyIndexed = $resume ? array_fill_keys($completedFiles, true) : [];

        $xlsxFiles = glob($sourceRoot . '/*/*.xlsx') ?: [];
        $xlsxFiles = array_merge($xlsxFiles, glob($sourceRoot . '/*/*/*.xlsx') ?: []);
        $xlsxFiles = array_values(array_unique(array_filter(
            $xlsxFiles,
            static fn (string $path): bool => !self::shouldSkipXlsxFile($path)
        )));
        sort($xlsxFiles);

        $upsertCode = $pdo->prepare('
            INSERT INTO ttc_code_brand (code_norm, brand_norm, ttc_art_id, art_code_1, art_brand, art_name, art_title, art_description, source_file)
            VALUES (:cnorm, :bnorm, :ttc, :code1, :brand, :name, :title, :desc, :src)
            ON CONFLICT(code_norm, brand_norm) DO UPDATE SET
                ttc_art_id = excluded.ttc_art_id,
                art_code_1 = excluded.art_code_1,
                art_brand = excluded.art_brand,
                art_name = excluded.art_name,
                art_title = excluded.art_title,
                art_description = excluded.art_description,
                source_file = excluded.source_file
        ');
        $insertOem = $pdo->prepare('
            INSERT OR IGNORE INTO ttc_oem (oem_norm, ttc_art_id, code_norm, brand_norm)
            VALUES (:oem, :ttc, :cnorm, :bnorm)
        ');

        $pdo->beginTransaction();
        foreach ($xlsxFiles as $file) {
            if ($limitFiles > 0 && $filesProcessed >= $limitFiles) {
                break;
            }

            $sourceKey = self::sourceFileKey($file);
            if (isset($alreadyIndexed[$sourceKey])) {
                ++$filesSkipped;
                continue;
            }

            ++$filesProcessed;
            if (is_callable($log)) {
                $log('Indexez compact ' . $sourceKey . '…');
            }

            $fileCodeUpserts = 0;
            foreach (TtcPozeXlsxReader::readRows($file) as $row) {
                $ttc = trim((string) ($row['ttc_art_id'] ?? ''));
                if ($ttc === '') {
                    continue;
                }
                $code1 = trim((string) ($row['art_code_1'] ?? ''));
                $brand = trim((string) ($row['art_brand'] ?? ''));
                $codeNorm = self::normCode($code1);
                $brandNorm = self::normBrand($brand);
                if ($codeNorm !== '' && $brandNorm !== '') {
                    $textFields = self::catalogRowTextFields($row);
                    $this->executeWithRetry($pdo, $upsertCode, [
                        ':cnorm' => $codeNorm,
                        ':bnorm' => $brandNorm,
                        ':ttc' => $ttc,
                        ':code1' => $code1,
                        ':brand' => $brand,
                        ':name' => $textFields['art_name'],
                        ':title' => $textFields['art_title'],
                        ':desc' => $textFields['art_description'],
                        ':src' => $sourceKey,
                    ]);
                    ++$codeBrandUpserts;
                    ++$fileCodeUpserts;
                }
                $code2 = trim((string) ($row['art_code_2'] ?? ''));
                $code2Norm = self::normCode($code2);
                if ($code2Norm !== '' && $code2Norm !== $codeNorm && $brandNorm !== '') {
                    $textFields = self::catalogRowTextFields($row);
                    $this->executeWithRetry($pdo, $upsertCode, [
                        ':cnorm' => $code2Norm,
                        ':bnorm' => $brandNorm,
                        ':ttc' => $ttc,
                        ':code1' => $code2,
                        ':brand' => $brand,
                        ':name' => $textFields['art_name'],
                        ':title' => $textFields['art_title'],
                        ':desc' => $textFields['art_description'],
                        ':src' => $sourceKey,
                    ]);
                    ++$codeBrandUpserts;
                }
                foreach (self::oemTokensFromRow($row) as $oemNorm) {
                    $this->executeWithRetry($pdo, $insertOem, [
                        ':oem' => $oemNorm,
                        ':ttc' => $ttc,
                        ':cnorm' => $codeNorm,
                        ':bnorm' => $brandNorm,
                    ]);
                    ++$oemInserts;
                }
                if ($codeBrandUpserts % 5000 === 0) {
                    $this->commitWithRetry($pdo);
                    $pdo->beginTransaction();
                    $upsertCode = $pdo->prepare('
                        INSERT INTO ttc_code_brand (code_norm, brand_norm, ttc_art_id, art_code_1, art_brand, art_name, art_title, art_description, source_file)
                        VALUES (:cnorm, :bnorm, :ttc, :code1, :brand, :name, :title, :desc, :src)
                        ON CONFLICT(code_norm, brand_norm) DO UPDATE SET
                            ttc_art_id = excluded.ttc_art_id,
                            art_code_1 = excluded.art_code_1,
                            art_brand = excluded.art_brand,
                            art_name = excluded.art_name,
                            art_title = excluded.art_title,
                            art_description = excluded.art_description,
                            source_file = excluded.source_file
                    ');
                    $insertOem = $pdo->prepare('
                        INSERT OR IGNORE INTO ttc_oem (oem_norm, ttc_art_id, code_norm, brand_norm)
                        VALUES (:oem, :ttc, :cnorm, :bnorm)
                    ');
                    if (is_callable($log)) {
                        $log('Compact: ' . $codeBrandUpserts . ' mapări cod+brand, ' . $oemInserts . ' OEM…');
                    }
                }
            }

            if ($fileCodeUpserts > 0 || $filesProcessed > 0) {
                $completedFiles[] = $sourceKey;
                $this->saveCompactProgressFiles($completedFiles);
            }
        }
        $this->commitWithRetry($pdo);

        $this->pdoCache = null;
        $this->pdoCachePath = null;

        $meta = [
            'built_at' => date('c'),
            'index_mode' => 'compact',
            'source_root' => $sourceRoot,
            'resume' => $resume,
            'files_total' => count($xlsxFiles),
            'files_processed' => $filesProcessed,
            'files_skipped' => $filesSkipped,
            'files_indexed' => count($completedFiles),
            'code_brand_rows' => $this->compactCodeBrandCount(),
            'oem_rows' => $this->compactOemCount(),
            'rows_added' => $codeBrandUpserts + $oemInserts,
            'rows' => $this->countRows(),
            'distinct_ttc' => $this->countDistinctTtc(),
        ];
        file_put_contents(
            $this->storageDir . '/catalog_meta.json',
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $this->optimizeCompactDatabase($pdo);

        return $meta;
    }

    /**
     * VACUUM + ANALYZE pe index compact; opțional șterge catalog.sqlite legacy (full).
     *
     * @return array<string, mixed>
     */
    public function optimizeCatalogStorage(bool $removeLegacyFull = true): array
    {
        $this->pdoCache = null;
        $this->pdoCachePath = null;

        $removedLegacyMb = 0.0;
        if ($removeLegacyFull) {
            foreach (['catalog.sqlite', 'catalog.sqlite-wal', 'catalog.sqlite-shm', 'catalog.sqlite-journal'] as $name) {
                $path = $this->storageDir . '/' . $name;
                if (is_file($path)) {
                    $removedLegacyMb += filesize($path) / 1048576;
                    @unlink($path);
                }
            }
        }

        $compactMbBefore = is_file($this->compactDbPath) ? filesize($this->compactDbPath) / 1048576 : 0;
        $pdo = $this->pdoForPath($this->compactDbPath);
        if ($pdo !== null) {
            $this->optimizeCompactDatabase($pdo);
        }

        $this->pdoCache = null;
        $this->pdoCachePath = null;

        if (is_file($this->storageDir . '/status_cache.json')) {
            @unlink($this->storageDir . '/status_cache.json');
        }

        return [
            'optimized_at' => date('c'),
            'legacy_removed_mb' => round($removedLegacyMb, 1),
            'compact_mb_before' => round($compactMbBefore, 1),
            'compact_mb_after' => is_file($this->compactDbPath) ? round(filesize($this->compactDbPath) / 1048576, 1) : 0,
            'code_brand_rows' => $this->compactCodeBrandCount(),
            'oem_rows' => $this->compactOemCount(),
        ];
    }

    private function optimizeCompactDatabase(\PDO $pdo): void
    {
        try {
            $pdo->exec('PRAGMA optimize');
            $pdo->exec('ANALYZE');
            $pdo->exec('VACUUM');
        } catch (\Throwable) {
            // VACUUM poate eșua dacă DB e locked — non-fatal
        }
    }

    /** @param array<string, string> $row @return list<string> */
    private static function oemTokensFromRow(array $row): array
    {
        $tokens = [];
        foreach (['art_cross', 'parts_info'] as $field) {
            $text = (string) ($row[$field] ?? '');
            foreach (preg_split('/[\r\n,;|]+/', $text) ?: [] as $part) {
                $norm = self::normCode(trim($part));
                if (strlen($norm) >= 4) {
                    $tokens[$norm] = true;
                }
            }
        }

        return array_keys($tokens);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, int|string|bool>
     */
    private function buildFullFromPozeTree(string $sourceRoot, array $options = []): array
    {
        $sourceRoot = rtrim(str_replace('\\', '/', $sourceRoot), '/');
        $pdo = $this->pdoForPath($this->dbPath);
        if ($pdo === null) {
            throw new \RuntimeException('SQLite indisponibil.');
        }

        $resume = !empty($options['resume']);
        $this->ensureSchema($pdo);
        if (!$resume) {
            $pdo->exec('DELETE FROM ttc_catalog');
        }

        $limitFiles = max(0, (int) ($options['limit_files'] ?? 0));
        $log = $options['log'] ?? null;
        $filesProcessed = 0;
        $filesSkipped = 0;
        $rowsInserted = 0;
        $rowsBefore = $resume ? $this->countRows() : 0;

        $xlsxFiles = glob($sourceRoot . '/*/*.xlsx') ?: [];
        $xlsxFiles = array_merge($xlsxFiles, glob($sourceRoot . '/*/*/*.xlsx') ?: []);
        $xlsxFiles = array_values(array_unique(array_filter(
            $xlsxFiles,
            static fn (string $path): bool => !self::shouldSkipXlsxFile($path)
        )));
        sort($xlsxFiles);

        $alreadyIndexed = $resume ? array_fill_keys($this->indexedSourceFiles(), true) : [];

        $insert = $this->prepareCatalogInsert($pdo);

        $pdo->beginTransaction();
        foreach ($xlsxFiles as $file) {
            if ($limitFiles > 0 && $filesProcessed >= $limitFiles) {
                break;
            }

            $sourceKey = self::sourceFileKey($file);
            if (isset($alreadyIndexed[$sourceKey])) {
                ++$filesSkipped;
                continue;
            }

            ++$filesProcessed;
            if (is_callable($log)) {
                $log('Indexez ' . $sourceKey . '…');
            }

            foreach (TtcPozeXlsxReader::readRows($file) as $row) {
                $code1 = trim((string) ($row['art_code_1'] ?? ''));
                $brand = trim((string) ($row['art_brand'] ?? ''));
                $this->executeWithRetry($pdo, $insert, [
                    ':ttc' => trim((string) ($row['ttc_art_id'] ?? '')),
                    ':brand' => $brand,
                    ':code1' => $code1,
                    ':code2' => trim((string) ($row['art_code_2'] ?? '')),
                    ':name' => trim((string) ($row['art_name'] ?? '')),
                    ':ean' => trim((string) ($row['art_ean'] ?? '')),
                    ':parts' => trim((string) ($row['parts_info'] ?? '')),
                    ':cross' => trim((string) ($row['art_cross'] ?? '')),
                    ':cbrand' => trim((string) ($row['car_brand'] ?? '')),
                    ':cmodel' => trim((string) ($row['car_model'] ?? '')),
                    ':ctyp' => trim((string) ($row['car_typ'] ?? '')),
                    ':cbody' => trim((string) ($row['car_body'] ?? '')),
                    ':cyfrom' => trim((string) ($row['car_of_year'] ?? '')),
                    ':cyto' => trim((string) ($row['car_to_year'] ?? '')),
                    ':ckw' => trim((string) ($row['car_kw'] ?? '')),
                    ':cnorm' => self::normCode($code1),
                    ':bnorm' => self::normBrand($brand),
                    ':cbnorm' => self::normText((string) ($row['car_brand'] ?? '')),
                    ':cmnorm' => self::normText((string) ($row['car_model'] ?? '')),
                    ':ctnorm' => self::normText((string) ($row['car_typ'] ?? '')),
                    ':src' => $sourceKey,
                ]);
                ++$rowsInserted;
                if ($rowsInserted % 5000 === 0) {
                    $this->commitWithRetry($pdo);
                    $pdo->beginTransaction();
                    $insert = $this->prepareCatalogInsert($pdo);
                    if (is_callable($log)) {
                        $log('Indexate ' . ($rowsBefore + $rowsInserted) . ' rânduri (+' . $rowsInserted . ' noi)…');
                    }
                }
            }
        }
        $this->commitWithRetry($pdo);

        $meta = [
            'built_at' => date('c'),
            'index_mode' => 'full',
            'source_root' => $sourceRoot,
            'resume' => $resume,
            'files_total' => count($xlsxFiles),
            'files_processed' => $filesProcessed,
            'files_skipped' => $filesSkipped,
            'files_indexed' => $this->countIndexedSourceFiles(),
            'rows_added' => $rowsInserted,
            'rows' => $this->countRows(),
            'distinct_ttc' => $this->countDistinctTtc(),
        ];
        file_put_contents(
            dirname($this->dbPath) . '/catalog_meta.json',
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return $meta;
    }

    private static function sourceFileKey(string $filePath): string
    {
        $filePath = str_replace('\\', '/', $filePath);
        $parts = explode('/', $filePath);
        $fileName = array_pop($parts) ?: '';
        $folder = array_pop($parts) ?: '';

        return $folder . '/' . $fileName;
    }

    private static function shouldSkipXlsxFile(string $filePath): bool
    {
        $base = basename(str_replace('\\', '/', $filePath));

        // Fișiere lock Excel (~$BRAND.xlsx) — nu sunt cataloage valide
        return str_starts_with($base, '~$');
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>|null
     */
    public function lookupForProduct(array $product, LocalTtcImageLibrary $imageLib): ?array
    {
        if (!$this->isReady()) {
            return null;
        }

        if ($this->isCompactActive()) {
            return $this->lookupCompactForProduct($product, $imageLib);
        }

        $ctx = self::extractSearchContext($product);
        $pdo = $this->pdo();
        if ($pdo === null) {
            return null;
        }

        $candidates = [];

        // 1) Cod articol + brand piesă
        if ($ctx['code_norm'] !== '' && $ctx['brand_norm'] !== '') {
            $candidates = array_merge($candidates, $this->fetchByCodeBrand($pdo, $ctx['code_norm'], $ctx['brand_norm']));
        }
        if ($ctx['code_norm'] !== '' && $candidates === []) {
            $candidates = array_merge($candidates, $this->fetchByCode($pdo, $ctx['code_norm']));
        }

        // 2) OEM din pOem / raw_json
        foreach ($ctx['oem_codes'] as $oem) {
            $candidates = array_merge($candidates, $this->fetchByOem($pdo, $oem));
        }

        // 3) Marcă + model + motorizare (fără cod — ultim resort)
        if ($candidates === [] && $ctx['marca_norm'] !== '' && ($ctx['model_norm'] !== '' || $ctx['motor_norm'] !== '')) {
            $candidates = array_merge($candidates, $this->fetchByVehicle($pdo, $ctx));
        }

        if ($candidates === []) {
            self::$lastMatchDebug = ['status' => 'miss', 'tried' => $ctx];

            return null;
        }

        $scored = [];
        foreach ($candidates as $row) {
            $score = self::scoreCandidate($row, $ctx);
            if ($score['total'] < 35) {
                continue;
            }
            $ttcId = trim((string) ($row['ttc_art_id'] ?? ''));
            if ($ttcId === '') {
                continue;
            }
            $key = $ttcId;
            if (!isset($scored[$key]) || $scored[$key]['total'] < $score['total']) {
                $scored[$key] = array_merge($score, ['row' => $row]);
            }
        }

        if ($scored === []) {
            self::$lastMatchDebug = ['status' => 'low_score', 'candidates' => count($candidates), 'context' => $ctx];

            return null;
        }

        uasort($scored, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
        $best = array_values($scored)[0];

        // Dacă produsul are cod articol, acceptă doar potriviri cu acel cod (sau OEM) — evită imagini greșite pe vehicul similar
        if ($ctx['code_norm'] !== '') {
            $reasons = $best['reasons'] ?? [];
            $hasCodeMatch = in_array('cod articol', $reasons, true);
            $hasOemMatch = !empty(array_filter($reasons, static fn (string $r): bool => str_starts_with($r, 'OEM ')));
            if (!$hasCodeMatch && !$hasOemMatch) {
                self::$lastMatchDebug = [
                    'status' => 'code_mismatch',
                    'context' => $ctx,
                    'best_score' => $best['total'],
                    'note' => 'Cod articol prezent dar fără potrivire în catalog — refuz potrivire doar pe vehicul',
                ];

                return null;
            }
        }

        $row = is_array($best['row'] ?? null) ? $best['row'] : [];
        $ttcId = trim((string) ($row['ttc_art_id'] ?? ''));
        $url = $imageLib->resolveUrl($ttcId);
        if ($url === '') {
            self::$lastMatchDebug = ['status' => 'no_local_image', 'ttc_art_id' => $ttcId, 'score' => $best['total']];

            return null;
        }

        $queryParts = array_filter([
            $ctx['code'] !== '' ? $ctx['code'] : null,
            $ctx['brand'] !== '' ? $ctx['brand'] : null,
            $ctx['marca'] !== '' ? $ctx['marca'] : null,
            $ctx['model'] !== '' ? $ctx['model'] : null,
            $ctx['motorizare'] !== '' ? $ctx['motorizare'] : null,
        ]);

        $hit = [
            'url' => $url,
            'source' => LocalTtcImageLibrary::SOURCE_ID,
            'ttc_art_id' => $ttcId,
            'brand' => trim((string) ($row['art_brand'] ?? $ctx['brand'])),
            'query' => implode(' | ', $queryParts),
            'match_score' => (int) $best['total'],
            'match_reason' => implode('; ', $best['reasons'] ?? []),
            'match_details' => [
                'score' => (int) $best['total'],
                'reasons' => $best['reasons'] ?? [],
                'catalog' => [
                    'art_code_1' => (string) ($row['art_code_1'] ?? ''),
                    'art_brand' => (string) ($row['art_brand'] ?? ''),
                    'art_name' => (string) ($row['art_name'] ?? ''),
                    'car_brand' => (string) ($row['car_brand'] ?? ''),
                    'car_model' => (string) ($row['car_model'] ?? ''),
                    'car_typ' => (string) ($row['car_typ'] ?? ''),
                    'parts_info' => mb_substr((string) ($row['parts_info'] ?? ''), 0, 200),
                    'source_file' => (string) ($row['source_file'] ?? ''),
                ],
                'product' => [
                    'pCode' => $ctx['code'],
                    'pBrand' => $ctx['brand'],
                    'pMarca' => $ctx['marca'],
                    'pModel' => $ctx['model'],
                    'pMotorizare' => $ctx['motorizare'],
                    'pCategory' => $ctx['category'],
                    'pSubcategory' => $ctx['subcategory'],
                ],
            ],
        ];

        self::$lastMatchDebug = $hit;

        return $hit;
    }

    /**
     * Lookup pe index compact (cod+brand, apoi OEM).
     *
     * @param array<string, mixed> $product
     * @return array<string, mixed>|null
     */
    private function lookupCompactForProduct(array $product, LocalTtcImageLibrary $imageLib): ?array
    {
        $ctx = self::extractSearchContext($product);
        $pdo = $this->pdoForPath($this->compactDbPath);
        if ($pdo === null) {
            return null;
        }

        $candidates = [];

        if ($ctx['code_norm'] !== '' && $ctx['brand_norm'] !== '') {
            $stmt = $pdo->prepare('SELECT *, \'code_brand\' AS _match_via FROM ttc_code_brand WHERE code_norm = :c AND brand_norm = :b LIMIT 8');
            $stmt->execute([':c' => $ctx['code_norm'], ':b' => $ctx['brand_norm']]);
            $candidates = array_merge($candidates, $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []);
        }
        if ($candidates === [] && $ctx['code_norm'] !== '') {
            $stmt = $pdo->prepare('SELECT *, \'code_only\' AS _match_via FROM ttc_code_brand WHERE code_norm = :c LIMIT 24');
            $stmt->execute([':c' => $ctx['code_norm']]);
            $candidates = array_merge($candidates, $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []);
        }
        if ($candidates === []) {
            foreach ($ctx['oem_codes'] as $oem) {
                $oemNorm = self::normCode($oem);
                if (strlen($oemNorm) < 4) {
                    continue;
                }
                $stmt = $pdo->prepare('
                    SELECT o.ttc_art_id, o.code_norm, o.brand_norm, o.oem_norm,
                           c.art_code_1, c.art_brand, c.art_name, c.art_title, c.art_description,
                           \'oem\' AS _match_via
                    FROM ttc_oem o
                    LEFT JOIN ttc_code_brand c ON c.code_norm = o.code_norm AND c.brand_norm = o.brand_norm
                    WHERE o.oem_norm = :oem
                    LIMIT 12
                ');
                $stmt->execute([':oem' => $oemNorm]);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $oemRow) {
                    if (!is_array($oemRow)) {
                        continue;
                    }
                    $oemRow['_oem_query'] = $oem;
                    $candidates[] = $oemRow;
                }
            }
        }

        if ($candidates === []) {
            self::$lastMatchDebug = [
                'status' => 'miss',
                'mode' => 'compact',
                'context' => $ctx,
                'note' => 'Cod/brand/OEM absent din index Excel Poze',
            ];

            return null;
        }

        $bestRow = null;
        $bestValidation = null;
        foreach ($candidates as $row) {
            if (!is_array($row)) {
                continue;
            }
            $matchVia = (string) ($row['_match_via'] ?? 'code');
            $validation = self::validatePozeGoldenRuleMatch($row, $ctx, $matchVia);
            if (empty($validation['accepted'])) {
                continue;
            }
            $ttcId = trim((string) ($row['ttc_art_id'] ?? ''));
            if ($ttcId === '' || $imageLib->resolveUrl($ttcId) === '') {
                continue;
            }
            if ($bestValidation === null || (int) ($validation['score'] ?? 0) > (int) ($bestValidation['score'] ?? 0)) {
                $bestRow = $row;
                $bestValidation = $validation;
            }
        }

        if (!is_array($bestRow) || !is_array($bestValidation)) {
            self::$lastMatchDebug = [
                'status' => 'golden_rule_reject',
                'mode' => 'compact',
                'context' => $ctx,
                'candidates' => count($candidates),
                'source_root' => self::resolveSourceRoot(),
            ];

            return null;
        }

        $ttcId = trim((string) ($bestRow['ttc_art_id'] ?? ''));
        $url = $imageLib->resolveUrl($ttcId);
        $score = (int) ($bestValidation['score'] ?? 70);
        $matchReason = implode('; ', $bestValidation['reasons'] ?? []);

        $hit = [
            'url' => $url,
            'source' => LocalTtcImageLibrary::SOURCE_ID,
            'ttc_art_id' => $ttcId,
            'brand' => trim((string) ($bestRow['art_brand'] ?? $ctx['brand'])),
            'query' => trim($ctx['code'] . ' | ' . $ctx['brand'], ' |'),
            'match_score' => $score,
            'match_reason' => $matchReason !== '' ? $matchReason : 'Excel Poze (regula de aur)',
            'match_details' => [
                'score' => $score,
                'mode' => 'compact',
                'golden_rule' => true,
                'source_root' => self::resolveSourceRoot(),
                'catalog' => [
                    'art_code_1' => (string) ($bestRow['art_code_1'] ?? ''),
                    'art_brand' => (string) ($bestRow['art_brand'] ?? ''),
                    'art_name' => (string) ($bestRow['art_name'] ?? ''),
                    'art_title' => (string) ($bestRow['art_title'] ?? ''),
                    'art_description' => mb_substr((string) ($bestRow['art_description'] ?? ''), 0, 240),
                ],
                'product' => [
                    'pCode' => $ctx['code'],
                    'pBrand' => $ctx['brand'],
                    'pName' => $ctx['name'],
                ],
            ],
        ];
        self::$lastMatchDebug = $hit;

        return $hit;
    }

    /** @return array<string, mixed>|null */
    public static function lastMatchDebug(): ?array
    {
        return self::$lastMatchDebug;
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    public static function extractSearchContext(array $product): array
    {
        $code = trim((string) ($product['pCode'] ?? ''));
        $brand = trim((string) ($product['pBrand'] ?? ''));
        $marca = trim((string) ($product['pMarca'] ?? ''));
        $model = trim((string) ($product['pModel'] ?? ''));
        $motor = trim((string) ($product['pMotorizare'] ?? ''));
        $category = trim((string) ($product['pCategory'] ?? ''));
        $subcategory = trim((string) ($product['pSubcategory'] ?? ''));
        $name = trim((string) ($product['pName'] ?? ''));
        $description = trim(strip_tags((string) ($product['pNote'] ?? '')));
        if ($description === '' && function_exists('besoiu_image_title_query_for_product')) {
            $pipeline = dirname(__DIR__, 3) . '/system/image_search_pipeline.php';
            if (is_file($pipeline)) {
                require_once $pipeline;
                $description = besoiu_image_title_query_for_product($product);
            }
        }
        $oemCodes = [];

        $oemField = trim((string) ($product['pOem'] ?? ''));
        foreach (preg_split('/[,;|\r\n]+/', $oemField) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '') {
                $oemCodes[] = $part;
            }
        }

        $raw = json_decode((string) ($product['raw_json'] ?? '{}'), true);
        if (is_array($raw)) {
            foreach (['rows', 'source_rows'] as $key) {
                foreach ((array) ($raw[$key] ?? []) as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $row = array_change_key_case($row, CASE_LOWER);
                    if ($marca === '' && trim((string) ($row['car brand'] ?? '')) !== '') {
                        $marca = trim((string) $row['car brand']);
                    }
                    if ($model === '' && trim((string) ($row['car model'] ?? '')) !== '') {
                        $model = trim((string) $row['car model']);
                    }
                    if ($motor === '' && trim((string) ($row['car typ'] ?? '')) !== '') {
                        $motor = trim((string) $row['car typ']);
                    }
                    if ($brand === '' && trim((string) ($row['art brand'] ?? '')) !== '') {
                        $brand = trim((string) $row['art brand']);
                    }
                    $cross = trim((string) ($row['art cross'] ?? ''));
                    if ($cross !== '') {
                        foreach (preg_split('/[\r\n,;]+/', $cross) ?: [] as $c) {
                            $c = trim($c);
                            if ($c !== '') {
                                $oemCodes[] = $c;
                            }
                        }
                    }
                }
            }
        }

        $oemCodes = array_values(array_unique(array_filter($oemCodes)));

        return [
            'code' => $code,
            'brand' => $brand,
            'marca' => $marca,
            'model' => $model,
            'motorizare' => $motor,
            'category' => $category,
            'subcategory' => $subcategory,
            'name' => $name,
            'description' => $description,
            'code_norm' => self::normCode($code),
            'brand_norm' => self::normBrand($brand),
            'marca_norm' => self::normText($marca),
            'model_norm' => self::normText($model),
            'motor_norm' => self::normText($motor),
            'category_norm' => self::normText($category . ' ' . $subcategory),
            'oem_codes' => $oemCodes,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function fetchByCodeBrand(\PDO $pdo, string $codeNorm, string $brandNorm): array
    {
        $stmt = $pdo->prepare('SELECT * FROM ttc_catalog WHERE code_norm = :c AND brand_norm = :b LIMIT 120');
        $stmt->execute([':c' => $codeNorm, ':b' => $brandNorm]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    private function fetchByCode(\PDO $pdo, string $codeNorm): array
    {
        $stmt = $pdo->prepare('SELECT * FROM ttc_catalog WHERE code_norm = :c LIMIT 80');
        $stmt->execute([':c' => $codeNorm]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    private function fetchByOem(\PDO $pdo, string $oem): array
    {
        $needle = self::normCode($oem);
        if ($needle === '' || strlen($needle) < 4) {
            return [];
        }
        $stmt = $pdo->prepare('SELECT * FROM ttc_catalog WHERE art_cross LIKE :q LIMIT 60');
        $stmt->execute([':q' => '%' . $needle . '%']);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $ctx
     * @return list<array<string, mixed>>
     */
    private function fetchByVehicle(\PDO $pdo, array $ctx): array
    {
        $sql = 'SELECT * FROM ttc_catalog WHERE car_brand_norm LIKE :marca';
        $params = [':marca' => '%' . $ctx['marca_norm'] . '%'];
        if ($ctx['model_norm'] !== '') {
            $sql .= ' AND car_model_norm LIKE :model';
            $params[':model'] = '%' . $ctx['model_norm'] . '%';
        }
        if ($ctx['motor_norm'] !== '') {
            $sql .= ' AND car_typ_norm LIKE :motor';
            $params[':motor'] = '%' . $ctx['motor_norm'] . '%';
        }
        if ($ctx['brand_norm'] !== '') {
            $sql .= ' AND brand_norm = :brand';
            $params[':brand'] = $ctx['brand_norm'];
        }
        $sql .= ' LIMIT 80';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $ctx
     * @return array{total: int, reasons: list<string>}
     */
    private static function scoreCandidate(array $row, array $ctx): array
    {
        $score = 0;
        $reasons = [];

        if ($ctx['code_norm'] !== '' && ($row['code_norm'] ?? '') === $ctx['code_norm']) {
            $score += 45;
            $reasons[] = 'cod articol';
        }
        if ($ctx['brand_norm'] !== '' && ($row['brand_norm'] ?? '') === $ctx['brand_norm']) {
            $score += 25;
            $reasons[] = 'brand piesă';
        }
        if ($ctx['marca_norm'] !== '' && self::textContains((string) ($row['car_brand_norm'] ?? ''), $ctx['marca_norm'])) {
            $score += 20;
            $reasons[] = 'marcă auto';
        }
        if ($ctx['model_norm'] !== '' && self::textContains((string) ($row['car_model_norm'] ?? ''), $ctx['model_norm'])) {
            $score += 18;
            $reasons[] = 'model auto';
        }
        if ($ctx['motor_norm'] !== '' && self::textContains((string) ($row['car_typ_norm'] ?? ''), $ctx['motor_norm'])) {
            $score += 18;
            $reasons[] = 'motorizare';
        }
        if ($ctx['category_norm'] !== '') {
            $blob = self::normText((string) (($row['art_name'] ?? '') . ' ' . ($row['parts_info'] ?? '')));
            if ($ctx['category_norm'] !== '' && self::textContains($blob, self::normText($ctx['category'] ?? ''))) {
                $score += 12;
                $reasons[] = 'categorie';
            }
            if ($ctx['subcategory'] !== '' && self::textContains($blob, self::normText((string) $ctx['subcategory']))) {
                $score += 10;
                $reasons[] = 'subcategorie';
            }
        }
        foreach ($ctx['oem_codes'] as $oem) {
            $on = self::normCode($oem);
            if ($on !== '' && str_contains(self::normText((string) ($row['art_cross'] ?? '')), $on)) {
                $score += 22;
                $reasons[] = 'OEM ' . $oem;
                break;
            }
        }

        $td = self::scoreTitleDescriptionMatch($row, $ctx);
        if (!empty($td['reject'])) {
            return ['total' => 0, 'reasons' => ['titlu/descriere respins']];
        }
        $score += (int) ($td['score'] ?? 0);
        foreach ($td['reasons'] ?? [] as $reason) {
            $reasons[] = $reason;
        }

        return ['total' => $score, 'reasons' => array_values(array_unique($reasons))];
    }

    /**
     * Regula de aur Poze — brand + cod (+ titlu/descriere când există în Excel).
     *
     * @param array<string, mixed> $catalogRow
     * @param array<string, mixed> $ctx
     * @return array{accepted: bool, score: int, reasons: list<string>, reject?: string}
     */
    public static function validatePozeGoldenRuleMatch(array $catalogRow, array $ctx, string $matchVia = 'code'): array
    {
        $reasons = [];
        $score = 0;
        $rowCodeNorm = (string) ($catalogRow['code_norm'] ?? self::normCode((string) ($catalogRow['art_code_1'] ?? '')));
        $rowBrandNorm = (string) ($catalogRow['brand_norm'] ?? self::normBrand((string) ($catalogRow['art_brand'] ?? '')));

        if ($matchVia === 'oem') {
            $oemQuery = trim((string) ($catalogRow['_oem_query'] ?? ''));
            if ($oemQuery !== '') {
                $score += 22;
                $reasons[] = 'OEM ' . $oemQuery;
            }
        } elseif ($ctx['code_norm'] !== '') {
            if ($rowCodeNorm === '' || $rowCodeNorm !== $ctx['code_norm']) {
                return ['accepted' => false, 'score' => 0, 'reasons' => [], 'reject' => 'cod articol diferit în Excel Poze'];
            }
            $score += 45;
            $reasons[] = 'cod articol';
        }

        if ($ctx['brand_norm'] !== '') {
            if ($rowBrandNorm === '') {
                $score += 5;
            } elseif (!self::brandsCompatible($ctx['brand_norm'], $rowBrandNorm, (string) ($catalogRow['art_brand'] ?? ''))) {
                return ['accepted' => false, 'score' => $score, 'reasons' => $reasons, 'reject' => 'brand incompatibil cu Excel Poze'];
            } else {
                $score += 25;
                $reasons[] = 'brand piesă';
            }
        }

        $hasCode = in_array('cod articol', $reasons, true);
        $hasBrand = in_array('brand piesă', $reasons, true);

        // Cod + brand din Excel Poze — acceptă direct (titlul din furnizor diferă adesea de catalog)
        if ($hasCode && ($hasBrand || $matchVia === 'code_brand')) {
            return [
                'accepted' => true,
                'score' => max($score, 70),
                'reasons' => array_values(array_unique($reasons)),
            ];
        }

        // Cod unic fără brand în produs — acceptă doar pe cod normalizat
        if ($hasCode && $ctx['brand_norm'] === '' && in_array($matchVia, ['code_only', 'code'], true)) {
            return [
                'accepted' => true,
                'score' => max($score, 45),
                'reasons' => array_values(array_unique($reasons)),
            ];
        }

        $td = self::scoreTitleDescriptionMatch($catalogRow, $ctx);
        if (!empty($td['reject'])) {
            return ['accepted' => false, 'score' => $score, 'reasons' => $reasons, 'reject' => (string) $td['reject']];
        }
        $score += (int) ($td['score'] ?? 0);
        foreach ($td['reasons'] ?? [] as $reason) {
            $reasons[] = $reason;
        }

        $hasOem = !empty(array_filter($reasons, static fn (string $r): bool => str_starts_with($r, 'OEM ')));
        if ($ctx['code_norm'] !== '' && !$hasCode && !$hasOem) {
            return ['accepted' => false, 'score' => $score, 'reasons' => $reasons, 'reject' => 'lipsește potrivire cod/OEM'];
        }

        return [
            'accepted' => $score >= 55,
            'score' => $score,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param array<string, string> $row
     * @return array{art_name: string, art_title: string, art_description: string}
     */
    private static function catalogRowTextFields(array $row): array
    {
        $title = trim((string) ($row['art_title'] ?? ''));
        $name = trim((string) ($row['art_name'] ?? ''));
        if ($title === '') {
            $title = $name;
        }
        if ($name === '') {
            $name = $title;
        }

        return [
            'art_name' => $name,
            'art_title' => $title,
            'art_description' => self::plainCatalogText((string) ($row['art_description'] ?? '')),
        ];
    }

    private static function plainCatalogText(string $html): string
    {
        $text = trim(strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private static function catalogTextBlobIsUsable(string $text): bool
    {
        $norm = self::normText($text);
        if ($norm === '') {
            return false;
        }
        if (str_contains($norm, 'system.xml') || str_contains($norm, 'xmlelement')) {
            return false;
        }
        if (strlen($norm) < 3) {
            return false;
        }

        return true;
    }

    private static function brandsCompatible(string $productBrandNorm, string $catalogBrandNorm, string $catalogBrandRaw = ''): bool
    {
        if ($productBrandNorm === '' || $catalogBrandNorm === '') {
            return true;
        }
        if ($productBrandNorm === $catalogBrandNorm) {
            return true;
        }
        if (str_contains($catalogBrandNorm, $productBrandNorm) || str_contains($productBrandNorm, $catalogBrandNorm)) {
            return true;
        }

        $rawNorm = self::normBrand($catalogBrandRaw);
        if ($rawNorm !== '' && ($rawNorm === $productBrandNorm || str_contains($rawNorm, $productBrandNorm) || str_contains($productBrandNorm, $rawNorm))) {
            return true;
        }

        $token = substr($productBrandNorm, 0, min(4, strlen($productBrandNorm)));
        if (strlen($token) >= 3 && str_contains($catalogBrandNorm, $token)) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $ctx
     * @return array{score: int, reasons: list<string>, reject?: string}
     */
    private static function scoreTitleDescriptionMatch(array $row, array $ctx): array
    {
        $productBlob = self::normText(trim((string) ($ctx['name'] ?? '') . ' ' . (string) ($ctx['description'] ?? '')));
        $catalogBlob = self::normText(trim(
            (string) ($row['art_name'] ?? '') . ' '
            . (string) ($row['art_title'] ?? '') . ' '
            . self::plainCatalogText((string) ($row['art_description'] ?? '')) . ' '
            . (string) ($row['parts_info'] ?? '')
        ));

        if ($productBlob === '') {
            return ['score' => 0, 'reasons' => []];
        }
        if (!self::catalogTextBlobIsUsable($catalogBlob)) {
            return ['score' => 0, 'reasons' => []];
        }

        $keywords = self::extractProductKeywords($productBlob);
        if ($keywords === []) {
            return ['score' => 0, 'reasons' => []];
        }

        $hits = 0;
        foreach ($keywords as $kw) {
            if (self::textContains($catalogBlob, $kw)) {
                ++$hits;
            }
        }

        if ($hits >= 2) {
            return ['score' => 18, 'reasons' => ['titlu/descriere confirmă (' . $hits . ' termeni)']];
        }
        if ($hits === 1) {
            return ['score' => 8, 'reasons' => ['titlu parțial']];
        }

        // Fără titlu valid în Excel index — nu respinge (re-index Poze pentru titlu/descriere)
        if (!self::catalogTextBlobIsUsable($catalogBlob)) {
            return ['score' => 0, 'reasons' => []];
        }

        if (strlen($productBlob) > 16 && strlen($catalogBlob) > 16 && !self::textContains($catalogBlob, substr($keywords[0], 0, min(6, strlen($keywords[0]))))) {
            return ['score' => 0, 'reasons' => [], 'reject' => 'titlu/descriere nu corespund Excel Poze'];
        }

        return ['score' => 0, 'reasons' => []];
    }

    /** @return list<string> */
    private static function extractProductKeywords(string $text): array
    {
        $text = self::normText($text);
        if ($text === '') {
            return [];
        }

        $stop = ['pentru', 'piesa', 'auto', 'set', 'stanga', 'dreapta', 'fata', 'spate', 'original', 'calitate'];
        $words = preg_split('/\s+/u', $text) ?: [];
        $keywords = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) < 4 || in_array($word, $stop, true)) {
                continue;
            }
            if (preg_match('/^\d+$/', $word)) {
                continue;
            }
            $keywords[] = $word;
        }

        return array_values(array_unique(array_slice($keywords, 0, 8)));
    }

    private static function normCode(string $code): string
    {
        self::bootImportHelpers();
        $code = trim($code);
        if ($code === '') {
            return '';
        }
        if (function_exists('import_normalize_product_code')) {
            return import_normalize_product_code($code);
        }

        return strtoupper(preg_replace('/[^A-Z0-9]/', '', $code) ?? '');
    }

    private static function normBrand(string $brand): string
    {
        self::bootImportHelpers();
        $brand = trim($brand);
        if ($brand === '') {
            return '';
        }
        if (function_exists('import_normalize_supplier_brand')) {
            return str_replace(' ', '', import_normalize_supplier_brand($brand));
        }

        return str_replace(' ', '', strtoupper($brand));
    }

    private static function normText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $text = str_replace(['ă', 'â', 'î', 'ș', 'ş', 'ț', 'ţ'], ['a', 'a', 'i', 's', 's', 't', 't'], $text);

        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }

    private static function textContains(string $haystack, string $needle): bool
    {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        return str_contains($haystack, $needle);
    }

    private static function bootImportHelpers(): void
    {
        static $booted = false;
        if ($booted) {
            return;
        }
        $base = dirname(__DIR__, 2) . '/src/Controllers/Produse';
        if (is_file($base . '/import_lib.php')) {
            require_once $base . '/import_lib.php';
        }
        if (is_file($base . '/import_supplier_lib.php')) {
            require_once $base . '/import_supplier_lib.php';
        }
        $booted = true;
    }
}
