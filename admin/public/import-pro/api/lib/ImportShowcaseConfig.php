<?php
declare(strict_types=1);

/**
 * Config tipuri vitrină — toate tipurile salvate, flag scan_enabled separat.
 */
final class ImportShowcaseConfig
{
    private const DEFAULT_TYPES = ['ulei', 'lichid', 'adeziv', 'baterie', 'bec', 'lubrifiant'];

    /** @var list<array{key:string,label:string,epiesa_url?:string,epiesa_subcategory?:string}> */
    private const EPIESA_TYPE_DEFS = [
        ['key' => 'ulei', 'label' => 'Uleiuri — Ulei motor', 'epiesa_url' => 'https://www.epiesa.ro/gmtn1:auto/gmtn2:uleiuri-si-lubrifianti-auto/', 'epiesa_subcategory' => 'Ulei motor'],
        ['key' => 'lubrifiant', 'label' => 'Uleiuri — Lubrifianți', 'epiesa_url' => 'https://www.epiesa.ro/gmtn1:auto/gmtn2:uleiuri-si-lubrifianti-auto/', 'epiesa_subcategory' => 'Lubrifianti auto'],
        ['key' => 'lichid', 'label' => 'Lichide auto', 'epiesa_url' => 'https://www.epiesa.ro/gmtn1:auto/gmtn2:lichide-auto/', 'epiesa_subcategory' => 'Antigel'],
        ['key' => 'baterie', 'label' => 'Electrice — Baterii', 'epiesa_url' => 'https://www.epiesa.ro/gmtn1:auto/gmtn2:electrice-auto/', 'epiesa_subcategory' => 'Baterii auto'],
        ['key' => 'bec', 'label' => 'Iluminat — Becuri', 'epiesa_url' => 'https://www.epiesa.ro/gmtn1:auto/gmtn2:iluminat-auto/', 'epiesa_subcategory' => 'Becuri auto'],
        ['key' => 'adeziv', 'label' => 'Consumabile — Adezivi', 'epiesa_url' => 'https://www.epiesa.ro/gmtn1:auto/gmtn2:consumabile-service-auto/', 'epiesa_subcategory' => 'Benzi adezive'],
    ];

    public static function path(): string
    {
        return IMPORT_CONFIG . DIRECTORY_SEPARATOR . 'showcase_types.json';
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'types' => self::DEFAULT_TYPES,
            'scan_enabled' => self::DEFAULT_TYPES,
            'match_min_score' => 72,
            'name_fields' => ['title', 'name', 'matchedName'],
            'type_defs' => self::EPIESA_TYPE_DEFS,
        ];
    }

    /** @return array<string, mixed> */
    public static function load(): array
    {
        $config = self::defaults();
        $path = self::path();
        if (!is_file($path)) {
            return $config;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return $config;
        }

        $config = array_merge($config, $decoded);
        if (!is_array($config['types'] ?? null) || $config['types'] === []) {
            $config['types'] = self::DEFAULT_TYPES;
        }
        if (!is_array($config['scan_enabled'] ?? null) || $config['scan_enabled'] === []) {
            $config['scan_enabled'] = $config['types'];
        }
        if (!is_array($config['type_defs'] ?? null) || $config['type_defs'] === []) {
            $config['type_defs'] = self::EPIESA_TYPE_DEFS;
        }

        return $config;
    }

    /**
     * @param list<string> $types
     * @param list<string>|null $scanEnabled
     * @return array<string, mixed>
     */
    public static function save(array $types, ?array $scanEnabled = null, int $minScore = 72): array
    {
        $types = self::cleanTypes($types);
        if ($types === []) {
            $types = self::DEFAULT_TYPES;
        }

        $enabled = $scanEnabled !== null ? self::cleanTypes($scanEnabled) : $types;
        $enabled = array_values(array_filter($enabled, static fn (string $t): bool => in_array($t, $types, true)));
        if ($enabled === []) {
            $enabled = [$types[0]];
        }

        $existing = self::load();
        $config = [
            'types' => $types,
            'scan_enabled' => $enabled,
            'match_min_score' => max(50, min(100, $minScore)),
            'name_fields' => is_array($existing['name_fields'] ?? null) ? $existing['name_fields'] : ['title', 'name', 'matchedName'],
            'type_defs' => self::mergeTypeDefs($types, is_array($existing['type_defs'] ?? null) ? $existing['type_defs'] : []),
            'epiesa_categories' => $existing['epiesa_categories'] ?? [],
        ];

        if (!is_dir(IMPORT_CONFIG)) {
            mkdir(IMPORT_CONFIG, 0775, true);
        }
        file_put_contents(
            self::path(),
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
            LOCK_EX
        );

        return $config;
    }

    /** @return list<string> */
    public static function scanTypes(): array
    {
        $config = self::load();
        $enabled = is_array($config['scan_enabled'] ?? null) ? $config['scan_enabled'] : [];
        $all = is_array($config['types'] ?? null) ? $config['types'] : self::DEFAULT_TYPES;

        return self::cleanTypes($enabled !== [] ? $enabled : $all);
    }

    /** @param list<string> $types @return list<string> */
    private static function cleanTypes(array $types): array
    {
        $out = [];
        foreach ($types as $type) {
            $t = mb_strtolower(trim((string) $type));
            if ($t !== '' && !in_array($t, $out, true)) {
                $out[] = $t;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $types
     * @param list<array<string, mixed>> $existing
     * @return list<array<string, mixed>>
     */
    private static function mergeTypeDefs(array $types, array $existing): array
    {
        $byKey = [];
        foreach (self::EPIESA_TYPE_DEFS as $def) {
            $byKey[$def['key']] = $def;
        }
        foreach ($existing as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = mb_strtolower(trim((string) ($row['key'] ?? '')));
            if ($key !== '') {
                $byKey[$key] = array_merge($byKey[$key] ?? ['key' => $key, 'label' => $key], $row);
            }
        }

        $out = [];
        foreach ($types as $type) {
            $out[] = $byKey[$type] ?? ['key' => $type, 'label' => ucfirst($type)];
        }

        return $out;
    }
}
