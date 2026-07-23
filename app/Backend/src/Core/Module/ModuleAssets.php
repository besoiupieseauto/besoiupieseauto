<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

/**
 * Helper URL / path pentru assets din modules/{Folder}/assets/.
 */
final class ModuleAssets
{
    public static function url(string $moduleId, string $relativeFile): string
    {
        $moduleId = strtolower(trim($moduleId));
        $rel = ltrim(str_replace('\\', '/', $relativeFile), '/');
        if ($moduleId === '' || $rel === '' || str_contains($rel, '..')) {
            return '';
        }

        $meta = ModuleGate::optionalMap()[$moduleId] ?? [];
        $folder = (string) ($meta['folder'] ?? '');
        if ($folder === '') {
            $parts = preg_split('/[^a-zA-Z0-9]+/', $moduleId) ?: [];
            $folder = '';
            foreach ($parts as $part) {
                if ($part !== '') {
                    $folder .= ucfirst(strtolower($part));
                }
            }
        }
        if ($folder === '') {
            return '';
        }

        return '/admin/modules/' . rawurlencode($folder) . '/assets/' . $rel;
    }
}

