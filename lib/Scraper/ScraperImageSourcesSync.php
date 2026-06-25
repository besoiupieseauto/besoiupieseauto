<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperPaths.php';
require_once __DIR__ . '/ScraperSourceStore.php';
require_once __DIR__ . '/ScraperIntegrationSchema.php';

/**
 * Sincronizare unică: scraper hub → image-search-sources.php → pipeline → cron/import.
 */
final class ScraperImageSourcesSync
{
    /** @return array<string, array<string, mixed>> */
    public static function internalSourceTemplates(): array
    {
        return [
            'caietcomenzi' => [
                'label' => 'Caiet comenzi / TTC_ART_ID',
                'roles' => ['image'],
                'categories' => ['*'],
            ],
            'tecdoc_csv' => [
                'label' => 'TecDoc CSV (import local)',
                'roles' => ['image', 'description'],
                'categories' => ['*'],
            ],
        ];
    }

    /** Reconstruiește config/image-search-sources.php + overlay din registry + planuri active. */
    public static function rebuild(): void
    {
        if (!class_exists('ScraperIntegrationStore', false)) {
            require_once __DIR__ . '/ScraperIntegrationStore.php';
        }

        $integration = ScraperIntegrationStore::load();
        $registry = ScraperSourceStore::registry();
        $plans = is_array($integration['image_plans'] ?? null) ? $integration['image_plans'] : [];

        $sources = [];
        $priority = 10;

        foreach ($plans as $plan) {
            if (!is_array($plan) || empty($plan['enabled'])) {
                continue;
            }
            $id = trim((string) ($plan['source_id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $entry = self::buildSourceEntry($id, $registry, $plan, $priority);
            if ($entry === null) {
                continue;
            }

            $sources[$id] = $entry;
            $priority += 10;
        }

        $audit = self::buildAuditSection($integration);
        self::writeImageSearchPhp($sources, $audit);
        self::writeOverlay($integration);
        self::purgeDisabledSourceConfigs(array_keys($sources));
    }

    /**
     * @param array<string, mixed> $registry
     * @param array<string, mixed> $plan
     * @return array<string, mixed>|null
     */
    private static function buildSourceEntry(string $id, array $registry, array $plan, int $priority): ?array
    {
        $roles = is_array($plan['roles'] ?? null) && $plan['roles'] !== []
            ? array_values($plan['roles'])
            : ['image'];

        $internals = self::internalSourceTemplates();
        if (isset($internals[$id])) {
            $tpl = $internals[$id];

            return [
                'label' => (string) ($plan['label'] ?? $tpl['label']),
                'enabled' => true,
                'priority' => $priority,
                'roles' => $roles ?: $tpl['roles'],
                'categories' => $tpl['categories'],
            ];
        }

        if (!isset($registry[$id]) || !is_array($registry[$id])) {
            return null;
        }

        $meta = $registry[$id];

        try {
            $srcCfg = ScraperSourceStore::load($id);
            $intg = is_array($srcCfg['integration'] ?? null) ? $srcCfg['integration'] : [];
            if (isset($intg['use_in_image_pipeline']) && empty($intg['use_in_image_pipeline'])) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        $entry = [
            'label' => (string) ($meta['label'] ?? $id),
            'enabled' => true,
            'priority' => $priority,
            'roles' => is_array($meta['roles'] ?? null) ? array_values($meta['roles']) : $roles,
            'categories' => ['*'],
        ];

        if (!empty($meta['env_required'])) {
            $entry['env_required'] = array_values((array) $meta['env_required']);
        }
        if (!empty($meta['domain'])) {
            $entry['domain'] = (string) $meta['domain'];
        }
        if (!empty($meta['search_url_template'])) {
            $entry['search_url_template'] = (string) $meta['search_url_template'];
        }
        if ($id === 'epiesa') {
            $entry['base_url'] = 'https://www.epiesa.ro/cautare-piesa/';
            $entry['admin_path'] = '/admin/scraper';
            $entry['categories'] = ['ulei', 'lichide', 'consumabile', '*'];
        }
        if ($id === 'emag') {
            $entry['categories'] = ['ulei', 'lichide', 'consumabile', '*'];
        }
        if (!empty($meta['custom'])) {
            $entry['custom'] = true;
            $entry['scraper_source'] = true;
        }
        if (!empty($meta['stub'])) {
            $entry['note'] = 'Stub — configurează pașii în scraper';
        }

        return $entry;
    }

    /** @param array<string, mixed> $integration @return array<string, mixed> */
    private static function buildAuditSection(array $integration): array
    {
        $ai = is_array($integration['image_ai'] ?? null)
            ? $integration['image_ai']
            : ScraperIntegrationSchema::defaultImageAi();

        return [
            'on_import_cron' => !empty($ai['on_import_cron']),
            'on_import_review' => !empty($ai['on_import_review']),
            'auto_retry_on_mismatch' => !empty($ai['auto_retry_on_mismatch']),
            'min_score_keep' => (int) ($ai['min_score_keep'] ?? 70),
            'verdicts_retry' => is_array($ai['verdicts_retry'] ?? null)
                ? array_values($ai['verdicts_retry'])
                : ['mismatch', 'error', 'no_image'],
            'prompt_extra' => trim((string) ($ai['prompt_extra'] ?? '')),
        ];
    }

    /** @param array<string, array<string, mixed>> $sources */
    private static function writeImageSearchPhp(array $sources, array $audit): void
    {
        $path = ScraperPaths::projectRoot() . '/config/image-search-sources.php';
        $payload = [
            'sources' => $sources,
            'audit' => $audit,
        ];

        $export = var_export($payload, true);
        $content = <<<'PHP'
<?php

declare(strict_types=1);

/**
 * AUTO-GENERAT din /admin/scraper — nu edita manual.
 * La adăugare/ștergere sursă sau salvare pipeline, fișierul se rescrie automat.
 * Sursă de adevăr: storage/scraper/sources_registry.json + integration_config.json
 */

PHP;
        $content .= 'return ' . $export . ";\n";

        file_put_contents($path, $content);
    }

    /** @param array<string, mixed> $integration */
    private static function writeOverlay(array $integration): void
    {
        ScraperPaths::ensureDirs();
        $overlay = [
            'image_plans' => $integration['image_plans'] ?? [],
            'image_ai' => $integration['image_ai'] ?? [],
            'active_source_ids' => [],
            'synced_at' => date('c'),
        ];
        $path = ScraperPaths::storageDir() . '/image_pipeline_overlay.json';
        file_put_contents(
            $path,
            json_encode($overlay, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Șterge fișiere config sursă rămase dacă sursa nu mai e în registry.
     *
     * @param list<string> $activeIds
     */
    private static function purgeDisabledSourceConfigs(array $activeIds): void
    {
        $dir = ScraperSourceStore::sourcesDir();
        if (!is_dir($dir)) {
            return;
        }

        $registry = ScraperSourceStore::registry();
        $allowed = array_fill_keys(array_merge($activeIds, array_keys($registry)), true);

        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $base = basename($file, '.json');
            if (str_ends_with($base, '_last_test') || str_ends_with($base, '_links')) {
                continue;
            }
            $id = str_replace('_', '', $base);
            foreach (array_keys($registry) as $regId) {
                $safe = preg_replace('/[^a-z0-9_-]/i', '_', $regId) ?? $regId;
                if ($safe === $base && !isset($allowed[$regId])) {
                    @unlink($file);
                }
            }
        }
    }

    /** După ștergere sursă: curăță planuri orfane + rescrie tot. */
    public static function onSourceDeleted(string $sourceId): void
    {
        if (!class_exists('ScraperIntegrationStore', false)) {
            require_once __DIR__ . '/ScraperIntegrationStore.php';
        }

        ScraperIntegrationStore::removeSourcePlan($sourceId);
        self::purgeOrphanPlans();
        self::rebuild();
    }

    /** După creare sursă: adaugă în planuri (dacă lipsește) + rescrie tot. */
    public static function onSourceCreated(string $sourceId, string $label, array $meta = []): void
    {
        if (!class_exists('ScraperIntegrationStore', false)) {
            require_once __DIR__ . '/ScraperIntegrationStore.php';
        }

        $roles = is_array($meta['roles'] ?? null) ? $meta['roles'] : [];
        if (in_array('image', $roles, true)) {
            ScraperIntegrationStore::appendSourcePlan($sourceId, $label);
        }

        self::rebuild();
    }

    private static function purgeOrphanPlans(): void
    {
        $cfg = ScraperIntegrationStore::load();
        $registry = ScraperSourceStore::registry();
        $internals = array_keys(self::internalSourceTemplates());

        $plans = array_values(array_filter(
            (array) ($cfg['image_plans'] ?? []),
            static function ($plan) use ($registry, $internals): bool {
                if (!is_array($plan)) {
                    return false;
                }
                $id = (string) ($plan['source_id'] ?? '');

                return $id !== '' && (isset($registry[$id]) || in_array($id, $internals, true));
            }
        ));

        foreach ($plans as $i => &$plan) {
            if (is_array($plan)) {
                $plan['tier'] = $i + 1;
            }
        }
        unset($plan);

        $cfg['image_plans'] = $plans;
        ScraperPaths::ensureDirs();
        file_put_contents(
            ScraperIntegrationStore::filePath(),
            json_encode(
                ScraperIntegrationSchema::normalizeGlobal($cfg),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );
    }

    /** @return list<string> */
    public static function activeSourceIds(): array
    {
        $path = ScraperPaths::projectRoot() . '/config/image-search-sources.php';
        if (!is_file($path)) {
            return [];
        }
        $cfg = require $path;
        $ids = [];
        foreach ((array) ($cfg['sources'] ?? []) as $id => $meta) {
            if (is_array($meta) && !empty($meta['enabled'])) {
                $ids[] = (string) $id;
            }
        }

        return $ids;
    }

    public static function isSourceActive(string $sourceId): bool
    {
        $sourceId = trim($sourceId);
        if ($sourceId === '' || !in_array($sourceId, self::activeSourceIds(), true)) {
            return false;
        }

        if (!self::isSourceRegistered($sourceId)) {
            return false;
        }

        $overlayPath = ScraperPaths::projectRoot() . '/storage/scraper/image_pipeline_overlay.json';
        if (!is_file($overlayPath)) {
            return true;
        }

        $overlay = json_decode((string) file_get_contents($overlayPath), true);
        $plans = is_array($overlay['image_plans'] ?? null) ? $overlay['image_plans'] : [];
        if ($plans === []) {
            return true;
        }

        foreach ($plans as $plan) {
            if (!is_array($plan) || empty($plan['enabled'])) {
                continue;
            }
            if (trim((string) ($plan['source_id'] ?? '')) === $sourceId) {
                return true;
            }
        }

        return false;
    }

    public static function isSourceRegistered(string $sourceId): bool
    {
        $sourceId = trim($sourceId);
        if ($sourceId === '') {
            return false;
        }

        if (isset(self::internalSourceTemplates()[$sourceId])) {
            return true;
        }

        if (!class_exists('ScraperSourceStore', false)) {
            require_once __DIR__ . '/ScraperSourceStore.php';
        }

        return isset(ScraperSourceStore::registry()[$sourceId]);
    }
}
