<?php

namespace App\Services\SupplierSearchNew;

use Illuminate\Support\Facades\DB;
use App\Services\AutoPartner\AutoPartnerService;

/**
 * Builds enriched request data from DB so the new search returns the same results as the old one.
 * - AutoPartner: multiple products from parts_catalog (with applyPrefix) + query
 * - Autototal: itemkeys from autototal_data + autototal_branduri_proprii
 * - Autonet: items from parts_catalog (brands filter) + autonet_qwp_data, chunked by 50
 */
class SearchEnricher
{
    private const AUTONET_BRANDS = [
        'ABAKUS','Ac Rolcar','AE','ALKAR','Arnott','ASSO','ATE','AUTLOG','AUTOFREN SEINSA',
        'B CAR','BEHR','BERU','BERU by DRiV','BILSTEIN','BorgWarner','BOSAL','BOSCH','BREMBO','BTS Turbo',
        'BUGIAD','CALORSTAT by Vernet','CIFAM','COFLE','CONTINENTAL CTAM','CONTINENTAL CTAM BR',
        'CONTINENTAL-APAC','CONTITECH AIR SPRING','CORTECO','DAYCO','DELPHI','DENSO','ELRING','FACET','FAE','FAG','FAI AutoParts','FEBI BILSTEIN','FERODO','FRECCIA',
        'GARRETT','GATES','GKN','GLYCO','GOETZE','GOETZE ENGINE','GRAF','HC-Cargo','HELLA','HEPU','HERTH+BUSS ELPARTS','HERTH+BUSS JAKOPARTS',
        'HITACHI','INA','KLOKKERHOLM','KNECHT','KOLBENSCHMIDT','KYB','LEMFÖRDER','LESJÖFORS','LÖBRO','LPR',
        'LuK','MAGNETI MARELLI','MAGNETI MARELLI - BR','MAHLE','MANN-FILTER','MEYLE','MOBILETRON','MONROE','MOOG','NGK','NIPPARTS','NISSENS',
        'NRF','NTK','NÜRAL','PIERBURG','QUICK BRAKE','SACHS','SKF','SNR','SPIDAN','STABILUS','TEXTAR',
        'TOPRAN','TRW','TYC','VAICO','VALEO','VDO','VEMO','VICTOR REINZ','WABCO','WAHLER','ZF','ZIMMERMANN',
    ];

    /** Set false to keep pool at 5 requests (~5 sec). True = multiple AT/AN requests (can be 60+ sec). */
    private const ENABLE_MULTIPLE_AUTOTOTAL_AUTONET = false;

    private const MAX_AUTOTOTAL_REQUESTS = 12;
    private const MAX_AUTONET_ITEMS = 50;
    private const AUTONET_CHUNK_SIZE = 50;

    public function __construct(
        protected AutoPartnerService $autoPartnerService
    ) {}

    /**
     * @param bool $forceAutototalEnrich When true, build autototal_requests even if ENABLE_MULTIPLE_AUTOTOTAL_AUTONET is false (for AT-only background request).
     * @return array{ap_products: array, autototal_requests: array, autonet_chunks: array}
     */
    public function enrich(string $query, string $rawQuery, array $selectedSuppliers, bool $forceAutototalEnrich = false): array
    {
        $out = [
            'ap_products' => [],
            'autototal_requests' => [],
            'autonet_chunks' => [],
        ];

        $partsRows = DB::table('parts_catalog')
            ->where('code_parts', $query)
            ->orWhere('code_parts', $rawQuery)
            ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(code_parts, ' ', ''), '-', ''), '/', ''), '|', ''), '\\\\', '') = ?", [$query])
            ->get();

        if (in_array('autopartner', $selectedSuppliers)) {
            $out['ap_products'] = $this->buildApProducts($partsRows, $query);
        }

        $buildAutototal = $forceAutototalEnrich || self::ENABLE_MULTIPLE_AUTOTOTAL_AUTONET;
        if ($buildAutototal && in_array('autototal', $selectedSuppliers)) {
            $out['autototal_requests'] = $this->buildAutototalRequests($partsRows);
        }

        if (self::ENABLE_MULTIPLE_AUTOTOTAL_AUTONET && in_array('autonet', $selectedSuppliers)) {
            $out['autonet_chunks'] = $this->buildAutonetChunks($query, $rawQuery, $partsRows);
        }

        return $out;
    }

    private function buildApProducts($partsRows, string $query): array
    {
        $v2Products = [];
        foreach ($partsRows as $row) {
            $rawMainCode = $row->mainart_code_parts ?? '';
            $apiCodeBase = trim(str_replace(' ', '', $rawMainCode));
            if ($apiCodeBase === '') {
                continue;
            }
            $v2Products[] = [
                'productCode' => $this->autoPartnerService->applyPrefix($row->mainart_brands ?? '', $apiCodeBase),
                'quantity' => 1,
            ];
        }
        $v2Products[] = ['productCode' => $query, 'quantity' => 1];

        $seen = [];
        return array_values(array_filter($v2Products, function ($p) use (&$seen) {
            $key = (string) ($p['productCode'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
            return true;
        }));
    }

    private function buildAutototalRequests($partsRows): array
    {
        $batch = [];
        foreach ($partsRows as $row) {
            $mainart = $row->mainart_code_parts ?? '';
            $originalCode = $mainart;
            if (($row->mainart_brands ?? '') === 'INA') {
                $originalCode = str_replace(' ', '', $originalCode);
            }

            $branduri = $this->getAutototalBranduriProprii($mainart);
            if (!empty($branduri)) {
                foreach ($branduri as $bp) {
                    $batch[] = ['itemkey' => $bp['itemkey'], 'quantity' => 1];
                }
                continue;
            }
            $itemkey = $this->findAutototalItemkey($mainart) ?: $this->findAutototalItemkey($originalCode);
            if ($itemkey !== null && $itemkey !== '') {
                $batch[] = ['itemkey' => $itemkey, 'quantity' => 1];
            }
        }
        return array_slice($batch, 0, self::MAX_AUTOTOTAL_REQUESTS);
    }

    private function getAutototalBranduriProprii(string $codSursa): array
    {
        $codSursa = trim($codSursa);
        if ($codSursa === '') {
            return [];
        }
        $rows = DB::table('autototal_branduri_proprii')
            ->select('itemkey', 'sup_brand')
            ->where('cod_sursa', $codSursa)
            ->get();
        $out = [];
        foreach ($rows as $r) {
            $ik = isset($r->itemkey) ? (is_string($r->itemkey) ? trim($r->itemkey) : (string) $r->itemkey) : '';
            if ($ik !== '') {
                $out[] = ['itemkey' => $ik, 'sup_brand' => isset($r->sup_brand) ? trim((string) $r->sup_brand) : null];
            }
        }
        return $out;
    }

    private function findAutototalItemkey(string $articleNr): ?string
    {
        $articleNr = trim($articleNr);
        if ($articleNr === '') {
            return null;
        }
        $row = DB::table('autototal_data')
            ->select('itemkey')
            ->where('art_article_nr', $articleNr)
            ->first();
        $itemkey = $row->itemkey ?? null;
        return (is_string($itemkey) && trim($itemkey) !== '') || (is_numeric($itemkey) && (string) $itemkey !== '')
            ? (is_string($itemkey) ? trim($itemkey) : (string) $itemkey)
            : null;
    }

    private function buildAutonetChunks(string $query, string $rawQuery, $partsRows): array
    {
        $items = [];
        $rows = collect($partsRows)->isEmpty()
            ? collect()
            : DB::table('parts_catalog')
                ->where(function ($q) use ($query, $rawQuery) {
                    $q->where('code_parts', $query)
                        ->orWhere('code_parts', $rawQuery)
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(code_parts, ' ', ''), '-', ''), '/', ''), '|', ''), '\\\\', '') = ?", [$query]);
                })
                ->whereIn('mainart_brands', self::AUTONET_BRANDS)
                ->get();

        if ($rows->isNotEmpty()) {
            foreach ($rows as $row) {
                if (!empty($row->brand_id)) {
                    $items[] = [
                        'TDBrandId' => $row->brand_id,
                        'TDArticleNo' => $row->mainart_code_parts,
                        'Quantity' => 1,
                    ];
                } else {
                    $items[] = ['PartNo' => $row->mainart_code_parts, 'Quantity' => 1];
                }
            }
            $refNrs = $rows->pluck('mainart_code_parts')->filter()->unique()->values()->all();
            $refBrands = $rows->pluck('mainart_brands')->filter()->unique()->values()->all();
            if (!empty($refNrs) && !empty($refBrands)) {
                $qwpRows = DB::table('autonet_qwp_data')
                    ->whereIn('RefNr', $refNrs)
                    ->whereIn('ReferenceBrand', $refBrands)
                    ->get();
                $validPairs = [];
                foreach ($rows as $row) {
                    $validPairs[($row->mainart_code_parts ?? '') . '|' . ($row->mainart_brands ?? '')] = true;
                }
                foreach ($qwpRows as $qRow) {
                    $key = ($qRow->RefNr ?? '') . '|' . ($qRow->ReferenceBrand ?? '');
                    if (isset($validPairs[$key])) {
                        $items[] = ['PartNo' => $qRow->ArtNr, 'Quantity' => 1];
                    }
                }
            }
        } else {
            $qwp = DB::table('autonet_qwp_data')->where('ArtNr', $query)->orWhere('RefNr', $query)->first();
            if ($qwp) {
                $items[] = ['PartNo' => $query, 'Quantity' => 1];
            }
        }

        $items[] = ['PartNo' => $query, 'Quantity' => 1];
        $seen = [];
        $items = array_values(array_filter($items, function ($item) use (&$seen) {
            if (isset($item['PartNo'])) {
                $key = 'part:' . (string) $item['PartNo'];
            } elseif (isset($item['TDBrandId'], $item['TDArticleNo'])) {
                $key = 'td:' . (string) $item['TDBrandId'] . '|' . (string) $item['TDArticleNo'];
            } else {
                $key = 'other:' . md5(json_encode($item));
            }
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
            return true;
        }));

        $items = array_slice($items, 0, self::MAX_AUTONET_ITEMS);
        $chunks = array_chunk($items, self::AUTONET_CHUNK_SIZE);
        return $chunks;
    }
}
