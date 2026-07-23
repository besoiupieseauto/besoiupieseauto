<?php

declare(strict_types=1);

namespace Besoiu\Core\Auth;

/**
 * Permisiuni chat intern (Composer / Section Assistant) — granular, per utilizator.
 */
final class AdminChatPermissionCatalog
{
    /** @return array<string, array{label: string, desc: string, features: array<string, array{label: string, desc: string}>}> */
    public static function groups(): array
    {
        return [
            'core' => [
                'label' => 'Acces chat',
                'desc' => 'Ce poate face operatorul în conversația cu asistentul intern',
                'features' => [
                    'chat.use' => [
                        'label' => 'Deschide chat-ul',
                        'desc' => 'Vede FAB-ul Composer și poate trimite mesaje',
                    ],
                    'chat.query' => [
                        'label' => 'Consultare (citire)',
                        'desc' => 'Inventar, liste, statistici, comenzi — fără modificări',
                    ],
                    'chat.navigate' => [
                        'label' => 'Navigare',
                        'desc' => 'Link-uri către pagini admin din răspunsuri',
                    ],
                ],
            ],
            'edit' => [
                'label' => 'Corecții & editare',
                'desc' => 'Modificări de date — doar ce bifezi aici se poate executa din chat',
                'features' => [
                    'chat.edit_light' => [
                        'label' => 'Corecții ușoare',
                        'desc' => 'Badge HOT/PROMO, vitrină homepage, titlu produs',
                    ],
                    'chat.edit_product' => [
                        'label' => 'Editare produs',
                        'desc' => 'Preț, stoc, categorie, descriere, imagini — un produs',
                    ],
                    'chat.edit_bulk' => [
                        'label' => 'Editare în masă',
                        'desc' => 'Modificări pe selecție / produse bifate',
                    ],
                    'chat.confirm_execute' => [
                        'label' => 'Confirmă & execută',
                        'desc' => 'Finalizează acțiunile pendinte (buton Da / execută)',
                    ],
                ],
            ],
            'advanced' => [
                'label' => 'Import, furnizori, sistem',
                'desc' => 'Acțiuni sensibile — recomandat doar manager / super ambassador',
                'features' => [
                    'chat.import' => [
                        'label' => 'Import & publicare',
                        'desc' => 'Publish staging, scan imagini, consumabile CSV',
                    ],
                    'chat.supplier_adaos' => [
                        'label' => 'Furnizori & adaos',
                        'desc' => 'Reguli adaos, reordonare furnizori',
                    ],
                    'chat.repair' => [
                        'label' => 'Reparații sistem',
                        'desc' => 'Fix alerte, job-uri blocate, scraper',
                    ],
                    'chat.learn' => [
                        'label' => 'Antrenare chat',
                        'desc' => 'Buton Învață — salvează pattern-uri noi',
                    ],
                    'chat.dom_marker_doc' => [
                        'label' => 'IT Specialist — marker DOM',
                        'desc' => 'Vede formularul de documentare RAG la click. Fără această bifă: doar instrucțiuni (popup + chat), fără selector tehnic.',
                    ],
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function allKeys(): array
    {
        $keys = [];
        foreach (self::groups() as $group) {
            foreach ($group['features'] as $key => $_) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** @return array<string, array{label: string, desc: string, permissions: list<string>}> */
    public static function rolePresets(): array
    {
        return [
            'super_ambassador' => [
                'label' => 'Super ambassador',
                'desc' => 'Chat complet — marker IT doar dacă bifezi explicit IT Specialist',
                'permissions' => array_values(array_diff(self::allKeys(), ['chat.dom_marker_doc'])),
            ],
            'manager' => [
                'label' => 'Manager',
                'desc' => 'Consultare + editare + import (fără antrenare)',
                'permissions' => array_values(array_diff(self::allKeys(), ['chat.learn'])),
            ],
            'operator' => [
                'label' => 'Operator',
                'desc' => 'Consultare + corecții ușoare',
                'permissions' => [
                    'chat.use',
                    'chat.query',
                    'chat.navigate',
                    'chat.edit_light',
                    'chat.confirm_execute',
                ],
            ],
            'executive' => [
                'label' => 'Executive',
                'desc' => 'Doar consultare și navigare',
                'permissions' => ['chat.use', 'chat.query', 'chat.navigate'],
            ],
            'custom' => [
                'label' => 'Personalizat',
                'desc' => 'Alege manual permisiunile chat',
                'permissions' => [],
            ],
        ];
    }

    /** @param mixed $raw @return list<string> */
    public static function parseStoredPermissions($raw): array
    {
        $keys = [];
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $k = trim((string) $item);
                if ($k !== '' && in_array($k, self::allKeys(), true)) {
                    $keys[] = $k;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /** @param mixed $raw @return list<string> */
    public static function normalizePermissions($raw, ?string $role = null): array
    {
        $explicit = self::parseStoredPermissions($raw);

        if ($role === 'super_ambassador') {
            if ($explicit !== []) {
                if (!in_array('chat.use', $explicit, true)) {
                    array_unshift($explicit, 'chat.use');
                }

                return array_values(array_unique($explicit));
            }

            return array_values(array_diff(self::allKeys(), ['chat.dom_marker_doc']));
        }

        if ($explicit !== []) {
            if (!in_array('chat.use', $explicit, true)) {
                array_unshift($explicit, 'chat.use');
            }

            return array_values(array_unique($explicit));
        }

        if ($role !== null && $role !== 'custom') {
            return self::rolePresets()[$role]['permissions'] ?? self::rolePresets()['operator']['permissions'];
        }

        return [];
    }

    /** @param list<string> $permissions */
    public static function permissionsSummary(array $permissions): string
    {
        $labels = [];
        foreach (self::groups() as $group) {
            foreach ($group['features'] as $key => $feat) {
                if (in_array($key, $permissions, true)) {
                    $labels[] = (string) $feat['label'];
                }
            }
        }

        if ($labels === []) {
            return 'Fără chat';
        }

        $max = 4;
        if (count($labels) <= $max) {
            return implode(' · ', $labels);
        }

        return implode(' · ', array_slice($labels, 0, $max)) . ' · +' . (count($labels) - $max);
    }

    /** Mapare tip acțiune chat → permisiune necesară. */
    public static function permissionForActionType(string $type, array $pending = []): string
    {
        $type = strtolower(trim($type));
        if ($type === 'product_crud' && (($pending['mode'] ?? '') === 'bulk' || !empty($pending['targets']))) {
            return 'chat.edit_bulk';
        }

        return match ($type) {
            'set_badge', 'vitrina_toggle', 'edit_product_title' => 'chat.edit_light',
            'product_crud' => 'chat.edit_product',
            'publish_import', 'extended_consumable_scan', 'extended_scan_images',
            'extended_reprocess_queue', 'extended_export_baselinker', 'extended_export_autopro' => 'chat.import',
            'extended_adaos_global', 'extended_adaos_apply', 'extended_adaos_create',
            'extended_supplier_reorder' => 'chat.supplier_adaos',
            'composer_repair', 'extended_repair' => 'chat.repair',
            default => 'chat.edit_product',
        };
    }
}
