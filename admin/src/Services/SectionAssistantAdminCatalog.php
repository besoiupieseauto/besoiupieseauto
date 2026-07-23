<?php

declare(strict_types=1);

namespace Besoiu\Services;

/** Catalog complet admin — toate modulele, faze, URL-uri (sursă unică pentru asistent). */
final class SectionAssistantAdminCatalog
{
    /** @return array<string, array{label:string,phase:string,features:list<string>}> */
    public static function all(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'phase' => 'Faza 0 — overview',
                'features' => [
                    'Dashboard KPI — /admin/dashboard (produse, comenzi, import, alerte)',
                ],
            ],
            'produse' => [
                'label' => 'Produse & catalog',
                'phase' => 'Faza 1 — catalog live',
                'features' => [
                    'Lista produse + filtre — /admin/product (categorie, brand, fara imagine)',
                    'Edit produs — /admin/editproduse (categorie, subcategorie, badge, pret, imagini)',
                    'Adauga produs — /admin/addproduse',
                    'Vitrina homepage (max 10) — /admin/vitrina',
                    'Produse scanate TecDoc — /admin/scanned',
                    'Categorii CRUD — /admin/categorii',
                    'Template campuri: pName, pBrand, pCode, pCategory, pSubcategory, pPrice, pImages, pOem, pSpecs',
                    'Badge produs (HOT, PROMO, NOU) — pBadge sau Composer: badge HOT toate',
                    'Composer: inventar, categorii/subcategorii, filtrare produse, vitrina',
                    'Composer: edit titlu produs — ex. edit titlu 30204A adauga categorie la final',
                ],
            ],
            'adaos' => [
                'label' => 'Adaos comercial & formare pret',
                'phase' => 'Faza 2 — pret magazin',
                'features' => [
                    'Adaos comercial global + TVA — /admin/adaoscomercial',
                    'Reguli adaos pe categorie/brand/prag pret (create, edit, toggle, preview, apply)',
                    'Rotunjire pret global (none, next integer, round to)',
                    'Reapply adaos global pe tot catalogul',
                    'Trace formare pret (price-log) — tab price-log, deep link import_id',
                    'Formula: pret CSV + compensator furnizor → adaos → TVA = pret final',
                ],
            ],
            'furnizori' => [
                'label' => 'Furnizori B2B',
                'phase' => 'Faza 3 — surse pret',
                'features' => [
                    'Comparare furnizori la import — /admin/suppliers?tab=compare',
                    'Pozitionare / ordine scan furnizori (AUTOTOTAL, AUTONET, MATEROM…)',
                    'Strategie pret: hierarchical_top3_lowest, tier size, omit furnizori',
                    'Reguli brand per furnizor (ignore, verify exact)',
                    'Lista furnizori — /admin/suppliers',
                    'Profil furnizor — credentiale, compensator pre-import %, test conexiune',
                    'Scan furnizor sync CSV/API — /admin/scan',
                    'Supplier Search live — /admin/supplier-search',
                ],
            ],
            'import' => [
                'label' => 'Import CSV / pipeline',
                'phase' => 'Faza 4 — staging → live',
                'features' => [
                    'Upload CSV/Excel furnizor — /admin/import',
                    'Scan consumabile (ulei, lichide, electrice) — local CSV',
                    'Pipeline Scraper Plan 1→N + ePiesa — import pe site',
                    'Coada import staging — /admin/importreview',
                    'Publica one/selected/all, exclude, restore',
                    'Scan imagini coada (refresh_images job)',
                    'Reprocess + sync TecDoc pe rand',
                    'Export validat CSV / Autopro / BaseLinker',
                    'Cron Sync — /admin/cron',
                    'Composer: publica N produse filtrate din coada, repara job blocat',
                ],
            ],
            'scraper' => [
                'label' => 'Scraper & imagini',
                'phase' => 'Faza 5 — enrichment',
                'features' => [
                    'Pipeline Plan 1→N — Autodoc, TecDoc API — /admin/scraper',
                    'Test pipeline pe query produs',
                    'Overlay planuri — sync .env',
                    'ePiesa catalog',
                    'Audit imagini Composer 2.5 — din lista produse',
                ],
            ],
            'comenzi' => [
                'label' => 'Comenzi & vanzari',
                'phase' => 'Faza 6 — orders',
                'features' => [
                    'Lista comenzi — /admin/orders',
                    'Comanda noua — /admin/order-create',
                    'Cos abandonat — /admin/abandoned-carts',
                    'Cos furnizori B2B — /admin/supplier-cart',
                    'Facturi — /admin/facturi',
                    'Livrare / AWB — /admin/livrare',
                ],
            ],
            'clienti' => [
                'label' => 'Clienti',
                'phase' => 'Faza 6 — CRM',
                'features' => [
                    'Lista clienti — /admin/clienti',
                ],
            ],
            'comunicare' => [
                'label' => 'Comunicare & social',
                'phase' => 'Faza 7 — mesaje',
                'features' => [
                    'Hub comunicare — /admin/comunicare',
                    'Mesagerie / Chat — /admin/messages',
                    'Template-uri raspuns — /admin/reply-templates',
                    'Raspunsuri rapide — /admin/reply-templates?tab=quick',
                    'Canale comunicare — /admin/comunicare-canale',
                    'Lead-uri contact — /admin/comunicare-leads',
                    'Broadcast mesaje — /admin/comunicare-broadcast',
                    'Arhiva conversatii — /admin/comunicare-archive',
                ],
            ],
            'automatizare' => [
                'label' => 'Automatizare & AI',
                'phase' => 'Faza 8 — agents',
                'features' => [
                    'Robot AI — /admin/bots',
                    'AI Agent / Supervizor — /admin/ai-agent',
                    'Marketplace integrari — /admin/marketplace',
                    'Export hub — /admin/export',
                    'Cron Sync — /admin/cron',
                    'Composer Repair — alerte ops (job blocat, import esuat)',
                ],
            ],
            'analiza' => [
                'label' => 'Analiza',
                'phase' => 'Faza 9 — insights',
                'features' => [
                    'Search Logs — /admin/search-logs',
                    'Echivalente OEM — /admin/cross-reference',
                    'Rapoarte — /admin/reports',
                ],
            ],
            'website' => [
                'label' => 'Web site & blog',
                'phase' => 'Faza 10 — CMS',
                'features' => [
                    'Editor pagini site — /admin/website',
                    'Blog — /admin/blog',
                    'Articol nou — /admin/addblog',
                ],
            ],
            'sistem' => [
                'label' => 'Sistem',
                'phase' => 'Faza 11 — ops',
                'features' => [
                    'Utilizatori admin — /admin/users',
                    'Alerte ops — /admin/alerts',
                    'Backup — /admin/backup',
                    'Setari (API keys, prompt rules) — /admin/settings',
                ],
            ],
        ];
    }

    /** @return array<string, array{label:string,phase:string,features:list<string>}> */
    public static function forSection(string $section): array
    {
        $all = self::all();
        $map = [
            'produse' => ['produse', 'adaos', 'scraper'],
            'furnizori' => ['furnizori', 'adaos'],
            'import' => ['import', 'furnizori', 'scraper'],
            'categorii' => ['produse'],
            'scraper' => ['scraper', 'import'],
            'comenzi' => ['comenzi', 'clienti'],
            'comunicare' => ['comunicare', 'clienti'],
            'clienti' => ['clienti', 'comunicare', 'comenzi'],
        ];
        $keys = $map[$section] ?? ['produse'];
        $out = [];
        foreach ($keys as $key) {
            if (isset($all[$key])) {
                $out[$key] = $all[$key];
            }
        }

        return $out;
    }

    /** @return list<array{module:string,label:string,phase:string,url:string,description:string}> */
    public static function flatFeatures(?string $section = null): array
    {
        $modules = $section !== null && $section !== '' && $section !== 'all'
            ? self::forSection($section)
            : self::all();
        $out = [];
        foreach ($modules as $moduleKey => $module) {
            foreach ($module['features'] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $url = '/admin/product';
                if (preg_match('#(/admin/[a-z0-9\-/?=&]+)#i', $line, $m)) {
                    $url = $m[1];
                }
                $label = $line;
                if (preg_match('/^(.+?)\s*[—–-]\s*/u', $line, $m)) {
                    $label = trim($m[1]);
                }
                $out[] = [
                    'module' => $moduleKey,
                    'label' => $label,
                    'phase' => $module['phase'],
                    'url' => $url,
                    'description' => $line,
                ];
            }
        }

        return $out;
    }
}
