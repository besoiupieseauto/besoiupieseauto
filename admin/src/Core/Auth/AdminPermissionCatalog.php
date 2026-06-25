<?php

declare(strict_types=1);

namespace Evasystem\Core\Auth;

/**
 * Catalog permisiuni admin — secțiuni + funcții granulare (delegare parțială).
 */
final class AdminPermissionCatalog
{
    /**
     * @return array<string, array{label: string, desc: string, features: array<string, array{label: string, desc: string, urls: list<string>}>}>
     */
    public static function sections(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'desc' => 'Panou principal',
                'features' => [
                    'dashboard.home' => [
                        'label' => 'Dashboard',
                        'desc' => 'Statistici și acces rapid',
                        'urls' => ['/admin/dashboard'],
                    ],
                ],
            ],
            'furnizori' => [
                'label' => 'Furnizori',
                'desc' => 'Comparare și listă furnizori B2B',
                'features' => [
                    'furnizori.compare' => [
                        'label' => 'Comparare furnizori',
                        'desc' => 'Scanare și comparare prețuri',
                        'urls' => ['/admin/suppliers'],
                    ],
                    'furnizori.list' => [
                        'label' => 'Lista furnizori',
                        'desc' => 'CRUD furnizori import',
                        'urls' => ['/admin/furnizori', '/admin/profilefurnizori', '/admin/addfurnizori'],
                    ],
                ],
            ],
            'produse' => [
                'label' => 'Produse',
                'desc' => 'Catalog magazin, import, categorii',
                'features' => [
                    'produse.list' => [
                        'label' => 'Lista produse',
                        'desc' => 'Grid produse, editare, audit imagini',
                        'urls' => ['/admin/product', '/admin/editproduse', '/admin/addproduse'],
                    ],
                    'produse.vitrina' => [
                        'label' => 'Vitrina homepage',
                        'desc' => 'Produse afișate pe site',
                        'urls' => ['/admin/vitrina', '/admin/produse-selective'],
                    ],
                    'produse.scanned' => [
                        'label' => 'Produse scanate',
                        'desc' => 'Rezultate scan marketplace',
                        'urls' => ['/admin/scanned'],
                    ],
                    'produse.caiet' => [
                        'label' => 'Caiet comenzi — produse ERP',
                        'desc' => 'Catalog produse legacy TM/Utvin (ERP)',
                        'urls' => ['/admin/caiet-produse'],
                    ],
                    'produse.categorii' => [
                        'label' => 'Categorii CMS',
                        'desc' => 'Arbore categorii admin',
                        'urls' => ['/admin/categorii', '/admin/addcategorii'],
                    ],
                    'produse.adaos' => [
                        'label' => 'Adaos comercial',
                        'desc' => 'Reguli markup preț',
                        'urls' => ['/admin/adaoscomercial', '/admin/crudadaoscomercial'],
                    ],
                    'produse.import' => [
                        'label' => 'Import CSV / Excel',
                        'desc' => 'Upload și procesare fișiere',
                        'urls' => ['/admin/import'],
                    ],
                    'produse.import_queue' => [
                        'label' => 'Coadă import',
                        'desc' => 'Review conflicte înainte de publish',
                        'urls' => ['/admin/importreview'],
                    ],
                ],
            ],
            'comenzi' => [
                'label' => 'Comenzi',
                'desc' => 'Comenzi site, facturi, livrare',
                'features' => [
                    'comenzi.list' => [
                        'label' => 'Toate comenzile',
                        'desc' => 'Comenzi website și interne',
                        'urls' => ['/admin/comenzi', '/admin/orders', '/admin/order-edit'],
                    ],
                    'comenzi.create' => [
                        'label' => 'Comandă nouă',
                        'desc' => 'Creare comandă manuală',
                        'urls' => ['/admin/order-create'],
                    ],
                    'comenzi.supplier_search' => [
                        'label' => 'Supplier Search',
                        'desc' => 'Căutare paralelă 5 furnizori',
                        'urls' => ['/admin/supplier-search', '/admin/supplier-cart', '/admin/searching'],
                    ],
                    'comenzi.caiet' => [
                        'label' => 'Caiet comenzi',
                        'desc' => 'ERP TM / Utvin legacy (tab-uri în Comenzi admin)',
                        'urls' => [
                            '/admin/orders',
                            '/admin/orders?legacy_tab=tm',
                            '/admin/orders?legacy_tab=utvin',
                            '/admin/orders?legacy_tab=ext',
                            '/admin/caiet-clienti',
                            '/admin/caiet-produse',
                            '/admin/caiet-facturi',
                            '/admin/caiet-incasari',
                        ],
                    ],
                    'comenzi.facturi' => [
                        'label' => 'Facturi',
                        'desc' => 'Emitere și listă facturi',
                        'urls' => ['/admin/facturi'],
                    ],
                    'comenzi.livrare' => [
                        'label' => 'Livrare / AWB',
                        'desc' => 'Curierat și AWB-uri',
                        'urls' => ['/admin/livrare'],
                    ],
                    'comenzi.abandoned_carts' => [
                        'label' => 'Coș abandonat',
                        'desc' => 'Lead-uri checkout nefinalizate',
                        'urls' => ['/admin/abandoned-carts'],
                    ],
                ],
            ],
            'clienti' => [
                'label' => 'Clienți',
                'desc' => 'CRM clienți magazin',
                'features' => [
                    'clienti.list' => [
                        'label' => 'Lista clienți',
                        'desc' => 'Conturi și date contact',
                        'urls' => ['/admin/clienti'],
                    ],
                ],
            ],
            'automatizare' => [
                'label' => 'Automatizare',
                'desc' => 'Roboți, mesagerie, marketplace',
                'features' => [
                    'automatizare.bots' => [
                        'label' => 'Roboți AI',
                        'desc' => 'Chat, WhatsApp, configurare bot',
                        'urls' => ['/admin/bots'],
                    ],
                    'automatizare.marketplace' => [
                        'label' => 'Marketplace',
                        'desc' => 'OLX, PieseAuto.ro',
                        'urls' => ['/admin/marketplace', '/admin/marketplace-pieseauto', '/admin/marketplace-baselinker'],
                    ],
                    'automatizare.export' => [
                        'label' => 'Export catalog',
                        'desc' => 'Generare fișiere export produse (Piese Autopro)',
                        'urls' => ['/admin/export'],
                    ],
                    'automatizare.cron' => [
                        'label' => 'Cron Sync',
                        'desc' => 'Sincronizări programate',
                        'urls' => ['/admin/cron', '/admin/scan'],
                    ],
                    'automatizare.scraper' => [
                        'label' => 'Scraper',
                        'desc' => 'Hub imagini și surse',
                        'urls' => ['/admin/scraper'],
                    ],
                ],
            ],
            'comunicare' => [
                'label' => 'Comunicare & Socializare',
                'desc' => 'Mesagerie, template-uri, canale, lead-uri',
                'features' => [
                    'comunicare.hub' => [
                        'label' => 'Hub comunicare',
                        'desc' => 'Panou central 10 module',
                        'urls' => ['/admin/comunicare'],
                    ],
                    'comunicare.messages' => [
                        'label' => 'Mesagerie / Chat',
                        'desc' => 'Inbox unificat clienți',
                        'urls' => ['/admin/messages'],
                    ],
                    'comunicare.templates' => [
                        'label' => 'Template-uri răspuns',
                        'desc' => 'Formulare WhatsApp, email, OLX',
                        'urls' => ['/admin/reply-templates'],
                    ],
                    'comunicare.channels' => [
                        'label' => 'Canale comunicare',
                        'desc' => 'Statistici per canal',
                        'urls' => ['/admin/comunicare-canale'],
                    ],
                    'comunicare.leads' => [
                        'label' => 'Lead-uri contact',
                        'desc' => 'Mesaje noi și formulare',
                        'urls' => ['/admin/comunicare-leads'],
                    ],
                    'comunicare.broadcast' => [
                        'label' => 'Broadcast mesaje',
                        'desc' => 'Mesaje în masă din template',
                        'urls' => ['/admin/comunicare-broadcast'],
                    ],
                    'comunicare.archive' => [
                        'label' => 'Arhivă conversații',
                        'desc' => 'Istoric rezolvat',
                        'urls' => ['/admin/comunicare-archive'],
                    ],
                ],
            ],
            'analiza' => [
                'label' => 'Analiză',
                'desc' => 'Logs căutări și rapoarte',
                'features' => [
                    'analiza.searchlogs' => [
                        'label' => 'Search Logs',
                        'desc' => 'VIN/OEM negăsite',
                        'urls' => ['/admin/searchlogs', '/admin/search-logs'],
                    ],
                    'analiza.oem' => [
                        'label' => 'Echivalențe OEM',
                        'desc' => 'Cross-reference coduri',
                        'urls' => ['/admin/cross-reference', '/admin/crossreference', '/admin/oem'],
                    ],
                    'analiza.reports' => [
                        'label' => 'Rapoarte',
                        'desc' => 'Statistici și export',
                        'urls' => ['/admin/reports', '/admin/report'],
                    ],
                ],
            ],
            'website' => [
                'label' => 'Website / CMS',
                'desc' => 'Conținut site public',
                'features' => [
                    'website.pages' => [
                        'label' => 'Pagini site',
                        'desc' => 'CMS texte pagini',
                        'urls' => ['/admin/website'],
                    ],
                    'website.blog' => [
                        'label' => 'Blog',
                        'desc' => 'Articole și editor',
                        'urls' => ['/admin/blog', '/admin/addblog', '/admin/editblog'],
                    ],
                ],
            ],
            'sistem' => [
                'label' => 'Sistem',
                'desc' => 'Setări, backup, tokeni',
                'features' => [
                    'sistem.settings' => [
                        'label' => 'Setări sistem',
                        'desc' => 'Utilizatori, tokeni, chei API',
                        'urls' => ['/admin/settings'],
                    ],
                    'sistem.backup' => [
                        'label' => 'Backup',
                        'desc' => 'Copii de siguranță BD',
                        'urls' => ['/admin/backup'],
                    ],
                    'sistem.ai_tokens' => [
                        'label' => 'Monitor tokeni AI',
                        'desc' => 'Consum Grok, Gemini, Groq',
                        'urls' => ['/admin/ai-tokens'],
                    ],
                    'sistem.alerts' => [
                        'label' => 'Alerte',
                        'desc' => 'Notificări sistem',
                        'urls' => ['/admin/alerts'],
                    ],
                ],
            ],
            'utilizatori' => [
                'label' => 'Utilizatori admin',
                'desc' => 'Gestiune echipă și delegare',
                'features' => [
                    'utilizatori.manage' => [
                        'label' => 'Gestiune utilizatori',
                        'desc' => 'Creare conturi și permisiuni',
                        'urls' => ['/admin/users', '/admin/addusers', '/admin/profileusers'],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, array{label: string, desc: string, urls: list<string>}> */
    public static function allFeatures(): array
    {
        $out = [];
        foreach (self::sections() as $section) {
            foreach ($section['features'] as $key => $feat) {
                $out[$key] = $feat;
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function allFeatureKeys(): array
    {
        return array_keys(self::allFeatures());
    }

    /** @return list<string> */
    public static function featureKeysForSection(string $sectionKey): array
    {
        $sections = self::sections();

        return array_keys($sections[$sectionKey]['features'] ?? []);
    }

    /** @return list<string> */
    public static function expandToFeatureKeys(array $keys): array
    {
        $sections = self::sections();
        $features = self::allFeatureKeys();
        $out = [];

        foreach ($keys as $key) {
            $key = (string) $key;
            if ($key === '') {
                continue;
            }
            if (in_array($key, $features, true)) {
                $out[] = $key;
                continue;
            }
            if (isset($sections[$key])) {
                foreach (array_keys($sections[$key]['features']) as $fk) {
                    $out[] = $fk;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /** Compatibilitate API vechi — agregat pe secțiune. */
    public static function modules(): array
    {
        $modules = [];
        foreach (self::sections() as $key => $section) {
            $urls = [];
            foreach ($section['features'] as $feat) {
                foreach ($feat['urls'] as $u) {
                    $urls[] = $u;
                }
            }
            $modules[$key] = [
                'label' => $section['label'],
                'desc' => $section['desc'],
                'color' => 'primary',
                'urls' => array_values(array_unique($urls)),
                'features' => $section['features'],
            ];
        }

        return $modules;
    }

    /** @return array<string, array{label: string, desc: string, permissions: list<string>}> */
    public static function rolePresets(): array
    {
        $allSections = array_keys(self::sections());

        return [
            'super_ambassador' => [
                'label' => 'Super ambassador',
                'desc' => 'Acces complet',
                'permissions' => $allSections,
            ],
            'manager' => [
                'label' => 'Manager',
                'desc' => 'Operațiuni zilnice, fără gestiune utilizatori',
                'permissions' => array_values(array_diff($allSections, ['utilizatori'])),
            ],
            'operator' => [
                'label' => 'Operator',
                'desc' => 'Comenzi, produse, clienți',
                'permissions' => ['dashboard', 'produse', 'comenzi', 'clienti'],
            ],
            'executive' => [
                'label' => 'Executive',
                'desc' => 'Vizualizare + analiză',
                'permissions' => ['dashboard', 'analiza', 'comenzi'],
            ],
            'custom' => [
                'label' => 'Personalizat',
                'desc' => 'Alege manual funcțiile',
                'permissions' => [],
            ],
        ];
    }

    /** @return list<string> */
    public static function allKeys(): array
    {
        return self::allFeatureKeys();
    }

    /** @return list<string> */
    public static function presetForRole(string $role): array
    {
        $presets = self::rolePresets();
        if (!isset($presets[$role])) {
            return self::expandToFeatureKeys($presets['operator']['permissions']);
        }

        return self::expandToFeatureKeys($presets[$role]['permissions']);
    }

    /** @param mixed $raw @return list<string> */
    public static function normalizePermissions($raw, ?string $role = null): array
    {
        if ($role === 'super_ambassador') {
            return self::allFeatureKeys();
        }

        $perms = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $perms = $decoded;
            }
        } elseif (is_array($raw)) {
            $perms = $raw;
        }

        if ($perms === [] && $role !== null && $role !== 'custom') {
            return self::presetForRole($role);
        }

        return self::expandToFeatureKeys(array_map('strval', $perms));
    }

    public static function canManageUsers(?string $role, array $permissions): bool
    {
        if ($role === 'super_ambassador') {
            return true;
        }

        $expanded = self::expandToFeatureKeys($permissions);

        return in_array('utilizatori.manage', $expanded, true);
    }

    /** @param list<string> $permissions */
    public static function permissionsSummary(array $permissions): string
    {
        $expanded = self::expandToFeatureKeys($permissions);
        $parts = [];
        foreach (self::sections() as $section) {
            $keys = array_keys($section['features']);
            $n = count(array_intersect($keys, $expanded));
            if ($n > 0) {
                $total = count($keys);
                $parts[] = $section['label'] . ($n < $total ? " ({$n}/{$total})" : '');
            }
        }

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    public static function urlAllowed(string $path, array $permissions, ?string $role = null): bool
    {
        if ($role === 'super_ambassador') {
            return true;
        }

        $path = rtrim(parse_url($path, PHP_URL_PATH) ?: $path, '/') ?: '/';
        $expanded = self::expandToFeatureKeys($permissions);
        $features = self::allFeatures();

        foreach ($expanded as $key) {
            if (!isset($features[$key])) {
                continue;
            }
            foreach ($features[$key]['urls'] as $prefix) {
                $prefix = rtrim($prefix, '/');
                if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                    return true;
                }
            }
        }

        foreach (['/admin/login', '/admin/logout', '/admin/settings', '/admin/dashboard'] as $always) {
            if ($path === $always || str_starts_with($path, $always . '/')) {
                return true;
            }
        }

        return false;
    }
}
