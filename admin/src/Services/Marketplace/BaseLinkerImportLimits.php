<?php

declare(strict_types=1);

namespace Evasystem\Services\Marketplace;

/**
 * Limite documentate BaseLinker pentru import fișier vs. API direct.
 * tm_109 — ~100k piese auto depășesc 30MB XML / 5MB CSV per import.
 */
final class BaseLinkerImportLimits
{
    public const CSV_MAX_BYTES = 5_242_880;

    public const XML_MAX_BYTES = 31_457_280;

    public const DAILY_MAX_BYTES = 104_857_600;

    public const DEFAULT_API_BATCH_SIZE = 50;

    public const MAX_API_BATCH_SIZE = 200;

    /** @return array<string, int|string> */
    public static function catalog(): array
    {
        return [
            'csv_max_mb' => 5,
            'xml_max_mb' => 30,
            'daily_max_mb' => 100,
            'csv_max_bytes' => self::CSV_MAX_BYTES,
            'xml_max_bytes' => self::XML_MAX_BYTES,
            'daily_max_bytes' => self::DAILY_MAX_BYTES,
        ];
    }

    /**
     * @return array{
     *   recommended:string,
     *   reason:string,
     *   file_import_feasible:bool,
     *   estimated_api_batches:int,
     *   batch_size:int,
     *   alternatives:list<string>
     * }
     */
    public static function recommendStrategy(int $activeProductCount, int $batchSize = self::DEFAULT_API_BATCH_SIZE): array
    {
        $batchSize = max(1, min(self::MAX_API_BATCH_SIZE, $batchSize));
        $estimatedBatches = $activeProductCount > 0 ? (int) ceil($activeProductCount / $batchSize) : 0;

        return [
            'recommended' => 'api_direct',
            'reason' => 'Catalog mare (~' . number_format($activeProductCount, 0, ',', '.')
                . ' produse) depășește limitele BaseLinker pentru import CSV/XML (5MB/30MB per fișier, 100MB/zi). '
                . 'API direct trimite produse individual/batch fără upload fișier.',
            'file_import_feasible' => false,
            'estimated_api_batches' => $estimatedBatches,
            'batch_size' => $batchSize,
            'alternatives' => [
                'api_direct',
                'feed_fragmentat',
                'import_din_magazin',
            ],
            'import_din_magazin' => self::storeImportSummary($activeProductCount),
        ];
    }

    /** @return array<string, mixed> */
    public static function storeImportSummary(int $activeProductCount = 0): array
    {
        return [
            'label' => 'Import din magazin (Shops API)',
            'bypasses_file_limit' => true,
            'continuous_sync' => true,
            'requires_integration_file' => true,
            'docs' => 'https://developers.baselinker.com/shops_api/',
            'feasible_for_catalog' => $activeProductCount > 0,
            'note' => 'BaseLinker descarcă produse paginat de pe serverul magazinului — fără upload CSV/XML. '
                . 'Necesită înregistrare magazin custom în panoul BaseLinker (tm_110).',
        ];
    }
}
