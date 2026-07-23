<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

use Besoiu\Core\AdminUrl;

/**
 * Gate simplu pentru Templates / Factory — „e modulul opțional activ?”
 */
final class ModuleGate
{
    /** Slug-uri / API system — nu depind de modul opțional instalat. */
    private const CORE_SLUGS = [
        'settings', 'dashboard', 'workspace', 'workspace-switch',
        'login', 'logout', 'alerts', 'system-errors', '403',
        'ai-agent',
        'ai-rag',
    ];

    private const CORE_API_SCRIPTS = [
        'settings_endpoint.php',
        'module_endpoint.php',
        'jobs_health_endpoint.php',
        'jobs_endpoint.php',
        'admin_hub_endpoint.php',
        'dashboard_endpoint.php',
        'search_logs_endpoint.php',
        'nav_registry_endpoint.php',
        'comunicare_endpoint.php',
        'ai_agent_endpoint.php',
        'ai_rag_endpoint.php',
        'ai_intelligence_endpoint.php',
    ];

    /** Căi admin infrastructură — mereu permise (fără modul business). */
    private const CORE_PATH_PREFIXES = [
        '/admin/assets/',
        '/admin/public/assets/',
    ];

    public static function isCoreAdminSlug(string $slug): bool
    {
        return in_array(strtolower(trim($slug)), self::CORE_SLUGS, true);
    }

    public static function enabled(string $moduleId): bool
    {
        try {
            $registry = ModuleRegistry::instance();
            $manifests = $registry->allManifests();
            if (!isset($manifests[$moduleId])) {
                return false;
            }

            return $registry->isEnabled($moduleId);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function crudAllowed(string $crudKey): bool
    {
        $crudKey = trim($crudKey);
        if ($crudKey === '') {
            return false;
        }

        $aliases = self::crudKeyAliases();
        $candidates = array_unique([$crudKey, $aliases[$crudKey] ?? '', strtolower($crudKey)]);
        $candidates = array_values(array_filter($candidates, static fn (string $v): bool => $v !== ''));

        $map = self::optionalMap();
        foreach ($map as $moduleId => $meta) {
            $registered = (string) ($meta['crud_key'] ?? '');
            if ($registered === '') {
                continue;
            }
            foreach ($candidates as $candidate) {
                if ($registered === $candidate || strtolower($registered) === strtolower($candidate)) {
                    return self::enabled($moduleId);
                }
            }
        }

        return false;
    }

    /** @return array<string, string> */
    private static function crudKeyAliases(): array
    {
        return [
            'Categorii' => 'categorii',
            'AdaosComercial' => 'adaos_comercial',
            'Produse' => 'produse',
            'Import' => 'import',
            'CoadaImport' => 'coada_import',
        ];
    }

    public static function slugAllowed(string $slug): bool
    {
        $slug = strtolower(trim($slug));
        if (self::isCoreAdminSlug($slug)) {
            return true;
        }
        foreach (self::optionalMap() as $moduleId => $meta) {
            $slugs = $meta['slugs'] ?? [];
            if (in_array($slug, $slugs, true)) {
                return self::enabled($moduleId);
            }
        }

        return false;
    }

    public static function pathAllowed(string $path): bool
    {
        $normalized = self::normalizePath($path);

        // Include variante clean (/admin/x) ↔ legacy (/admin/public/x) — rutele DB
        // sunt adesea pe legacy, iar frontend-ul pe clean (ex. crudproduse).
        $candidates = AdminUrl::alternatePaths($normalized);
        $legacyToClean = AdminUrl::legacyPathToClean($normalized);
        if ($legacyToClean !== null) {
            $candidates[] = self::normalizePath($legacyToClean);
        }
        $candidates = array_values(array_unique(array_map(
            static fn (string $p): string => self::normalizePath($p),
            $candidates
        )));

        foreach ($candidates as $candidate) {
            if (self::isCoreAdminPath($candidate)) {
                return true;
            }

            foreach (self::CORE_PATH_PREFIXES as $prefix) {
                if (str_starts_with($candidate, $prefix)) {
                    return true;
                }
            }

            foreach (self::optionalMap() as $moduleId => $meta) {
                foreach ($meta['paths'] ?? [] as $registered) {
                    $base = rtrim((string) $registered, '/') ?: '/';
                    if ($candidate === $base || str_starts_with($candidate . '/', $base . '/')) {
                        return self::enabled((string) $moduleId);
                    }
                }
            }
        }

        return false;
    }

    public static function apiScriptAllowed(string $script): bool
    {
        $script = basename(trim($script));
        if ($script === '' || $script === '_autoload.php') {
            return true;
        }
        if (in_array($script, self::CORE_API_SCRIPTS, true)) {
            return true;
        }

        foreach (self::optionalMap() as $moduleId => $meta) {
            $scripts = $meta['api_scripts'] ?? [];
            if (in_array($script, $scripts, true)) {
                return self::enabled((string) $moduleId);
            }
        }

        return false;
    }

    public static function quickActionAllowed(string $actionId): bool
    {
        $actionId = trim($actionId);
        if ($actionId === '') {
            return true;
        }

        foreach (self::optionalMap() as $moduleId => $meta) {
            $actions = $meta['quick_actions'] ?? [];
            if (in_array($actionId, $actions, true)) {
                return self::enabled((string) $moduleId);
            }
        }

        return false;
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            return '/';
        }

        return rtrim($path, '/') ?: '/';
    }

    private static function isCoreAdminPath(string $path): bool
    {
        static $exact = null;
        if ($exact === null) {
            $exact = [
                '/admin' => true,
                '/admin/public' => true,
            ];
            foreach (self::CORE_SLUGS as $slug) {
                if (!class_exists(AdminUrl::class)) {
                    $exact['/admin/' . $slug] = true;
                    $exact['/admin/public/' . $slug] = true;
                    continue;
                }
                foreach (AdminUrl::alternatePaths(AdminUrl::path($slug)) as $variant) {
                    $exact[$variant] = true;
                }
            }
        }

        if (isset($exact[$path])) {
            return true;
        }

        foreach (['/admin/workspace', '/admin/settings', '/admin/system-errors'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function inactiveSlugs(): array
    {
        $out = [];
        foreach (self::optionalMap() as $moduleId => $meta) {
            if (self::enabled($moduleId)) {
                continue;
            }
            foreach ($meta['slugs'] ?? [] as $slug) {
                $out[] = (string) $slug;
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function knownOptionalIds(): array
    {
        return array_keys(self::optionalMap());
    }

    /**
     * @return array<string, array{slugs: list<string>, paths: list<string>, crud_key: ?string, api_scripts?: list<string>, quick_actions?: list<string>, folder?: string, external?: bool}>
     */
    public static function optionalMap(): array
    {
        if (is_array(self::$cachedMap)) {
            return self::$cachedMap;
        }

        $adminRoot = dirname(__DIR__, 3);
        $map = [];

        // 1) Manifestele din modules/{Id}/ — baza pachetului MVP
        try {
            $registry = ModuleRegistry::instance($adminRoot);
            $registry->discover();
            foreach ($registry->allManifests() as $id => $manifest) {
                $provides = $manifest->provides();
                $map[$id] = [
                    'slugs' => $provides['slugs'],
                    'paths' => $provides['paths'],
                    'crud_key' => $provides['crud_key'],
                    'api_scripts' => $provides['api_scripts'],
                    'quick_actions' => $provides['quick_actions'],
                    'folder' => $provides['folder'],
                ];
                if (!empty($provides['workspace'])) {
                    $map[$id]['workspace'] = $provides['workspace'];
                }
            }
        } catch (\Throwable) {
            // Registry indisponibil — fallback pe config
        }

        // 2) optional_modules.php — completează / override pentru bridge legacy
        $path = $adminRoot . '/config/optional_modules.php';
        $static = is_file($path) ? require $path : [];
        if (is_array($static)) {
            foreach ($static as $id => $meta) {
                if (!is_string($id) || !is_array($meta)) {
                    continue;
                }
                $map[$id] = self::mergeModuleMeta($map[$id] ?? [], $meta);
            }
        }

        // 3) installed_map — module externe din ZIP
        $installedFile = $adminRoot . '/storage/modules/installed_map.json';
        if (is_file($installedFile)) {
            $raw = file_get_contents($installedFile);
            if (is_string($raw) && str_starts_with($raw, "\xEF\xBB\xBF")) {
                $raw = substr($raw, 3);
            }
            $extra = is_string($raw) ? json_decode($raw ?: '{}', true) : null;
            if (is_array($extra)) {
                foreach ($extra as $id => $meta) {
                    if (!is_string($id) || !is_array($meta)) {
                        continue;
                    }
                    $map[$id] = self::mergeModuleMeta($map[$id] ?? [], $meta);
                }
            }
        }

        self::$cachedMap = $map;

        return self::$cachedMap;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overlay
     * @return array<string, mixed>
     */
    private static function mergeModuleMeta(array $base, array $overlay): array
    {
        foreach (['slugs', 'paths', 'api_scripts', 'quick_actions'] as $listKey) {
            if (!isset($overlay[$listKey]) || !is_array($overlay[$listKey])) {
                continue;
            }
            $merged = array_merge(
                is_array($base[$listKey] ?? null) ? $base[$listKey] : [],
                $overlay[$listKey]
            );
            $base[$listKey] = array_values(array_unique(array_map('strval', $merged)));
        }
        if (array_key_exists('crud_key', $overlay)) {
            $base['crud_key'] = $overlay['crud_key'];
        }
        if (!empty($overlay['folder'])) {
            $base['folder'] = (string) $overlay['folder'];
        }
        if (!empty($overlay['workspace']) && is_string($overlay['workspace'])) {
            $base['workspace'] = $overlay['workspace'];
        }
        if (array_key_exists('external', $overlay)) {
            $base['external'] = (bool) $overlay['external'];
        }

        return $base;
    }

    public static function resetCache(): void
    {
        self::$cachedMap = null;
    }

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cachedMap = null;
}
