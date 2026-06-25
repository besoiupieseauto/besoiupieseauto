<?php

namespace App\Services\SupplierSearchNew;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\Materom\MateromService;
use App\Services\Elit\ElitService;
use App\Services\Autonet\AutonetService;
use App\Services\Autototal\AutototalService;
use App\Services\AutoPartner\AutoPartnerService;

class PoolRunner
{
    private const AUTONET_MAX_ITEMS_PER_REQUEST = 50;
    private const AUTOTOTAL_CACHE_TTL_SECONDS = 120;
    private const ENABLE_ELIT_REMOTE_CALL = false;

    public function __construct(
        protected MateromService $materomService,
        protected ElitService $elitService,
        protected AutonetService $autonetService,
        protected AutototalService $autototalService,
        protected AutoPartnerService $autoPartnerService
    ) {}

    /**
     * Run HTTP pool for selected suppliers and query. Returns [ 'supplier_name' => Illuminate\Http\Client\Response, ... ] and total time in seconds.
     * When provided, $autopartnerProducts and $autonetItems are used instead of a single code (additional codes from parts_catalog).
     *
     * @param array<int, array{productCode: string, quantity: int}>|null $autopartnerProducts Products for Autopartner API (includes parts_catalog codes)
     * @param array<int, array{PartNo?: string, TDBrandId?: mixed, TDArticleNo?: string, Quantity: int}>|null $autonetItems Items for Autonet GetDeliveryData
     * @param array<int, array{itemkey: string, quantity: int, base_code?: string, manufacturer?: ?string, db_name?: ?string, sup_brand?: ?string}>|null $autototalRequests
     */
    public function run(
        array $selectedSuppliers,
        string $query,
        int $timeout = 15,
        int $connectTimeout = 5,
        ?array $autopartnerProducts = null,
        ?array $autonetItems = null,
        ?array $autototalRequests = null
    ): array
    {

        $runStart = microtime(true);
        $autototalToken = null;
        if (in_array('autototal', $selectedSuppliers, true)) {
            $autototalToken = $this->autototalService->getAvailabilityToken(null, $timeout, $connectTimeout);
        }

        $apProducts = ($autopartnerProducts !== null && $autopartnerProducts !== [])
            ? $autopartnerProducts
            : [['productCode' => $query, 'quantity' => 1]];
        $anBody = ($autonetItems !== null && $autonetItems !== [])
            ? $autonetItems
            : [['PartNo' => $query, 'Quantity' => 1]];
        $autonetChunks = array_chunk($anBody, self::AUTONET_MAX_ITEMS_PER_REQUEST);

        $start = microtime(true);
        $atRequests = ($autototalRequests !== null && $autototalRequests !== [])
            ? $autototalRequests
            : [['itemkey' => $query, 'quantity' => 1]];
        $cachedAutototal = [];
        $uncachedAtRequests = [];
        $atCacheStart = microtime(true);
        if (in_array('autototal', $selectedSuppliers, true)) {
            foreach ($atRequests as $req) {
                $itemkey = (string) ($req['itemkey'] ?? '');
                $qty = (int) ($req['quantity'] ?? 1);
                if ($itemkey === '') {
                    continue;
                }
                $cacheKey = 'supplier-search-new:autototal:' . md5($itemkey . '|' . $qty);
                $cachedPayload = Cache::get($cacheKey);
                if (is_array($cachedPayload) && !empty($cachedPayload)) {
                    $cachedAutototal[] = [
                        'response' => $cachedPayload,
                        'meta' => $req,
                    ];
                } else {
                    $uncachedAtRequests[] = $req;
                }
            }
        }
        $atCacheSeconds = round(microtime(true) - $atCacheStart, 3);

        $poolCallStart = microtime(true);
        $responses = Http::pool(function ($pool) use ($selectedSuppliers, $query, $apProducts, $anBody, $autonetChunks, $autototalToken, $timeout, $connectTimeout, $uncachedAtRequests) {
            $requests = [];

            if (in_array('materom', $selectedSuppliers, true)) {
                $maUrl = rtrim($this->materomService->getBaseUrl(), '/') . '/v4/part_search/global';
                $maToken = $this->materomService->getSearchToken();
                $requests['materom'] = $pool->as('materom')
                    ->timeout($timeout)
                    ->connectTimeout($connectTimeout)
                    ->withToken($maToken)
                    ->acceptJson()
                    ->get($maUrl, ['term' => $query]);
            }

            if (in_array('autopartner', $selectedSuppliers, true)) {
                $apUrl = rtrim($this->autoPartnerService->getBaseUrl(), '/') . '/ProductsAvailabilityV2';

                $apPayload = array_merge(
                    $this->autoPartnerService->getAuthCredentialsForRequest(),
                    ['products' => $apProducts, 'onlySite' => false]
                );

                $requests['autopartner'] = $pool->as('autopartner')
                    ->timeout($timeout)
                    ->connectTimeout($connectTimeout)
                    ->withHeaders(['Content-Type' => 'application/json', 'Accept' => 'application/json'])
                    ->post($apUrl, $apPayload);
            }

            if (in_array('autonet', $selectedSuppliers, true)) {
                $anUrl = rtrim($this->autonetService->getBaseUrl(), '/') . '/GetDeliveryData';
                $anHeaders = $this->autonetService->getHeadersForSearch();
                if (count($autonetChunks) <= 1) {
                    $requests['autonet'] = $pool->as('autonet')
                        ->timeout($timeout)
                        ->connectTimeout($connectTimeout)
                        ->withHeaders($anHeaders)
                        ->asJson()
                        ->post($anUrl, $anBody);
                } else {
                    foreach ($autonetChunks as $chunkIndex => $chunk) {
                        $alias = 'autonet_chunk_' . $chunkIndex;
                        $requests[$alias] = $pool->as($alias)
                            ->timeout($timeout)
                            ->connectTimeout($connectTimeout)
                            ->withHeaders($anHeaders)
                            ->asJson()
                            ->post($anUrl, $chunk);
                    }
                }
            }

            if (in_array('autototal', $selectedSuppliers, true) && $autototalToken) {
                $atUrl = rtrim($this->autototalService->getAvailabilityBaseUrl(), '/') . '/api/Availability';
                if (count($uncachedAtRequests) <= 1) {
                    $first = $uncachedAtRequests[0] ?? ['itemkey' => $query, 'quantity' => 1];
                    $requests['autototal'] = $pool->as('autototal')
                        ->timeout($timeout)
                        ->connectTimeout($connectTimeout)
                        ->withToken($autototalToken, 'Bearer')
                        ->get($atUrl, ['itemkey' => $first['itemkey'] ?? $query, 'quantity' => (int) ($first['quantity'] ?? 1)]);
                } else {
                    foreach ($uncachedAtRequests as $idx => $atRequest) {
                        $alias = 'autototal_req_' . $idx;
                        $requests[$alias] = $pool->as($alias)
                            ->timeout($timeout)
                            ->connectTimeout($connectTimeout)
                            ->withToken($autototalToken, 'Bearer')
                            ->get($atUrl, [
                                'itemkey' => $atRequest['itemkey'] ?? $query,
                                'quantity' => (int) ($atRequest['quantity'] ?? 1),
                            ]);
                    }
                }
            }

            if (self::ENABLE_ELIT_REMOTE_CALL && in_array('elit', $selectedSuppliers, true)) {
                $elitConfig = $this->elitService->getItemInfoRequestConfig($query, 1);
                $requests['elit'] = $pool->as('elit')
                    ->timeout($timeout)
                    ->connectTimeout($connectTimeout)
                    ->withHeaders($elitConfig['headers'])
                    ->withBody($elitConfig['body'], 'text/xml')
                    ->post($elitConfig['url']);
            }

            return $requests;
        });
		//dd($responses['autopartner']->body());
        $poolCallSeconds = round(microtime(true) - $poolCallStart, 3);

        $autonetMergeStart = microtime(true);
        if (in_array('autonet', $selectedSuppliers, true) && count($autonetChunks) > 1) {
            $mergedAutonetData = [];
            $hasAutonetSuccess = false;

            foreach ($autonetChunks as $chunkIndex => $chunk) {
                $alias = 'autonet_chunk_' . $chunkIndex;
                $chunkResponse = $responses[$alias] ?? null;
                unset($responses[$alias]);

                if ($chunkResponse === null || !$chunkResponse->successful()) {
                    continue;
                }

                $hasAutonetSuccess = true;
                $chunkData = $chunkResponse->json();
                if (!is_array($chunkData)) {
                    continue;
                }

                if (isset($chunkData[0])) {
                    $mergedAutonetData = array_merge($mergedAutonetData, $chunkData);
                } else {
                    $mergedAutonetData[] = $chunkData;
                }
            }

            $responses['autonet'] = new ArrayResponseAdapter($mergedAutonetData, $hasAutonetSuccess);
        }
        $autonetMergeSeconds = round(microtime(true) - $autonetMergeStart, 3);

        $autototalMergeStart = microtime(true);
        if (in_array('autototal', $selectedSuppliers, true) && count($atRequests) > 1) {
            $mergedAutototalData = [];
            $hasAutototalSuccess = !empty($cachedAutototal);

            foreach ($cachedAutototal as $cached) {
                $mergedAutototalData[] = $cached;
            }

            foreach ($uncachedAtRequests as $idx => $atRequest) {
                $alias = 'autototal_req_' . $idx;
                $chunkResponse = $responses[$alias] ?? null;
                unset($responses[$alias]);

                if ($chunkResponse === null || !$chunkResponse->successful()) {
                    continue;
                }

                $hasAutototalSuccess = true;
                $chunkData = $chunkResponse->json();
                if (!is_array($chunkData)) {
                    continue;
                }

                $itemkey = (string) ($atRequest['itemkey'] ?? '');
                $qty = (int) ($atRequest['quantity'] ?? 1);
                if ($itemkey !== '') {
                    $cacheKey = 'supplier-search-new:autototal:' . md5($itemkey . '|' . $qty);
                    Cache::put($cacheKey, $chunkData, now()->addSeconds(self::AUTOTOTAL_CACHE_TTL_SECONDS));
                }

                $mergedAutototalData[] = [
                    'response' => $chunkData,
                    'meta' => $atRequest,
                ];
            }

            $responses['autototal'] = new ArrayResponseAdapter($mergedAutototalData, $hasAutototalSuccess);
        } elseif (in_array('autototal', $selectedSuppliers, true) && count($atRequests) <= 1) {
            // Single-request path: still attach cached response if available and no live response exists.
            if (!isset($responses['autototal']) && !empty($cachedAutototal)) {
                $responses['autototal'] = new ArrayResponseAdapter($cachedAutototal, true);
            } elseif (isset($responses['autototal']) && $responses['autototal']->successful()) {
                $firstReq = $uncachedAtRequests[0] ?? ($atRequests[0] ?? null);
                if (is_array($firstReq)) {
                    $itemkey = (string) ($firstReq['itemkey'] ?? '');
                    $qty = (int) ($firstReq['quantity'] ?? 1);
                    $chunkData = $responses['autototal']->json();
                    if ($itemkey !== '' && is_array($chunkData)) {
                        $cacheKey = 'supplier-search-new:autototal:' . md5($itemkey . '|' . $qty);
                        Cache::put($cacheKey, $chunkData, now()->addSeconds(self::AUTOTOTAL_CACHE_TTL_SECONDS));
                    }
                }
            }
        }
        $autototalMergeSeconds = round(microtime(true) - $autototalMergeStart, 3);

        // Elit parser in new flow is DB-driven; keep parser active without blocking on SOAP call.
        if (in_array('elit', $selectedSuppliers, true) && !self::ENABLE_ELIT_REMOTE_CALL) {
            $responses['elit'] = new ArrayResponseAdapter([], true);
        }

        $totalTime = round(microtime(true) - $start, 3);
        $runnerTotalSeconds = round(microtime(true) - $runStart, 3);
//dd($autonetChunks);

        return [
            'responses' => $responses,
            'total_time' => $totalTime,
            'stats' => [
                'autototal_total_requests' => count($atRequests),
                'autototal_uncached_requests' => count($uncachedAtRequests),
                'autototal_cached_hits' => count($cachedAutototal),
                'autonet_total_items' => count($anBody),
                'autonet_chunk_count' => count($autonetChunks),
                'elit_remote_skipped' => in_array('elit', $selectedSuppliers, true) && !self::ENABLE_ELIT_REMOTE_CALL,
                'timing' => [
                    'autototal_cache_prep' => $atCacheSeconds,
                    'http_pool_wait' => $poolCallSeconds,
                    'autonet_merge' => $autonetMergeSeconds,
                    'autototal_merge' => $autototalMergeSeconds,
                    'runner_total' => $runnerTotalSeconds,
                ],
            ],
        ];
    }
}
