<?php

declare(strict_types=1);

/**
 * Schema dinamică pași scraper — tipuri, elemente, migrare v1→v2.
 */
final class ScraperStepSchema
{
    /** @return list<array<string, mixed>> */
    public static function stepTypeCatalog(): array
    {
        return [
            ['type' => 'fetch', 'label' => 'Deschide URL (fetch HTML)', 'hint' => 'Trimite request scrape.do — primești HTML.'],
            ['type' => 'login', 'label' => 'Login (user + parolă)', 'hint' => 'Pagină autentificare — folosește render JS dacă e formular.'],
            ['type' => 'extract_list', 'label' => 'Extrage din listă HTML', 'hint' => 'Adaugă elemente: bloc produs, titlu, imagine…'],
            ['type' => 'follow_links', 'label' => 'Deschide linkuri produse', 'hint' => 'Intră în URL-urile din pasul anterior.'],
            ['type' => 'extract_detail', 'label' => 'Extrage din pagină detaliu', 'hint' => 'Parsează HTML-ul ultimei pagini deschise.'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function elementTypeCatalog(): array
    {
        return [
            ['key' => 'block', 'label' => 'Bloc produs (container CSS/XPath)', 'only' => ['extract_list', 'extract_detail']],
            ['key' => 'title', 'label' => 'Titlu'],
            ['key' => 'image', 'label' => 'Imagine'],
            ['key' => 'url', 'label' => 'URL produs'],
            ['key' => 'price', 'label' => 'Preț'],
            ['key' => 'description', 'label' => 'Descriere'],
            ['key' => 'sku', 'label' => 'SKU / cod articol'],
            ['key' => 'oem', 'label' => 'Cod OEM'],
            ['key' => 'custom', 'label' => 'Câmp custom (denumești tu)'],
        ];
    }

    /** @param list<array<string, mixed>> $steps @return list<array<string, mixed>> */
    public static function migrateSteps(array $steps): array
    {
        $out = [];
        foreach ($steps as $i => $step) {
            if (!is_array($step)) {
                continue;
            }
            if (isset($step['params']) && is_array($step['params'])) {
                $out[] = self::ensureStepIds($step, $i + 1);
                continue;
            }
            $out[] = self::migrateLegacyStep($step, $i + 1);
        }

        return $out;
    }

    /** @param array<string, mixed> $step */
    private static function migrateLegacyStep(array $step, int $order): array
    {
        $type = (string) ($step['type'] ?? 'fetch');
        $id = (string) ($step['id'] ?? 'step_' . $order);

        if ($type === 'fetch') {
            return self::ensureStepIds([
                'id' => $id,
                'order' => (int) ($step['order'] ?? $order),
                'label' => (string) ($step['label'] ?? 'Pas ' . $order . ' — Fetch'),
                'type' => 'fetch',
                'enabled' => !isset($step['enabled']) || !empty($step['enabled']),
                'params' => [
                    'url_template' => (string) ($step['url_template'] ?? ''),
                ],
            ], $order);
        }

        if ($type === 'parse_list') {
            $fm = is_array($step['field_map'] ?? null) ? $step['field_map'] : [];
            $elements = [];
            if (trim((string) ($step['block_selector'] ?? '')) !== '') {
                $elements[] = ['id' => 'el_block', 'key' => 'block', 'label' => 'Bloc produs', 'selector' => (string) $step['block_selector']];
            }
            foreach ($fm as $key => $sel) {
                if (trim((string) $sel) === '') {
                    continue;
                }
                $elements[] = [
                    'id' => 'el_' . $key,
                    'key' => (string) $key,
                    'label' => ucfirst((string) $key),
                    'selector' => (string) $sel,
                ];
            }

            return self::ensureStepIds([
                'id' => $id,
                'order' => (int) ($step['order'] ?? $order),
                'label' => (string) ($step['label'] ?? 'Pas ' . $order . ' — Extrage listă'),
                'type' => 'extract_list',
                'enabled' => !isset($step['enabled']) || !empty($step['enabled']),
                'params' => [
                    'limit' => (int) ($step['limit'] ?? 5),
                    'ignore' => implode(', ', is_array($step['ignore_rules'] ?? null) ? $step['ignore_rules'] : []),
                    'parser_builtin' => $step['parser_builtin'] ?? null,
                    'elements' => $elements,
                ],
            ], $order);
        }

        if ($type === 'follow_links') {
            $fm = is_array($step['field_map'] ?? null) ? $step['field_map'] : [];
            $elements = [];
            if (trim((string) ($step['block_selector'] ?? '')) !== '') {
                $elements[] = ['id' => 'el_block', 'key' => 'block', 'label' => 'Bloc detaliu', 'selector' => (string) $step['block_selector']];
            }
            foreach ($fm as $key => $sel) {
                if (trim((string) $sel) === '') {
                    continue;
                }
                $elements[] = ['id' => 'el_' . $key, 'key' => (string) $key, 'label' => ucfirst((string) $key), 'selector' => (string) $sel];
            }

            return self::ensureStepIds([
                'id' => $id,
                'order' => (int) ($step['order'] ?? $order),
                'label' => (string) ($step['label'] ?? 'Pas ' . $order . ' — Follow links'),
                'type' => 'follow_links',
                'enabled' => !empty($step['enabled']),
                'params' => [
                    'save_links' => !empty($step['save_links']),
                    'max_follow' => (int) ($step['max_follow'] ?? 1),
                    'elements' => $elements,
                ],
            ], $order);
        }

        return self::ensureStepIds([
            'id' => $id,
            'order' => (int) ($step['order'] ?? $order),
            'label' => (string) ($step['label'] ?? 'Pas ' . $order),
            'type' => $type,
            'enabled' => !empty($step['enabled']),
            'params' => [],
        ], $order);
    }

    /** @param array<string, mixed> $step */
    private static function ensureStepIds(array $step, int $order): array
    {
        $step['id'] = (string) ($step['id'] ?? 'step_' . $order);
        $step['order'] = (int) ($step['order'] ?? $order);
        if (!isset($step['params']) || !is_array($step['params'])) {
            $step['params'] = [];
        }
        if (isset($step['params']['elements']) && is_array($step['params']['elements'])) {
            foreach ($step['params']['elements'] as $ei => $el) {
                if (!is_array($el)) {
                    continue;
                }
                if (empty($el['id'])) {
                    $step['params']['elements'][$ei]['id'] = 'el_' . ($el['key'] ?? $ei) . '_' . $order;
                }
            }
        }

        return $step;
    }

    /** @return array<string, mixed> */
    public static function newStep(string $type, int $order): array
    {
        $catalog = self::stepTypeCatalog();
        $label = 'Pas ' . $order;
        foreach ($catalog as $row) {
            if (($row['type'] ?? '') === $type) {
                $label = 'Pas ' . $order . ' — ' . ($row['label'] ?? $type);
                break;
            }
        }

        $params = match ($type) {
            'fetch' => ['url_template' => 'https://example.com/search?q={query}'],
            'login' => [
                'url' => '',
                'username_selector' => '',
                'password_selector' => '',
                'submit_selector' => '',
                'username' => '',
                'password' => '',
            ],
            'extract_list' => [
                'limit' => 5,
                'ignore' => 'placeholder, star-fill, #',
                'elements' => [
                    ['id' => 'el_block', 'key' => 'block', 'label' => 'Bloc produs', 'selector' => ''],
                ],
            ],
            'follow_links' => [
                'save_links' => true,
                'max_follow' => 1,
                'elements' => [],
            ],
            'extract_detail' => [
                'elements' => [
                    ['id' => 'el_block', 'key' => 'block', 'label' => 'Bloc detaliu', 'selector' => ''],
                ],
            ],
            default => [],
        };

        return [
            'id' => 'step_' . bin2hex(random_bytes(4)),
            'order' => $order,
            'label' => $label,
            'type' => $type,
            'enabled' => true,
            'params' => $params,
        ];
    }

    /** @return array<string, mixed> */
    public static function newElement(string $key, string $customLabel = ''): array
    {
        $label = $customLabel;
        if ($label === '') {
            foreach (self::elementTypeCatalog() as $row) {
                if (($row['key'] ?? '') === $key) {
                    $label = (string) ($row['label'] ?? $key);
                    break;
                }
            }
        }

        return [
            'id' => 'el_' . bin2hex(random_bytes(3)),
            'key' => $key === 'custom' && $customLabel !== '' ? self::slugKey($customLabel) : $key,
            'label' => $label !== '' ? $label : 'Custom',
            'selector' => '',
        ];
    }

    private static function slugKey(string $label): string
    {
        $k = strtolower(trim($label));
        $k = preg_replace('/[^a-z0-9_]+/', '_', $k) ?? 'custom';

        return trim($k, '_') ?: 'custom';
    }

    /**
     * Convertește pas v2 în format plat pentru runner (compat).
     *
     * @param array<string, mixed> $step
     * @return array<string, mixed>
     */
    public static function flattenForRunner(array $step): array
    {
        if (!isset($step['params']) || !is_array($step['params'])) {
            return $step;
        }

        $type = (string) ($step['type'] ?? '');
        $p = $step['params'];
        $flat = $step;

        if ($type === 'fetch') {
            $flat['url_template'] = (string) ($p['url_template'] ?? '');
        } elseif ($type === 'login') {
            $flat['url_template'] = (string) ($p['url'] ?? '');
            $flat['is_login'] = true;
            $flat['login'] = $p;
        } elseif (in_array($type, ['extract_list', 'extract_detail'], true)) {
            $flat['type'] = $type === 'extract_detail' ? 'parse_detail' : 'parse_list';
            $flat['limit'] = (int) ($p['limit'] ?? 5);
            $flat['ignore_rules'] = array_values(array_filter(array_map('trim', explode(',', (string) ($p['ignore'] ?? '')))));
            $flat['parser_builtin'] = $p['parser_builtin'] ?? null;
            $flat['field_map'] = [];
            foreach ((array) ($p['elements'] ?? []) as $el) {
                if (!is_array($el)) {
                    continue;
                }
                $k = (string) ($el['key'] ?? '');
                $sel = (string) ($el['selector'] ?? '');
                if ($k === 'block') {
                    $flat['block_selector'] = $sel;
                } elseif ($k !== '') {
                    $flat['field_map'][$k] = $sel;
                }
            }
        } elseif ($type === 'follow_links') {
            $flat['save_links'] = !empty($p['save_links']);
            $flat['max_follow'] = (int) ($p['max_follow'] ?? 1);
            $flat['field_map'] = [];
            foreach ((array) ($p['elements'] ?? []) as $el) {
                if (!is_array($el)) {
                    continue;
                }
                $k = (string) ($el['key'] ?? '');
                if ($k === 'block') {
                    $flat['block_selector'] = (string) ($el['selector'] ?? '');
                } elseif ($k !== '') {
                    $flat['field_map'][$k] = (string) ($el['selector'] ?? '');
                }
            }
        }

        return $flat;
    }

    /** @param list<array<string, mixed>> $steps */
    public static function listBlockSelector(array $steps): string
    {
        foreach ($steps as $step) {
            if (!is_array($step) || (string) ($step['type'] ?? '') !== 'extract_list') {
                continue;
            }
            $params = is_array($step['params'] ?? null) ? $step['params'] : [];
            foreach ((array) ($params['elements'] ?? []) as $el) {
                if (!is_array($el) || (string) ($el['key'] ?? '') !== 'block') {
                    continue;
                }

                return trim((string) ($el['selector'] ?? ''));
            }
        }

        return '';
    }

    /**
     * Completează Pas 2 (extract_list) din presetul sursei dacă blocul e gol.
     *
     * @param list<array<string, mixed>> $steps
     * @return list<array<string, mixed>>
     */
    public static function fillEmptyListSelectorsFromDefaults(string $sourceId, array $steps): array
    {
        if (self::listBlockSelector($steps) !== '') {
            return $steps;
        }

        $defaults = ScraperSourceStore::defaultConfig($sourceId);
        $defaultList = null;
        foreach ((array) ($defaults['steps'] ?? []) as $ds) {
            if (is_array($ds) && (string) ($ds['type'] ?? '') === 'extract_list') {
                $defaultList = $ds;
                break;
            }
        }
        if ($defaultList === null) {
            return $steps;
        }

        $defaultParams = is_array($defaultList['params'] ?? null) ? $defaultList['params'] : [];
        $defaultElements = (array) ($defaultParams['elements'] ?? []);
        if ($defaultElements === []) {
            return $steps;
        }

        $out = [];
        $replaced = false;
        foreach ($steps as $step) {
            if (!is_array($step) || (string) ($step['type'] ?? '') !== 'extract_list') {
                $out[] = $step;
                continue;
            }
            $params = is_array($step['params'] ?? null) ? $step['params'] : [];
            if (self::listBlockSelector([$step]) !== '') {
                $out[] = $step;
                continue;
            }
            $params['elements'] = $defaultElements;
            if (trim((string) ($params['ignore'] ?? '')) === '' && !empty($defaultParams['ignore'])) {
                $params['ignore'] = $defaultParams['ignore'];
            }
            if (!isset($params['limit'])) {
                $params['limit'] = (int) ($defaultParams['limit'] ?? 5);
            }
            $step['params'] = $params;
            $step['enabled'] = $step['enabled'] ?? true;
            $out[] = $step;
            $replaced = true;
        }

        if (!$replaced) {
            $out[] = $defaultList;
        }

        return self::migrateSteps($out);
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @return list<array<string, mixed>>
     */
    public static function repairStepsFromDefaults(string $sourceId, array $steps): array
    {
        return self::fillEmptyListSelectorsFromDefaults($sourceId, self::migrateSteps($steps));
    }
}
