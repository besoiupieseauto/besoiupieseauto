<?php

declare(strict_types=1);

namespace Evasystem\Services\Marketplace;

/**
 * tm_110 — Investigare opțiune BaseLinker „Import din magazin” + tichet suport.
 */
final class BaseLinkerStoreImportInvestigation
{
    /** @return array<string, mixed> */
    public static function report(int $activeProductCount = 0): array
    {
        $limits = BaseLinkerImportLimits::catalog();

        return [
            'task_id' => 'tm_110',
            'title' => 'Import din magazin — BaseLinker',
            'status' => 'investigated',
            'conclusion' => self::conclusion($activeProductCount),
            'findings' => self::findings($activeProductCount, $limits),
            'comparison' => self::comparison($limits),
            'required_shops_api_methods' => self::requiredMethods(),
            'baselinker_panel_path' => 'Integrări → Adaugă integrare → Magazine → Platformă personalizată / fișier integrare',
            'documentation_urls' => [
                'shops_api' => 'https://developers.baselinker.com/shops_api/',
                'import_from_store' => 'https://base.com/en-EN/help/knowledgebase/importing-products-from-a-store-or-wholesaler/',
                'integration_file' => 'https://proxy-help-gr.baselinker.com/knowledgebase/integrating-store-with-an-integration-file/',
                'warehouse_integration' => 'https://proxy-help-gr.baselinker.com/knowledgebase/stores-warehouse-integration/',
            ],
            'next_steps' => self::nextSteps(),
        ];
    }

    /** @param array<string, int|string> $limits @return list<string> */
    private static function findings(int $activeProductCount, array $limits): array
    {
        $countLabel = number_format(max(0, $activeProductCount), 0, ',', '.');

        return [
            'Opțiunea BaseLinker „Import din magazin” (Products → Import → Import products from external storage) conectează magazinul ca sursă continuă, nu prin upload fișier.',
            'Datele se descarcă on-the-fly de pe serverul magazinului prin fișier integrare (Shops API) — fără limită 30MB XML / 5MB CSV per fișier.',
            'BaseLinker suportă paginare (ProductsList, ProductsPrices, ProductsQuantity) — catalog mare (~' . $countLabel . ' produse) se sincronizează în batch-uri mici.',
            'Pentru platforme custom (PHP nativ, fără WooCommerce/PrestaShop), este necesar fișier integrare propriu conform protocolului Shops API.',
            'Besoiu a implementat endpoint `/api/baselinker-shop-integration.php` (tm_110) cu metodele obligatorii SupportedMethods, FileVersion, ProductsList.',
            'Limita rămâne doar la import fișier manual: ' . (int) ($limits['xml_max_mb'] ?? 30) . 'MB XML / '
                . (int) ($limits['csv_max_mb'] ?? 5) . 'MB CSV / ' . (int) ($limits['daily_max_mb'] ?? 100) . 'MB/zi.',
        ];
    }

    /** @param array<string, int|string> $limits @return array<string, mixed> */
    private static function comparison(array $limits): array
    {
        return [
            'file_import' => [
                'label' => 'Import fișier CSV/XML',
                'feasible_for_100k' => false,
                'limits' => $limits,
                'note' => 'Depășește limita per fișier și volum zilnic pentru ~100k piese.',
            ],
            'api_direct' => [
                'label' => 'API direct (addInventoryProduct)',
                'feasible_for_100k' => true,
                'note' => 'Implementat tm_109 — batch-uri în coadă, fără upload.',
            ],
            'feed_fragmented' => [
                'label' => 'Feed XML/JSON fragmentat',
                'feasible_for_100k' => true,
                'note' => 'Implementat tm_108 — URL fix sub 30MB/fragment.',
            ],
            'import_din_magazin' => [
                'label' => 'Import din magazin (Shops API)',
                'feasible_for_100k' => true,
                'note' => 'Sincronizare continuă; necesită înregistrare magazin în panoul BaseLinker + bl_pass.',
                'bypasses_file_limit' => true,
            ],
        ];
    }

    /** @return list<string> */
    private static function requiredMethods(): array
    {
        return [
            'SupportedMethods (obligatoriu)',
            'FileVersion (obligatoriu)',
            'ProductsList (obligatoriu, cu paginare pages)',
            'ProductsData',
            'ProductsPrices',
            'ProductsQuantity',
            'ProductsCategories',
        ];
    }

    /** @return list<string> */
    private static function nextSteps(): array
    {
        return [
            'Deschide tichet suport BaseLinker (text pregătit mai jos) — solicită activarea integrării magazin custom.',
            'În panoul BaseLinker: Integrări → Magazine → adaugă magazin cu URL fișier integrare Besoiu.',
            'Copiază bl_pass generat în admin Besoiu → BaseLinker → secțiunea Import din magazin.',
            'Testează conexiunea din BaseLinker; activează sincronizare preț/stoc periodică.',
            'Alternativ imediat: API direct (tm_109) sau feed fragmentat (tm_108) — deja funcționale.',
        ];
    }

    private static function conclusion(int $activeProductCount): string
    {
        $countLabel = number_format(max(0, $activeProductCount), 0, ',', '.');

        return 'Import din magazin este soluția recomandată de BaseLinker pentru sincronizare continuă a catalogului '
            . $countLabel . ' produse, ocolind limita de upload fișier. Besoiu are fișier integrare Shops API pregătit; '
            . 'urmează configurarea în panoul BaseLinker (posibil cu asistență suport pentru platformă custom).';
    }

    /** @param array<string, mixed> $shopInfo */
    public static function buildSupportTicket(array $shopInfo, int $activeProductCount = 0): string
    {
        $integrationUrl = trim((string) ($shopInfo['urls']['integration_file'] ?? ''));
        $blPass = trim((string) ($shopInfo['bl_pass'] ?? ''));
        $countLabel = number_format(max(0, $activeProductCount), 0, ',', '.');
        $siteUrl = 'https://besoiupieseauto.ro';

        return <<<TICKET
Subject: Custom store integration — besoiupieseauto.ro (~{$countLabel} auto parts, bypass 30MB file limit)

Hello BaseLinker Support,

We operate a custom PHP e-commerce store (Besoiu Piese Auto — {$siteUrl}) with approximately {$countLabel} active auto parts products.

We need to import/sync our full catalog into BaseLinker continuously, but manual CSV/XML file import exceeds your documented limits (5MB CSV / 30MB XML per file, 100MB/day). Our catalog cannot fit within these constraints.

We investigated the "Import products from external storage (store/wholesaler)" option and prepared a custom Shops API integration file on our server:

Integration file URL: {$integrationUrl}
Platform: Custom PHP (Besoiu Piese Auto)
Shops API version: 1.0.0-tm110
Supported methods: SupportedMethods, FileVersion, ProductsList, ProductsData, ProductsPrices, ProductsQuantity, ProductsCategories
Pagination: enabled (500 products/page)

Communication password (bl_pass): {$blPass}

Could you please:
1. Confirm how to register a fully custom store (non-WooCommerce/PrestaShop) in Integrations → Shops.
2. Advise whether our integration file URL can be connected for continuous product import/sync.
3. Confirm recommended sync settings (price/stock pull from store) for a ~100k SKU catalog.

Alternative approaches we already use: direct Inventory API (batch queue) and fragmented XML feed URL — but we prefer native "Import from store" for ongoing sync.

Thank you,
Besoiu Piese Auto Team
TICKET;
    }
}
