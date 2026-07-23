<?php
declare(strict_types=1);

/**
 * TecDoc unified DB helpers + migrare compatibilități legacy → shop.
 */

function besoiupieseimport_tecdoc_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $path = dirname(__DIR__) . '/config/besoiupieseimport_sync.php';
    $config = is_file($path) ? require $path : [];

    return is_array($config) ? $config : [];
}

function besoiupieseimport_tecdoc_use_unified_db(): bool
{
    $config = besoiupieseimport_tecdoc_config();

    return !empty($config['tecdoc_unified_db']);
}

function besoiupieseimport_tecdoc_db_target(): array
{
    $config = besoiupieseimport_tecdoc_config();
    $shopName = trim((string) (getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? '')));

    return [
        'unified' => besoiupieseimport_tecdoc_use_unified_db(),
        'name' => $shopName !== '' ? $shopName : (string) ($config['tecdoc_db_name'] ?? 'besoiupieseauto.ro'),
        'legacy' => (string) ($config['tecdoc_legacy_db_name'] ?? 'besoiu_tecdoc_base'),
    ];
}

function besoiupieseimport_tecdoc_shop_pdo(): PDO
{
    if (class_exists(\Config\Database::class) && \Config\Database::hasConnection()) {
        return \Config\Database::getDB();
    }

    $host = (string) (getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1'));
    $name = (string) (getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'besoiupieseauto.ro'));
    $user = (string) (getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root'));
    $pass = (string) (getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? ''));

    return new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

function besoiupieseimport_tecdoc_legacy_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = besoiupieseimport_tecdoc_config();
    $host = (string) ($config['tecdoc_legacy_db_host'] ?? '127.0.0.1');
    $name = (string) ($config['tecdoc_legacy_db_name'] ?? 'besoiu_tecdoc_base');
    $user = (string) ($config['tecdoc_legacy_db_user'] ?? 'root');
    $pass = (string) ($config['tecdoc_legacy_db_pass'] ?? '');

    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    return $pdo;
}

function besoiupieseimport_tecdoc_merge_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = besoiupieseimport_tecdoc_config();
    $host = (string) ($config['tecdoc_merge_db_host'] ?? $config['tecdoc_legacy_db_host'] ?? '127.0.0.1');
    $name = (string) ($config['tecdoc_legacy_db_name'] ?? 'besoiu_tecdoc_base');
    $user = (string) ($config['tecdoc_merge_db_user'] ?? $config['tecdoc_legacy_db_user'] ?? 'root');
    $pass = (string) ($config['tecdoc_merge_db_pass'] ?? $config['tecdoc_legacy_db_pass'] ?? '');

    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    return $pdo;
}

function besoiupieseimport_tecdoc_pdo(): PDO
{
    return besoiupieseimport_tecdoc_shop_pdo();
}

function besoiupieseimport_tecdoc_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
    );
    $stmt->execute([$table]);

    return (bool) $stmt->fetchColumn();
}

function besoiupieseimport_tecdoc_table_has_rows(PDO $pdo, string $table): bool
{
    if (!besoiupieseimport_tecdoc_table_exists($pdo, $table)) {
        return false;
    }

    $safe = preg_replace('/[^a-z0-9_]/i', '', $table) ?? '';
    if ($safe === '') {
        return false;
    }

    return (int) $pdo->query("SELECT 1 FROM `{$safe}` LIMIT 1")->fetchColumn() === 1;
}

function besoiupieseimport_tecdoc_approximate_row_count(PDO $pdo, string $table): int
{
    $safe = preg_replace('/[^a-z0-9_]/i', '', $table) ?? '';
    if ($safe === '') {
        return 0;
    }

    $stmt = $pdo->query(
        "SELECT TABLE_ROWS FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($safe)
    );
    $rows = $stmt ? $stmt->fetchColumn() : 0;

    return max(0, (int) $rows);
}

function besoiupieseimport_tecdoc_prepare_with_timeout(PDO $pdo, string $sql, int $timeoutMs = 5000): PDOStatement
{
    $pdo->exec('SET SESSION max_execution_time = ' . max(0, $timeoutMs));

    return $pdo->prepare($sql);
}

function besoiupieseimport_tecdoc_catalog_ready(PDO $pdo): bool
{
    foreach (['tecdoc_brands', 'tecdoc_products', 'tecdoc_product_codes'] as $table) {
        if (!besoiupieseimport_tecdoc_table_exists($pdo, $table)) {
            return false;
        }
    }

    return true;
}

function besoiupieseimport_tecdoc_catalog_has_data(PDO $pdo): bool
{
    return besoiupieseimport_tecdoc_catalog_ready($pdo)
        && besoiupieseimport_tecdoc_table_has_rows($pdo, 'tecdoc_products');
}

function besoiupieseimport_tecdoc_lock_path(): string
{
    return dirname(__DIR__) . '/storage/tecdoc_merge.lock';
}

function besoiupieseimport_tecdoc_merge_in_progress(): bool
{
    $path = besoiupieseimport_tecdoc_lock_path();
    if (!is_file($path)) {
        return false;
    }

    $mtime = (int) filemtime($path);
    if ($mtime > 0 && (time() - $mtime) > 7200) {
        return false;
    }

    return true;
}

function besoiupieseimport_tecdoc_merge_lock_touch(): void
{
    $path = besoiupieseimport_tecdoc_lock_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($path, (string) time());
}

function besoiupieseimport_tecdoc_merge_lock_release(): void
{
    $path = besoiupieseimport_tecdoc_lock_path();
    if (is_file($path)) {
        @unlink($path);
    }
}

function besoiupieseimport_tecdoc_apply_schema(): array
{
    return ['ok' => true, 'message' => 'Schema tecdoc_* există deja în baza magazin.'];
}

function besoiupieseimport_tecdoc_compat_migration_state_path(): string
{
    return dirname(__DIR__) . '/storage/tecdoc_compat_migration.state.json';
}

function besoiupieseimport_tecdoc_try_exec(PDO $pdo, string $sql): bool
{
    try {
        $pdo->exec($sql);

        return true;
    } catch (PDOException) {
        return false;
    }
}

function besoiupieseimport_tecdoc_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
         LIMIT 1'
    );
    $stmt->execute([$table, $index]);

    return (bool) $stmt->fetchColumn();
}

function besoiupieseimport_tecdoc_compat_fk_name(PDO $pdo): ?string
{
    $stmt = $pdo->query(
        "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'tecdoc_product_compatibilities'
           AND REFERENCED_TABLE_NAME = 'tecdoc_products'
         LIMIT 1"
    );
    $name = $stmt ? $stmt->fetchColumn() : false;

    return is_string($name) && $name !== '' ? $name : null;
}

function besoiupieseimport_tecdoc_compat_new_table(): string
{
    return 'tecdoc_product_compatibilities_new';
}

function besoiupieseimport_tecdoc_active_compat_table(PDO $pdo): string
{
    $new = besoiupieseimport_tecdoc_compat_new_table();
    if (besoiupieseimport_tecdoc_table_has_rows($pdo, $new)) {
        return $new;
    }

    return 'tecdoc_product_compatibilities';
}

function besoiupieseimport_tecdoc_create_compat_staging(PDO $merge, string $shopDb, callable $log): string
{
    $staging = besoiupieseimport_tecdoc_compat_new_table();
    $log("Creez tabel staging {$staging} (evit lock pe tabelul vechi)...");

    $merge->exec("DROP TABLE IF EXISTS `{$shopDb}`.`{$staging}`");
    $merge->exec(
        "CREATE TABLE `{$shopDb}`.`{$staging}` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `product_id` bigint unsigned NOT NULL,
            `ttc_typ_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `car_brand` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `car_model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `car_typ` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `car_body` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `car_of_year` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `car_to_year` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `car_kw` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `car_pm` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `car_cc` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_tecdoc_compat_product` (`product_id`),
            KEY `idx_tecdoc_compat_car` (`car_brand`,`car_model`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    return $staging;
}

/**
 * @return array{ok: bool, table: string, message?: string}
 */
function besoiupieseimport_tecdoc_swap_compat_staging(PDO $merge, string $shopDb, callable $log): array
{
    $staging = besoiupieseimport_tecdoc_compat_new_table();
    $main = 'tecdoc_product_compatibilities';
    $backup = 'tecdoc_product_compatibilities_old_lock';

    try {
        besoiupieseimport_tecdoc_try_exec($merge, 'SET FOREIGN_KEY_CHECKS=0');
        $merge->exec("DROP TABLE IF EXISTS `{$shopDb}`.`{$backup}`");
        $merge->exec(
            "RENAME TABLE
                `{$shopDb}`.`{$main}` TO `{$shopDb}`.`{$backup}`,
                `{$shopDb}`.`{$staging}` TO `{$shopDb}`.`{$main}`"
        );
        besoiupieseimport_tecdoc_try_exec($merge, 'SET FOREIGN_KEY_CHECKS=1');
        $log('Swap staging → tecdoc_product_compatibilities reușit.');

        return ['ok' => true, 'table' => $main];
    } catch (PDOException $e) {
        $log('Swap blocat (' . $e->getMessage() . ') — se folosește staging ' . $staging);

        return ['ok' => false, 'table' => $staging, 'message' => $e->getMessage()];
    }
}

function besoiupieseimport_tecdoc_drop_compat_fk(PDO $shop, callable $log): void
{
    $fk = besoiupieseimport_tecdoc_compat_fk_name($shop);
    if ($fk === null) {
        return;
    }

    $safe = str_replace('`', '``', $fk);
    $log("Elimin FK {$fk} temporar pentru import rapid...");
    $shop->exec("ALTER TABLE tecdoc_product_compatibilities DROP FOREIGN KEY `{$safe}`");
}

function besoiupieseimport_tecdoc_restore_compat_fk(PDO $shop, callable $log): void
{
    if (besoiupieseimport_tecdoc_compat_fk_name($shop) !== null) {
        return;
    }

    $log('Re-adaug FK fk_tecdoc_compat_product...');
    $shop->exec(
        'ALTER TABLE tecdoc_product_compatibilities
         ADD CONSTRAINT fk_tecdoc_compat_product
         FOREIGN KEY (product_id) REFERENCES tecdoc_products (id) ON DELETE CASCADE'
    );
}

function besoiupieseimport_tecdoc_drop_compat_indexes(PDO $shop, callable $log): void
{
    $drops = [];
    if (besoiupieseimport_tecdoc_index_exists($shop, 'tecdoc_product_compatibilities', 'idx_tecdoc_compat_product')) {
        $drops[] = 'DROP INDEX idx_tecdoc_compat_product';
    }
    if (besoiupieseimport_tecdoc_index_exists($shop, 'tecdoc_product_compatibilities', 'idx_tecdoc_compat_car')) {
        $drops[] = 'DROP INDEX idx_tecdoc_compat_car';
    }
    if ($drops === []) {
        return;
    }

    $log('Elimin indexuri secundare pentru import rapid...');
    $shop->exec('ALTER TABLE tecdoc_product_compatibilities ' . implode(', ', $drops));
}

function besoiupieseimport_tecdoc_restore_compat_indexes(PDO $shop, callable $log): void
{
    $adds = [];
    if (!besoiupieseimport_tecdoc_index_exists($shop, 'tecdoc_product_compatibilities', 'idx_tecdoc_compat_product')) {
        $adds[] = 'ADD KEY idx_tecdoc_compat_product (product_id)';
    }
    if (!besoiupieseimport_tecdoc_index_exists($shop, 'tecdoc_product_compatibilities', 'idx_tecdoc_compat_car')) {
        $adds[] = 'ADD KEY idx_tecdoc_compat_car (car_brand, car_model)';
    }
    if ($adds === []) {
        return;
    }

    $log('Reconstruiesc indexuri secundare...');
    $shop->exec('ALTER TABLE tecdoc_product_compatibilities ' . implode(', ', $adds));
}

function besoiupieseimport_tecdoc_prepare_compat_bulk_table(PDO $shop, callable $log): void
{
    besoiupieseimport_tecdoc_drop_compat_fk($shop, $log);
    besoiupieseimport_tecdoc_drop_compat_indexes($shop, $log);
}

function besoiupieseimport_tecdoc_restore_compat_bulk_table(PDO $shop, callable $log): void
{
    besoiupieseimport_tecdoc_restore_compat_indexes($shop, $log);
    besoiupieseimport_tecdoc_restore_compat_fk($shop, $log);
}

function besoiupieseimport_tecdoc_prepare_bulk_import(PDO $shop, callable $log): void
{
    besoiupieseimport_tecdoc_try_exec($shop, 'SET SESSION foreign_key_checks=0');
    besoiupieseimport_tecdoc_try_exec($shop, 'SET SESSION unique_checks=0');
    if (!besoiupieseimport_tecdoc_try_exec($shop, 'SET SESSION sql_log_bin=0')) {
        $log('sql_log_bin=0 omis (fără privilegiu SUPER).');
    }
    if (!besoiupieseimport_tecdoc_try_exec($shop, 'SET SESSION innodb_flush_log_at_trx_commit=2')) {
        $log('innodb_flush_log_at_trx_commit=2 omis (fără privilegiu SUPER).');
    }
}

function besoiupieseimport_tecdoc_restore_bulk_import(PDO $shop): void
{
    besoiupieseimport_tecdoc_try_exec($shop, 'SET SESSION foreign_key_checks=1');
    besoiupieseimport_tecdoc_try_exec($shop, 'SET SESSION unique_checks=1');
}

/**
 * @return array<string, mixed>|null
 */
function besoiupieseimport_tecdoc_load_compat_migration_state(): ?array
{
    $path = besoiupieseimport_tecdoc_compat_migration_state_path();
    if (!is_file($path)) {
        return null;
    }

    $data = json_decode((string) file_get_contents($path), true);

    return is_array($data) ? $data : null;
}

/**
 * @param array<string, mixed> $state
 */
function besoiupieseimport_tecdoc_save_compat_migration_state(array $state): void
{
    $path = besoiupieseimport_tecdoc_compat_migration_state_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function besoiupieseimport_tecdoc_clear_compat_migration_state(): void
{
    $path = besoiupieseimport_tecdoc_compat_migration_state_path();
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * @param array<string, mixed> $options
 * @return array<string, mixed>
 */
function besoiupieseimport_tecdoc_migrate_compatibilities(array $options = []): array
{
    $force = !empty($options['force']);
    $batchSize = max(10000, (int) ($options['batch_size'] ?? 500000));
    $deadlineTs = (float) ($options['deadline_ts'] ?? (microtime(true) + 5100));
    $logger = $options['logger'] ?? null;

    $log = static function (string $msg) use ($logger): void {
        if (is_callable($logger)) {
            $logger($msg);
        }
    };

    $target = besoiupieseimport_tecdoc_db_target();
    $shop = besoiupieseimport_tecdoc_shop_pdo();
    $legacy = besoiupieseimport_tecdoc_legacy_pdo();
    $merge = besoiupieseimport_tecdoc_merge_pdo();
    $legacyDb = str_replace('`', '``', (string) $target['legacy']);
    $shopDb = str_replace('`', '``', (string) $target['name']);

    if (!besoiupieseimport_tecdoc_table_exists($shop, 'tecdoc_product_compatibilities')) {
        return ['ok' => false, 'errors' => ['Tabela tecdoc_product_compatibilities lipsește din shop.']];
    }

    $targetTable = 'tecdoc_product_compatibilities';
    $existing = $force
        ? 0
        : (int) $shop->query('SELECT COUNT(*) FROM tecdoc_product_compatibilities')->fetchColumn();
    $legacyTotal = $force
        ? besoiupieseimport_tecdoc_approximate_row_count($legacy, 'product_compatibilities')
        : (int) $legacy->query('SELECT COUNT(*) FROM product_compatibilities')->fetchColumn();

    if ($legacyTotal === 0) {
        return ['ok' => false, 'errors' => ['Legacy product_compatibilities este goală.']];
    }

    $state = besoiupieseimport_tecdoc_load_compat_migration_state();
    $resume = !$force && is_array($state) && !empty($state['last_to_id']);

    if ($existing > 0 && !$force && !$resume) {
        if ($existing >= (int) round($legacyTotal * 0.995)) {
            return [
                'ok' => true,
                'skipped' => true,
                'message' => "Compatibilități deja migrate ({$existing}/{$legacyTotal}).",
                'existing' => $existing,
                'legacy_total' => $legacyTotal,
            ];
        }

        return [
            'ok' => false,
            'errors' => [
                "Migrare parțială ({$existing}/{$legacyTotal}) fără fișier stare. Re-rulează fără --force după ce există state.json, sau --force pentru restart.",
            ],
            'existing' => $existing,
            'legacy_total' => $legacyTotal,
        ];
    }

    if ($force) {
        besoiupieseimport_tecdoc_clear_compat_migration_state();
        $targetTable = besoiupieseimport_tecdoc_create_compat_staging($merge, $shopDb, $log);
        $existing = 0;
        $state = null;
        $resume = false;
        $indexesDropped = false;
    }

    $bounds = $legacy->query('SELECT MIN(id) AS min_id, MAX(id) AS max_id FROM product_compatibilities')->fetch(PDO::FETCH_ASSOC);
    $minId = (int) ($bounds['min_id'] ?? 0);
    $maxId = (int) ($bounds['max_id'] ?? 0);
    if ($maxId <= 0) {
        return ['ok' => false, 'errors' => ['Nu am găsit ID-uri în legacy product_compatibilities.']];
    }

    $startFrom = $minId - 1;
    $inserted = $existing;
    $batchNo = (int) ($state['batch_no'] ?? 0);
    if (!$force) {
        $indexesDropped = !empty($state['indexes_dropped']);
    } elseif (!isset($indexesDropped)) {
        $indexesDropped = false;
    }

    if ($resume) {
        $startFrom = max($startFrom, (int) $state['last_to_id']);
        $inserted = max($inserted, (int) ($state['inserted'] ?? 0));
        $log(sprintf(
            'Reluare migrare de la legacy id > %d (%s rânduri deja în shop).',
            $startFrom,
            number_format($inserted, 0, '.', ' ')
        ));
    }

    $log("Migrare {$legacyTotal} compatibilități → `{$targetTable}` ({$legacyDb} → {$shopDb}). Batch={$batchSize}.");
    $started = microtime(true);

    besoiupieseimport_tecdoc_prepare_bulk_import($merge, $log);

    if ($targetTable === besoiupieseimport_tecdoc_compat_new_table()) {
        $log('Elimin indexuri pe staging pentru import rapid...');
        $merge->exec(
            "ALTER TABLE `{$shopDb}`.`{$targetTable}`
             DROP INDEX idx_tecdoc_compat_product,
             DROP INDEX idx_tecdoc_compat_car"
        );
    } elseif (!$indexesDropped && $targetTable === 'tecdoc_product_compatibilities') {
        besoiupieseimport_tecdoc_prepare_compat_bulk_table($shop, $log);
        $indexesDropped = true;
    }

    $insertSql = "
        INSERT INTO `{$shopDb}`.`{$targetTable}` (
            product_id, ttc_typ_id, car_brand, car_model, car_typ, car_body,
            car_of_year, car_to_year, car_kw, car_pm, car_cc
        )
        SELECT
            c.product_id, c.ttc_typ_id, c.car_brand, c.car_model, c.car_typ, c.car_body,
            c.car_of_year, c.car_to_year, c.car_kw, c.car_pm, c.car_cc
        FROM `{$legacyDb}`.product_compatibilities c
        WHERE c.id > :from_id AND c.id <= :to_id
    ";

    $stmt = $merge->prepare($insertSql);

    for ($from = $startFrom; $from < $maxId; $from += $batchSize) {
        if (microtime(true) >= $deadlineTs) {
            besoiupieseimport_tecdoc_save_compat_migration_state([
                'last_to_id' => $from,
                'inserted' => $inserted,
                'batch_no' => $batchNo,
                'indexes_dropped' => $indexesDropped,
                'updated_at' => date('c'),
            ]);
            besoiupieseimport_tecdoc_restore_bulk_import($merge);
            besoiupieseimport_tecdoc_restore_bulk_import($shop);

            return [
                'ok' => false,
                'partial' => true,
                'inserted' => $inserted,
                'legacy_total' => $legacyTotal,
                'errors' => ['Deadline depășit. Re-rulează scriptul fără --force pentru continuare.'],
            ];
        }

        $to = min($from + $batchSize, $maxId);
        ++$batchNo;
        $stmt->execute(['from_id' => $from, 'to_id' => $to]);
        $batchInserted = (int) $merge->query('SELECT ROW_COUNT()')->fetchColumn();
        if ($batchInserted < 0) {
            $batchInserted = 0;
        }
        $inserted += $batchInserted;

        besoiupieseimport_tecdoc_save_compat_migration_state([
            'last_to_id' => $to,
            'inserted' => $inserted,
            'batch_no' => $batchNo,
            'indexes_dropped' => $indexesDropped,
            'updated_at' => date('c'),
        ]);

        $elapsed = max(0.001, microtime(true) - $started);
        $rate = $inserted / $elapsed;
        $remaining = max(0, $legacyTotal - $inserted);
        $etaSec = $rate > 0 ? (int) round($remaining / $rate) : 0;
        $pct = $legacyTotal > 0 ? round(($inserted / $legacyTotal) * 100, 1) : 100;

        $log(sprintf(
            'Batch %d: id %d-%d → +%s (total %s / %s, %.1f%%, %.0f r/s, ETA %s)',
            $batchNo,
            $from + 1,
            $to,
            number_format($batchInserted, 0, '.', ' '),
            number_format($inserted, 0, '.', ' '),
            number_format($legacyTotal, 0, '.', ' '),
            $pct,
            $rate,
            gmdate('H:i:s', $etaSec)
        ));
    }

    if ($targetTable === besoiupieseimport_tecdoc_compat_new_table()) {
        $log('Reconstruiesc indexuri pe staging...');
        $merge->exec(
            "ALTER TABLE `{$shopDb}`.`{$targetTable}`
             ADD KEY idx_tecdoc_compat_product (product_id),
             ADD KEY idx_tecdoc_compat_car (car_brand, car_model)"
        );
        $swap = besoiupieseimport_tecdoc_swap_compat_staging($merge, $shopDb, $log);
        $targetTable = $swap['table'];
        if ($swap['ok']) {
            besoiupieseimport_tecdoc_restore_compat_fk($shop, $log);
        }
    } else {
        besoiupieseimport_tecdoc_restore_compat_bulk_table($shop, $log);
    }

    besoiupieseimport_tecdoc_restore_bulk_import($merge);
    besoiupieseimport_tecdoc_restore_bulk_import($shop);
    besoiupieseimport_tecdoc_clear_compat_migration_state();

    $final = (int) $shop->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $targetTable) . '`')->fetchColumn();
    $elapsedMin = round((microtime(true) - $started) / 60, 1);

    return [
        'ok' => true,
        'message' => "Migrare compatibilități finalizată în {$elapsedMin} min.",
        'inserted' => $inserted,
        'final_count' => $final,
        'legacy_total' => $legacyTotal,
        'elapsed_min' => $elapsedMin,
        'batches' => $batchNo,
    ];
}

/**
 * @return array<string, mixed>
 */
function besoiupieseimport_tecdoc_merge_legacy_into_shop(bool $dryRun = false): array
{
    $target = besoiupieseimport_tecdoc_db_target();
    $shop = besoiupieseimport_tecdoc_shop_pdo();
    $compatExisting = besoiupieseimport_tecdoc_table_has_rows($shop, 'tecdoc_product_compatibilities')
        ? (int) $shop->query('SELECT COUNT(*) FROM tecdoc_product_compatibilities')->fetchColumn()
        : 0;

    if ($dryRun) {
        $legacy = besoiupieseimport_tecdoc_legacy_pdo();
        $legacyCompat = (int) $legacy->query('SELECT COUNT(*) FROM product_compatibilities')->fetchColumn();

        return [
            'ok' => true,
            'dry_run' => true,
            'message' => 'Simulare: produse/coduri deja în shop; compatibilități de migrat.',
            'legacy_db' => $target['legacy'],
            'shop_db' => $target['name'],
            'compat_to_migrate' => max(0, $legacyCompat - $compatExisting),
        ];
    }

    if ($compatExisting > 0) {
        besoiupieseimport_tecdoc_merge_lock_release();

        return [
            'ok' => true,
            'message' => 'Catalog produse deja în shop; compatibilități deja migrate.',
            'copied' => ['compatibilities' => $compatExisting],
            'legacy_db' => $target['legacy'],
            'shop_db' => $target['name'],
        ];
    }

    $result = besoiupieseimport_tecdoc_migrate_compatibilities([
        'force' => false,
        'batch_size' => 500000,
        'deadline_ts' => microtime(true) + 5100,
    ]);

    besoiupieseimport_tecdoc_merge_lock_release();

    if (empty($result['ok'])) {
        return [
            'ok' => false,
            'errors' => $result['errors'] ?? ['Migrare compatibilități eșuată.'],
            'legacy_db' => $target['legacy'],
            'shop_db' => $target['name'],
        ];
    }

    return [
        'ok' => true,
        'message' => (string) ($result['message'] ?? 'Compatibilități migrate.'),
        'copied' => ['compatibilities' => (int) ($result['final_count'] ?? 0)],
        'legacy_db' => $target['legacy'],
        'shop_db' => $target['name'],
        'merge_detail' => $result,
    ];
}
