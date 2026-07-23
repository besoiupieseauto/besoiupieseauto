<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

/**
 * Feature-uri RBAC derivate din module.json (permissions + provides.paths).
 * Se merg în AdminPermissionCatalog::allFeatures().
 */
final class ModulePermissionBridge
{
    /**
     * @return array<string, array{label: string, desc: string, urls: list<string>, module: string}>
     */
    public static function featuresFromModules(): array
    {
        $out = [];
        try {
            $adminRoot = dirname(__DIR__, 3);
            $registry = ModuleRegistry::instance($adminRoot);
            $registry->discover();

            foreach ($registry->allManifests() as $id => $manifest) {
                if (!$registry->isEnabled($id)) {
                    continue;
                }
                $provides = $manifest->provides();
                $urls = $provides['paths'] ?? [];
                if ($urls === []) {
                    foreach ($provides['slugs'] ?? [] as $slug) {
                        $urls[] = '/admin/' . $slug;
                    }
                }
                $urls = array_values(array_unique(array_map('strval', $urls)));

                $perms = $manifest->permissions();
                if ($perms === []) {
                    $key = 'module.' . $id;
                    $out[$key] = [
                        'label' => $manifest->name(),
                        'desc' => 'Modul opțional: ' . $id,
                        'urls' => $urls,
                        'module' => $id,
                    ];
                    continue;
                }

                foreach ($perms as $perm) {
                    $perm = trim($perm);
                    if ($perm === '') {
                        continue;
                    }
                    // Nu suprascrie feature-uri CORE deja definite (merge doar urls)
                    if (isset($out[$perm])) {
                        $out[$perm]['urls'] = array_values(array_unique(array_merge(
                            $out[$perm]['urls'],
                            $urls
                        )));
                        continue;
                    }
                    $out[$perm] = [
                        'label' => $manifest->name() . ' (' . $perm . ')',
                        'desc' => 'Permisiune din modulul „' . $id . '”',
                        'urls' => $urls,
                        'module' => $id,
                    ];
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $out;
    }
}

