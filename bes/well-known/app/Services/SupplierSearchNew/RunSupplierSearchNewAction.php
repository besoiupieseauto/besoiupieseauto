<?php

namespace App\Services\SupplierSearchNew;

use App\Services\SupplierSearch\PricingApplier;
use App\Services\SupplierSearch\DeliveryFormatter;
use App\Services\SupplierSearch\ProductNameResolver;
use App\Services\SupplierSearch\SearchPayloadBuilder;
use App\Services\AutoPartner\AutoPartnerService;
use App\Services\Autonet\AutonetService;
use Illuminate\Support\Facades\DB;

class RunSupplierSearchNewAction
{
    private const SUPPLIER_TIMEOUT_SECONDS = 15;
    private const SUPPLIER_CONNECT_TIMEOUT_SECONDS = 5;

    /** Autonet-supported brands (mainart_brands) – same list as old supplier search. */
    private const AUTONET_BRANDS = [
        'ABAKUS', 'Ac Rolcar', 'AE', 'ALKAR', 'Arnott', 'ASSO', 'ATE', 'AUTLOG', 'AUTOFREN SEINSA',
        'B CAR', 'BEHR', 'BERU', 'BERU by DRiV', 'BILSTEIN', 'BorgWarner', 'BOSAL', 'BOSCH', 'BREMBO', 'BTS Turbo',
        'BUGIAD', 'CALORSTAT by Vernet', 'CIFAM', 'COFLE', 'CONTINENTAL CTAM', 'CONTINENTAL CTAM BR',
        'CONTINENTAL-APAC', 'CONTITECH AIR SPRING', 'CORTECO', 'DAYCO', 'DELPHI', 'DENSO', 'ELRING', 'FACET', 'FAE', 'FAG', 'FAI AutoParts', 'FEBI BILSTEIN', 'FERODO', 'FRECCIA',
        'GARRETT', 'GATES', 'GKN', 'GLYCO', 'GOETZE', 'GOETZE ENGINE', 'GRAF', 'HC-Cargo', 'HELLA', 'HEPU', 'HERTH+BUSS ELPARTS', 'HERTH+BUSS JAKOPARTS',
        'HITACHI', 'INA', 'KLOKKERHOLM', 'KNECHT', 'KOLBENSCHMIDT', 'KYB', 'LEMFÖRDER', 'LESJÖFORS', 'LÖBRO', 'LPR',
        'LuK', 'MAGNETI MARELLI', 'MAGNETI MARELLI - BR', 'MAHLE', 'MANN-FILTER', 'MEYLE', 'MOBILETRON', 'MONROE', 'MOOG', 'NGK', 'NIPPARTS', 'NISSENS',
        'NRF', 'NTK', 'NÜRAL', 'PIERBURG', 'QUICK BRAKE', 'SACHS', 'SKF', 'SNR', 'SPIDAN', 'STABILUS', 'TEXTAR',
        'TOPRAN', 'TRW', 'TYC', 'VAICO', 'VALEO', 'VDO', 'VEMO', 'VICTOR REINZ', 'WABCO', 'WAHLER', 'ZF', 'ZIMMERMANN',
    ];

    public function __construct(
        protected PoolRunner $poolRunner,
        protected ResultBuilder $resultBuilder,
        protected PricingApplier $pricingApplier,
        protected DeliveryFormatter $deliveryFormatter,
        protected ProductNameResolver $productNameResolver,
        protected SearchPayloadBuilder $payloadBuilder,
        protected PartsCatalogLookup $partsCatalogLookup,
        protected AutoPartnerService $autoPartnerService,
        protected AutonetService $autonetService
    ) {}

    /**
     * Run the full search: pool -> parse -> format -> same payload as old supplier search.
     * Uses parts_catalog for additional codes (Autopartner + Autonet) like the old search.
     *
     * @return array{payload: array, total_time: float}
     */
    public function run(
        string $query,
        array $selectedSuppliers,
        bool $debugTimings = false,
        string $rawQuery = '',
        bool $directOnly = false
    ): array
    {
        $rawQuery = $rawQuery !== '' ? $rawQuery : $query;

        $timings = [];
        $buildInputSteps = [];
        $tStart = microtime(true);
        $t0 = microtime(true);

        $autopartnerProducts = null;
        $autopartnerSeedMap = [];
        $autonetItems = null;
        $autototalRequests = null;
        $autonetRowMap = [];
        $catalogRows = null;

        if (!$directOnly && (in_array('autopartner', $selectedSuppliers) || in_array('autonet', $selectedSuppliers))) {
            $tStep = microtime(true);
            $catalogRows = $this->partsCatalogLookup->getAllRowsByCode($query);
            $buildInputSteps['catalog_rows'] = round(microtime(true) - $tStep, 3);

            $tStep = microtime(true);
            $autopartnerProducts = $this->buildAutopartnerProducts($query, $selectedSuppliers, $catalogRows);
            $buildInputSteps['autopartner_products'] = round(microtime(true) - $tStep, 3);

            $tStep = microtime(true);
            $autopartnerSeedMap = $this->buildAutopartnerSeedMap($catalogRows);
            $buildInputSteps['autopartner_seed_map'] = round(microtime(true) - $tStep, 3);
            if (in_array('autonet', $selectedSuppliers, true)) {
                $tStep = microtime(true);
                $autonetRows = $this->partsCatalogLookup->getRowsForAutonet($query, $rawQuery, self::AUTONET_BRANDS);
                $buildInputSteps['autonet_rows'] = round(microtime(true) - $tStep, 3);

                $tStep = microtime(true);
                $autonetItems = $this->buildAutonetItems($query, $rawQuery, $selectedSuppliers, $autonetRows);
                $buildInputSteps['autonet_items'] = round(microtime(true) - $tStep, 3);

                $tStep = microtime(true);
                $autonetRowMap = $this->buildAutonetRowMap($autonetRows, $query);
                $buildInputSteps['autonet_row_map'] = round(microtime(true) - $tStep, 3);
            } else {
                $autonetItems = $this->buildAutonetItems($query, $rawQuery, $selectedSuppliers);
            }
        }
        if (!$directOnly && in_array('autototal', $selectedSuppliers, true)) {
            $tStep = microtime(true);
            $autototalRequests = $this->buildAutototalRequests($query, $rawQuery, 18);
            if (!is_array($autototalRequests) || $autototalRequests === []) {
                $fallbackItemkey = trim($rawQuery !== '' ? $rawQuery : $query);
                if ($fallbackItemkey !== '') {
                    $autototalRequests = [[
                        'itemkey' => $fallbackItemkey,
                        'quantity' => 2,
                        'targets' => [[
                            'base_code' => preg_replace('/[.\s\-\/|\\\\]+/', '', $query) ?: $query,
                            'manufacturer' => null,
                            'db_name' => null,
                            'sup_brand' => null,
                        ]],
                    ]];
                }
            }
            $buildInputSteps['autototal_requests'] = round(microtime(true) - $tStep, 3);
        }
        $timings['build_inputs'] = round(microtime(true) - $t0, 3);
        $timings['build_inputs_breakdown'] = $buildInputSteps;
        $timings['autototal_request_count'] = is_array($autototalRequests) ? count($autototalRequests) : 0;
        $timings['autopartner_product_count'] = is_array($autopartnerProducts) ? count($autopartnerProducts) : 0;
        $timings['autonet_item_count'] = is_array($autonetItems) ? count($autonetItems) : 0;
        if ($debugTimings) {
            $timings['autonet_debug'] = [
                'db_row_count' => is_countable($autonetRows ?? null) ? count($autonetRows) : 0,
                'db_rows_preview' => array_slice($this->summarizeAutonetRows($autonetRows ?? []), 0, 30),
                'submitted_item_count' => is_array($autonetItems) ? count($autonetItems) : 0,
                'submitted_items_preview' => is_array($autonetItems) ? array_slice($autonetItems, 0, 60) : [],
            ];
        }

        $tPool = microtime(true);
        $poolResult = $this->poolRunner->run(
            $selectedSuppliers,
            $query,
            self::SUPPLIER_TIMEOUT_SECONDS,
            self::SUPPLIER_CONNECT_TIMEOUT_SECONDS,
            $autopartnerProducts,
            $autonetItems,
            $autototalRequests
        );
        $responses = $poolResult['responses'];
        $totalTime = $poolResult['total_time'];
        $timings['pool'] = round(microtime(true) - $tPool, 3);
        if (!empty($poolResult['stats']) && is_array($poolResult['stats'])) {
            $timings['pool_stats'] = $poolResult['stats'];
        }
        if ($debugTimings) {
            $timings['autonet_debug'] = array_merge(
                $timings['autonet_debug'] ?? [],
                $this->buildAutonetApiDebug($responses, $autonetItems)
            );
        }

        $tParse = microtime(true);
        $productsMap = $this->resultBuilder->build($query, $selectedSuppliers, $responses, [
            'autonet_row_map' => $autonetRowMap,
            'autopartner_seed_map' => $autopartnerSeedMap,
        ]);
        $timings['parse_merge'] = round(microtime(true) - $tParse, 3);

        $tPost = microtime(true);
        $this->pricingApplier->applyToProducts($productsMap);
        $this->deliveryFormatter->formatProducts($productsMap);
        $this->productNameResolver->resolveProducts($productsMap);
        $timings['postprocess'] = round(microtime(true) - $tPost, 3);
        $timings['total'] = round(microtime(true) - $tStart, 3);

        $payloadTimings = $debugTimings ? $timings : [];
        $payload = $this->payloadBuilder->build($productsMap, $payloadTimings, []);

        return [
            'payload' => $payload,
            'total_time' => $totalTime,
        ];
    }

    private function summarizeAutonetRows($rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'code_parts' => $row->code_parts ?? null,
                'code_parts_advanced' => $row->code_parts_advanced ?? null,
                'mainart_code_parts' => $row->mainart_code_parts ?? null,
                'mainart_brands' => $row->mainart_brands ?? null,
                'brand_id' => $row->brand_id ?? null,
            ];
        }
        return $out;
    }

    private function normalizeAutonetCodeForCompare(?string $code): string
    {
        $code = (string) ($code ?? '');
        if ($code === '') {
            return '';
        }
        $normalized = $this->autonetService->normalizeCode($code);
        $normalized = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $normalized);
        return strtoupper((string) $normalized);
    }

    private function buildAutonetApiDebug(array $responses, ?array $autonetItems): array
    {
        $submittedPartCodes = [];
        $submittedPartCodesNormalized = [];
        $submittedTdItems = [];

        foreach (($autonetItems ?? []) as $item) {
            if (isset($item['PartNo'])) {
                $partNo = (string) $item['PartNo'];
                $submittedPartCodes[$partNo] = true;
                $norm = $this->normalizeAutonetCodeForCompare($partNo);
                if ($norm !== '') {
                    $submittedPartCodesNormalized[$norm] = $partNo;
                }
            } elseif (isset($item['TDBrandId']) || isset($item['TDArticleNo'])) {
                $submittedTdItems[] = [
                    'TDBrandId' => $item['TDBrandId'] ?? null,
                    'TDArticleNo' => $item['TDArticleNo'] ?? null,
                ];
            }
        }

        $returnedPartCodes = [];
        $returnedPartCodesNormalized = [];
        $returnedArticles = [];
        $autonetResponseCount = 0;

        $autonetResponse = $responses['autonet'] ?? null;
        if ($autonetResponse && method_exists($autonetResponse, 'successful') && $autonetResponse->successful()) {
            $data = method_exists($autonetResponse, 'json') ? $autonetResponse->json() : [];
            $articles = is_array($data) ? (isset($data[0]) ? $data : [$data]) : [];
            foreach ($articles as $article) {
                if (!is_array($article)) {
                    continue;
                }
                $autonetResponseCount++;
                $partNo = (string) ($article['PartNo'] ?? '');
                if ($partNo !== '') {
                    $returnedPartCodes[$partNo] = true;
                    $norm = $this->normalizeAutonetCodeForCompare($partNo);
                    if ($norm !== '') {
                        $returnedPartCodesNormalized[$norm] = $partNo;
                    }
                }
                $returnedArticles[] = [
                    'PartNo' => $partNo !== '' ? $partNo : null,
                    'Currency' => $article['Currency'] ?? null,
                    'PriceWoVat' => $article['PriceWoVat'] ?? null,
                    'DeliveryDataCount' => isset($article['DeliveryData']) && is_array($article['DeliveryData'])
                        ? count($article['DeliveryData'])
                        : 0,
                ];
            }
        }

        $missing = [];
        foreach ($submittedPartCodesNormalized as $norm => $original) {
            if (!isset($returnedPartCodesNormalized[$norm])) {
                $missing[] = $original;
            }
        }

        return [
            'submitted_partno_count' => count($submittedPartCodes),
            'submitted_partno_preview' => array_slice(array_keys($submittedPartCodes), 0, 80),
            'submitted_td_count' => count($submittedTdItems),
            'submitted_td_preview' => array_slice($submittedTdItems, 0, 80),
            'api_article_count' => $autonetResponseCount,
            'api_returned_partno_count' => count($returnedPartCodes),
            'api_returned_partno_preview' => array_slice(array_keys($returnedPartCodes), 0, 80),
            'api_articles_preview' => array_slice($returnedArticles, 0, 80),
            'missing_partno_count' => count($missing),
            'missing_partno_preview' => array_slice($missing, 0, 120),
        ];
    }

    /**
     * Build Autopartner API products array: searched code + all mainart codes from parts_catalog (code_parts = query).
     */
    private function buildAutopartnerProducts(string $query, array $selectedSuppliers, $catalogRows = null): ?array
    {
        if (!in_array('autopartner', $selectedSuppliers)) {
            return null;
        }

        $rows = $catalogRows ?? $this->partsCatalogLookup->getAllRowsByCode($query);
        $products = [];

        foreach ($rows as $row) {
            $rawMainCode = $row->mainart_code_parts ?? '';
            $apiCodeBase = trim(str_replace(' ', '', $rawMainCode));
            if ($apiCodeBase === '') {
                continue;
            }
            $productCode = $this->autoPartnerService->applyPrefix($row->mainart_brands ?? '', $apiCodeBase);
            $products[$productCode] = ['productCode' => $productCode, 'quantity' => 1];
        }

        $products[$query] = ['productCode' => $query, 'quantity' => 1];
        return array_values($products);
    }

    /**
     * Legacy parity: pre-seed manufacturer/name by normalized mainart code from initial parts_catalog rows.
     */
    private function buildAutopartnerSeedMap($rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $rawMainCode = (string) ($row->mainart_code_parts ?? '');
            if ($rawMainCode === '') {
                continue;
            }
            $normalizedBase = $rawMainCode;
            if (strcasecmp((string) ($row->mainart_brands ?? ''), 'CALORSTAT by Vernet') === 0) {
                $normalizedBase = preg_replace('/^TH/i', '', $normalizedBase);
            }
            $code = preg_replace('/[.\s\-\/|\\\\]+/', '', $normalizedBase);
            if ($code === '') {
                continue;
            }
            if (!isset($map[$code])) {
                $map[$code] = [
                    'manufacturer' => $row->mainart_brands ?? null,
                    'db_name' => $row->mainart_name ?? null,
                    'order_code' => $code,
                ];
            }
        }

        return $map;
    }

    /**
     * Build Autonet API items array: searched code + all parts from parts_catalog (code match + brand in Autonet list).
     */
    private function buildAutonetItems(string $query, string $rawQuery, array $selectedSuppliers, $rows = null): ?array
    {
        if (!in_array('autonet', $selectedSuppliers)) {
            return null;
        }

        $rows = $rows ?? $this->partsCatalogLookup->getRowsForAutonet($query, $rawQuery, self::AUTONET_BRANDS);
        $items = [];
        $seen = [];
        $refNrs = [];
        $refBrands = [];
        $validPairs = [];
        $fallbackBrandIds = [];

        $brandsNeedingId = [];
        foreach ($rows as $row) {
            if (empty($row->brand_id) && !empty($row->mainart_brands)) {
                $brandsNeedingId[] = (string) $row->mainart_brands;
            }
        }
        $brandsNeedingId = array_values(array_unique(array_filter($brandsNeedingId)));
        if (!empty($brandsNeedingId)) {
            $brandIdRows = DB::table('parts_catalog')
                ->select('mainart_brands', 'brand_id')
                ->whereIn('mainart_brands', $brandsNeedingId)
                ->whereNotNull('brand_id')
                ->get();
            foreach ($brandIdRows as $brandIdRow) {
                $brandName = (string) ($brandIdRow->mainart_brands ?? '');
                if ($brandName === '' || isset($fallbackBrandIds[$brandName])) {
                    continue;
                }
                $fallbackBrandIds[$brandName] = $brandIdRow->brand_id;
            }
        }

        foreach ($rows as $row) {
            if (!empty($row->brand_id)) {
                $key = 'td:' . (string) $row->brand_id . '|' . (string) ($row->mainart_code_parts ?? '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $items[] = [
                    'TDBrandId'   => $row->brand_id,
                    'TDArticleNo' => $row->mainart_code_parts,
                    'Quantity'    => 2,
                ];
            } else {
                $brandName = (string) ($row->mainart_brands ?? '');
                $fallbackBrandId = $brandName !== '' ? ($fallbackBrandIds[$brandName] ?? null) : null;
                if (!empty($fallbackBrandId)) {
                    $tdKey = 'td:' . (string) $fallbackBrandId . '|' . (string) ($row->mainart_code_parts ?? '');
                    if (!isset($seen[$tdKey])) {
                        $seen[$tdKey] = true;
                        $items[] = [
                            'TDBrandId'   => $fallbackBrandId,
                            'TDArticleNo' => $row->mainart_code_parts,
                            'Quantity'    => 2,
                        ];
                    }
                }

                $partNo = (string) ($row->mainart_code_parts ?? '');
                if ($partNo !== '') {
                    $key = 'part:' . $partNo;
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $items[] = ['PartNo' => $partNo, 'Quantity' => 2];
                    }
                }

                // Apply Autonet prefix/suffix rules for pooled flow requests.
                $codeForRule = preg_replace('/[.\s\-\/|\\\\]+/', '', $partNo);
                if ($codeForRule !== '') {
                    $mappedPartNo = $this->autonetService->applyPrefix($brandName, $codeForRule);
                    if ($mappedPartNo !== '' && $mappedPartNo !== $partNo) {
                        $mappedKey = 'part:' . $mappedPartNo;
                        if (!isset($seen[$mappedKey])) {
                            $seen[$mappedKey] = true;
                            $items[] = ['PartNo' => $mappedPartNo, 'Quantity' => 2];
                        }
                    }
                }
            }

            // Collect for batched QWP expansion (avoid N+1 queries).
            if (!empty($row->mainart_code_parts) && !empty($row->mainart_brands)) {
                $refNrs[] = (string) $row->mainart_code_parts;
                $refBrands[] = (string) $row->mainart_brands;
                $validPairs[(string) $row->mainart_code_parts . '|' . (string) $row->mainart_brands] = true;
            }
        }

        if (!empty($refNrs) && !empty($refBrands)) {
            $qwpRows = DB::table('autonet_qwp_data')
                ->whereIn('RefNr', array_values(array_unique($refNrs)))
                ->whereIn('ReferenceBrand', array_values(array_unique($refBrands)))
                ->get();

            foreach ($qwpRows as $qRow) {
                $pairKey = (string) ($qRow->RefNr ?? '') . '|' . (string) ($qRow->ReferenceBrand ?? '');
                if (!isset($validPairs[$pairKey])) {
                    continue;
                }
                $artNr = (string) ($qRow->ArtNr ?? '');
                if ($artNr === '') {
                    continue;
                }
                $key = 'part:' . $artNr;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $items[] = ['PartNo' => $artNr, 'Quantity' => 2];
            }
        }

        // Broader fallback: include QWP by RefNr even when ReferenceBrand does not
        // match parts_catalog brand exactly (common for OE mappings).
        $qwpRefCandidates = $this->buildQwpRefCandidates($refNrs, $query);
        if (!empty($qwpRefCandidates)) {
            $qwpRowsByRef = DB::table('autonet_qwp_data')
                ->whereIn('RefNr', $qwpRefCandidates)
                ->get();

            foreach ($qwpRowsByRef as $qRow) {
                $artNr = (string) ($qRow->ArtNr ?? '');
                if ($artNr === '') {
                    continue;
                }
                $key = 'part:' . $artNr;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $items[] = ['PartNo' => $artNr, 'Quantity' => 2];
            }
        }

        $directSearchCode = trim($rawQuery !== '' ? $rawQuery : $query);
        if ($directSearchCode === '') {
            $directSearchCode = $query;
        }

        // Legacy behavior: if no parts_catalog rows, still allow direct QWP code searches.
        if (empty($rows) || (is_object($rows) && method_exists($rows, 'isEmpty') && $rows->isEmpty())) {
            $directCandidates = $this->buildQwpRefCandidates([], $query);
            $qwpDirect = DB::table('autonet_qwp_data')
                ->whereIn('ArtNr', $directCandidates)
                ->orWhereIn('RefNr', $directCandidates)
                ->first();
            if ($qwpDirect) {
                $key = 'part:' . $directSearchCode;
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $items[] = ['PartNo' => $directSearchCode, 'Quantity' => 2];
                }
            }
        }

        $queryKey = 'part:' . $directSearchCode;
        if (!isset($seen[$queryKey])) {
            $seen[$queryKey] = true;
            $items[] = ['PartNo' => $directSearchCode, 'Quantity' => 2];
        }
        return $items;
    }

    /**
     * Build a deterministic map used by Autonet parser to preserve legacy
     * manufacturer/name/order_code selection per normalized article code.
     */
    private function buildAutonetRowMap($rows, string $query = ''): array
    {
        $map = [];
        $refNrs = [];
        $refBrands = [];
        $validPairs = [];
        foreach ($rows as $row) {
            $mainCode = (string) ($row->mainart_code_parts ?? '');
            if ($mainCode === '') {
                continue;
            }
            $normalized = preg_replace('/[.\s\-\/|\\\\]+/', '', $mainCode);
            if ($normalized === '') {
                continue;
            }
            if (!isset($map[$normalized])) {
                $meta = [
                    'manufacturer' => $row->mainart_brands ?? null,
                    'db_name' => $row->mainart_name ?? null,
                    'order_code' => $mainCode,
                ];
                $map[$normalized] = $meta;
                // LEMFÖRDER: Autonet PartNo e.g. 38968LMI → normalizeCode → "38968"; catalog are "38968 01" → "3896801".
                // Fără alias, requestRowMap nu se potrivește și brandul rămâne gol în UI.
                $brandName = (string) ($row->mainart_brands ?? '');
                if ($this->isLemforderCatalogBrand($brandName) && preg_match('/^(\d{4,})01$/', $normalized, $m)) {
                    $shortKey = $m[1];
                    if (!isset($map[$shortKey])) {
                        $map[$shortKey] = $meta;
                    }
                }
            }

            if (!empty($row->mainart_code_parts) && !empty($row->mainart_brands)) {
                $refNrs[] = (string) $row->mainart_code_parts;
                $refBrands[] = (string) $row->mainart_brands;
                $validPairs[(string) $row->mainart_code_parts . '|' . (string) $row->mainart_brands] = true;
            }
        }

        // Seed QWP ArtNr rows in map so parser does not need DB fallback.
        if (!empty($refNrs) && !empty($refBrands)) {
            $qwpRows = DB::table('autonet_qwp_data')
                ->whereIn('RefNr', array_values(array_unique($refNrs)))
                ->whereIn('ReferenceBrand', array_values(array_unique($refBrands)))
                ->get();
            foreach ($qwpRows as $qRow) {
                $pairKey = (string) ($qRow->RefNr ?? '') . '|' . (string) ($qRow->ReferenceBrand ?? '');
                if (!isset($validPairs[$pairKey])) {
                    continue;
                }
                $artNr = (string) ($qRow->ArtNr ?? '');
                if ($artNr === '') {
                    continue;
                }
                $normalizedArt = preg_replace('/[.\s\-\/|\\\\]+/', '', $artNr);
                if ($normalizedArt === '') {
                    continue;
                }
                if (!isset($map[$normalizedArt])) {
                    $map[$normalizedArt] = [
                        'manufacturer' => 'QWP',
                        'db_name' => 'QWP',
                        'order_code' => $artNr,
                    ];
                }
            }
        }

        // Keep parser metadata for QWP rows found by RefNr-only fallback.
        $qwpRefCandidates = $this->buildQwpRefCandidates($refNrs, $query);
        if (!empty($qwpRefCandidates)) {
            $qwpRowsByRef = DB::table('autonet_qwp_data')
                ->whereIn('RefNr', $qwpRefCandidates)
                ->get();

            foreach ($qwpRowsByRef as $qRow) {
                $artNr = (string) ($qRow->ArtNr ?? '');
                if ($artNr === '') {
                    continue;
                }
                $normalizedArt = preg_replace('/[.\s\-\/|\\\\]+/', '', $artNr);
                if ($normalizedArt === '') {
                    continue;
                }
                if (!isset($map[$normalizedArt])) {
                    $map[$normalizedArt] = [
                        'manufacturer' => 'QWP',
                        'db_name' => 'QWP',
                        'order_code' => $artNr,
                    ];
                }
            }
        }

        if (empty($map) && $query !== '') {
            $directCandidates = $this->buildQwpRefCandidates([], $query);
            $qwpDirect = DB::table('autonet_qwp_data')
                ->whereIn('ArtNr', $directCandidates)
                ->orWhereIn('RefNr', $directCandidates)
                ->first();
            if ($qwpDirect) {
                $q = preg_replace('/[.\s\-\/|\\\\]+/', '', $query);
                if ($q !== '') {
                    $map[$q] = [
                        'manufacturer' => 'QWP',
                        'db_name' => 'QWP',
                        'order_code' => $query,
                    ];
                }
            }
        }

        return $map;
    }

    private function isLemforderCatalogBrand(string $brand): bool
    {
        $u = strtoupper(str_replace(['Ö', 'ö'], 'O', $brand));

        return str_contains($u, 'LEMFORDER');
    }

    /**
     * Build robust RefNr candidates for QWP matching.
     * Includes original, normalized and no-trailing-letter variants
     * (e.g. 401604793R -> 401604793).
     *
     * @param array<int, string> $refNrs
     * @return array<int, string>
     */
    private function buildQwpRefCandidates(array $refNrs, string $query): array
    {
        $raw = array_values(array_filter(array_merge($refNrs, [$query]), static function ($v) {
            return is_string($v) && trim($v) !== '';
        }));
        if (empty($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $value) {
            $v = trim($value);
            if ($v === '') {
                continue;
            }
            $out[$v] = true;

            $normalized = preg_replace('/[.\s\-\/|\\\\]+/', '', $v);
            if ($normalized !== '') {
                $out[$normalized] = true;
                $upper = strtoupper($normalized);
                $out[$upper] = true;

                if (preg_match('/^(\d{5,})[A-Z]{1,3}$/', $upper, $m)) {
                    $out[$m[1]] = true;
                }
            }
        }

        return array_keys($out);
    }

    /**
     * Build AutoTotal request list from legacy mapping tables.
     * Requests are deduplicated by itemkey(+sup_brand) and each response is fanned out to all targets.
     *
     * @return array<int, array{
     *   itemkey: string,
     *   quantity: int,
     *   targets: array<int, array{base_code: string, manufacturer: ?string, db_name: ?string, sup_brand: ?string}>
     * }>
     */
    private function buildAutototalRequests(string $query, string $rawQuery, int $maxRequests = 18): array
    {
        $requests = [];
        $requestIndexByKey = [];
        $normalizedQuery = preg_replace('/[.\s\-\/|\\\\]+/', '', $query) ?: $query;

        $addRequest = function (
            string $itemkey,
            string $baseCode,
            ?string $manufacturer = null,
            ?string $dbName = null,
            ?string $supBrand = null
        ) use (&$requests, &$requestIndexByKey): void {
            $itemkey = trim($itemkey);
            if ($itemkey === '') {
                return;
            }
            $baseCode = trim($baseCode);
            if ($baseCode === '') {
                return;
            }
            // Same AutoTotal response is keyed by itemkey, so dedupe strictly by itemkey
            // and fan out to all mapped targets/sup_brands in parser.
            $requestKey = $itemkey;
            if (!isset($requestIndexByKey[$requestKey])) {
                $requestIndexByKey[$requestKey] = count($requests);
                $requests[] = [
                    'itemkey' => $itemkey,
                    // /searching-new default AutoTotal request quantity
                    // should remain 2. Wishlist reload uses directOnly=true
                    // and does not use this request builder.
                    'quantity' => 2,
                    'targets' => [],
                ];
            }
            $idx = $requestIndexByKey[$requestKey];

            $target = [
                'base_code' => $baseCode,
                'manufacturer' => $manufacturer !== null ? trim($manufacturer) : null,
                'db_name' => $dbName,
                'sup_brand' => $supBrand !== null ? trim($supBrand) : null,
            ];
            $targetKey = $target['base_code'] . '|' . (string) $target['sup_brand'];
            if (!isset($requests[$idx]['_target_seen'][$targetKey])) {
                $requests[$idx]['_target_seen'][$targetKey] = true;
                $requests[$idx]['targets'][] = $target;
            }
        };

        // Always keep direct query item as fallback.
        $addRequest($rawQuery !== '' ? $rawQuery : $query, $normalizedQuery);

        $rows = DB::table('parts_catalog')
            ->where('code_parts', $query)
            ->get();

        $candidateCodes = [];
        $originalToBase = [];
        foreach ($rows as $row) {
            $mainart = (string) ($row->mainart_code_parts ?? '');
            if ($mainart === '') {
                continue;
            }
            $baseCode = preg_replace('/[.\s\-\/|\\\\]+/', '', $mainart);
            if ($baseCode === '') {
                continue;
            }
            $manufacturer = (string) ($row->mainart_brands ?? '');
            $originalCode = $mainart;
            if ($manufacturer === 'INA') {
                $originalCode = str_replace(' ', '', $originalCode);
            }

            $candidateCodes[$mainart] = true;
            $candidateCodes[$originalCode] = true;
            $originalToBase[$mainart] = $baseCode;
            $originalToBase[$originalCode] = $baseCode;
        }

        $branduriByCodSursa = [];
        $itemkeyByArticle = [];
        if (!empty($candidateCodes)) {
            $codes = array_keys($candidateCodes);

            $branduriRows = DB::table('autototal_branduri_proprii')
                ->select('cod_sursa', 'itemkey', 'sup_brand')
                ->whereIn('cod_sursa', $codes)
                ->get();
            foreach ($branduriRows as $bpRow) {
                $cod = (string) ($bpRow->cod_sursa ?? '');
                if ($cod === '') {
                    continue;
                }
                $branduriByCodSursa[$cod][] = $bpRow;
            }

            $itemkeyRows = DB::table('autototal_data')
                ->select('art_article_nr', 'itemkey')
                ->whereIn('art_article_nr', $codes)
                ->get();
            foreach ($itemkeyRows as $ikRow) {
                $art = (string) ($ikRow->art_article_nr ?? '');
                $ik = (string) ($ikRow->itemkey ?? '');
                if ($art === '' || $ik === '') {
                    continue;
                }
                $itemkeyByArticle[$art] = $ik;
            }
        }

        foreach ($rows as $row) {
            $mainart = (string) ($row->mainart_code_parts ?? '');
            if ($mainart === '') {
                continue;
            }
            $baseCode = preg_replace('/[.\s\-\/|\\\\]+/', '', $mainart);
            if ($baseCode === '') {
                continue;
            }
            $manufacturer = $row->mainart_brands ?? null;
            $dbName = $row->mainart_name ?? null;

            $originalCode = $mainart;
            if (($manufacturer ?? '') === 'INA') {
                $originalCode = str_replace(' ', '', $originalCode);
            }

            $branduriRows = $branduriByCodSursa[$mainart] ?? [];
            if ($originalCode !== $mainart) {
                $branduriRows = array_merge($branduriRows, $branduriByCodSursa[$originalCode] ?? []);
            }

            foreach ($branduriRows as $bp) {
                $addRequest(
                    (string) ($bp->itemkey ?? ''),
                    $baseCode,
                    $manufacturer,
                    $dbName,
                    isset($bp->sup_brand) ? (string) $bp->sup_brand : null
                );
            }

            $itemkey = $itemkeyByArticle[$mainart] ?? '';
            if ($itemkey === '' && $originalCode !== $mainart) {
                $itemkey = $itemkeyByArticle[$originalCode] ?? '';
            }
            if ($itemkey !== '') {
                $addRequest($itemkey, $baseCode, $manufacturer, $dbName);
            }
        }

        foreach ($requests as &$request) {
            unset($request['_target_seen']);
        }
        unset($request);

        return array_slice($requests, 0, $maxRequests);
    }
}
