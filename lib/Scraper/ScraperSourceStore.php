<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperPaths.php';
require_once __DIR__ . '/ScraperStepSchema.php';
require_once __DIR__ . '/ScraperIntegrationSchema.php';
require_once __DIR__ . '/ScraperIntegrationStore.php';
require_once __DIR__ . '/ScraperImageSourcesSync.php';

/**
 * Persistență configurare per sursă web (pași, selectori, test).
 */
final class ScraperSourceStore
{
    private const REGISTRY_FILE = 'sources_registry.json';

    /** @return array<string, mixed> */
    public static function defaultRegistryFromPhp(): array
    {
        $path = ScraperPaths::projectRoot() . '/config/scraper-sources-registry.php';

        return is_file($path) ? (require $path) : [];
    }

    public static function registryFilePath(): string
    {
        ScraperPaths::ensureDirs();

        return ScraperPaths::storageDir() . '/' . self::REGISTRY_FILE;
    }

    /** @return array<string, mixed> */
    public static function registry(): array
    {
        self::ensureRegistry();
        $path = self::registryFilePath();
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $registry */
    public static function saveRegistry(array $registry): void
    {
        ScraperPaths::ensureDirs();
        file_put_contents(
            self::registryFilePath(),
            json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private static function ensureRegistry(): void
    {
        $path = self::registryFilePath();
        if (is_file($path)) {
            return;
        }
        self::saveRegistry(self::defaultRegistryFromPhp());
    }

    public static function normalizeId(string $raw): string
    {
        $id = strtolower(trim($raw));
        $id = preg_replace('/[^a-z0-9_-]+/', '_', $id) ?? '';
        $id = trim($id, '_');

        return substr($id, 0, 48);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function createSource(array $input): array
    {
        $label = trim((string) ($input['label'] ?? ''));
        if ($label === '') {
            throw new InvalidArgumentException('Numele sursei este obligatoriu.');
        }

        $id = self::normalizeId((string) ($input['id'] ?? $label));
        if ($id === '') {
            throw new InvalidArgumentException('ID sursă invalid.');
        }

        $registry = self::registry();
        if (isset($registry[$id])) {
            throw new InvalidArgumentException('Există deja o sursă cu ID: ' . $id);
        }

        $domain = trim((string) ($input['domain'] ?? ''));
        if ($domain === '') {
            $domain = $id . '.ro';
        }

        $urlTemplate = trim((string) ($input['url_template'] ?? ''));
        if ($urlTemplate === '') {
            $urlTemplate = 'https://' . ltrim($domain, '.') . '/search?q={query}';
        }

        $colors = ['#2563eb', '#059669', '#7c3aed', '#dc2626', '#0d9488', '#ea580c', '#4f46e5'];
        $icon = mb_strtoupper(mb_substr(preg_replace('/[^a-zA-Z]/', '', $label) ?: 'NW', 0, 2), 'UTF-8');

        $meta = [
            'label' => $label,
            'domain' => $domain,
            'color' => (string) ($input['color'] ?? $colors[array_rand($colors)]),
            'icon' => $icon,
            'fetch_via' => 'scrape_do',
            'env_required' => ['SCRAPE_DO_TOKEN'],
            'parser_builtin' => null,
            'roles' => is_array($input['roles'] ?? null) ? $input['roles'] : ['image', 'title'],
            'description' => trim((string) ($input['description'] ?? 'Sursă custom — configurează pașii și testează.')),
            'custom' => true,
            'search_url_template' => $urlTemplate,
        ];

        $registry[$id] = $meta;
        self::saveRegistry($registry);

        $config = self::defaultConfig($id);
        if (isset($config['steps'][0]) && is_array($config['steps'][0])) {
            $config['steps'][0]['url_template'] = $urlTemplate;
        }
        self::save($id, $config);

        ScraperImageSourcesSync::onSourceCreated($id, $label, $meta);

        return ['id' => $id, 'meta' => $meta, 'config' => $config];
    }

    public static function deleteSource(string $sourceId): void
    {
        $sourceId = self::normalizeId($sourceId);
        if ($sourceId === '') {
            throw new InvalidArgumentException('ID invalid.');
        }

        $registry = self::registry();
        if (!isset($registry[$sourceId])) {
            throw new InvalidArgumentException('Sursa nu există: ' . $sourceId);
        }

        unset($registry[$sourceId]);
        self::saveRegistry($registry);

        $safe = preg_replace('/[^a-z0-9_-]/i', '_', $sourceId) ?? $sourceId;
        foreach ([
            self::configPath($sourceId),
            self::sourcesDir() . '/' . $safe . '_last_test.json',
            self::sourcesDir() . '/' . $safe . '_links.json',
        ] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        ScraperImageSourcesSync::onSourceDeleted($sourceId);
    }

    /** Restaurează preseturile din config PHP (fără a șterge surse custom). */
    public static function restoreBuiltinPresets(): int
    {
        $builtins = self::defaultRegistryFromPhp();
        $registry = self::registry();
        $added = 0;
        foreach ($builtins as $id => $meta) {
            if (!is_array($meta) || isset($registry[$id])) {
                continue;
            }
            $registry[$id] = $meta;
            $added++;
        }
        self::saveRegistry($registry);

        ScraperImageSourcesSync::rebuild();

        return $added;
    }

    public static function sourcesDir(): string
    {
        ScraperPaths::ensureDirs();
        $dir = ScraperPaths::storageDir() . '/sources';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function configPath(string $sourceId): string
    {
        $safe = preg_replace('/[^a-z0-9_-]/i', '_', $sourceId) ?? 'unknown';

        return self::sourcesDir() . '/' . $safe . '.json';
    }

    /** @return array<string, mixed> */
    public static function defaultConfig(string $sourceId): array
    {
        $registry = self::registry();
        $meta = is_array($registry[$sourceId] ?? null) ? $registry[$sourceId] : [];
        $label = (string) ($meta['label'] ?? $sourceId);

        $steps = self::defaultStepsFor($sourceId, $meta);

        return [
            'id' => $sourceId,
            'label' => $label,
            'enabled' => empty($meta['stub']),
            'fetch' => [
                'via' => (string) ($meta['fetch_via'] ?? 'scrape_do'),
                'timeout_sec' => 90,
                'super' => false,
                'render' => false,
                'save_raw' => true,
            ],
            'test' => [
                'query' => self::defaultTestQuery($sourceId),
                'limit' => 5,
            ],
            'output' => [
                'fields_needed' => ['title', 'image', 'url', 'price'],
                'map_to_product' => [
                    'title' => 'pName',
                    'image' => 'pImages',
                    'url' => 'source_url',
                    'price' => 'pPrice',
                ],
            ],
            'steps' => ScraperStepSchema::migrateSteps(self::defaultStepsFor($sourceId, $meta)),
            'integration' => ScraperIntegrationSchema::defaultSourceIntegration(),
            'ai_agent' => [
                'enabled' => true,
                'auto_on_fail' => true,
                'goals' => 'Extrage fiecare produs din listă: titlu, preț RON, imagine, link pagină produs, cod articol.',
            ],
            'notes' => '',
            'updated_at' => null,
        ];
    }

    /** @param array<string, mixed> $meta @return list<array<string, mixed>> */
    private static function defaultStepsFor(string $sourceId, array $meta): array
    {
        if (!empty($meta['no_html'])) {
            return [
                [
                    'order' => 1,
                    'label' => 'Pas 1 — Interogare TecDoc API',
                    'type' => 'api_tecdoc',
                    'enabled' => true,
                    'description' => 'Caută articol după cod OEM / brand — fără HTML.',
                ],
            ];
        }

        $urlTemplates = [
            'epiesa' => 'https://www.epiesa.ro/cautare-piesa/?find={query}',
            'emag' => 'https://www.emag.ro/search/{query}?ref=effective_search',
            'autodoc' => 'https://www.autodoc24.ro/search?keyword={query}',
            'pieseauto' => 'https://www.pieseauto.ro/cautare?q={query}',
            'autovit' => 'https://www.autovit.ro/piese-auto?q={query}',
        ];
        if (!empty($meta['search_url_template'])) {
            $urlTemplates[$sourceId] = (string) $meta['search_url_template'];
        }

        $listSelectors = [
            'epiesa' => [
                'block' => "div.sub-product-inner",
                'title' => ".product-auto-title a",
                'image' => ".sub-product-img img",
                'url' => ".product-auto-title a@href",
                'price' => ".bricolaje-bottom-text h4",
            ],
            'emag' => [
                'block' => 'div.card-v2',
                'title' => '.card-v2-title',
                'image' => 'img[src*="akamaized.net/products"]',
                'url' => 'a.card-v2-thumb@href',
                'price' => '.product-new-price',
            ],
            'autodoc' => [
                'block' => 'div.listing-item__wrap',
                'title' => 'a.listing-item__name',
                'image' => '.listing-item__image-product img@src',
                'url' => 'a.listing-item__name@href',
                'price' => '.listing-item__price-new',
                'sku' => '.listing-item__article-item',
            ],
            'pieseauto' => [
                'block' => '.product-item, .product-box',
                'title' => '.product-title, h2 a',
                'image' => 'img@src',
                'url' => 'a@href',
                'price' => '.price',
            ],
            'autovit' => [
                'block' => 'article[data-testid="listing-ad"]',
                'title' => 'h2',
                'image' => 'img@src',
                'url' => 'a@href',
                'price' => '[data-testid="price"]',
            ],
        ];

        $sel = $listSelectors[$sourceId] ?? [
            'block' => '',
            'title' => '',
            'image' => '',
            'url' => '',
            'price' => '',
        ];

        return ScraperStepSchema::migrateSteps([
            [
                'order' => 1,
                'label' => 'Pas 1 — Deschide pagina (fetch)',
                'type' => 'fetch',
                'enabled' => true,
                'url_template' => $urlTemplates[$sourceId] ?? 'https://example.com/search?q={query}',
            ],
            [
                'order' => 2,
                'label' => 'Pas 2 — Scanează blocul listă',
                'type' => 'parse_list',
                'enabled' => true,
                'block_selector' => (string) ($sel['block'] ?? ''),
                'field_map' => [
                    'title' => (string) ($sel['title'] ?? ''),
                    'image' => (string) ($sel['image'] ?? ''),
                    'url' => (string) ($sel['url'] ?? ''),
                    'price' => (string) ($sel['price'] ?? ''),
                ],
                'ignore_rules' => ['placeholder', 'star-fill', '#'],
                'limit' => 5,
                'parser_builtin' => $meta['parser_builtin'] ?? null,
            ],
            [
                'order' => 3,
                'label' => 'Pas 3 — Intră în link produs (detaliu)',
                'type' => 'follow_links',
                'enabled' => false,
                'save_links' => true,
                'max_follow' => 1,
                'block_selector' => '',
                'field_map' => ['description' => '', 'sku' => '', 'oem' => ''],
            ],
        ]);
    }

    private static function defaultTestQuery(string $sourceId): string
    {
        return match ($sourceId) {
            'epiesa', 'emag' => 'ulei motor 5W30',
            'autodoc', 'pieseauto' => 'filtre ulei',
            default => 'ulei auto',
        };
    }

    /** @return array<string, mixed> */
    public static function load(string $sourceId): array
    {
        $registry = self::registry();
        if (!isset($registry[$sourceId])) {
            throw new InvalidArgumentException('Sursă necunoscută: ' . $sourceId);
        }

        $path = self::configPath($sourceId);
        if (!is_file($path)) {
            return self::defaultConfig($sourceId);
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            return self::defaultConfig($sourceId);
        }

        $merged = array_replace_recursive(self::defaultConfig($sourceId), $data);
        $merged['steps'] = ScraperStepSchema::migrateSteps(is_array($merged['steps'] ?? null) ? $merged['steps'] : []);
        $repaired = ScraperStepSchema::repairStepsFromDefaults($sourceId, $merged['steps']);
        if ($repaired !== $merged['steps']) {
            $merged['steps'] = $repaired;
            $merged['updated_at'] = date('c');
            file_put_contents(
                $path,
                json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }
        if (is_array($merged['integration'] ?? null)) {
            $merged['integration'] = ScraperIntegrationSchema::normalizeSourceIntegration($merged['integration']);
        }

        return $merged;
    }

    /** @param array<string, mixed> $config @return array<string, mixed> */
    public static function save(string $sourceId, array $config): array
    {
        if (!isset(self::registry()[$sourceId])) {
            throw new InvalidArgumentException('Sursă necunoscută: ' . $sourceId);
        }

        if (!empty($config['__reset'])) {
            $path = self::configPath($sourceId);
            if (is_file($path)) {
                unlink($path);
            }
            $lastPath = self::sourcesDir() . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $sourceId) . '_last_test.json';
            if (is_file($lastPath)) {
                unlink($lastPath);
            }

            return self::defaultConfig($sourceId);
        }

        unset($config['__reset']);
        $merged = array_replace_recursive(self::defaultConfig($sourceId), $config);
        $merged['id'] = $sourceId;
        $merged['updated_at'] = date('c');
        $merged['steps'] = ScraperStepSchema::migrateSteps(is_array($merged['steps'] ?? null) ? $merged['steps'] : []);
        $merged['steps'] = ScraperStepSchema::repairStepsFromDefaults($sourceId, $merged['steps']);
        if (is_array($merged['integration'] ?? null)) {
            $merged['integration'] = ScraperIntegrationSchema::normalizeSourceIntegration($merged['integration']);
        }
        self::reorderSteps($merged['steps']);

        $path = self::configPath($sourceId);
        file_put_contents(
            $path,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        self::saveLastTestMeta($sourceId, null);
        ScraperImageSourcesSync::rebuild();

        return $merged;
    }

    /** @param list<array<string, mixed>> $steps */
    private static function reorderSteps(array &$steps): void
    {
        usort($steps, static fn ($a, $b) => ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0)));
        foreach ($steps as $i => &$step) {
            $step['order'] = $i + 1;
        }
        unset($step);
    }

    /** @return list<array<string, mixed>> */
    public static function listCards(): array
    {
        $registry = self::registry();
        $cards = [];

        foreach ($registry as $id => $meta) {
            if (!is_array($meta)) {
                continue;
            }
            $config = self::load((string) $id);
            $lastTest = self::lastTestMeta((string) $id);
            $envOk = self::envOk($meta);

            $enabledSteps = 0;
            foreach ((array) ($config['steps'] ?? []) as $step) {
                if (is_array($step) && !empty($step['enabled'])) {
                    $enabledSteps++;
                }
            }

            $cards[] = [
                'id' => (string) $id,
                'label' => (string) ($meta['label'] ?? $id),
                'domain' => (string) ($meta['domain'] ?? ''),
                'color' => (string) ($meta['color'] ?? '#64748b'),
                'icon' => (string) ($meta['icon'] ?? strtoupper(substr((string) $id, 0, 2))),
                'description' => (string) ($meta['description'] ?? ''),
                'enabled' => !empty($config['enabled']),
                'stub' => !empty($meta['stub']),
                'custom' => !empty($meta['custom']),
                'builtin' => empty($meta['custom']),
                'no_html' => !empty($meta['no_html']),
                'env_ok' => $envOk,
                'roles' => is_array($meta['roles'] ?? null) ? $meta['roles'] : [],
                'steps_count' => count((array) ($config['steps'] ?? [])),
                'steps_enabled' => $enabledSteps,
                'last_test' => $lastTest,
                'updated_at' => $config['updated_at'] ?? null,
            ];
        }

        return $cards;
    }

    /** @param array<string, mixed> $meta */
    private static function envOk(array $meta): bool
    {
        foreach ((array) ($meta['env_required'] ?? []) as $key) {
            if (trim((string) ($_ENV[$key] ?? getenv((string) $key) ?: '')) === '') {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed>|null */
    public static function lastTestMeta(string $sourceId): ?array
    {
        $path = self::sourcesDir() . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $sourceId) . '_last_test.json';
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed>|null $meta */
    public static function saveLastTestMeta(string $sourceId, ?array $meta): void
    {
        $path = self::sourcesDir() . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $sourceId) . '_last_test.json';
        if ($meta === null) {
            return;
        }
        $meta['tested_at'] = date('c');
        file_put_contents($path, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
