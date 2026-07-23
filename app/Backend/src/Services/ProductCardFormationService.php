<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Reguli dinamice formare titlu / carte produs — per canal (site, PieseAuto, OEM).
 */
final class ProductCardFormationService
{
    public const CHANNEL_WEBSITE = 'website';
    public const CHANNEL_PIESEAUTO = 'pieseauto';
    public const CHANNEL_OEM = 'oem';

    private string $configPath;

    public function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? dirname(__DIR__, 2) . '/storage/config/product_card_formation.json';
    }

    /** @return array<string, array{label:string, hint:string, placeholder:string}> */
    public static function segmentCatalog(): array
    {
        return [
            'denumire' => [
                'label' => 'Denumire piesă',
                'hint' => 'Numele articolului (din TecDoc sau CSV)',
                'placeholder' => '{denumire}',
            ],
            'brand_piesa' => [
                'label' => 'Brand piesă',
                'hint' => 'Producător (Bosch, Mann, OEM…)',
                'placeholder' => '{brand_piesa}',
            ],
            'cod' => [
                'label' => 'Cod articol / OEM',
                'hint' => 'Cod principal sau primul OEM',
                'placeholder' => '{cod}',
            ],
            'pozitie' => [
                'label' => 'Poziție montaj',
                'hint' => 'Față, spate, stânga, dreapta — din specs sau titlu',
                'placeholder' => '{pozitie}',
            ],
            'vehicul_manual' => [
                'label' => 'Vehicul manual (marcă + model + motorizare)',
                'hint' => 'Doar dacă ai completat pMarca / pModel / pMotorizare',
                'placeholder' => '{vehicul_manual}',
            ],
            'compat_marci' => [
                'label' => 'Mărci auto compatibile (TecDoc)',
                'hint' => 'AUDI/BMW/VW — din compatibilitate CSV/TecDoc',
                'placeholder' => '{compat_marci}',
            ],
            'compat_serii' => [
                'label' => 'Serii model compatibile',
                'hint' => 'Serii scurte din compatibilitate (ex. Golf, A3)',
                'placeholder' => '{compat_serii}',
            ],
            'categorie' => [
                'label' => 'Categorie magazin',
                'hint' => 'pCategory',
                'placeholder' => '{categorie}',
            ],
            'subcategorie' => [
                'label' => 'Subcategorie',
                'hint' => 'pSubcategory',
                'placeholder' => '{subcategorie}',
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function channelCatalog(): array
    {
        return [
            self::CHANNEL_WEBSITE => [
                'label' => 'Site web (magazin)',
                'desc' => 'Fără mărci auto din compatibilitate TecDoc — doar denumire, brand piesă, cod, poziție, vehicul manual.',
            ],
            self::CHANNEL_PIESEAUTO => [
                'label' => 'PieseAuto.ro / export marketplace',
                'desc' => 'Include mărci și serii compatibile din TecDoc, plus vehicul manual dacă e setat.',
            ],
            self::CHANNEL_OEM => [
                'label' => 'Căutare după cod OEM',
                'desc' => 'Prioritate cod OEM — denumire + cod + brand + poziție.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function defaultConfig(): array
    {
        $websiteSegments = [
            ['id' => 'denumire', 'enabled' => true],
            ['id' => 'brand_piesa', 'enabled' => true],
            ['id' => 'cod', 'enabled' => true],
            ['id' => 'pozitie', 'enabled' => true],
            ['id' => 'vehicul_manual', 'enabled' => true],
            ['id' => 'compat_marci', 'enabled' => false],
            ['id' => 'compat_serii', 'enabled' => false],
            ['id' => 'categorie', 'enabled' => false],
            ['id' => 'subcategorie', 'enabled' => false],
        ];

        $pieseautoSegments = [
            ['id' => 'denumire', 'enabled' => true],
            ['id' => 'brand_piesa', 'enabled' => true],
            ['id' => 'cod', 'enabled' => true],
            ['id' => 'pozitie', 'enabled' => true],
            ['id' => 'compat_marci', 'enabled' => true],
            ['id' => 'compat_serii', 'enabled' => true],
            ['id' => 'vehicul_manual', 'enabled' => true],
            ['id' => 'categorie', 'enabled' => false],
            ['id' => 'subcategorie', 'enabled' => false],
        ];

        $oemSegments = [
            ['id' => 'denumire', 'enabled' => true],
            ['id' => 'cod', 'enabled' => true],
            ['id' => 'brand_piesa', 'enabled' => true],
            ['id' => 'pozitie', 'enabled' => true],
            ['id' => 'vehicul_manual', 'enabled' => true],
            ['id' => 'compat_marci', 'enabled' => false],
            ['id' => 'compat_serii', 'enabled' => false],
            ['id' => 'categorie', 'enabled' => false],
            ['id' => 'subcategorie', 'enabled' => false],
        ];

        $mk = static function (array $segments, string $template) use ($websiteSegments): array {
            return [
                'mode' => 'segments',
                'segments' => $segments,
                'template' => $template,
                'separator' => ' ',
                'vehicle_prefix' => 'pentru',
                'compat_prefix' => 'pentru',
                'max_length' => 150,
                'uppercase' => false,
            ];
        };

        return [
            'version' => 4,
            'updated_at' => null,
            'scopes' => [],
            'channels' => [
                self::CHANNEL_WEBSITE => $mk(
                    $websiteSegments,
                    '{denumire} {brand_piesa} {cod} {pozitie} {vehicul_manual}'
                ),
                self::CHANNEL_PIESEAUTO => $mk(
                    $pieseautoSegments,
                    '{denumire} {brand_piesa} {cod} {pozitie} {compat_marci} {compat_serii} {vehicul_manual}'
                ),
                self::CHANNEL_OEM => $mk(
                    $oemSegments,
                    '{denumire} {cod} {brand_piesa} {pozitie} {vehicul_manual}'
                ),
            ],
            'product_tabs' => ProductDescriptionTabsService::defaultTabsConfig(),
        ];
    }

    /** @return array<string, mixed> */
    public function load(): array
    {
        $defaults = self::defaultConfig();
        if (!is_file($this->configPath)) {
            return $defaults;
        }

        $raw = json_decode((string) file_get_contents($this->configPath), true);
        if (!is_array($raw)) {
            return $defaults;
        }

        $merged = $this->mergeConfig($defaults, $raw);
        if ((int) ($raw['version'] ?? 1) < 4) {
            $merged = $this->migrateConfigToV4($merged);
            $this->save($merged);
        }

        return $merged;
    }

    /** @param array<string, mixed> $config */
    public function save(array $config): array
    {
        $merged = $this->mergeConfig(self::defaultConfig(), $config);
        $merged['updated_at'] = date('c');
        $dir = dirname($this->configPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $this->configPath,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $merged;
    }

    public static function scopeKeyCategory(string $category): string
    {
        return 'category:' . trim($category);
    }

    public static function scopeKeyProject(string $channel): string
    {
        return 'project:' . (new self())->normalizeChannel($channel);
    }

    /**
     * @return array{type:string, key:string, scope_id:string}
     */
    public static function parseScope(string $scope): array
    {
        $scope = trim($scope);
        if ($scope === '' || $scope === 'default') {
            return ['type' => 'default', 'key' => '', 'scope_id' => 'default'];
        }
        if (str_starts_with($scope, 'category:')) {
            return [
                'type' => 'category',
                'key' => trim(substr($scope, 9)),
                'scope_id' => $scope,
            ];
        }
        if (str_starts_with($scope, 'project:')) {
            $channel = (new self())->normalizeChannel(substr($scope, 8));

            return [
                'type' => 'project',
                'key' => $channel,
                'scope_id' => self::scopeKeyProject($channel),
            ];
        }

        return ['type' => 'default', 'key' => '', 'scope_id' => 'default'];
    }

    /**
     * @return list<array{id:string, type:string, label:string, hint:string}>
     */
    public function listSavedScopes(): array
    {
        $config = $this->load();
        $scopes = is_array($config['scopes'] ?? null) ? $config['scopes'] : [];
        $channels = self::channelCatalog();
        $out = [];

        foreach ($scopes as $scopeId => $slice) {
            if (!is_array($slice)) {
                continue;
            }
            $parsed = self::parseScope((string) $scopeId);
            if ($parsed['type'] === 'category') {
                $out[] = [
                    'id' => (string) $scopeId,
                    'type' => 'category',
                    'label' => $parsed['key'],
                    'hint' => 'Tab-uri + titlu pentru produse din această categorie',
                ];
                continue;
            }
            if ($parsed['type'] === 'project') {
                $meta = $channels[$parsed['key']] ?? [];
                $out[] = [
                    'id' => (string) $scopeId,
                    'type' => 'project',
                    'label' => (string) ($meta['label'] ?? $parsed['key']),
                    'hint' => 'Titlu la import/export pentru acest canal',
                ];
            }
        }

        usort($out, static function (array $a, array $b): int {
            $typeCmp = strcmp($a['type'], $b['type']);
            if ($typeCmp !== 0) {
                return $typeCmp;
            }

            return strcasecmp($a['label'], $b['label']);
        });

        return $out;
    }

    /**
     * Config editabil în admin pentru scope-ul ales (global sau per categorie/proiect).
     *
     * @return array{channels:array<string, mixed>, product_tabs:array<string, mixed>, scope:string, scope_label:string}
     */
    public function loadEditorConfig(string $scope = 'default'): array
    {
        $full = $this->load();
        $parsed = self::parseScope($scope);
        $defaults = self::defaultConfig();
        $tabsService = new ProductDescriptionTabsService($this);

        if ($parsed['type'] === 'default') {
            return [
                'scope' => 'default',
                'scope_label' => 'Implicit (toate produsele)',
                'channels' => $full['channels'],
                'product_tabs' => $full['product_tabs'],
            ];
        }

        $slice = is_array($full['scopes'][$parsed['scope_id']] ?? null)
            ? $full['scopes'][$parsed['scope_id']]
            : [];

        if ($parsed['type'] === 'project') {
            $channel = $parsed['key'];
            $globalCh = is_array($full['channels'][$channel] ?? null)
                ? $full['channels'][$channel]
                : $defaults['channels'][$channel];
            $incCh = is_array($slice['channels'][$channel] ?? null) ? $slice['channels'][$channel] : [];
            $meta = self::channelCatalog()[$channel] ?? [];

            return [
                'scope' => $parsed['scope_id'],
                'scope_label' => (string) ($meta['label'] ?? $channel),
                'channels' => [
                    $channel => $this->mergeSingleChannel(
                        is_array($globalCh) ? $globalCh : [],
                        $incCh
                    ),
                ],
                'product_tabs' => $full['product_tabs'],
            ];
        }

        $mergedChannels = [];
        foreach ($defaults['channels'] as $key => $defChannel) {
            $globalCh = is_array($full['channels'][$key] ?? null) ? $full['channels'][$key] : $defChannel;
            $incCh = is_array($slice['channels'][$key] ?? null) ? $slice['channels'][$key] : [];
            $mergedChannels[$key] = $this->mergeSingleChannel(
                is_array($globalCh) ? $globalCh : $defChannel,
                $incCh
            );
        }

        return [
            'scope' => $parsed['scope_id'],
            'scope_label' => $parsed['key'],
            'channels' => $mergedChannels,
            'product_tabs' => $tabsService->mergeTabsConfig(
                is_array($full['product_tabs'] ?? null) ? $full['product_tabs'] : $defaults['product_tabs'],
                is_array($slice['product_tabs'] ?? null) ? $slice['product_tabs'] : []
            ),
        ];
    }

    /**
     * Salvează editorul pentru scope-ul ales în config-ul complet.
     *
     * @param array<string, mixed> $editorConfig
     * @param array<string, mixed>|null $fullConfig
     * @return array<string, mixed>
     */
    public function saveEditorConfig(string $scope, array $editorConfig, ?array $fullConfig = null): array
    {
        $full = $fullConfig ?? $this->load();
        $parsed = self::parseScope($scope);
        $normalizedEditor = $this->normalizeEditorSlice($editorConfig);

        if ($parsed['type'] === 'default') {
            if (isset($normalizedEditor['channels']) && is_array($normalizedEditor['channels'])) {
                $full['channels'] = $normalizedEditor['channels'];
            }
            if (isset($normalizedEditor['product_tabs']) && is_array($normalizedEditor['product_tabs'])) {
                $full['product_tabs'] = $normalizedEditor['product_tabs'];
            }

            return $this->save($full);
        }

        if (!isset($full['scopes']) || !is_array($full['scopes'])) {
            $full['scopes'] = [];
        }

        $slice = [
            'updated_at' => date('c'),
        ];

        if ($parsed['type'] === 'project') {
            $channel = $parsed['key'];
            $slice['channels'] = [
                $channel => is_array($normalizedEditor['channels'][$channel] ?? null)
                    ? $normalizedEditor['channels'][$channel]
                    : [],
            ];
        } else {
            if (isset($normalizedEditor['channels']) && is_array($normalizedEditor['channels'])) {
                $slice['channels'] = $normalizedEditor['channels'];
            }
            if (isset($normalizedEditor['product_tabs']) && is_array($normalizedEditor['product_tabs'])) {
                $slice['product_tabs'] = $normalizedEditor['product_tabs'];
            }
        }

        $full['scopes'][$parsed['scope_id']] = $this->normalizeScopeSlice($slice, $parsed);

        return $this->save($full);
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    public function resolveChannelConfig(string $channel, array $ctx): array
    {
        $config = $this->load();
        $defaults = self::defaultConfig();
        $channel = $this->normalizeChannel($channel);
        $defCh = is_array($defaults['channels'][$channel] ?? null)
            ? $defaults['channels'][$channel]
            : [];
        $globalCh = is_array($config['channels'][$channel] ?? null)
            ? $config['channels'][$channel]
            : $defCh;
        $resolved = $this->mergeSingleChannel($defCh, $globalCh);

        $projectScope = is_array($config['scopes'][self::scopeKeyProject($channel)] ?? null)
            ? $config['scopes'][self::scopeKeyProject($channel)]
            : [];
        $projectCh = is_array($projectScope['channels'][$channel] ?? null)
            ? $projectScope['channels'][$channel]
            : [];
        if ($projectCh !== []) {
            $resolved = $this->mergeSingleChannel($defCh, $projectCh);
        }

        $category = trim((string) ($ctx['pCategory'] ?? ''));
        if ($category !== '') {
            $catScope = is_array($config['scopes'][self::scopeKeyCategory($category)] ?? null)
                ? $config['scopes'][self::scopeKeyCategory($category)]
                : [];
            $catCh = is_array($catScope['channels'][$channel] ?? null)
                ? $catScope['channels'][$channel]
                : [];
            if ($catCh !== []) {
                $resolved = $this->mergeSingleChannel($defCh, $catCh);
            }
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    public function resolveTabsConfigForProduct(array $product): array
    {
        $config = $this->load();
        $defaults = self::defaultConfig();
        $tabsService = new ProductDescriptionTabsService($this);
        $defTabs = $defaults['product_tabs'];
        $globalTabs = is_array($config['product_tabs'] ?? null)
            ? $config['product_tabs']
            : $defTabs;
        $tabs = $tabsService->mergeTabsConfig($defTabs, $globalTabs);

        $category = trim((string) ($product['pCategory'] ?? ''));
        if ($category !== '') {
            $catScope = is_array($config['scopes'][self::scopeKeyCategory($category)] ?? null)
                ? $config['scopes'][self::scopeKeyCategory($category)]
                : [];
            $catTabs = is_array($catScope['product_tabs'] ?? null) ? $catScope['product_tabs'] : [];
            if ($catTabs !== []) {
                $tabs = $tabsService->mergeTabsConfig($defTabs, $catTabs);
            }
        }

        return $tabsService->mergeTabsConfig(ProductDescriptionTabsService::defaultTabsConfig(), $tabs);
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function buildTitle(string $channel, array $ctx): string
    {
        $channel = $this->normalizeChannel($channel);
        $channelCfg = $this->resolveChannelConfig($channel, $ctx);

        $values = $this->resolveSegmentValues($ctx, $channelCfg);
        $mode = (string) ($channelCfg['mode'] ?? 'segments');

        if ($mode === 'template') {
            $template = trim((string) ($channelCfg['template'] ?? ''));
            $title = $this->applyTemplate($template, $values, $channelCfg);
        } else {
            $title = $this->buildFromSegments($channelCfg, $values);
        }

        return $this->finalizeTitle($title, $channelCfg);
    }

    /**
     * @param array<string, mixed>|null $sampleCtx
     * @return array{title:string, segments:array<string, string>, channel:string}
     */
    public function preview(string $channel, ?array $sampleCtx = null): array
    {
        $ctx = $sampleCtx ?? self::sampleContext();
        $channel = $this->normalizeChannel($channel);
        $channelCfg = $this->resolveChannelConfig($channel, $ctx);
        $values = $this->resolveSegmentValues($ctx, $channelCfg);

        return [
            'channel' => $channel,
            'title' => $this->buildTitle($channel, $ctx),
            'segments' => $values,
        ];
    }

    /** @param array<string, mixed> $incoming */
    public function normalizeIncomingConfig(array $incoming): array
    {
        return $this->mergeConfig(self::defaultConfig(), $incoming);
    }

    /**
     * @param array<string, mixed> $ctx
     * @return array<string, string>
     */
    public function segmentValues(string $channel, array $ctx): array
    {
        $channel = $this->normalizeChannel($channel);
        $channelCfg = $this->resolveChannelConfig($channel, $ctx);

        return $this->resolveSegmentValues($ctx, $channelCfg);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed>|null $sampleCtx
     * @return array{title:string, segments:array<string, string>, channel:string}
     */
    public function previewWithConfig(string $channel, array $config, ?array $sampleCtx = null): array
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bpa_pcf_' . bin2hex(random_bytes(8)) . '.json';
        $merged = $this->mergeConfig(self::defaultConfig(), $config);
        file_put_contents($tmp, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        try {
            $draft = new self($tmp);

            return $draft->preview($channel, $sampleCtx);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    /** @return array<string, mixed> */
    public static function sampleContext(): array
    {
        return [
            'piece_name' => 'Plăcuțe frână',
            'brand' => 'BOSCH',
            'code' => '0 986 424 268',
            'pMarca' => 'Audi',
            'pModel' => 'A3 Sportback',
            'pMotorizare' => '1.6 TDI',
            'pCategory' => 'Frâne',
            'pSubcategory' => 'Plăcuțe frână',
            'specs_text' => 'Partea de montare: punte fata stanga',
            'compat_brands' => ['AUDI', 'VW', 'SEAT'],
            'compat_series' => ['A3', 'Golf', 'Leon'],
            'entries' => [],
        ];
    }

    /**
     * Aplică șabloanele formare carte la un produs staging (Import-Pro → coadă).
     * pName devine titlul website; titlurile pe canal rămân în product_formation.
     *
     * @param array<string, mixed> $product
     * @param array<string, mixed> $rawPayload
     * @return array{product: array<string, mixed>, product_formation: array<string, mixed>}
     */
    public function applyToImportProduct(array $product, array $rawPayload = []): array
    {
        $sourceName = trim((string) ($product['pName'] ?? ''));
        $fallbackFormation = [
            'title_website' => $sourceName,
            'title_pieseauto' => $sourceName,
            'title_oem' => $sourceName,
            'source_name' => $sourceName,
            'applied_at' => date('c'),
            'applied' => false,
        ];

        try {
            $this->ensureImportBaseLib();
            $entries = $this->extractEntriesFromRaw($rawPayload);
            $pieceName = $this->resolveBasePieceName($product, $rawPayload, $entries);
            if ($pieceName === '') {
                $pieceName = $sourceName;
            }
            $pieceName = $this->stripVehicleSuffixFromPieceName($pieceName);

            // Curăță motorizarea contaminată (note TecDoc / specificații) înainte de formare titlu.
            if (function_exists('import_sanitize_motorizare_value')) {
                $product['pMotorizare'] = import_sanitize_motorizare_value(
                    (string) ($product['pMotorizare'] ?? '')
                );
            }

            $ctxProduct = $product;
            $ctxProduct['pName'] = $pieceName;
            $ctx = $this->contextFromProduct($ctxProduct, $entries);

            // Marketplace: multi-marcă din compat/descriere (ex. RENAULT/VOLVO).
            // Nu bloca pe o singură marcă din pMarca — altfel MP rămâne doar „pentru RENAULT”.
            if (($ctx['compat_brands'] ?? []) === []) {
                $parsed = $this->compatFromProductText($product, $rawPayload);
                if ($parsed['brands'] !== []) {
                    $ctx['compat_brands'] = $parsed['brands'];
                }
                if ($parsed['series'] !== [] && ($ctx['compat_series'] ?? []) === []) {
                    $ctx['compat_series'] = $parsed['series'];
                }
            }
            if (($ctx['compat_brands'] ?? []) === []) {
                $structured = $this->compatFromStructuredVehicle($product);
                if ($structured['brands'] !== []) {
                    $ctx['compat_brands'] = $structured['brands'];
                }
                if ($structured['series'] !== [] && ($ctx['compat_series'] ?? []) === []) {
                    $ctx['compat_series'] = $structured['series'];
                }
            }

            $titleWebsite = trim($this->buildTitle(self::CHANNEL_WEBSITE, $ctx));
            $titlePieseauto = trim($this->buildTitle(self::CHANNEL_PIESEAUTO, $ctx));
            $titleOem = trim($this->buildTitle(self::CHANNEL_OEM, $ctx));

            if ($titleWebsite === '') {
                $titleWebsite = $sourceName;
            }
            if ($titlePieseauto === '') {
                $titlePieseauto = $titleWebsite;
            }
            if ($titleOem === '') {
                $titleOem = $titleWebsite;
            }

            $product['pName'] = $titleWebsite;
            $product['pNameMarketplace'] = $titlePieseauto;

            return [
                'product' => $product,
                'product_formation' => [
                    'title_website' => $titleWebsite,
                    'title_pieseauto' => $titlePieseauto,
                    'title_oem' => $titleOem,
                    // Denumirea de bază (nu titlul website deja format).
                    'source_name' => $pieceName !== '' ? $pieceName : $sourceName,
                    'applied_at' => date('c'),
                    'applied' => true,
                ],
            ];
        } catch (\Throwable $e) {
            error_log('[ProductCardFormationService] applyToImportProduct: ' . $e->getMessage());

            return [
                'product' => $product,
                'product_formation' => $fallbackFormation,
            ];
        }
    }

    /**
     * Recalculează titlurile pe canalele din /admin/product-formation
     * (website → pName, pieseauto/marketplace → pNameMarketplace).
     *
     * @param array<string, mixed> $row
     * @return array{website:string,marketplace:string,oem:string,applied:bool,product_formation:array<string,mixed>}
     */
    public static function resolveDualTitlesFromRow(array $row): array
    {
        $raw = $row['raw_json'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        } elseif (!is_array($raw)) {
            $raw = [];
        }

        $website = trim((string) ($row['pName'] ?? ''));
        $marketplace = trim((string) ($row['pNameMarketplace'] ?? ''));
        $oem = $website;

        try {
            $svc = new self();
            $applied = $svc->applyToImportProduct($row, $raw);
            $pf = is_array($applied['product_formation'] ?? null) ? $applied['product_formation'] : [];
            $website = trim((string) ($pf['title_website'] ?? ($applied['product']['pName'] ?? $website)));
            $marketplace = trim((string) ($pf['title_pieseauto'] ?? ($applied['product']['pNameMarketplace'] ?? $marketplace)));
            $oem = trim((string) ($pf['title_oem'] ?? $oem));
            if ($marketplace === '') {
                $marketplace = $website;
            }

            return [
                'website' => $website,
                'marketplace' => $marketplace,
                'oem' => $oem !== '' ? $oem : $website,
                'applied' => !empty($pf['applied']),
                'product_formation' => $pf,
            ];
        } catch (\Throwable $e) {
            error_log('[ProductCardFormationService] resolveDualTitlesFromRow: ' . $e->getMessage());
        }

        if ($marketplace === '') {
            $marketplace = $website;
        }

        return [
            'website' => $website,
            'marketplace' => $marketplace,
            'oem' => $oem !== '' ? $oem : $website,
            'applied' => false,
            'product_formation' => [],
        ];
    }

    /**
     * Titlu marketplace (PieseAuto) din raw_json.product_formation sau recalcul.
     *
     * @param array<string, mixed> $row
     */
    public static function resolveMarketplaceTitleFromRow(array $row): string
    {
        $dual = self::resolveDualTitlesFromRow($row);
        $title = trim((string) ($dual['marketplace'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        return trim((string) ($row['pName'] ?? ''));
    }

    /**
     * Titlu website din regulile Formare carte (recalcul).
     *
     * @param array<string, mixed> $row
     */
    public static function resolveWebsiteTitleFromRow(array $row): string
    {
        $dual = self::resolveDualTitlesFromRow($row);
        $title = trim((string) ($dual['website'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        return trim((string) ($row['pName'] ?? ''));
    }

    /**
     * @param array<string, mixed> $rawPayload
     * @return list<array<string, mixed>>
     */
    private function extractEntriesFromRaw(array $rawPayload): array
    {
        foreach (['entries', 'tecdoc_entries', 'source_rows'] as $key) {
            if (isset($rawPayload[$key]) && is_array($rawPayload[$key])) {
                $list = $rawPayload[$key];
                if ($list !== [] && isset($list[0]) && is_array($list[0])) {
                    return array_values(array_filter($list, 'is_array'));
                }
            }
        }

        $audit = $rawPayload['tecdoc_audit'] ?? null;
        if (is_array($audit) && isset($audit['entries']) && is_array($audit['entries'])) {
            return array_values(array_filter($audit['entries'], 'is_array'));
        }

        return [];
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $rawPayload
     * @param list<array<string, mixed>> $entries
     */
    private function resolveBasePieceName(array $product, array $rawPayload, array $entries): string
    {
        $summary = is_array($rawPayload['product_summary'] ?? null) ? $rawPayload['product_summary'] : [];
        $formation = is_array($rawPayload['product_formation'] ?? null) ? $rawPayload['product_formation'] : [];
        $candidates = [];

        if ($entries !== []) {
            $first = $entries[0] ?? [];
            $candidates[] = trim((string) ($first['ART_NAME'] ?? ''));
        }
        $candidates[] = trim((string) ($rawPayload['__tecdoc_art_name'] ?? ''));
        $candidates[] = trim((string) ($summary['tecdoc_art_name'] ?? ''));
        $candidates[] = $this->pieceNameFromDescriptionHtml(trim((string) ($summary['description'] ?? '')));
        $candidates[] = trim((string) ($formation['source_name'] ?? ''));
        $candidates[] = trim((string) ($product['pName'] ?? ''));

        $normalize = static function (string $candidate): string {
            $candidate = trim($candidate);
            if ($candidate === '') {
                return '';
            }
            if (function_exists('import_base_normalize_product_name')) {
                return trim(import_base_normalize_product_name($candidate));
            }

            return $candidate;
        };

        $isNoisy = static function (string $name): bool {
            return $name === ''
                || (bool) preg_match('/^(set|kit|pachet|oferta|ofertă)\b/iu', $name)
                || (bool) preg_match('/\b(set|kit|pachet)\b/iu', $name);
        };

        $fallback = '';
        foreach ($candidates as $candidate) {
            $normalized = $normalize((string) $candidate);
            if ($normalized === '') {
                continue;
            }
            if ($fallback === '') {
                $fallback = $normalized;
            }
            if (!$isNoisy($normalized)) {
                $brand = trim((string) ($product['pBrand'] ?? ''));
                $code = trim((string) ($product['pCode'] ?? ''));
                if ($brand !== '' && function_exists('import_base_strip_redundant_title_suffix')) {
                    $normalized = import_base_strip_redundant_title_suffix($normalized, $brand, $code);
                }

                return trim($normalized);
            }
        }

        $brand = trim((string) ($product['pBrand'] ?? ''));
        $code = trim((string) ($product['pCode'] ?? ''));
        if ($fallback !== '' && function_exists('import_base_strip_redundant_title_suffix')) {
            return trim(import_base_strip_redundant_title_suffix($fallback, $brand, $code));
        }

        return $fallback;
    }

    private function pieceNameFromDescriptionHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        if (!preg_match('/<p>\s*<b>([^<]+)<\/b>/iu', $html, $m)) {
            return '';
        }
        $name = trim(html_entity_decode((string) $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        // Header Base: „Placute frana ATE” / „… pentru MARCA…” — păstrăm doar denumirea.
        $name = trim((string) preg_replace('/\s+pentru\s+.+$/iu', '', $name));

        return $name;
    }

    /**
     * Mărci / serii din pMarca + pModel (sursă de încredere pentru layout template).
     *
     * @param array<string, mixed> $product
     * @return array{brands: list<string>, series: list<string>}
     */
    private function compatFromStructuredVehicle(array $product): array
    {
        $brands = [];
        $series = [];
        foreach (preg_split('/\s*,\s*/u', (string) ($product['pMarca'] ?? '')) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $normalized = $this->normalizeAllowedCarBrand($part);
            if ($normalized !== '') {
                $brands[$normalized] = true;
            }
        }
        foreach (preg_split('/\s*,\s*/u', (string) ($product['pModel'] ?? '')) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $first = trim((string) (preg_split('/\s+/u', $part)[0] ?? ''));
            if ($first !== '' && mb_strlen($first, 'UTF-8') <= 24) {
                $series[$first] = true;
            }
        }

        return [
            'brands' => array_slice(array_keys($brands), 0, 12),
            'series' => array_slice(array_keys($series), 0, 12),
        ];
    }

    private function normalizeAllowedCarBrand(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }
        if (function_exists('import_base_normalize_car_brand')) {
            $token = import_base_normalize_car_brand($token);
        }
        $key = function_exists('mb_strtoupper')
            ? mb_strtoupper($token, 'UTF-8')
            : strtoupper($token);
        if ($key === '') {
            return '';
        }
        if (function_exists('import_base_allowed_car_brands')) {
            $allowed = import_base_allowed_car_brands();
            if ($allowed !== [] && !isset($allowed[$key])) {
                // Alias uzual
                if ($key === 'VOLKSWAGEN') {
                    return isset($allowed['VW']) ? 'VW' : '';
                }
                if ($key === 'MERCEDES') {
                    return isset($allowed['MERCEDES-BENZ']) ? 'MERCEDES-BENZ' : '';
                }

                return '';
            }
        }

        return $key;
    }

    private function stripVehicleSuffixFromPieceName(string $pieceName): string
    {
        $pieceName = trim($pieceName);
        if ($pieceName === '') {
            return '';
        }
        // Elimină „pentru MARCA…” lipit greșit în denumirea de bază.
        $pieceName = trim((string) preg_replace('/\s+pentru\s+.+$/iu', '', $pieceName));
        $pieceName = trim((string) preg_replace('/\s*\|\s*Specificat.+$/iu', '', $pieceName));

        return $this->cleanPieceNameForTitle($pieceName);
    }

    /**
     * Curăță denumiri generice tip „set plăcuțe frână, frână disc”.
     */
    private function cleanPieceNameForTitle(string $pieceName): string
    {
        $pieceName = trim(preg_replace('/\s+/u', ' ', $pieceName) ?? $pieceName);
        if ($pieceName === '') {
            return '';
        }

        // Prefixe / sufixe zgomot
        $pieceName = trim((string) preg_replace('/^(set|kit|pachet|oferta|ofertă)\s+/iu', '', $pieceName));
        $pieceName = trim((string) preg_replace('/\s+(set|kit|pachet)$/iu', '', $pieceName));
        // Separatoare tip listă → spațiu
        $pieceName = str_replace([',', ';', '/', '|'], ' ', $pieceName);
        $pieceName = trim(preg_replace('/\s+/u', ' ', $pieceName) ?? $pieceName);

        $tokens = preg_split('/\s+/u', $pieceName) ?: [];
        $out = [];
        $seen = [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }
            $key = function_exists('mb_strtolower')
                ? mb_strtolower($token, 'UTF-8')
                : strtolower($token);
            // Normalizează diacritice simple pentru dedupe (frana/frână).
            $keyFold = strtr($key, [
                'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
            ]);
            if (isset($seen[$keyFold])) {
                continue;
            }
            $seen[$keyFold] = true;
            $out[] = $token;
        }

        return trim(implode(' ', $out));
    }

    /**
     * Completează marcă/model/motorizare din primul entry TecDoc valid.
     *
     * @param array<string, mixed> $ctx
     * @return array{marca:string,model:string,motor:string}
     */
    private function resolveVehicleFromContext(array $ctx): array
    {
        $marca = trim((string) ($ctx['pMarca'] ?? ''));
        $model = trim((string) ($ctx['pModel'] ?? ''));
        $motor = trim((string) ($ctx['pMotorizare'] ?? ''));

        // Dump-uri vechi din coadă: „RENAULT, VOLVO” / „MEGANE III,, VOLVO”.
        if (str_contains($marca, ',')) {
            $marca = trim((string) explode(',', $marca)[0]);
        }
        if (str_contains($model, ',')) {
            $model = trim((string) explode(',', $model)[0]);
        }
        if (preg_match('/\b(VOLVO|AUDI|BMW|VW|FORD|OPEL|RENAULT|TOYOTA|SKODA|SEAT|DACIA|PEUGEOT|CITROEN|MERCEDES)\b/iu', $model, $m)
            && stripos($model, (string) $m[1]) !== 0
        ) {
            $model = trim((string) preg_replace('/,+\s*' . preg_quote((string) $m[1], '/') . '.*$/iu', '', $model));
        }

        $entries = is_array($ctx['entries'] ?? null) ? $ctx['entries'] : [];
        if ($entries !== [] && function_exists('import_base_filter_entries')) {
            $allowed = import_base_filter_entries($entries);
            $first = $allowed[0] ?? null;
            if (is_array($first)) {
                if ($marca === '') {
                    $marca = trim((string) ($first['CAR_BRAND'] ?? ''));
                }
                if ($model === '') {
                    $model = trim((string) ($first['CAR_MODEL'] ?? ''));
                }
                if ($motor === '') {
                    $typ = trim((string) ($first['CAR_TYP'] ?? ''));
                    $typ = trim((string) preg_replace('/\([^)]*\)/u', '', $typ));
                    $typ = trim((string) preg_replace('/\s+/u', ' ', $typ));
                    $motor = $typ;
                }
            }
        }

        // Fallback: prima marcă/serie din compat text
        if ($marca === '' && is_array($ctx['compat_brands'] ?? null) && ($ctx['compat_brands'][0] ?? '') !== '') {
            $marca = trim((string) $ctx['compat_brands'][0]);
        }
        if ($model === '' && is_array($ctx['compat_series'] ?? null) && ($ctx['compat_series'][0] ?? '') !== '') {
            $model = trim((string) $ctx['compat_series'][0]);
        }

        return [
            'marca' => $marca,
            'model' => $model,
            'motor' => $motor,
        ];
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $rawPayload
     * @return array{brands: list<string>, series: list<string>}
     */
    private function compatFromProductText(array $product, array $rawPayload): array
    {
        $summary = is_array($rawPayload['product_summary'] ?? null) ? $rawPayload['product_summary'] : [];
        $chunks = [
            trim((string) ($product['pCompatibilitati'] ?? '')),
            trim((string) ($summary['compat_text'] ?? '')),
        ];
        $desc = trim((string) ($summary['description'] ?? ''));
        if ($desc !== '' && function_exists('import_compat_text_from_description_html')) {
            $chunks[] = import_compat_text_from_description_html($desc);
        } elseif ($desc !== '') {
            // Fără helper: taie secțiunea Specificații ca să nu apară FRANA/COD ca „mărci”.
            if (preg_match('/Compatibil\s+cu(?:\s+urmatoarele\s+modele\s+auto)?:\s*(.+)$/is', strip_tags($desc), $m)) {
                $chunks[] = trim((string) $m[1]);
            }
        }

        $text = trim(implode("\n", array_filter($chunks, static fn (string $s): bool => $s !== '')));
        // Codurile OE (BENDIX, BREMBO…) nu sunt mărci auto — taie secțiunea.
        $text = trim((string) (preg_split('/Coduri\s+OE\b/iu', $text)[0] ?? $text));
        if ($text === '') {
            return $this->compatFromStructuredVehicle($product);
        }

        $brands = [];
        $series = [];
        $upper = function_exists('mb_strtoupper') ? mb_strtoupper($text, 'UTF-8') : strtoupper($text);

        if (preg_match_all('/\b([A-Z][A-Z0-9ÄÖÜË\- ]{1,20})\b/u', $upper, $m)) {
            foreach ($m[1] as $token) {
                $normalized = $this->normalizeAllowedCarBrand(trim((string) $token));
                if ($normalized !== '') {
                    $brands[$normalized] = true;
                }
            }
        }

        if (preg_match_all('/\b([A-Z][A-Za-z0-9\-]{1,16})\b/u', $text, $sm)) {
            foreach ($sm[1] as $token) {
                $token = trim((string) $token);
                if ($token === '' || mb_strlen($token, 'UTF-8') < 2) {
                    continue;
                }
                if ($this->normalizeAllowedCarBrand($token) !== '') {
                    continue;
                }
                if (preg_match('/^(OEM|OE|SKU|SET|KIT|ABS|TDI|TSI|CDI|HDI|KW|CP|MM|CM)$/iu', $token)) {
                    continue;
                }
                // Serii model tipice (C-HR, Golf, A3…) — nu token-uri din specificații.
                if (preg_match('/^[A-Z]{1,3}-?[A-Z0-9]{1,8}$/u', $token)
                    || preg_match('/^(Golf|Passat|Leon|Octavia|Focus|Astra|Corsa|Clio|Megane|Punto|Fabia|Polo|Ibiza|Corolla|Prius)$/iu', $token)
                ) {
                    $series[$token] = true;
                }
            }
        }

        $structured = $this->compatFromStructuredVehicle($product);
        foreach ($structured['brands'] as $b) {
            $brands[$b] = true;
        }
        foreach ($structured['series'] as $s) {
            $series[$s] = true;
        }

        return [
            'brands' => array_slice(array_keys($brands), 0, 12),
            'series' => array_slice(array_keys($series), 0, 12),
        ];
    }

    /**
     * @param array<string, mixed> $product
     * @param list<array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    public function contextFromProduct(array $product, array $entries = []): array
    {
        $this->ensureImportBaseLib();

        $pieceName = trim((string) ($product['pName'] ?? ''));
        $brand = trim((string) ($product['pBrand'] ?? ''));
        $code = trim((string) ($product['pCode'] ?? ''));

        if ($entries !== [] && function_exists('import_base_normalize_product_name')) {
            $first = $entries[0] ?? [];
            if ($pieceName === '' && !empty($first['ART_NAME'])) {
                $pieceName = import_base_normalize_product_name((string) $first['ART_NAME']);
            }
            if ($brand === '' && !empty($first['ART_BRAND'])) {
                $brand = import_base_normalize_special_chars((string) $first['ART_BRAND']);
            }
            if ($code === '' && !empty($first['ART_CODE_1'])) {
                $code = (string) $first['ART_CODE_1'];
            }
        }

        $compatBrands = [];
        $compatSeries = [];
        if ($entries !== [] && function_exists('import_base_filter_entries')) {
            $allowed = import_base_filter_entries($entries);
            foreach ($allowed as $entry) {
                $cb = trim((string) ($entry['CAR_BRAND'] ?? ''));
                if ($cb !== '') {
                    $compatBrands[$cb] = true;
                }
                $model = trim((string) ($entry['CAR_MODEL'] ?? ''));
                $series = trim((string) (preg_split('/\s+/u', $model)[0] ?? ''));
                if ($series !== '') {
                    $compatSeries[$series] = true;
                }
            }
        }

        $specs = trim((string) ($product['pNote'] ?? ''));
        if ($entries !== [] && $specs === '') {
            $specs = trim((string) ($entries[0]['PARTS_INFO'] ?? ''));
        }

        $artName = '';
        if ($entries !== [] && function_exists('import_base_normalize_product_name')) {
            $artName = import_base_normalize_product_name((string) ($entries[0]['ART_NAME'] ?? ''));
        }
        if ($artName !== '' && ($pieceName === '' || preg_match('/^(set|kit|pachet)\b/iu', $pieceName))) {
            $pieceName = $artName;
        }

        $ctx = [
            'piece_name' => $pieceName,
            'brand' => $brand,
            'code' => $code,
            'pMarca' => trim((string) ($product['pMarca'] ?? '')),
            'pModel' => trim((string) ($product['pModel'] ?? '')),
            'pMotorizare' => trim((string) ($product['pMotorizare'] ?? '')),
            'pCategory' => trim((string) ($product['pCategory'] ?? '')),
            'pSubcategory' => trim((string) ($product['pSubcategory'] ?? '')),
            'specs_text' => $specs,
            'compat_brands' => array_keys($compatBrands),
            'compat_series' => array_keys($compatSeries),
            'entries' => $entries,
            'vehicle_suffix' => trim((string) ($product['vehicle_suffix'] ?? '')),
        ];
        $vehicle = $this->resolveVehicleFromContext($ctx);
        $ctx['pMarca'] = $vehicle['marca'] !== '' ? $vehicle['marca'] : $ctx['pMarca'];
        $ctx['pModel'] = $vehicle['model'] !== '' ? $vehicle['model'] : $ctx['pModel'];
        $ctx['pMotorizare'] = $vehicle['motor'] !== '' ? $vehicle['motor'] : $ctx['pMotorizare'];

        return $ctx;
    }

    /**
     * @param array<string, mixed> $ctx
     * @param array<string, mixed> $channelCfg
     * @return array<string, string>
     */
    private function resolveSegmentValues(array $ctx, array $channelCfg): array
    {
        $this->ensureImportBaseLib();

        $pieceName = trim((string) ($ctx['piece_name'] ?? $ctx['pName'] ?? ''));
        $brand = trim((string) ($ctx['brand'] ?? $ctx['pBrand'] ?? ''));
        $code = trim((string) ($ctx['code'] ?? $ctx['pCode'] ?? ''));
        $entries = is_array($ctx['entries'] ?? null) ? $ctx['entries'] : [];
        $subcategory = trim((string) ($ctx['pSubcategory'] ?? ''));
        $category = trim((string) ($ctx['pCategory'] ?? ''));

        // Denumiri generice cu set/kit: preferă subcategoria / categoria magazin.
        if ($pieceName === ''
            || preg_match('/^(set|kit|pachet)\b/iu', $pieceName)
            || preg_match('/\b(set|kit|pachet)\b/iu', $pieceName)
        ) {
            if ($subcategory !== '') {
                $pieceName = $subcategory;
            } elseif ($category !== '' && ($pieceName === '' || preg_match('/^(set|kit|pachet)\b/iu', $pieceName))) {
                $pieceName = $category;
            }
        }

        $pieceName = $this->stripVehicleSuffixFromPieceName($pieceName);
        if ($pieceName !== '' && function_exists('import_base_strip_redundant_title_suffix')) {
            $pieceName = import_base_strip_redundant_title_suffix($pieceName, $brand, $code);
        }
        $pieceName = $this->cleanPieceNameForTitle($pieceName);

        $brandLabel = function_exists('import_base_title_brand_label')
            ? import_base_title_brand_label($brand, $code)
            : $brand;
        $codeLabel = function_exists('import_base_title_code_label')
            ? import_base_title_code_label($code, $brand)
            : $code;

        $pozitie = $this->extractPosition($ctx, $entries, $pieceName);
        $vehicle = $this->resolveVehicleFromContext($ctx);
        $marca = $vehicle['marca'];
        $model = $vehicle['model'];
        $motor = $vehicle['motor'];
        if (function_exists('import_sanitize_motorizare_value')) {
            $motor = import_sanitize_motorizare_value($motor);
        }
        // În titlu: fără coduri caroserie TecDoc tip „(DZ0/1_)”.
        if ($motor !== '') {
            $motor = trim((string) preg_replace('/\([^)]*\)/u', '', $motor));
            $motor = trim((string) preg_replace('/\s+/u', ' ', $motor));
        }
        // În titlu: cel mult 1–2 tipuri motor scurte (layout), nu tot dump-ul multi-linie.
        if ($motor !== '') {
            $motorLines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/u', $motor) ?: [])));
            $motor = implode(', ', array_slice($motorLines, 0, 2));
            if (function_exists('mb_strlen') && mb_strlen($motor, 'UTF-8') > 48) {
                $motor = function_exists('mb_substr')
                    ? mb_substr($motor, 0, 45, 'UTF-8') . '…'
                    : (substr($motor, 0, 45) . '…');
            }
        }

        $vehiclePrefix = trim((string) ($channelCfg['vehicle_prefix'] ?? 'pentru'));
        $compatPrefix = trim((string) ($channelCfg['compat_prefix'] ?? 'pentru'));

        $vehiculParts = array_values(array_filter([$marca, $model, $motor]));
        $vehiculManual = $vehiculParts !== []
            ? trim($vehiclePrefix . ' ' . implode(' ', $vehiculParts))
            : '';

        $legacySuffix = trim((string) ($ctx['vehicle_suffix'] ?? ''));
        if ($vehiculManual === '' && $legacySuffix !== '') {
            $vehiculManual = $legacySuffix;
        }

        $compatBrands = $ctx['compat_brands'] ?? [];
        if (!is_array($compatBrands) && $entries !== []) {
            $compatBrands = $this->compatBrandsFromEntries($entries);
        }
        $compatSeries = $ctx['compat_series'] ?? [];
        if (!is_array($compatSeries) && $entries !== []) {
            $compatSeries = $this->compatSeriesFromEntries($entries);
        }

        $compatMarci = '';
        if (is_array($compatBrands) && $compatBrands !== []) {
            $compatMarci = trim($compatPrefix . ' ' . implode('/', array_map('strval', $compatBrands)));
        }

        $compatSerii = '';
        if (is_array($compatSeries) && $compatSeries !== []) {
            $compatSerii = implode('/', array_map('strval', $compatSeries));
        }

        return [
            'denumire' => $pieceName,
            'brand_piesa' => $brandLabel,
            'cod' => $codeLabel,
            'pozitie' => $pozitie,
            'vehicul_manual' => $vehiculManual,
            'compat_marci' => $compatMarci,
            'compat_serii' => $compatSerii,
            'categorie' => $category,
            'subcategorie' => $subcategory,
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<string>
     */
    private function compatBrandsFromEntries(array $entries): array
    {
        if (!function_exists('import_base_filter_entries')) {
            return [];
        }
        $brands = [];
        foreach (import_base_filter_entries($entries) as $entry) {
            $b = trim((string) ($entry['CAR_BRAND'] ?? ''));
            if ($b !== '') {
                $brands[$b] = true;
            }
        }

        return array_keys($brands);
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<string>
     */
    private function compatSeriesFromEntries(array $entries): array
    {
        if (!function_exists('import_base_filter_entries')) {
            return [];
        }
        $series = [];
        foreach (import_base_filter_entries($entries) as $entry) {
            $model = trim((string) ($entry['CAR_MODEL'] ?? ''));
            $s = trim((string) (preg_split('/\s+/u', $model)[0] ?? ''));
            if ($s !== '') {
                $series[$s] = true;
            }
        }

        return array_keys($series);
    }

    /**
     * @param array<string, mixed> $ctx
     * @param list<array<string, mixed>> $entries
     */
    private function extractPosition(array $ctx, array $entries, string $pieceName): string
    {
        if (function_exists('import_base_extract_mounting_side') && $entries !== []) {
            $side = trim(import_base_extract_mounting_side($entries));
            if ($side !== '') {
                return $side;
            }
        }

        $haystack = mb_strtolower(
            trim((string) ($ctx['specs_text'] ?? '')) . ' ' . $pieceName,
            'UTF-8'
        );

        $parts = [];
        if (preg_match('/\b(fata|față|front|fata\.|f\.)\b/u', $haystack)) {
            $parts[] = 'Față';
        }
        if (preg_match('/\b(spate|rear|spate\.|sp\.)\b/u', $haystack)) {
            $parts[] = 'Spate';
        }
        if (preg_match('/\b(stanga|stânga|stg|left|lh)\b/u', $haystack)) {
            $parts[] = 'Stânga';
        }
        if (preg_match('/\b(dreapta|dr|right|rh)\b/u', $haystack)) {
            $parts[] = 'Dreapta';
        }

        return implode(' ', array_unique($parts));
    }

    /**
     * @param array<string, string> $values
     * @param array<string, mixed> $channelCfg
     */
    private function buildFromSegments(array $channelCfg, array $values): string
    {
        $enabled = [];
        foreach ($channelCfg['segments'] ?? [] as $seg) {
            if (!is_array($seg)) {
                continue;
            }
            $id = (string) ($seg['id'] ?? '');
            if ($id === '' || empty($seg['enabled'])) {
                continue;
            }
            $val = trim((string) ($values[$id] ?? ''));
            if ($val !== '') {
                $enabled[] = $val;
            }
        }

        $separator = (string) ($channelCfg['separator'] ?? ' ');
        if ($separator === '') {
            $separator = ' ';
        }

        return trim(implode($separator, $enabled));
    }

    /**
     * @param array<string, string> $values
     * @param array<string, mixed> $channelCfg
     */
    private function applyTemplate(string $template, array $values, array $channelCfg): string
    {
        if ($template === '') {
            return $this->buildFromSegments($channelCfg, $values);
        }

        // Mod template: șablonul decide ce placeholdere apar — nu depinde de bifele din „Segmente”.
        if (!preg_match_all('/\{([a-z_]+)\}/', $template, $matches)) {
            return trim($template);
        }

        $replacements = [];
        foreach (array_unique($matches[1] ?? []) as $id) {
            $replacements['{' . $id . '}'] = trim((string) ($values[$id] ?? ''));
        }

        $title = strtr($template, $replacements);
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

        return trim($title);
    }

    /** @param array<string, mixed> $channelCfg */
    private function finalizeTitle(string $title, array $channelCfg): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
        if (!empty($channelCfg['uppercase'])) {
            $title = function_exists('mb_strtoupper')
                ? mb_strtoupper($title, 'UTF-8')
                : strtoupper($title);
        }

        $max = max(40, min(255, (int) ($channelCfg['max_length'] ?? 150)));
        if (function_exists('mb_strlen') && mb_strlen($title, 'UTF-8') > $max) {
            return mb_substr($title, 0, $max - 3, 'UTF-8') . '...';
        }
        if (strlen($title) > $max) {
            return substr($title, 0, $max - 3) . '...';
        }

        return $title;
    }

    private function normalizeChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));
        $map = [
            'site' => self::CHANNEL_WEBSITE,
            'web' => self::CHANNEL_WEBSITE,
            'website' => self::CHANNEL_WEBSITE,
            'export' => self::CHANNEL_PIESEAUTO,
            'marketplace' => self::CHANNEL_PIESEAUTO,
            'pieseauto' => self::CHANNEL_PIESEAUTO,
            'oem' => self::CHANNEL_OEM,
            'oem_search' => self::CHANNEL_OEM,
        ];

        return $map[$channel] ?? self::CHANNEL_WEBSITE;
    }

    /**
     * Păstrează ordinea din config salvat; adaugă segmente noi din catalog (ex. categorie).
     *
     * @param list<array<string, mixed>> $defaults
     * @param list<array<string, mixed>> $incoming
     * @return list<array<string, mixed>>
     */
    private function mergeChannelSegments(array $defaults, array $incoming): array
    {
        $defaultById = [];
        foreach ($defaults as $def) {
            if (!is_array($def)) {
                continue;
            }
            $id = trim((string) ($def['id'] ?? ''));
            if ($id !== '') {
                $defaultById[$id] = $def;
            }
        }

        $out = [];
        $seen = [];

        foreach ($incoming as $seg) {
            if (!is_array($seg)) {
                continue;
            }
            $id = trim((string) ($seg['id'] ?? ''));
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $def = $defaultById[$id] ?? null;
            $out[] = is_array($def) ? array_merge($def, $seg) : $seg;
        }

        foreach ($defaults as $def) {
            if (!is_array($def)) {
                continue;
            }
            $id = trim((string) ($def['id'] ?? ''));
            if ($id !== '' && !isset($seen[$id])) {
                $out[] = $def;
            }
        }

        return $out;
    }

    private function mergeConfig(array $defaults, array $incoming): array
    {
        $out = $defaults;
        $out['version'] = (int) ($incoming['version'] ?? $defaults['version']);
        $out['updated_at'] = $incoming['updated_at'] ?? null;

        if (isset($incoming['scopes']) && is_array($incoming['scopes'])) {
            $out['scopes'] = $incoming['scopes'];
        }

        $incomingChannels = is_array($incoming['channels'] ?? null) ? $incoming['channels'] : [];
        foreach ($defaults['channels'] as $key => $defChannel) {
            $inc = is_array($incomingChannels[$key] ?? null) ? $incomingChannels[$key] : [];
            $merged = array_merge($defChannel, $inc);
            if (isset($inc['segments']) && is_array($inc['segments'])) {
                $merged['segments'] = $this->mergeChannelSegments(
                    is_array($defChannel['segments'] ?? null) ? $defChannel['segments'] : [],
                    $inc['segments']
                );
            }
            $out['channels'][$key] = $merged;
        }

        $tabsService = new ProductDescriptionTabsService();
        $out['product_tabs'] = $tabsService->mergeTabsConfig(
            $defaults['product_tabs'] ?? ProductDescriptionTabsService::defaultTabsConfig(),
            is_array($incoming['product_tabs'] ?? null) ? $incoming['product_tabs'] : []
        );

        return $out;
    }

    /**
     * v4: tab Descriere → text descriptiv + carduri cu icon (în loc de listă dt/dd).
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function migrateConfigToV4(array $config): array
    {
        $config['version'] = 4;
        $tabs = is_array($config['product_tabs']['tabs'] ?? null) ? $config['product_tabs']['tabs'] : [];
        foreach ($tabs as $idx => $tab) {
            if (!is_array($tab) || trim((string) ($tab['id'] ?? '')) !== 'description') {
                continue;
            }
            $format = (string) ($tab['format'] ?? 'fields');
            if (!in_array($format, ['fields', 'list', 'inline', 'stats'], true)) {
                continue;
            }
            $tabs[$idx]['format'] = 'prose';
            $tabs[$idx]['prose_words'] = ProductProseDescriptionService::DEFAULT_TARGET_WORDS;
        }
        $config['product_tabs']['tabs'] = $tabs;

        if (is_array($config['scopes'] ?? null)) {
            foreach ($config['scopes'] as $sIdx => $scope) {
                if (!is_array($scope)) {
                    continue;
                }
                $scopeTabs = is_array($scope['product_tabs']['tabs'] ?? null) ? $scope['product_tabs']['tabs'] : [];
                foreach ($scopeTabs as $tIdx => $tab) {
                    if (!is_array($tab) || trim((string) ($tab['id'] ?? '')) !== 'description') {
                        continue;
                    }
                    $format = (string) ($tab['format'] ?? 'fields');
                    if (!in_array($format, ['fields', 'list', 'inline', 'stats'], true)) {
                        continue;
                    }
                    $scopeTabs[$tIdx]['format'] = 'prose';
                    $scopeTabs[$tIdx]['prose_words'] = ProductProseDescriptionService::DEFAULT_TARGET_WORDS;
                }
                if ($scopeTabs !== []) {
                    $config['scopes'][$sIdx]['product_tabs']['tabs'] = $scopeTabs;
                }
            }
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $editorConfig
     * @return array<string, mixed>
     */
    private function normalizeEditorSlice(array $editorConfig): array
    {
        $defaults = self::defaultConfig();
        $tabsService = new ProductDescriptionTabsService();
        $out = [];

        if (isset($editorConfig['channels']) && is_array($editorConfig['channels'])) {
            $out['channels'] = [];
            foreach ($defaults['channels'] as $key => $defChannel) {
                if (!isset($editorConfig['channels'][$key]) || !is_array($editorConfig['channels'][$key])) {
                    continue;
                }
                $out['channels'][$key] = $this->mergeSingleChannel($defChannel, $editorConfig['channels'][$key]);
            }
        }

        if (isset($editorConfig['product_tabs']) && is_array($editorConfig['product_tabs'])) {
            $out['product_tabs'] = $tabsService->mergeTabsConfig(
                $defaults['product_tabs'],
                $editorConfig['product_tabs']
            );
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $slice
     * @param array{type:string, key:string, scope_id:string} $parsed
     * @return array<string, mixed>
     */
    private function normalizeScopeSlice(array $slice, array $parsed): array
    {
        $normalized = $this->normalizeEditorSlice($slice);
        $normalized['updated_at'] = $slice['updated_at'] ?? date('c');

        return $normalized;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    private function mergeSingleChannel(array $base, array $incoming): array
    {
        $merged = array_merge($base, $incoming);
        if (isset($incoming['segments']) && is_array($incoming['segments'])) {
            $merged['segments'] = $this->mergeChannelSegments(
                is_array($base['segments'] ?? null) ? $base['segments'] : [],
                $incoming['segments']
            );
        }

        return $merged;
    }

    private function ensureImportBaseLib(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $importLib = dirname(__DIR__) . '/Controllers/Produse/import_lib.php';
        if (is_file($importLib)) {
            require_once $importLib;
        }
        $path = dirname(__DIR__) . '/Controllers/Produse/import_base_lib.php';
        if (is_file($path)) {
            require_once $path;
        }
        $loaded = true;
    }
}
