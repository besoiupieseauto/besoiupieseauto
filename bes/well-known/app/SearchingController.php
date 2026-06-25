<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Services\Materom\MateromService;
use App\Services\Elit\ElitService;
use App\Services\Autonet\AutonetService;
use App\Services\Autototal\AutototalService;
use App\Services\AutoPartner\AutoPartnerService;

use App\Models\Tmp;
use App\Models\Comenzi;
use App\Models\Produse;
use App\Models\User;
use App\Models\SupplierSavedCart;
use App\Models\Employee;
use App\Models\SupplierOrder;
use App\Models\Promotion;
use App\Models\MessageTemplate;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Helpers\RotatedPdf;
use Illuminate\Support\Facades\Http;

use Carbon\Carbon;

class SearchingController extends Controller
{
	protected $materomService;
    const ICONV_CHARSET_INPUT = 'UTF-8';
    const ICONV_CHARSET_OUTPUT_A = 'ISO-8859-1//TRANSLIT';
    const ICONV_CHARSET_OUTPUT_B = 'windows-1252//TRANSLIT';
    public $font = 'helvetica';
    public $firstColumnWidth = 70;
    public $columns = 5;
    public $columnSpacing = 0.01;
    public $fontSizeProductDescription = 8;
    public $columnOpacity = 0.06;

    public function __construct(
		MateromService $materomService,
		ElitService $elitService,
		AutonetService $autonetService,
		AutototalService $autototalService,
		AutoPartnerService $autoPartnerService
	)
    {
		$this->materomService = $materomService;
		$this->elitService = $elitService;
		$this->autonetService = $autonetService;
		$this->autototalService = $autototalService;
		$this->autoPartnerService = $autoPartnerService;
    }
	
    public function index()
    {
		$supplierlabels = [
			'materom'     => 'MA',
			'elit'        => 'EL',
			'intercars'   => 'IN',
			'autototal'   => 'AT',
			'autonet'     => 'AN',
			'autopartner' => 'AP',
		];
        return view('searching.index', compact('supplierlabels'));
    }
	
	public function getPromotions()
	{
		try {
			$promotions = Promotion::orderBy('supplier')->orderBy('brand')->get();
			
			return response()->json([
				'success' => true,
				'promotions' => $promotions->map(function($promo) {
					return [
						'id' => $promo->id,
						'supplier' => $promo->supplier,
						'brand' => $promo->brand
					];
				})
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to load promotions'
			], 500);
		}
	}
	
	public function savePromotion(Request $request)
	{
		$request->validate([
			'supplier' => 'required|string|max:50',
			'brand' => 'required|string|max:100'
		]);
		
		try {
			$promotion = Promotion::firstOrCreate(
				[
					'supplier' => strtolower(trim($request->supplier)),
					'brand' => trim($request->brand)
				]
			);
			
			return response()->json([
				'success' => true,
				'message' => 'Promotion saved successfully',
				'promotion' => [
					'id' => $promotion->id,
					'supplier' => $promotion->supplier,
					'brand' => $promotion->brand
				]
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to save promotion: ' . $e->getMessage()
			], 500);
		}
	}
	
	public function deletePromotion($id)
	{
		try {
			$promotion = Promotion::findOrFail($id);
			$promotion->delete();
			
			return response()->json([
				'success' => true,
				'message' => 'Promotion deleted successfully'
			]);
		} catch (\Exception $e) {
			return response()->json([
				'success' => false,
				'message' => 'Failed to delete promotion'
			], 500);
		}
	}
	
	public function searchSuppliers(Request $request)
	{

		$rawQuery = $request->input('query');
		$selectedSuppliers = $request->input('suppliers', []);
		$query = str_replace([' ', '-', '/', '|', '\\'], '', $rawQuery);

		if (!$query) {
			return response()->json([
				'success' => false,
				'message' => 'Query is required'
			], 400);
		}

		try {
			$productsMap = [];
			$originalCodeMap = []; // Maps normalized code => original mainart_code_parts

			/* =========================
			   AUTOPARTNER
			========================= */
			if (in_array('autopartner', $selectedSuppliers)) {
				$rows = DB::table('parts_catalog')
					->select('code_parts', 'mainart_code_parts', 'mainart_brands', 'mainart_name')
					->where('code_parts', $query)
					->get();

				$v2Products = [];

				foreach ($rows as $row) {
					$code = trim(str_replace(' ', '', $row->mainart_code_parts ?? ''));
					$originalCode = $row->mainart_code_parts ?? ''; // Store original unchanged code

					if ($code === '') {
						continue;
					}

					// Map normalized code to original code
					$originalCodeMap[$code] = $originalCode;

					if (!isset($productsMap[$code])) {
						$productsMap[$code] = [
							'mfrpn'        => $code,
							'manufacturer' => $row->mainart_brands,
							'db_name'      => $row->mainart_name ?? null, // 👈 store DB name
							'name'         => null, // resolved later
							'ean'          => null,
							'material'     => null,
							'suppliers'    => [
								'autopartner' => ['variants' => []],
								'materom'     => ['variants' => []],
								'autonet'     => ['variants' => []],
								'autototal'   => ['variants' => []],
								'elit'        => ['variants' => []],
							]
						];
					}
					
					$v2Products[] = [
						'productCode' => $this->autoPartnerService->applyPrefix($row->mainart_brands, $code),
						'quantity' => 1
					];
				}

				// Also add searched code
				$v2Products[] = [
					'productCode' => $query,
					'quantity' => 1
				];

				if (!empty($v2Products)) {
					$apiResponse = $this->autoPartnerService
						->productsAvailabilityV2($v2Products, false);

					$availabilityList =
						$apiResponse['data']['RestProductsAvailabilityV2Result']['Availability']
						?? [];

					// Batch DB lookup: collect all codes from API response and fetch in 1–2 queries
					$codesToLookup = [];
					foreach ($availabilityList as $item) {
						$apiCode = $item['ProductCode'] ?? '';
						$code = $this->autoPartnerService->normalizeCode($apiCode);
						if ($code !== '' && !isset($codesToLookup[$code])) {
							$codesToLookup[$code] = true;
						}
					}
					$codesToLookup = array_keys($codesToLookup);
					$autopartnerDbByCode = [];
					if (!empty($codesToLookup)) {
						$batchRows = DB::table('parts_catalog')
							->select('code_parts', 'mainart_code_parts', 'mainart_brands', 'mainart_name')
							->where(function ($q) use ($codesToLookup) {
								$q->whereIn('code_parts', $codesToLookup)
									->orWhereIn('mainart_code_parts', $codesToLookup);
							})
							->get();
						foreach ($batchRows as $r) {
							$norm = str_replace([' ', '-', '/', '|', '\\'], '', $r->code_parts ?? '');
							if ($norm !== '' && !isset($autopartnerDbByCode[$norm])) {
								$autopartnerDbByCode[$norm] = $r;
							}
							$normMain = str_replace([' ', '-', '/', '|', '\\'], '', $r->mainart_code_parts ?? '');
							if ($normMain !== '' && !isset($autopartnerDbByCode[$normMain])) {
								$autopartnerDbByCode[$normMain] = $r;
							}
						}
						$missingCodes = array_diff($codesToLookup, array_keys($autopartnerDbByCode));
						if (!empty($missingCodes)) {
							foreach ($missingCodes as $code) {
								$dbRow = DB::table('parts_catalog')
									->select('code_parts', 'mainart_code_parts', 'mainart_brands', 'mainart_name')
									->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(code_parts, ' ', ''), '-', ''), '/', ''), '|', ''), '\\\\', '') = ?", [$code])
									->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mainart_code_parts, ' ', ''), '-', ''), '/', ''), '|', ''), '\\\\', '') = ?", [$code])
									->first();
								if ($dbRow) {
									$autopartnerDbByCode[$code] = $dbRow;
								}
							}
						}
					}

					foreach ($availabilityList as $item) {

						$apiCode = $item['ProductCode'] ?? '';
						$code = $this->autoPartnerService->normalizeCode($apiCode); // 🔴 normalize FE prefix

						$dbRow = $autopartnerDbByCode[$code] ?? null;

						if (!isset($productsMap[$code])) {
							// Create entry for searched code if not in DB
							$productsMap[$code] = [
								'mfrpn'        => $code,
								'manufacturer' => $dbRow->mainart_brands ?? null,
								'db_name'      => $dbRow->mainart_name ?? null,
								'name'         => null,
								'ean'          => null,
								'material'     => null,
								'suppliers'    => [
									'autopartner' => ['variants' => []],
									'materom'     => ['variants' => []],
									'autonet'     => ['variants' => []],
									'autototal'   => ['variants' => []],
									'elit'        => ['variants' => []],
								]
							];
							
							// Map normalized code to original code if found in DB
							if ($dbRow) {
								$originalCodeMap[$code] = $dbRow->mainart_code_parts;
							}
						} else {
							// Update manufacturer and db_name if they're null and we can find them in DB
							if ((empty($productsMap[$code]['manufacturer']) || empty($productsMap[$code]['db_name'])) && !isset($originalCodeMap[$code]) && $dbRow) {
								if (empty($productsMap[$code]['manufacturer']) && $dbRow->mainart_brands) {
									$productsMap[$code]['manufacturer'] = $dbRow->mainart_brands;
								}
								if (empty($productsMap[$code]['db_name']) && $dbRow->mainart_name) {
									$productsMap[$code]['db_name'] = $dbRow->mainart_name;
								}
								$originalCodeMap[$code] = $dbRow->mainart_code_parts;
							}
						}

						// ✅ ALWAYS override if API gives name
						if (!empty($item['ProductName'] ?? null)) {
							$productsMap[$code]['name'] = $item['ProductName'];
						} elseif (empty($productsMap[$code]['name']) && !empty($productsMap[$code]['db_name'])) {
							// Use db_name if API doesn't provide name
							$productsMap[$code]['name'] = $productsMap[$code]['db_name'];
						}

						// Only use the first State item for Autopartner
						$states = $item['States'] ?? [];
						if (!empty($states)) {
							$state = $states[0]; // Get first state only
							$DepartmentCode = $state['DepartmentCode'] ?? '';
							if($DepartmentCode == "CN"){
								$DepartmentCode = "maine 8:00";
							}elseif($DepartmentCode == "120" || $DepartmentCode == "72"){
								$DepartmentCode = "poimaine 8:00";
							}
						
							// Use original code from mainart_code_parts if available, otherwise use apiCode
							$orderCode = $originalCodeMap[$code] ?? $apiCode;
							
							$productsMap[$code]['suppliers']['autopartner']['variants'][] = [
								'supplier_stock'  => (int) ($state['InStock'] ?? 0),
								'price'           => (float) ($item['Price'] ?? 0),
								'currency'        => $item['CurrencyCode'] ?? 'RON',
								'departamentCode' => $state['DepartmentCode'] ?? '',
								'order_code'      => $orderCode,
								'depot'           => $DepartmentCode,
								'is_blocked'      => (bool) ($item['IsBlocked'] ?? false),
								'delivery'        => [
									'info_text'  => '',
									'plant_name' => null
								],
								'deposit_included' => $item['DepositIncluded'] ?? false,
								'deposit_price'    => $item['DepositPrice'] ?? 0,
								'possible_return'  => $item['PossibleReturn'] ?? true,
								'multiple_qty'     => $item['MultipleQty'] ?? 1,
								'PossibleReturn'     => (bool) ($item['PossibleReturn'] ?? false)
							];
						}
					}
				}
			}

			/* =========================
			   MATEROM
			========================= */
			if (in_array('materom', $selectedSuppliers)) {

				$response = $this->materomService->partSearchV4($query);
				$materomProducts = $response['body'] ?? [];

				foreach ($materomProducts as $product) {
					$code = $product['mfrpn'] ?? '';

					if ($code === '') {
						continue;
					}

					if (!isset($productsMap[$code])) {
						$productsMap[$code] = [
							'mfrpn'        => $code,
							'manufacturer' => $product['manufacturer'] ?? null,
							'db_name'      => null,
							'name'         => null,
							'ean'          => $product['ean'] ?? null,
							'material'     => $product['material'] ?? null,
							'suppliers'    => [
								'autopartner' => ['variants' => []],
								'materom'     => ['variants' => []],
								'autonet'     => ['variants' => []],
								'autototal'   => ['variants' => []],
								'elit'        => ['variants' => []],
							]
						];
					}

					// Materom name only if API didn't set it
					if (empty($productsMap[$code]['name']) && !empty($product['name'])) {
						$productsMap[$code]['name'] = $product['name'];
					}

					// ✅ FILTER OUT RESEALED VARIANTS
					$variants = $product['pricingVariants'] ?? [];
					$variants = array_filter($variants, function($v) {
						return empty($v['is_resealed']) || $v['is_resealed'] != 1;
					});

					$productsMap[$code]['suppliers']['materom']['variants'] = array_values($variants);
				}
			}
			
			
			/* =========================
			   AUTONET
			========================= */
			if (in_array('autonet', $selectedSuppliers)) {
				$brands = [
					'ABAKUS','Ac Rolcar','AE','ALKAR','Arnott','ASSO','ATE','AUTLOG','AUTOFREN SEINSA',
					'B CAR','BEHR','BERU','BERU by DRiV','BILSTEIN','BorgWarner','BOSAL','BOSCH','BREMBO','BTS Turbo',
					'BUGIAD','CALORSTAT by Vernet','CIFAM','COFLE','CONTINENTAL CTAM','CONTINENTAL CTAM BR',
					'CONTINENTAL-APAC','CONTITECH AIR SPRING','CORTECO','DAYCO','DELPHI','DENSO','ELRING','FACET','FAE','FAG','FAI AutoParts','FEBI BILSTEIN','FERODO','FRECCIA',
					'GARRETT','GATES','GKN','GLYCO','GOETZE','GOETZE ENGINE','GRAF','HC-Cargo','HELLA','HEPU','HERTH+BUSS ELPARTS','HERTH+BUSS JAKOPARTS',
					'HITACHI','INA','KLOKKERHOLM','KNECHT','KOLBENSCHMIDT','KYB','LEMFÖRDER','LESJÖFORS','LÖBRO','LPR',
					'LuK','MAGNETI MARELLI','MAGNETI MARELLI - BR','MAHLE','MANN-FILTER','MEYLE','MOBILETRON','MONROE','MOOG','NGK','NIPPARTS','NISSENS',
					'NRF','NTK','NÜRAL','PIERBURG','QUICK BRAKE','SACHS','SKF','SNR','SPIDAN','STABILUS','TEXTAR',
					'TOPRAN','TRW','TYC','VAICO','VALEO','VDO','VEMO','VICTOR REINZ','WABCO','WAHLER','ZF','ZIMMERMANN'
				];
				// 1️⃣ Fetch matching parts from DB (only needed columns)
				$rows = DB::table('parts_catalog')
					->select('code_parts', 'mainart_code_parts', 'mainart_brands', 'mainart_name', 'brand_id')
					->where('code_parts', $query)
					->whereIn('mainart_brands', $brands)
					->get();

				// ⛔ Do NOT return — just skip AutoNet
				if ($rows->isEmpty()) {
					// nothing for autonet
				} else {

					// 2️⃣ Prepare items for AutoNet API
					$items  = [];
					$rowMap = []; // baseCode => DB row

					foreach ($rows as $row) {
						$baseCode = $row->mainart_code_parts;
						$normalizedCode = str_replace([' ', '-', '/', '|', '\\'], '', $baseCode);
						
						// Map normalized code to original code
						$originalCodeMap[$normalizedCode] = $baseCode;

						if(!empty($row->brand_id)){
							$items[] = [
								'TDBrandId'   => $row->brand_id,
								'TDArticleNo'   => $row->mainart_code_parts,
								'Quantity' => 1
							];
						}else{
							$items[] = [
								'PartNo'   => $row->mainart_code_parts,
								'Quantity' => 1
							];
						}

						$rowMap[$baseCode] = $row;
					}

					// Also add searched code
					$items[] = [
						'PartNo'   => $query,
						'Quantity' => 1
					];

					// 3️⃣ Split into chunks of 50 (AutoNet limit)
					$itemChunks = array_chunk($items, 50);

					foreach ($itemChunks as $chunk) {

						$response = $this->autonetService->getDeliveryData($chunk);
						//echo "<pre>";print_R($response);

						// Skip failed chunks, don't kill whole flow
						if (empty($response) || !is_array($response)) {
							continue;
						}

						// Batch DB lookup for this chunk: collect normalized codes from response
						$chunkNormalizedCodes = [];
						$chunkNormalizedBaseCodes = [];
						foreach ($response as $article) {
							if (empty($article['PartNo'])) continue;
							$mappedPartNo = $this->autonetService->mapAutonetLemforderPartNoToCatalogStyle(
								trim((string) $article['PartNo'])
							);
							$normalizedCode = $this->autonetService->normalizeCode($mappedPartNo);
							$normalizedBaseCode = str_replace([' ', '-', '/', '|', '\\'], '', $normalizedCode);
							$chunkNormalizedCodes[$normalizedBaseCode] = $normalizedCode;
							$chunkNormalizedBaseCodes[$normalizedBaseCode] = true;
						}
						$chunkNormalizedBaseCodes = array_keys($chunkNormalizedBaseCodes);
						$autonetDbByNormalized = [];
						if (!empty($chunkNormalizedBaseCodes)) {
							$batchAutonetRows = DB::table('parts_catalog')
								->select('code_parts', 'mainart_code_parts', 'mainart_brands', 'mainart_name', 'brand_id')
								->where(function ($q) use ($chunkNormalizedBaseCodes, $chunkNormalizedCodes) {
									$q->whereIn('code_parts', $chunkNormalizedBaseCodes)
										->orWhereIn('mainart_code_parts', $chunkNormalizedBaseCodes)
										->orWhereIn('code_parts', array_values($chunkNormalizedCodes))
										->orWhereIn('mainart_code_parts', array_values($chunkNormalizedCodes));
								})
								->get();
							foreach ($batchAutonetRows as $r) {
								$norm = str_replace([' ', '-', '/', '|', '\\'], '', $r->code_parts ?? '');
								if ($norm !== '' && !isset($autonetDbByNormalized[$norm])) {
									$autonetDbByNormalized[$norm] = $r;
								}
								$normMain = str_replace([' ', '-', '/', '|', '\\'], '', $r->mainart_code_parts ?? '');
								if ($normMain !== '' && !isset($autonetDbByNormalized[$normMain])) {
									$autonetDbByNormalized[$normMain] = $r;
								}
							}
						}

						// 4️⃣ EACH response item is a MAIN PART
						foreach ($response as $article) {

							if (empty($article['PartNo'])) {
								continue;
							}

							$apiPartNo = $this->autonetService->mapAutonetLemforderPartNoToCatalogStyle(
								trim((string) $article['PartNo'])
							);

							// Normalize the API code to remove prefixes/suffixes using AutonetService
							$normalizedCode = $this->autonetService->normalizeCode($apiPartNo);
							// Also normalize by removing spaces/dashes for matching
							$normalizedBaseCode = str_replace([' ', '-', '/', '|', '\\'], '', $normalizedCode);
							
							// Try to find row from rowMap first (by original API code, then by normalized code)
							$row = $rowMap[$apiPartNo] ?? null;
							if (!$row) {
								// Try to find by normalized code in rowMap
								foreach ($rowMap as $mapKey => $mapRow) {
									$mapNormalized = str_replace([' ', '-', '/', '|', '\\'], '', $mapKey);
									if ($mapNormalized === $normalizedBaseCode) {
										$row = $mapRow;
										break;
									}
								}
							}
							// Use batch-fetched map before hitting DB again
							if (!$row) {
								$row = $autonetDbByNormalized[$normalizedBaseCode] ?? null;
								if ($row) {
									$originalCodeMap[$normalizedBaseCode] = $row->mainart_code_parts;
								}
							}
							
							// If not found, look up in database (single fallback for REPLACE / LIKE)
							if (!$row) {
								$dbRow = DB::table('parts_catalog')
									->select('code_parts', 'mainart_code_parts', 'mainart_brands', 'mainart_name', 'brand_id')
									->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(code_parts, ' ', ''), '-', ''), '/', ''), '|', ''), '\\\\', '') = ?", [$normalizedBaseCode])
									->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mainart_code_parts, ' ', ''), '-', ''), '/', ''), '|', ''), '\\\\', '') = ?", [$normalizedBaseCode])
									->first();
								
								if ($dbRow) {
									$row = $dbRow;
									$originalCodeMap[$normalizedBaseCode] = $dbRow->mainart_code_parts;
								} else {
									// If direct match fails, query a subset and compare normalized codes
									$searchPrefix = substr($normalizedBaseCode, 0, min(5, strlen($normalizedBaseCode)));
									$dbRows = DB::table('parts_catalog')
										->select('code_parts', 'mainart_code_parts', 'mainart_brands', 'mainart_name', 'brand_id')
										->where('code_parts', 'LIKE', $searchPrefix . '%')
										->orWhere('mainart_code_parts', 'LIKE', $searchPrefix . '%')
										->limit(200)
										->get();
									
									foreach ($dbRows as $dbRow) {
										$dbNormalizedCode = $this->autonetService->normalizeCode($dbRow->mainart_code_parts ?? '');
										$dbNormalizedCode = str_replace([' ', '-', '/', '|', '\\'], '', $dbNormalizedCode);
										if ($dbNormalizedCode === $normalizedBaseCode) {
											$row = $dbRow;
											$originalCodeMap[$normalizedBaseCode] = $dbRow->mainart_code_parts;
											break;
										}
									}
								}
							}

							// Skip if we couldn't find a matching row
							if (!$row) {
								continue;
							}

							if (!isset($productsMap[$normalizedBaseCode])) {
								$productsMap[$normalizedBaseCode] = [
									'mfrpn'         => $normalizedBaseCode,
									'manufacturer' => $row->mainart_brands ?? null,
									'db_name'       => $row->mainart_name ?? null,
									'name'          => null,
									'ean'           => null,
									'material'      => null,
									'suppliers'     => [
										'autopartner' => ['variants' => []],
										'materom'     => ['variants' => []],
										'autonet'     => ['variants' => []],
										'autototal'   => ['variants' => []],
										'elit'        => ['variants' => []],
									]
								];
							} else {
								// Update manufacturer if it's null and we found it
								if (empty($productsMap[$normalizedBaseCode]['manufacturer']) && $row && $row->mainart_brands) {
									$productsMap[$normalizedBaseCode]['manufacturer'] = $row->mainart_brands;
								}
							}

							// 5️⃣ DeliveryData = variants
							// Use original code from mainart_code_parts if available, otherwise use normalized code
							$orderCode = $originalCodeMap[$normalizedBaseCode] ?? $normalizedCode;
							
							// If DeliveryData is empty but we have price info, create a variant from main article data
							$deliveryData = $article['DeliveryData'] ?? [];
							if (empty($deliveryData) && isset($article['PriceWoVat']) && $article['PriceWoVat'] > 0) {
								// Create a single variant from the main article data
								$productsMap[$normalizedBaseCode]['suppliers']['autonet']['variants'][] = [
									'supplier_stock' => 0, // Unknown stock when DeliveryData is empty
									'price'          => (float) ($article['PriceWoVat'] ?? 0),
									'currency'       => $article['Currency'] ?? 'RON',
									'order_code'     => $orderCode,
									'depot'          => '',
									'is_blocked'     => false,
									'delivery'       => [
										'info_text'  => '',
										'plant_name' => null
									],
									'multiple_qty'   => 1
								];
							} else {
								// Process DeliveryData as variants
								foreach ($deliveryData as $delivery) {
									$productsMap[$normalizedBaseCode]['suppliers']['autonet']['variants'][] = [
										'supplier_stock' => (int) ($delivery['Quantity'] ?? 0),
										'price'          => (float) ($article['PriceWoVat'] ?? 0),
										'currency'       => $article['Currency'] ?? 'RON',
										'order_code'     => $orderCode,
										'depot'          => $delivery['Code'] ?? '',
										'is_blocked'     => false,
										'delivery'       => [
											'info_text'  => $delivery['DeliveryDate'] ?? '',
											'plant_name' => null
										],
										'multiple_qty'   => 1
									];
								}
							}
						}
					}//die('asd');
				}
			}
			
			
			/* =========================
			   AUTOTOTAL
			========================= */
			if (in_array('autototal', $selectedSuppliers)) {
				$seenAutoTotalVariants = [];
				$rows = DB::table('parts_catalog')
					->select('code_parts', 'mainart_code_parts', 'mainart_brands', 'mainart_name')
					->where('code_parts', $query)
					->get();

				// Also search with searched code directly
				$response = $this->autototalService->checkAvailability($query, 1);
				if (
					!empty($response['webApiResponse']['status']) &&
					$response['webApiResponse']['status'] == 1
				) {
					$baseCode = $query;
					if (!isset($productsMap[$baseCode])) {
						$productsMap[$baseCode] = [
							'mfrpn'         => $baseCode,
							'manufacturer' => $response['searchCode']['manufacturer'] ?? null,
							'db_name'       => null,
							'name'          => $response['searchCode']['name'] ?? null,
							'ean'           => null,
							'material'      => null,
							'suppliers'     => [
								'autopartner' => ['variants' => []],
								'materom'     => ['variants' => []],
								'autonet'     => ['variants' => []],
								'autototal'   => ['variants' => []],
								'elit'        => ['variants' => []],
							]
						];
					} else {
						// Update name and manufacturer if they're null and API provides them
						if (empty($productsMap[$baseCode]['name']) && !empty($response['searchCode']['name'])) {
							$productsMap[$baseCode]['name'] = $response['searchCode']['name'];
						}
						if (empty($productsMap[$baseCode]['manufacturer']) && !empty($response['searchCode']['manufacturer'])) {
							$productsMap[$baseCode]['manufacturer'] = trim($response['searchCode']['manufacturer']);
						}
					}
					$availabilityInfoText = 'Verifica stoc';
					if (!empty($response['searchCode']['availability'][0]['arrivesAt'])) {
						$availabilityInfoText = $response['searchCode']['availability'][0]['arrivesAt'];
					}
					if (
						!empty($response['searchCode']['supplierCode']) &&
						(($response['searchCode']['status'] ?? 'In stoc') === 'In stoc') &&
						$availabilityInfoText !== 'Verifica stoc'
					) {
						$orderCode = $response['searchCode']['supplierCode'];
						// For codes not in DB, use original API code as order_code
						$finalOrderCode = $orderCode;
						$price = (float) ($response['searchCode']['price'] ?? 0);
						$uniqueKey = $orderCode . '|' . $price;
						if (!isset($seenAutoTotalVariants[$baseCode][$uniqueKey])) {
							$seenAutoTotalVariants[$baseCode][$uniqueKey] = true;
							$productsMap[$baseCode]['suppliers']['autototal']['variants'][] = [
								'supplier_stock' => 1,
								'price'          => $price,
								'currency'       => 'RON',
								'order_code'     => $finalOrderCode,
								'depot'          => null,
								'is_blocked'     => ($response['searchCode']['blockedOnReturn'] ?? 'NU') === 'DA',
								'delivery'       => [
									'info_text'  => $availabilityInfoText,
									'plant_name' => $response['searchCode']['availability'][0]['warehouse'] ?? null
								],
								'multiple_qty'   => (int) ($response['searchCode']['minQtyOrd'] ?? 1),
								'blockedOnReturn'=> $response['searchCode']['blockedOnReturn'] ?? '',
								'name'           => $response['searchCode']['name'] ?? null,
								'manufacturer'  => $response['searchCode']['manufacturer'] ?? null,
								'exchangePart'  => $response['searchCode']['exchangePart'] ?? '',
								'priceEP'       => (float) ($response['searchCode']['priceEP'] ?? 0),
								'is_main_result' => true
							];
						}
					}
					
					/* =========================
					   CROSS REFERENCES (for codes not in DB)
					========================= */
					if (!empty($response['searchCode']['crossReference']) && $availabilityInfoText !== 'Verifica stoc') {
						foreach ($response['searchCode']['crossReference'] as $cross) {
							if (($cross['status'] ?? '') !== 'In stoc') {
								continue;
							}

							$orderCode = $cross['supplierCode'] ?? '';
							if ($orderCode === '') {
								continue;
							}

							// For cross-references, use original supplierCode as order_code
							$finalOrderCode = $orderCode;
							$uniqueKey = $orderCode . '|' . ($cross['price'] ?? 0);

							if (isset($seenAutoTotalVariants[$baseCode][$uniqueKey])) {
								continue;
							}

							$seenAutoTotalVariants[$baseCode][$uniqueKey] = true;

							$productsMap[$baseCode]['suppliers']['autototal']['variants'][] = [
								'supplier_stock' => 1,
								'price'          => (float) ($cross['price'] ?? 0),
								'currency'       => 'RON',
								'order_code'     => $finalOrderCode,
								'depot'          => null,
								'is_blocked'     => ($cross['blockedOnReturn'] ?? 'NU') === 'DA',
								'delivery'       => [
									'info_text'  => $availabilityInfoText,
									'plant_name' => $response['searchCode']['availability'][0]['warehouse'] ?? null
								],
								'multiple_qty'   => 1,
								'blockedOnReturn'=> $cross['blockedOnReturn'] ?? '',
								'name'           => $cross['name'] ?? null,
								'manufacturer'  => $cross['manufacturer'] ?? null,
								'exchangePart'  => $response['searchCode']['exchangePart'] ?? '',
								'priceEP'       => (float) ($response['searchCode']['priceEP'] ?? 0),
								'is_main_result' => false
							];
						}
					}
				}

				if ($rows->isEmpty()) {
					// nothing to do
				} else {
					// Batch resolve itemkeys from autototal_data (one query instead of N)
					$articleNrs = $rows->pluck('mainart_code_parts')->filter()->unique()->values()->all();
					$autototalItemkeyMap = [];
					if (!empty($articleNrs)) {
						$atRows = DB::table('autototal_data')
							->select('art_article_nr', 'itemkey')
							->whereIn('art_article_nr', $articleNrs)
							->get();
						foreach ($atRows as $at) {
							$nr = trim($at->art_article_nr ?? '');
							$key = is_string($at->itemkey) ? trim($at->itemkey) : (is_numeric($at->itemkey) ? (string) $at->itemkey : '');
							if ($nr !== '' && $key !== '') {
								$autototalItemkeyMap[$nr] = $key;
								$norm = str_replace([' ', '-', '/', '|', '\\'], '', $nr);
								if ($norm !== '' && !isset($autototalItemkeyMap[$norm])) {
									$autototalItemkeyMap[$norm] = $key;
								}
							}
						}
					}

					foreach ($rows as $row) {
						$baseCode = str_replace([' ', '-', '/', '|', '\\'], '', $row->mainart_code_parts);
						$originalCode = $row->mainart_code_parts;
						if ($row->mainart_brands === 'INA') {
							$originalCode = str_replace(' ', '', $originalCode);
						}
						
						// Map normalized code to original code
						$originalCodeMap[$baseCode] = $originalCode;

						// AutoTotal: use only itemkey from autototal_data (batch map or fallback to single lookup)
						$itemkey = $autototalItemkeyMap[$row->mainart_code_parts ?? ''] ?? $autototalItemkeyMap[$baseCode] ?? null;
						if (!$itemkey) {
							$itemkey = $this->findAutototalItemkey($row->mainart_code_parts ?? '');
						}
						if (!$itemkey && $originalCode !== ($row->mainart_code_parts ?? '')) {
							$itemkey = $autototalItemkeyMap[$originalCode] ?? $this->findAutototalItemkey($originalCode);
						}
						if (!$itemkey) {
							continue; // no autototal_data row for this part; do not call API with mainart_code_parts
						}

						if (!isset($productsMap[$baseCode])) {
							$productsMap[$baseCode] = [
								'mfrpn'         => $baseCode,
								'manufacturer' => $row->mainart_brands ?? null,
								'db_name'       => $row->mainart_name ?? null,
								'name'          => null,
								'ean'           => null,
								'material'      => null,
								'suppliers'     => [
									'autopartner' => ['variants' => []],
									'materom'     => ['variants' => []],
									'autonet'     => ['variants' => []],
									'autototal'   => ['variants' => []],
									'elit'        => ['variants' => []],
								]
							];
						}

						$response = $this->autototalService
							->checkAvailability($itemkey, 1);

						if (
							empty($response['webApiResponse']['status']) ||
							$response['webApiResponse']['status'] != 1
						) {
							continue;
						}

						$availabilityInfoText = 'Verifica stoc';
						if (!empty($response['searchCode']['availability'][0]['arrivesAt'])) {
							$availabilityInfoText = $response['searchCode']['availability'][0]['arrivesAt'];
						}

						/* =========================
						   MAIN SEARCH RESULT
						========================= */
						if (
							!empty($response['searchCode']['supplierCode']) &&
							(($response['searchCode']['status'] ?? 'In stoc') === 'In stoc') &&
							$availabilityInfoText !== 'Verifica stoc'
						) {
							$orderCode = $response['searchCode']['supplierCode'];
							$normalizedOrderCode = $this->autototalService->normalizeCode($orderCode);
							
							// Use original code from mainart_code_parts if available, otherwise use normalizedOrderCode
							$finalOrderCode = $originalCodeMap[$baseCode] ?? $normalizedOrderCode;
							$variantCodeForOrder = $itemkey ?: $orderCode;
							
							$price     = (float) ($response['searchCode']['price'] ?? 0);
							$uniqueKey = $orderCode . '|' . $price;

							if (!isset($seenAutoTotalVariants[$baseCode][$uniqueKey])) {
								$seenAutoTotalVariants[$baseCode][$uniqueKey] = true;

								$productsMap[$baseCode]['suppliers']['autototal']['variants'][] = [
									'supplier_stock' => 1,
									'price'          => $price,
									'currency'       => 'RON',
									'order_code'     => $finalOrderCode,
									'variant_code'   => $variantCodeForOrder,
									'depot'          => null,
									'is_blocked'     => ($response['searchCode']['blockedOnReturn'] ?? 'NU') === 'DA',
									'delivery'       => [
										'info_text'  => $availabilityInfoText,
										'plant_name' => $response['searchCode']['availability'][0]['warehouse'] ?? null
									],
									'multiple_qty'   => (int) ($response['searchCode']['minQtyOrd'] ?? 1),
									'blockedOnReturn'=> $response['searchCode']['blockedOnReturn'] ?? '',
									'name'           => $response['searchCode']['name'] ?? null,
									'manufacturer'  => $response['searchCode']['manufacturer'] ?? null,
									'exchangePart'  => $response['searchCode']['exchangePart'] ?? '',
									'priceEP'       => (float) ($response['searchCode']['priceEP'] ?? 0),
									'is_main_result' => true
								];
							}
						}

						/* =========================
						   CROSS REFERENCES
						========================= */
						if (!empty($response['searchCode']['crossReference']) && $availabilityInfoText !== 'Verifica stoc') {
							foreach ($response['searchCode']['crossReference'] as $cross) {
								if (($cross['status'] ?? '') !== 'In stoc') {
									continue;
								}

								$orderCode = $cross['supplierCode'] ?? '';
								if ($orderCode === '') {
									continue;
								}

								// For cross-references, use original supplierCode as order_code
								// (cross-references are different codes, so we use the API code directly)
								$finalOrderCode = $orderCode;
								$variantCodeForOrder = $this->findAutototalItemkey($orderCode) ?: $orderCode;
								
								$uniqueKey = $orderCode . '|' . ($cross['price'] ?? 0);

								if (isset($seenAutoTotalVariants[$baseCode][$uniqueKey])) {
									continue;
								}

								$seenAutoTotalVariants[$baseCode][$uniqueKey] = true;

								$productsMap[$baseCode]['suppliers']['autototal']['variants'][] = [
									'supplier_stock' => 1,
									'price'          => (float) ($cross['price'] ?? 0),
									'currency'       => 'RON',
									'order_code'     => $finalOrderCode,
									'variant_code'   => $variantCodeForOrder,
									'depot'          => null,
									'is_blocked'     => ($cross['blockedOnReturn'] ?? 'NU') === 'DA',
									'delivery'       => [
										'info_text'  => $availabilityInfoText,
										'plant_name' => $response['searchCode']['availability'][0]['warehouse'] ?? null
									],
									'multiple_qty'   => 1,
									'blockedOnReturn'=> $cross['blockedOnReturn'] ?? '',
									'name'           => $cross['name'] ?? null,
									'manufacturer'  => $cross['manufacturer'] ?? null,
									'exchangePart'  => $response['searchCode']['exchangePart'] ?? '',
									'priceEP'       => (float) ($response['searchCode']['priceEP'] ?? 0),
									'is_main_result' => false
								];
							}
						}
					}
				}
			}
			
			
			/* =========================
			   ELIT
			========================= */
			if (in_array('elit', $selectedSuppliers)) {

				// Also search with searched code directly
				$elitRows = DB::table('lkq_prices')
					->where('supplier_catalog_nr', $query)
					->get();

				if (!$elitRows->isEmpty()) {
					$baseCode = $query;
					if (!isset($productsMap[$baseCode])) {
						$productsMap[$baseCode] = [
							'mfrpn'        => $baseCode,
							'manufacturer' => null,
							'db_name'      => null,
							'name'         => null,
							'ean'          => null,
							'material'     => null,
							'suppliers'    => [
								'autopartner' => ['variants' => []],
								'materom'     => ['variants' => []],
								'autonet'     => ['variants' => []],
								'autototal'   => ['variants' => []],
								'elit'        => ['variants' => []],
							]
						];
					}
					foreach ($elitRows as $elitRow) {
						$productsMap[$baseCode]['suppliers']['elit']['variants'][] = [
							'supplier_stock' => 1,
							'price'          => (float) ($elitRow->net_price ?? 0),
							'currency'       => 'RON',
							'order_code'     => $elitRow->item_nr ?? '',
							'depot'          => null,
							'is_blocked'     => false,
							'delivery'       => [
								'info_text'  => 'Verifica stoc',
								'plant_name' => null
							],
							'multiple_qty'   => 1
						];
					}
				}

				$rows = DB::table('parts_catalog')
					->select('code_parts', 'mainart_code_parts', 'mainart_brands', 'mainart_name')
					->where('code_parts', $query)
					->get();

				if (!$rows->isEmpty()) {

					// Batch fetch lkq_prices for all (supplier_catalog_nr, brand_name) pairs (one query instead of N)
					$elitPairs = [];
					$elitPairsSeen = [];
					foreach ($rows as $r) {
						$c = $r->mainart_code_parts ?? '';
						$b = $r->mainart_brands ?? '';
						$k = $c . '|' . $b;
						if (!isset($elitPairsSeen[$k])) {
							$elitPairsSeen[$k] = true;
							$elitPairs[] = ['catalog' => $c, 'brand' => $b];
						}
					}
					$elitPricesByKey = [];
					if (!empty($elitPairs)) {
						$lkqQuery = DB::table('lkq_prices')->select('supplier_catalog_nr', 'brand_name', 'net_price', 'item_nr');
						$lkqQuery->where(function ($q) use ($elitPairs) {
							foreach ($elitPairs as $p) {
								if ($p['catalog'] !== '' || $p['brand'] !== '') {
									$q->orWhere(function ($q2) use ($p) {
									$q2->where('supplier_catalog_nr', $p['catalog'])->where('brand_name', $p['brand']);
									});
								}
							}
						});
						$allLkq = $lkqQuery->get();
						foreach ($allLkq as $elitRow) {
							$key = ($elitRow->supplier_catalog_nr ?? '') . '|' . ($elitRow->brand_name ?? '');
							if (!isset($elitPricesByKey[$key])) {
								$elitPricesByKey[$key] = [];
							}
							$elitPricesByKey[$key][] = $elitRow;
						}
					}

					foreach ($rows as $row) {
						$baseCode = str_replace([' ', '-', '/', '|', '\\'], '', $row->mainart_code_parts);
						$originalCode = $row->mainart_code_parts; // Store original unchanged code
						
						// Map normalized code to original code
						$originalCodeMap[$baseCode] = $originalCode;

						if (!isset($productsMap[$baseCode])) {
							$productsMap[$baseCode] = [
								'mfrpn'        => $baseCode,
								'manufacturer' => $row->mainart_brands ?? null,
								'db_name'      => $row->mainart_name ?? null,
								'name'         => null,
								'ean'          => null,
								'material'     => null,
								'suppliers'    => [
									'autopartner' => ['variants' => []],
									'materom'     => ['variants' => []],
									'autonet'     => ['variants' => []],
									'autototal'   => ['variants' => []],
									'elit'        => ['variants' => []],
								]
							];
						}

						// Use pre-fetched lkq_prices for this (catalog, brand)
						$elitKey = ($row->mainart_code_parts ?? '') . '|' . ($row->mainart_brands ?? '');
						$elitRows = $elitPricesByKey[$elitKey] ?? [];

						// Use original code from mainart_code_parts
						$orderCode = $originalCode;

						foreach ($elitRows as $elitRow) {
							$productsMap[$baseCode]['suppliers']['elit']['variants'][] = [
								'supplier_stock' => 1, // or any logic you have
								'price'          => (float) ($elitRow->net_price ?? 0),
								'currency'       => 'RON',
								'order_code'     => $elitRow->item_nr ?? '',
								'depot'          => null,
								'is_blocked'     => false,
								'delivery'       => [
									'info_text'  => 'Verifica stoc',
									'plant_name' => null
								],
								'multiple_qty'   => 1
							];
						}
					}
				}
			}
			

			/* =========================
			   PRICING CALCULATION
			========================= */
			foreach ($productsMap as &$product) {
				$this->applyPricingToProduct($product);
			}

			/* =========================
			   FORMAT DELIVERY INFO (Livrare, Depozit)
			========================= */
			foreach ($productsMap as &$product) {
				foreach ($product['suppliers'] as $supplierName => &$supplierData) {
					if (isset($supplierData['variants'])) {
						foreach ($supplierData['variants'] as &$variant) {
							$deliveryFormatted = $this->formatDeliveryInfo($supplierName, $variant);
							$variant['livrare'] = $deliveryFormatted['livrare'];
							$variant['depozit'] = $deliveryFormatted['depozit'];
						}
					}
				}
			}

			/* =========================
			   FINAL NAME RESOLUTION
			========================= */
			foreach ($productsMap as &$product) {
				$this->resolveProductName($product);
				unset($product['db_name']); // cleanup
			}

			return response()->json([
				'success'  => true,
				'products' => array_values($productsMap)
			]);

		} catch (\Throwable $e) {
			dd($e->getMessage());
			\Log::error('Supplier search error', [
				'message' => $e->getMessage()
			]);

			return response()->json([
				'success' => false,
				'message' => 'Supplier search failed'
			], 500);
		}
	}

	public function cartAdd(Request $request)
	{
		$request->validate([
			'supplier'      => 'required|string',
			'product_code'  => 'required|string',
			'product_name'  => 'required|string',
			'manufacturer'  => 'nullable|string', 
			'variant_code'  => 'required|string',
			'qty'           => 'required|integer|min:1',
			'price'         => 'required|numeric',
			'currency'      => 'required|string', 
			'plantname'      => 'nullable|string',
			'delivery'      => 'nullable|string',
			'livrare'       => 'nullable|string',
			'depozit'       => 'nullable|string',
			'dot_image_path' => 'nullable|string',
			'departamentcode' => 'nullable|string',
		]);

		$supplier     = $request->supplier;
		$productCode  = $request->product_code;
		$productName  = $request->product_name;
		$manufacturer = $request->manufacturer ?? '';
		$variantCode  = $request->variant_code;
		$qty          = $request->qty;
		$price        = $request->price;
		$currency     = $request->currency;
		$plantname     = $request->plantname;
		$delivery     = $request->delivery ?? '';
		$livrare      = $request->livrare ?? '-';
		$depozit      = $request->depozit ?? '-';
		$dotImagePath = $request->dot_image_path;
		$departamentCode = $request->departamentcode ?? '';

		// Get current cart from session
		$cart = session()->get('suppliercart', []);

		// Create a unique key for product + variant + supplier
		$cartKey = $productCode . '-' . md5($variantCode);
		
		// Use dot_image_path from frontend if provided, otherwise fallback to plantname logic
		$plantImage = '';
		if ($dotImagePath) {
			$plantImage = '<img src="' . $dotImagePath . '" width="14" height="14" />';
		} else {
			// Fallback to old logic for backward compatibility
			if ($plantname === 'Timișoara') {
				$plantImage = '<img src="/image/green-dot.png" width="14" height="14" />';
			} elseif ($plantname === 'Centru Logistic') {
				$plantImage = '<img src="/image/double-dot.png" width="14" height="14" />';
			} else {
				$plantImage = '<img src="/image/red-dot.png" width="14" height="14" />';
			}
		}

		if (!isset($cart[$supplier])) {
			$cart[$supplier] = [];
		}

		if (isset($cart[$supplier][$cartKey])) {
			$cart[$supplier][$cartKey]['qty'] += $qty;

			$cart[$supplier][$cartKey]['order_code'] =
				$this->updateOrderCodeQuantity(
					$cart[$supplier][$cartKey]['order_code'],
					$cart[$supplier][$cartKey]['qty']
				);

		} else {
			$cart[$supplier][$cartKey] = [
				'supplier' => $supplier,
				'product_code' => $productCode,
				'product_name' => $productName,
				'manufacturer' => $manufacturer,
				'variant_code' => $variantCode,
				'qty' => $qty,
				'price' => $price,
				'currency' => $currency,
				'delivery' => $delivery,
				'plantraw' => $plantname,
				'plantname' => $plantImage,
				'livrare' => $livrare,
				'depozit' => $depozit,
				'departamentCode' => $departamentCode,

				// IMPORTANT: order_code MUST be Materom order_code
				'order_code' => $this->updateOrderCodeQuantity($variantCode, $qty),
			];
		}

		// Save back to session
		session()->put('suppliercart', $cart);

		return response()->json([
			'message' => 'Product added to cart successfully',
			'cart'    => $cart,
		]);
	}
	
	public function cartShow()
	{
		$cart = session()->get('suppliercart', []);
		
		$total = 0;
		foreach ($cart as $supplierItems) {
			foreach ($supplierItems as $item) {
				$total += $item['price'] * $item['qty'];
			}
		}

		return view('searching.cart', [
			'cart'  => $cart,
			'total' => $total
		]);
	}
	
	public function cartUpdate(Request $request)
	{
		$cart = session()->get('suppliercart', []);
		
		$request->validate([
			'supplier' => 'required|string',
			'key' => 'required|string',
			'qty' => 'required|integer|min:1'
		]);

		if (isset($cart[$request->supplier][$request->key])) {

			$cart[$request->supplier][$request->key]['qty'] = $request->qty;

			$cart[$request->supplier][$request->key]['order_code'] =
				$this->updateOrderCodeQuantity(
					$cart[$request->supplier][$request->key]['order_code'],
					$request->qty
				);

			session()->put('suppliercart', $cart);
		}

		return response()->json(['success' => true]);
	}

	public function cartRemove(Request $request)
	{
		$cart = session()->get('suppliercart', []);
		
		$request->validate([
			'supplier' => 'required|string',
			'key' => 'required|string'
		]);

		unset($cart[$request->supplier][$request->key]);

		// Remove supplier bucket if empty
		if (empty($cart[$request->supplier])) {
			unset($cart[$request->supplier]);
		}

		session()->put('suppliercart', $cart);

		return response()->json(['success' => true]);
	}
	
	public function cartUpdateVariant(Request $request)
	{
		$request->validate([
			'supplier' => 'required|string',
			'key' => 'required|string',
			'variant_code' => 'required|string'
		]);

		$cart = session('suppliercart', []);

		if (!isset($cart[$request->supplier][$request->key])) {
			return response()->json(['error' => 'Item not found'], 404);
		}

		$cart[$request->supplier][$request->key]['variant_code'] = $request->variant_code;
		$cart[$request->supplier][$request->key]['order_code']   = $request->variant_code;

		session()->put('suppliercart', $cart);

		return response()->json(['success' => true]);
	}
	
	public function placeOrder(Request $request)
	{
		$cart = session('suppliercart', []);

		$request->validate([
			'order_from'  => 'required|in:UTVIN,TIMISOARA',
			'import_from' => 'required|in:UTVIN,TIMISOARA,EXTERNE,NuImporta'
		]);

		$importFrom = $request->import_from;
		$orderFrom = $request->order_from;
		
		if ($importFrom === 'NuImporta') {
			return redirect()->route('searching.index')->with('success', 'Order not processed because import location is NuImporta.');
		}

		if (empty($cart)) {
			return redirect()->back()->with('error', 'Cart is empty');
		}

		$materomService = $this->materomService;
		$autopartnerService = $this->autoPartnerService;
		$autonetService = $this->autonetService;
		$autototalService = $this->autototalService;

		foreach ($cart as $supplier => $items) {
			if (empty($items)) continue;

			$orderItems = [];

			foreach ($items as $key => $item) {
				if (empty($item['order_code'])) {
					return redirect()->back()->with(
						'error',
						"Missing order code for {$item['product_name']} ({$supplier})"
					);
				}

				// Prepare supplier-specific order code
				if ($supplier === 'materom') {
					$orderItems[] = preg_replace('/qty:\d+/', 'qty:' . $item['qty'], $item['order_code']);
				} elseif ($supplier === 'autopartner') {
					$orderItems[] = [
						'productCode' => $item['product_code'],
						'quantity'    => (int)$item['qty'],
						// Add other necessary fields if required by your Autopartner service
					];
				}
			}


			try {
				if ($supplier === 'materom') {
					$response = $materomService->createStandardOrderV2(
						$orderItems,
						'Order from web cart',
						null,
						$orderFrom
					);

					if (!in_array($response['http_code'], [200, 201])) {
						return redirect()->back()
							->with('error', 'Materom error: ' . json_encode($response['body']));
					}

					$orders = $response['body']['data']['orders'] ?? [];
					foreach ($orders as $order) {
						SupplierOrder::updateOrCreate(
							['supplier' => 'materom', 'order_number' => $order['order_number']],
							['raw_response' => $order]
						);
					}

					$this->addProductsToDbAndTemp($cart['materom']);
				}

				if ($supplier === 'autopartner') {
					$customerId = 1; // Replace with actual logic
					$orderItemList = [];
					$position = 1;

					foreach ($items as $item) {
						// fallback to order_code if product_code missing
						$prodCode = $item['product_code'] ?? $item['order_code'];

						$orderItemList[] = [
							'ProductCode'    => $prodCode,
							'Quantity'       => (int) $item['qty'],
							'QuantityP'      => 0,
							'PositionNumber' => $position++,
						];
					}

					$payload = [
						'comments'                 => 'Order from web cart',
						'orderItemList'            => $orderItemList,
						'zone'                     => '03',
						'ownCollect'               => '0',
						'separateDocsAndPackaging' => '0',
						'onlyWhenAllAvailable'     => '0',
						'source'                   => 0,
					];

					$response = $autopartnerService->insertOrder($payload,$orderFrom);

					if (!$response['success']) {
						return redirect()->back()
							->with('error', 'Autopartner error: ' . json_encode($response['error'] ?? $response['data']));
					}

					// Save order to DB
					$orders = [$response['data']['RestInsertOrderResult'] ?? []];
					foreach ($orders as $order) {
						SupplierOrder::updateOrCreate(
							['supplier' => 'autopartner', 'order_number' => $order['OrderNumber'] ?? null],
							['raw_response' => $order]
						);
					}

					$this->addProductsToDbAndTemp($cart['autopartner']);
				}
				
				
				if ($supplier === 'autonet') {
					// Build order items array for Autonet API
					$orderItems = [];
					foreach ($items as $item) {
						$orderItems[] = [
							'PartNo'   => $item['order_code'], // Autonet expects product code
							'Quantity' => (float) $item['qty']
						];
					}

					// Generate a unique external order number
					$externalOrderNumber = 'WEB-' . time() . '-' . rand(1000, 9999);

					try {
						// Send order to Autonet
						$response = $autonetService->createDynamicOrder($externalOrderNumber, $orderItems, $orderFrom);

						// =========================
						// SUCCESS CHECK
						// =========================
						if ( !isset($response['Response']['Code']) || $response['Response']['Code'] != 0) {
							$message = $response['Response']['Message'] ?? 'Unknown Autonet error';
							return redirect()->back()->with('error', 'Autonet error: ' . $message);
						}

						// Check each item individually (optional, but recommended)
						if (!empty($response['OrderItems']) && is_array($response['OrderItems'])) {
							foreach ($response['OrderItems'] as $itemResponse) {
								if (
									!isset($itemResponse['Response']['Code']) || $itemResponse['Response']['Code'] != 0
								) {
									$itemMessage = $itemResponse['Response']['Message'] ?? 'Unknown item error';
								}
							}
						}
						
						// === SAVE ORDER TO DATABASE ===
						SupplierOrder::updateOrCreate(
							[
								'supplier'     => 'autonet', 
								'order_number' => $response['OrderNumber'] ?? null
							],
							[
								'external_order_number' => $response['ExternalOrderNumber'] ?? $externalOrderNumber,
								'raw_response'          => json_encode($response) // store full response for debugging
							]
						);

						// Add products to your DB / temporary tables
						$this->addProductsToDbAndTemp($cart['autonet'] ?? []);
						unset($cart['autonet']);

					} catch (\Exception $e) {
						dd($e->getMessage());
						// Catch any unexpected errors
						return redirect()->back()->with('error', 'Autonet order failed: ' . $e->getMessage());
					}
				}
				
				if ($supplier === 'autototal') {
					// Build AutoTotal order items (ATE => mapped itemkey, others => variant_code)
					$orderItems = [];

					foreach ($items as $item) {
						$manufacturer = strtoupper(trim((string) ($item['manufacturer'] ?? '')));
						if ($manufacturer === 'ATE') {
							$itemkeyToOrder = $this->findAutototalItemkey((string) ($item['product_code'] ?? ''));
							if (!$itemkeyToOrder) {
								$itemkeyToOrder = $this->findAutototalItemkey((string) ($item['variant_code'] ?? ''));
							}
							if (!$itemkeyToOrder) {
								return redirect()->back()->with(
									'error',
									"AutoTotal ATE: cannot place order without a mapped itemkey for {$item['product_name']}. "
									. 'The article must exist in autototal_data or autototal_branduri_proprii.'
								);
							}
						} else {
							$itemkeyToOrder = trim((string) ($item['variant_code'] ?? ''));
							if ($itemkeyToOrder === '') {
								return redirect()->back()->with(
									'error',
									"Missing order code for {$item['product_name']} (AutoTotal)"
								);
							}
						}

						$orderItems[] = [
							'ITEMKEY'   => $itemkeyToOrder,
							'QUANTITY' => (int) $item['qty'],
						];
					}

					try {
						// Call AutoTotal Order API
						$response = $autototalService->createOrder(
							$orderItems,
							null, // URL (optional)
							'Order from web cart', // remarks
							'1', // API version
							$orderFrom // Location for credentials
						);

						if (!$response || empty($response['success'])) {
							return redirect()->back()
								->with('error', 'AutoTotal order failed');
						}

						// Save order response
						SupplierOrder::updateOrCreate(
							[
								'supplier'     => 'autototal',
								'order_number' => $response['order_id'],
							],
							[
								'raw_response' => json_encode($response),
							]
						);

						// Add products to DB / temp tables
						$this->addProductsToDbAndTemp($cart['autototal'] ?? []);

						// Remove supplier cart
						unset($cart['autototal']);

					} catch (\Exception $e) {
						dd($e->getMessage());
						return redirect()->back()
							->with('error', 'AutoTotal error: ' . $e->getMessage());
					}
				}

				// Remove processed supplier from cart
				unset($cart[$supplier]);

			} catch (\Exception $e) {
				dd($e->getMessage());
				return redirect()->back()->with('error', "{$supplier} order failed: " . $e->getMessage());
			}
		}

		// Update or clear session cart
		if (empty($cart)) {
			session()->forget('suppliercart');
		} else {
			session()->put('suppliercart', $cart);
		}

		// Redirect according to import location
		switch ($importFrom) {
			case 'UTVIN':
				return redirect()->route('orders.create', ['type' => 'utvin', 'from' => 'supplier'])
					->with('success', 'Orders placed and products imported to UTVIN');
			case 'TIMISOARA':
				return redirect()->route('orders.create', ['from' => 'supplier'])
					->with('success', 'Orders placed and products imported to TIMIȘOARA');
			case 'EXTERNE':
				return redirect()->route('comenzi.create', ['from' => 'supplier'])
					->with('success', 'Orders placed and products imported to EXTERNE');
			default:
				return redirect()->back()->with('error', 'Invalid import location');
		}
	}
	
    public function saveWishlist(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'vin' => 'nullable|string|max:255',
        ]);

        $cart = session()->get('suppliercart', []);

        if (empty($cart)) {
            return back()->with('error', 'Your cart is empty.');
        }

        SupplierSavedCart::create([
            'user_id' => Auth::user()->Id,
            'name' => $request->name,
            'cart' => $cart,
            'phone' => $request->phone ?? null,
            'vin' => $request->vin ?? null,
            'alreadygenerated' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        return back()->with('success', 'Cart saved successfully!');
    }
	
	public function loadWishlist($id)
	{
		$savedCart = SupplierSavedCart::where('user_id', Auth::user()->Id)->findOrFail($id);

		$newCart = [];
		$skippedItems = [];

		foreach ($savedCart->cart as $supplier => $items) {
			foreach ($items as $item) {

				// 🔁 Re-run parts search
				$searchResult = $this->materomService->partSearchV4($item['product_code']);

				// Safety checks
				if (
					empty($searchResult['body']) ||
					empty($searchResult['body'][0]['pricingVariants'])
				) {
					$skippedItems[] = $item['product_name'] . ' (not found)';
					continue;
				}

				$pricingVariants = $searchResult['body'][0]['pricingVariants'];

				// 🔍 Match exact variant by order_code
				$matchedVariant = collect($pricingVariants)
					->firstWhere('order_code', $item['variant_code']);

				if (!$matchedVariant) {
					$skippedItems[] = $item['product_name'] . ' (variant unavailable)';
					continue;
				}

				// 📦 Stock check
				if (
					empty($matchedVariant['is_stock']) ||
					$matchedVariant['stock'] < $item['qty']
				) {
					$skippedItems[] = $item['product_name'] . ' (out of stock)';
					continue;
				}

				// 🔄 Update item with latest live data
				$item['price']          = $matchedVariant['price'];
				$item['currency']       = $matchedVariant['currency'];
				$item['variant_code']   = $matchedVariant['order_code'];
				$item['delivery']       = $matchedVariant['delivery']['plant_name'] ?? '';
				$item['disponibilitate']= $matchedVariant['delivery']['info_text'] ?? '';
				$item['plantname']      = $matchedVariant['delivery']['plant_name'] ?? '';

				$newCart[$supplier][] = $item;
			}
		}

		// 💾 Save only valid items
		session(['suppliercart' => $newCart]);

		// ⚠️ Feedback
		if (!empty($skippedItems)) {
			return redirect()->route('searching.cartShow')
				->with('warning', 'Some items were skipped: ' . implode(', ', $skippedItems));
		}

		return redirect()->route('searching.cartShow')
			->with('success', 'Wishlist loaded into your cart.');
	}

    public function listSavedWishlists()
    {
        $savedCarts = SupplierSavedCart::where('user_id', Auth::user()->Id)->get();
        return view('searching.saved_carts', compact('savedCarts'));
    }
	
	public function wishlistCreateOffer($id)
	{
		$savedCart = SupplierSavedCart::where('user_id', Auth::user()->Id)->findOrFail($id);
		$employee = Employee::findOrFail(2);

		$cartItems = $savedCart->cart ?? [];
/* 
		$pdf = Pdf::loadView('searching.cart_offer_pdf', compact('savedCart', 'cartItems'));

		return $pdf->download($savedCart->name . '_offer.pdf'); */
		
		$details = ['dewdwed'=>'efewdew','wewedew'=>'weefwewedw'];
        try {
            // margins
            $margins = [
                'l' => 15,
                't' => 15,
                'r' => 15,
            ]; /* l: Left Side , t: Top Side , r: Right Side */

            // A4 width and height in mm
            $document = ['w' => 210,'h' => 297];

            $maxImageDimensions = [230, 130];

            $FirstName = !empty($employee->FirstName) ? $employee->FirstName : '';
            $LastName = !empty($employee->LastName) ? $employee->LastName : '';
            $CNP = !empty($employee->CNP) ? $employee->CNP : '';
            $CI = !empty($employee->CI) ? $employee->CI : '';
            $CiNr = !empty($employee->CiNr) ? $employee->CiNr : '';
			
            $agent = $FirstName . $LastName;
            $agent_detalii = "CNP: " . $CNP . ",CI " . $CI . $CiNr;

            $from = ['BESOIU PIESE AUTO SRL', 'CUI: RO 31298897', 'ROONRC: J2013000544351', 'Adresa: Utvin, nr. 489, jud. Timis, Romania'
            , 'BANCA: Raiffeisen Bank', 'CONT: RO32 RZBR 0000 0600 2191 4930'];
            $to = [$savedCart->name];


            $pdf = new RotatedPdf('P', 'mm', 'A4');
            $pdf->AliasNbPages();
            $pdf->AddPage();


            $pdf->SetMargins($margins['l'], $margins['t'], $margins['r']);

            list($width, $height) = getimagesize(public_path('assets/image/Capture.jpg'));
            $newWidth = $maxImageDimensions[0] / $width;
            $newHeight = $maxImageDimensions[1] / $height;
            $scale = min($newWidth, $newHeight);

            $dimensions = [
                round($this->pixelsToMM($scale * $width)),
                round($this->pixelsToMM($scale * $height)),
            ];

            // Insert image inside that cell
            $pdf->Image(public_path('assets/image/Capture.jpg'), $margins['l'], $margins['t'], $dimensions[0], $dimensions[1]);

            //Title
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont($this->font, 'B', 20);
            $pdf->SetY($margins['t']); // Move down from top
            //$pdf->SetX($margins['l'] + $dimensions[0] + 50); // Move left after the image
            $pdf->SetFont($this->font, '', 9);
            $pdf->Ln(5);

            $lineheight = 3;
            //Calculate position of strings
            $positionX = $document['w'] - $margins['l'] - $margins['r'] - max(
                    $pdf->GetStringWidth(mb_strtoupper('NUMAR', self::ICONV_CHARSET_INPUT)),
                    $pdf->GetStringWidth(mb_strtoupper('Data', self::ICONV_CHARSET_INPUT))
            ) - max(
                    $pdf->GetStringWidth(mb_strtoupper('98334', self::ICONV_CHARSET_INPUT)),
                    $pdf->GetStringWidth(mb_strtoupper(date('d.m.Y', strtotime($savedCart->created_at)), self::ICONV_CHARSET_INPUT))
            )-4;

            //Number
            $pdf->Cell($positionX, $lineheight);
            $color = $this->hex2rgb('#AA3939'); // Default color
            $pdf->SetTextColor($color[0], $color[1], $color[2]);
            $pdf->SetFont($this->font, 'B', 9);
            $pdf->Cell(32, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('NUMAR', self::ICONV_CHARSET_INPUT) . ':'), 0, 0, 'L');
            $pdf->SetTextColor(50, 50, 50);
            $pdf->SetFont($this->font, '', 9);
            $pdf->Cell(0, $lineheight, $savedCart->id, 0, 1, 'R');

            //Date
            $pdf->Cell($positionX, $lineheight);
            $pdf->SetFont($this->font, 'B', 9);
            $pdf->SetTextColor($color[0], $color[1], $color[2]);
            $pdf->Cell(32, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('Data', self::ICONV_CHARSET_INPUT)) . ':', 0, 0, 'L');
            $pdf->SetTextColor(50, 50, 50);
            $pdf->SetFont($this->font, '', 9);
            $pdf->Cell(0, $lineheight, date('d.m.Y', strtotime($savedCart->created_at)), 0, 1, 'R');

            // Client information
            $dimensions = $dimensions[1] ?: 0;
            if (($margins['t'] + $dimensions) > $pdf->GetY()) {
                $pdf->SetY($margins['t'] + $dimensions + 5);
            }
            else {
                $pdf->SetY($pdf->GetY() + 10);
            }
            $pdf->Ln(2);
            $pdf->SetFillColor($color[0], $color[1], $color[2]);
            $pdf->SetTextColor($color[0], $color[1], $color[2]);

            $pdf->SetDrawColor($color[0], $color[1], $color[2]);
            $pdf->SetFont($this->font, 'B', 10);

            $width = ($document['w'] - $margins['l'] - $margins['r']) / 2;

            $pdf->Cell($width, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('Furnizor', self::ICONV_CHARSET_INPUT)), 0, 0, 'L');
            $pdf->Cell(0, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('Client', self::ICONV_CHARSET_INPUT)), 0, 0, 'L');
            $pdf->Ln(7);
            $pdf->SetLineWidth(0.4);
            $pdf->Line($margins['l'], $pdf->GetY(), $margins['l'] + $width - 10, $pdf->GetY());
            $pdf->Line($margins['l'] + $width, $pdf->GetY(),$margins['l'] + $width + $width, $pdf->GetY());

            //To and From Information
            $pdf->Ln(5);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->SetFont($this->font, 'B', 10);
            $pdf->Cell($width, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, $from[0] ?: 0), 0, 0, 'L');
            $pdf->Cell(0, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, $to[0] ?: 0), 0, 0, 'L');
            $pdf->SetFont($this->font, '', 8);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Ln(7);

            for ($i = 1, $iMax = max($from === null ? 0 : count($from), $to === null ? 0 : count($to)); $i < $iMax; $i++) {
                // avoid undefined error if TO and FROM array lengths are different
                if (!empty($from[$i]) || !empty($to[$i])) {
                    $pdf->Cell($width, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, empty($from[$i]) ? '' : $from[$i]), 0, 0, 'L');
                    $pdf->Cell(0, $lineheight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, empty($to[$i]) ? '' : $to[$i]), 0, 0, 'L');
                }
                $pdf->Ln(5);
            }
            $pdf->Ln(-20);
            $pdf->Ln(2);


            //Table header
            $width_other = ($document['w'] - $margins['l'] - $margins['r'] - $this->firstColumnWidth - ($this->columns * $this->columnSpacing)) / ($this->columns - 1);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->Ln(14);
            $pdf->SetFont($this->font, 'B', 9);
            $pdf->Cell(1, 10, '', 0, 0, 'L', 0);
            $pdf->Cell($this->firstColumnWidth, 10,
                    iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('Produs', self::ICONV_CHARSET_INPUT)), 0, 0, 'L', 0);
            $pdf->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
            $pdf->Cell($width_other, 10, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('UM', self::ICONV_CHARSET_INPUT)), 0, 0, 'C', 0);
            $pdf->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
            $pdf->Cell($width_other, 10, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('Cantitate', self::ICONV_CHARSET_INPUT)), 0, 0, 'C', 0);
            $pdf->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
            $pdf->Cell($width_other, 10,
                    iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('Pret Unitar', self::ICONV_CHARSET_INPUT)),
                    0, 0, 'C', 0);
            $pdf->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
            $pdf->Cell($width_other, 10,
                    iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('Valoare', self::ICONV_CHARSET_INPUT)),
                    0, 0, 'C', 0);
            $pdf->Cell($this->columnSpacing, 10, '', 0, 0, 'L', 0);
/*             $pdf->Cell($width_other, 10,
                    iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, mb_strtoupper('T.V.A.', self::ICONV_CHARSET_INPUT)),
                    0, 0, 'C', 0); */
            $pdf->Ln();
            $pdf->SetLineWidth(0.3);
            $pdf->SetDrawColor($color[0], $color[1], $color[2]);
            $pdf->Line($margins['l'], $pdf->GetY(), $document['w'] - $margins['r'], $pdf->GetY());
            $pdf->Ln(2);
			
			// Products table from cartItems
			$width_other = ($document['w'] - $margins['l'] - $margins['r'] - $this->firstColumnWidth - ($this->columns * $this->columnSpacing)) / ($this->columns - 1);
			$cellHeight = 8;
			$bgcolor = (1 - $this->columnOpacity) * 255;

			$suma_total = 0;

			if (!empty($cartItems)) {
				foreach ($cartItems as $supplier => $products) {
					foreach ($products as $code => $item) {
						$nume_produs = $item['product_name'] ?? '';
						$um = 'buc';
						$cantitate = (float)($item['qty'] ?? 1);
						$cantitate_f = number_format($cantitate, 2);
						$pret_unitar = (float)($item['price'] ?? 0);
						$pret_unitar_f = number_format($pret_unitar, 2);
						$valoare = $pret_unitar * $cantitate;
						$valoare_f = number_format($valoare, 2);

						$suma_total += $valoare;

						$pdf->SetFont($this->font, 'b', 8);
						$pdf->SetTextColor(50, 50, 50);
						$pdf->SetFillColor($bgcolor, $bgcolor, $bgcolor);
						$pdf->Cell(1, $cellHeight, '', 0, 0, 'L', 1);

						$x = $pdf->GetX();
						$pdf->Cell($this->firstColumnWidth, $cellHeight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_A, $nume_produs), 0, 0, 'L', 1);

						$pdf->SetFont($this->font, '', 8);
						$pdf->SetTextColor(50, 50, 50);
						$pdf->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);
						$pdf->Cell($width_other, $cellHeight, $um, 0, 0, 'C', 1);
						$pdf->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);
						$pdf->Cell($width_other, $cellHeight, $cantitate_f, 0, 0, 'C', 1);
						$pdf->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);
						$pdf->Cell($width_other, $cellHeight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_B, $pret_unitar_f), 0, 0, 'C', 1);
						$pdf->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);
						$pdf->Cell($width_other, $cellHeight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_B, $valoare_f), 0, 0, 'C', 1);
						$pdf->Ln();
						$pdf->Ln($this->columnSpacing);
					}
				}
			}

			// Optionally, add total at the bottom
			// Prepare totals from cartItems
			$suma_total = 0;

			foreach ($cartItems as $supplier => $products) {
				foreach ($products as $code => $item) {
					$cantitate = (float)($item['qty'] ?? 1);
					$pret_unitar = (float)($item['price'] ?? 0);
					$valoare = $pret_unitar * $cantitate;
					$suma_total += $valoare;
				}
			}

			// Create totals array similar to your UI
			$totals = [
				['name' => 'Total', 'value' => number_format($suma_total, 2), 'colored' => true]
			];
			$pdf->Ln(30);

			$badgeX = $pdf->getX();
			$badgeY = $pdf->getY();

			if (!empty($totals)) {
				foreach ($totals as $total) {
					$pdf->SetTextColor(50, 50, 50);
					$pdf->SetFillColor($bgcolor, $bgcolor, $bgcolor);
					$pdf->Cell(1 + $this->firstColumnWidth, $cellHeight, '', 0, 0, 'L', 0);

					// Empty cells for spacing
					for ($i = 0; $i < $this->columns - 3; $i++) {
						$pdf->Cell($width_other, $cellHeight, '', 0, 0, 'L', 0);
						$pdf->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);
					}
					$pdf->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);

					// Colored total cell
					if ($total['colored']) {
						$pdf->SetTextColor(255, 255, 255);
						$pdf->SetFillColor($color[0], $color[1], $color[2]);
					}

					$pdf->SetFont($this->font, 'b', 8);
					$pdf->Cell(1, $cellHeight, '', 0, 0, 'L', 1);
					$pdf->Cell($width_other - 1, $cellHeight,
						iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_B, $total['name']),
						0, 0, 'L', 1);

					$pdf->Cell($this->columnSpacing, $cellHeight, '', 0, 0, 'L', 0);
					$pdf->SetFont($this->font, 'b', 8);
					$pdf->SetFillColor($bgcolor, $bgcolor, $bgcolor);

					if ($total['colored']) {
						$pdf->SetTextColor(255, 255, 255);
						$pdf->SetFillColor($color[0], $color[1], $color[2]);
					}

					$pdf->Cell($width_other, $cellHeight, iconv(self::ICONV_CHARSET_INPUT, self::ICONV_CHARSET_OUTPUT_B, $total['value']), 0, 0, 'C', 1);
					$pdf->Ln();
					$pdf->Ln($this->columnSpacing);
				}
			}
			$pdf->Ln(2);
			//$pdf->Ln(3);

            $pdf->SetLineWidth(0.3);
            $pdf->SetDrawColor($color[0], $color[1], $color[2]);
            $pdf->Line($margins['l'], $pdf->GetY(), $document['w'] - $margins['r'], $pdf->GetY());

            //Footer
            $pdf->SetY(-25);
            $pdf->SetFont($this->font, '', 8);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->Cell(0, 3, 'BESOIU PIESE AUTO', 0, 0, 'L');
            $pdf->Cell(0, 3, iconv('UTF-8', 'ISO-8859-1', 'Pagina') . ' ' . $pdf->PageNo() . ' ' . 'din' . ' {nb}', 0, 0, 'R');
            
			$number = 1233444;
			$savedCart->update([
				'alreadygenerated' => 1,
			]);
            $pdf->Output($number . '.pdf', 'I');
            exit; // Stop further processing after output
        }
        catch (\Exception $e) {
			    dd([
					'message' => $e->getMessage(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
					'trace' => $e->getTraceAsString(),
				]);
            Log::error('Error printing invoice' . $e->getMessage(), [
                'user_id' => Auth::user()->Id ?? 'unknown'
            ]);
            
            return redirect()->back()->with('error', 'Error printing invoice: ' . $e->getMessage());
        }
	}
	
	public function getOrders(Request $request)
	{
		$selectedSuppliers = $request->input('suppliers', []);
		
		// Handle comma-separated string from URL
		if (is_string($selectedSuppliers)) {
			$selectedSuppliers = array_filter(explode(',', $selectedSuppliers));
		}
		
		// Get all supplier orders or filter by selected suppliers
		$query = SupplierOrder::query();
		
		if (!empty($selectedSuppliers)) {
			$query->whereIn('supplier', $selectedSuppliers);
		}
		
		$storedOrders = $query->orderBy('created_at', 'desc')->get();
		
		$orders = [];
		$suppliers = [];
		
		foreach ($storedOrders as $storedOrder) {
			$supplier = $storedOrder->supplier;
			$suppliers[$supplier] = true;
			
			try {
				$orderData = $this->parseOrderData($storedOrder);
				if ($orderData) {
					$orders[] = $orderData;
				}
			} catch (\Exception $e) {
				\Log::error('Order parse failed', [
					'supplier' => $supplier,
					'order_number' => $storedOrder->order_number,
					'error' => $e->getMessage(),
				]);
			}
		}
		
		$availableSuppliers = array_keys($suppliers);
		
		return view('searching.orders', [
			'orders' => $orders,
			'availableSuppliers' => $availableSuppliers,
			'selectedSuppliers' => $selectedSuppliers
		]);
	}
	
	private function parseOrderData($storedOrder)
	{
		$supplier = $storedOrder->supplier;
		$rawResponse = $storedOrder->raw_response;
		
		// Handle JSON string or array
		if (is_string($rawResponse)) {
			$rawResponse = json_decode($rawResponse, true);
		}
		
		if (empty($rawResponse)) {
			return null;
		}
		
		$orderData = [
			'id' => $storedOrder->id,
			'supplier' => $supplier,
			'order_number' => $storedOrder->order_number,
			'raw_response' => $rawResponse,
			'created_at' => $storedOrder->created_at ? $storedOrder->created_at->format('Y-m-d H:i:s') : null,
		];
		
		// Parse based on supplier type
		switch (strtolower($supplier)) {
			case 'materom':
				$orderData['status'] = $rawResponse['status'] ?? $rawResponse['Status'] ?? 'Unknown';
				$orderData['order_date'] = $rawResponse['order_date'] ?? $rawResponse['OrderDate'] ?? ($storedOrder->created_at ? $storedOrder->created_at->format('Y-m-d') : '-');
				$orderData['total'] = $rawResponse['total'] ?? $rawResponse['Total'] ?? 0;
				
				// Parse Materom items
				$items = [];
				if (isset($rawResponse['items']) && is_array($rawResponse['items'])) {
					foreach ($rawResponse['items'] as $item) {
						$items[] = [
							'product_code' => $item['product_code'] ?? $item['ProductCode'] ?? '-',
							'product_name' => $item['product_name'] ?? $item['ProductName'] ?? $item['name'] ?? '-',
							'quantity' => $item['quantity'] ?? $item['Quantity'] ?? $item['qty'] ?? 0,
							'price' => $item['price'] ?? $item['Price'] ?? $item['unit_price'] ?? 0,
						];
					}
				}
				$orderData['items'] = $items;
				break;
				
			case 'autopartner':
				$orderData['status'] = $rawResponse['Status'] ?? 'Unknown';
				$orderData['order_date'] = $rawResponse['OrderDate'] ?? ($storedOrder->created_at ? $storedOrder->created_at->format('Y-m-d') : '-');
				$orderData['total'] = $rawResponse['Total'] ?? $rawResponse['TotalAmount'] ?? 0;
				
				// Parse Autopartner items
				$items = [];
				if (isset($rawResponse['OrderItems']) && is_array($rawResponse['OrderItems'])) {
					foreach ($rawResponse['OrderItems'] as $item) {
						$items[] = [
							'product_code' => $item['ProductCode'] ?? '-',
							'product_name' => $item['ProductName'] ?? '-',
							'quantity' => $item['Quantity'] ?? 0,
							'price' => $item['Price'] ?? 0,
						];
					}
				}
				$orderData['items'] = $items;
				break;
				
			case 'autonet':
				$orderData['status'] = (isset($rawResponse['Response']['Code']) && $rawResponse['Response']['Code'] == 0) ? 'Success' : 'Error';
				$orderData['order_date'] = $rawResponse['OrderDate'] ?? ($storedOrder->created_at ? $storedOrder->created_at->format('Y-m-d') : '-');
				$orderData['external_order_number'] = $rawResponse['ExternalOrderNumber'] ?? null;
				
				// Parse Autonet items and calculate total
				$items = [];
				$total = 0;
				if (isset($rawResponse['OrderItems']) && is_array($rawResponse['OrderItems'])) {
					foreach ($rawResponse['OrderItems'] as $item) {
						$quantity = $item['Quantity'] ?? 0;
						$price = $item['PriceWoVat'] ?? $item['Price'] ?? 0;
						$itemTotal = $quantity * $price;
						$total += $itemTotal;
						
						$items[] = [
							'product_code' => $item['PartNo'] ?? '-',
							'product_name' => $item['PartName'] ?? $item['Description'] ?? '-',
							'quantity' => $quantity,
							'price' => $price,
						];
					}
				}
				$orderData['items'] = $items;
				$orderData['total'] = $total;
				break;
				
			case 'autototal':
			case 'elit':
			default:
				$orderData['status'] = 'Unknown';
				$orderData['order_date'] = $storedOrder->created_at ? $storedOrder->created_at->format('Y-m-d') : '-';
				$orderData['total'] = 0;
				$orderData['items'] = [];
				break;
		}
		
		return $orderData;
	}
	
    private function br2nl($string) {
        return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $string);
    }

    /**
     * PDF generation helper for hex2rgb
     */
    private function hex2rgb($hex) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        $rgb = [$r, $g, $b];

        return $rgb;
    }
	
    /**
     * PDF generation helper for pixelsToMM
     */
    private function pixelsToMM($val) {
        $mm_inch = 25.4;
        $dpi = 96;

        return ($val * $mm_inch) / $dpi;
    }

    public function deleteSavedWishlist($id)
    {
        $savedCart = SupplierSavedCart::where('user_id', Auth::user()->Id)->findOrFail($id);
        $savedCart->delete();
        return back()->with('success', 'Saved cart deleted.');
    }

    public function wishlistWhatsApp($id)
    {
        $savedCart = SupplierSavedCart::where('user_id', Auth::user()->Id)->findOrFail($id);
        
        if (empty($savedCart->phone)) {
            return back()->with('error', 'Phone number is not available for this cart.');
        }

        $cartItems = $savedCart->cart ?? [];
        
        // Get template from database or use default
        $templateBody = MessageTemplate::getTemplate('wishlist_offer', 'whatsapp');
        
        // Build product lines
        $totalPrice = 0;
        $productLines = [];
        
        // Collect all products from all suppliers
        foreach ($cartItems as $supplier => $products) {
            foreach ($products as $code => $item) {
                $productName = $item['product_name'] ?? '-';
                $qty = $item['qty'] ?? 1;
                $price = $item['price'] ?? 0;
                $total = $price * $qty;
                $totalPrice += $total;
                
                // Format: "PRODUCT NAME - QTY X PRICE RON" or "PRODUCT NAME - PRICE RON" if qty = 1
                if ($qty > 1) {
                    $productLines[] = strtoupper($productName) . " - " . $qty . " X " . number_format($price, 0) . " RON";
                } else {
                    $productLines[] = strtoupper($productName) . " - " . number_format($price, 0) . " RON";
                }
            }
        }
        
        $vin = trim((string) ($savedCart->vin ?? ''));

        // Replace only the dynamic parts in the template
        $message = strtr($templateBody, [
            '{{product_lines}}' => implode("\n", $productLines),
            '{{total}}' => number_format($totalPrice, 0),
            '{{vin}}' => $vin,
        ]);
        
        // Format phone number (remove spaces, keep + if present, ensure country code)
        $phone = preg_replace('/[^0-9+]/', '', $savedCart->phone);
        
        // If phone doesn't start with +, assume it's a Romanian number and add +40
        if (!str_starts_with($phone, '+')) {
            // Remove leading 0 if present
            $phone = ltrim($phone, '0');
            $phone = '+40' . $phone;
        }
        
        // Encode message for URL
        $encodedMessage = urlencode($message);
        
        // WhatsApp URL format: https://wa.me/PHONENUMBER?text=MESSAGE
        $whatsappUrl = 'https://web.whatsapp.com/send/?phone=' . preg_replace('/[^0-9]/', '', $phone) . '&text=' . $encodedMessage;
        
        return redirect($whatsappUrl);
    }
	
	private function updateOrderCodeQuantity(string $orderCode, int $newQty): string
	{
		// Replace qty:X with qty:newQty
		return preg_replace('/qty:\d+/', 'qty:' . $newQty, $orderCode);
	}

	private function findAutototalItemkey(string $articleNr): ?string
	{
		$articleNr = trim($articleNr);
		if ($articleNr === '') {
			return null;
		}

		$normalized = str_replace([' ', '-', '/', '|', '\\'], '', $articleNr);

		try {
			$row = DB::table('autototal_data')
				->select('itemkey')
				->where('art_article_nr', $articleNr)
				->orWhere('art_article_nr', $normalized)
				->orWhereRaw(
					"REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(art_article_nr, ' ', ''), '-', ''), '/', ''), '|', ''), '\\\\', '') = ?",
					[$normalized]
				)
				->first();

			$itemkey = $row->itemkey ?? null;
			$itemkey = is_string($itemkey) ? trim($itemkey) : (is_numeric($itemkey) ? (string) $itemkey : null);

			return $itemkey !== '' ? $itemkey : null;
		} catch (\Throwable $e) {
			return null;
		}
	}
	
	/**
	 * Get supplier code for furnizor field
	 */
	private function getSupplierCode(string $supplier): string
	{
		$supplier = strtolower(trim($supplier));
		$mapping = [
			'materom' => 'MA',
			'autototal' => 'AT',
			'autonet' => 'AN',
			'autopartner' => 'AP',
			'elit' => 'EL'
		];
		
		return $mapping[$supplier] ?? 'MA'; // Default to MA if not found
	}
	
	/**
	 * Extract plant name from delivery info and return appropriate color
	 * Works for all suppliers: materom, autototal, autonet, autopartner
	 */
	private function getColorFromDeliveryInfo(array $item): string
	{
		$supplier = strtolower($item['supplier'] ?? '');
		$plantraw = $item['plantraw'] ?? '';
		$delivery = $item['delivery'] ?? '';
		$livrare = $item['livrare'] ?? '';
		
		/* ===== MATEROM ===== */
		if ($supplier === 'materom') {
			if ($plantraw === 'Timișoara') {
				return '7CFC00'; // Green - today
			} elseif ($plantraw === 'Centru Logistic') {
				return 'ADD8E6'; // Blue - tomorrow
			}
			
			// Check livrare text for additional info
			$livrareLower = strtolower($livrare);
			if (strpos($livrareLower, 'poimâine') !== false) {
				return 'F5A000'; // Orange - day after tomorrow
			}
			if (preg_match('/(\d+)[\s-]*zile/i', $livrareLower, $matches)) {
				$days = (int)$matches[1];
				if ($days === 2) return 'F5A000'; // Orange - 2 days
				if ($days >= 3) return 'FF0000'; // Red - 3+ days
			}
			
			return 'FF0000'; // Default red
		}
		
		/* ===== AUTOPARTNER ===== */
		if ($supplier === 'autopartner') {
			// Check if we have departamentCode in the item (might be stored differently)
			$deptCode = $item['departamentCode'] ?? $item['departamentcode'] ?? '';
			$deptCode = trim($deptCode);
			
			if ($deptCode === 'CN') {
				return 'ADD8E6'; // Blue - tomorrow
			}
			if ($deptCode === '120' || $deptCode === '72') {
				return 'F5A000'; // Orange - 2 days
			}
			
			// Fallback: check livrare text
			$livrareLower = strtolower($livrare);
			if (strpos($livrareLower, 'maine') !== false || strpos($livrareLower, 'mâine') !== false) {
				return 'ADD8E6'; // Blue - tomorrow
			}
			if (strpos($livrareLower, 'poimâine') !== false || strpos($livrareLower, '2 zile') !== false) {
				return 'F5A000'; // Orange - 2 days
			}
			if (preg_match('/(\d+)[\s-]*zile/i', $livrareLower, $matches)) {
				$days = (int)$matches[1];
				if ($days >= 3) return 'FF0000'; // Red - 3+ days
			}
			
			return 'FF0000'; // Default red
		}
		
		/* ===== AUTONET / AUTOTOTAL ===== */
		if ($supplier === 'autonet' || $supplier === 'autototal') {
			if (empty($delivery)) {
				return 'FF0000'; // Red - no delivery info
			}
			
			$deliveryDate = null;
			
			// Try to parse ISO date format (2026-02-03T13:10:00)
			if (preg_match('/(\d{4}-\d{2}-\d{2})[Tt](\d{2}:\d{2}):\d{2}/i', $delivery, $matches)) {
				$dateStr = $matches[1];
				try {
					$deliveryDate = new \DateTime($dateStr);
				} catch (\Exception $e) {
					// Invalid date
				}
			}
			
			// Try to parse RO date format (dd.mm.yyyy hh:mm)
			if (!$deliveryDate && preg_match('/(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}:\d{2})/', $delivery, $matches)) {
				$dateStr = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
				try {
					$deliveryDate = new \DateTime($dateStr);
				} catch (\Exception $e) {
					// Invalid date
				}
			}
			
			if ($deliveryDate) {
				$deliveryDate->setTime(0, 0, 0);
				$today = new \DateTime();
				$today->setTime(0, 0, 0);
				
				$diff = $today->diff($deliveryDate);
				$daysDiff = (int)$diff->format('%r%a');
				
				if ($daysDiff === 0) return '7CFC00'; // Green - today
				if ($daysDiff === 1) return 'ADD8E6'; // Blue - tomorrow
				if ($daysDiff === 2) return 'F5A000'; // Orange - day after tomorrow
				if ($daysDiff >= 3) return 'FF0000'; // Red - 3+ days
			}
			
			// Fallback: check livrare text
			$livrareLower = strtolower($livrare);
			if (strpos($livrareLower, 'azi') !== false) return '7CFC00'; // Green
			if (strpos($livrareLower, 'maine') !== false || strpos($livrareLower, 'mâine') !== false) return '007bff'; // Blue
			if (strpos($livrareLower, 'poimâine') !== false) return 'F5A000'; // Orange
			
			return 'FF0000'; // Default red
		}
		
		// Default: red
		return 'FF0000';
	}
	
	/**
	 * Normalize plant name from different supplier formats
	 */
	private function normalizePlantName(string $supplier, ?string $plantraw, ?string $delivery): string
	{
		$supplier = strtolower($supplier);
		
		// Materom: plantraw is already "Timișoara" or "Centru Logistic"
		if ($supplier === 'materom') {
			if ($plantraw === 'Timișoara') {
				return 'Timișoara';
			} elseif ($plantraw === 'Centru Logistic') {
				return 'Centru Logistic';
			}
		}
		
		// Autototal: plantraw can be "Tm." or "Buc.-IMGB" etc.
		if ($supplier === 'autototal') {
			$plantrawLower = strtolower(trim($plantraw ?? ''));
			// "Tm." or "Timișoara" or contains "tm" → Timișoara
			if ($plantrawLower === 'tm.' || $plantrawLower === 'timișoara' || 
			    strpos($plantrawLower, 'tm') !== false || strpos($plantrawLower, 'timi') !== false) {
				return 'Timișoara';
			}
			// "Centru Logistic" or "Mures" → Centru Logistic
			if ($plantrawLower === 'centru logistic' || $plantrawLower === 'mures' ||
			    strpos($plantrawLower, 'centru') !== false || strpos($plantrawLower, 'mures') !== false) {
				return 'Centru Logistic';
			}
			// Check delivery date if plantraw doesn't match
			if ($delivery) {
				$normalizedFromDelivery = $this->extractPlantFromDeliveryDate($delivery);
				if ($normalizedFromDelivery) {
					return $normalizedFromDelivery;
				}
			}
		}
		
		// Autonet: plantraw is usually null, but delivery contains ISO date
		if ($supplier === 'autonet') {
			if ($delivery) {
				$normalizedFromDelivery = $this->extractPlantFromDeliveryDate($delivery);
				if ($normalizedFromDelivery) {
					return $normalizedFromDelivery;
				}
			}
		}
		
		// Autopartner: uses departamentCode, but we can check delivery date
		if ($supplier === 'autopartner') {
			if ($delivery) {
				$normalizedFromDelivery = $this->extractPlantFromDeliveryDate($delivery);
				if ($normalizedFromDelivery) {
					return $normalizedFromDelivery;
				}
			}
		}
		
		// Default: return empty string (will result in red color)
		return '';
	}
	
	/**
	 * Extract plant name from delivery date string
	 * For ISO dates: today → Timișoara, tomorrow → Centru Logistic, else → empty
	 */
	private function extractPlantFromDeliveryDate(string $delivery): string
	{
		// Try to parse ISO date format (2026-02-04T08:10:00)
		if (preg_match('/(\d{4}-\d{2}-\d{2})[Tt](\d{2}:\d{2}):\d{2}/i', $delivery, $matches)) {
			$dateStr = $matches[1];
			try {
				$deliveryDate = new \DateTime($dateStr);
				$today = new \DateTime();
				$today->setTime(0, 0, 0);
				$deliveryDate->setTime(0, 0, 0);
				
				$diff = $today->diff($deliveryDate);
				$daysDiff = (int)$diff->format('%r%a'); // signed days difference
				
				if ($daysDiff === 0) {
					return 'Timișoara'; // Today
				} elseif ($daysDiff === 1) {
					return 'Centru Logistic'; // Tomorrow
				}
			} catch (\Exception $e) {
				// Invalid date, return empty
			}
		}
		
		// Try to parse RO date format (dd.mm.yyyy hh:mm)
		if (preg_match('/(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}:\d{2})/', $delivery, $matches)) {
			$dateStr = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
			try {
				$deliveryDate = new \DateTime($dateStr);
				$today = new \DateTime();
				$today->setTime(0, 0, 0);
				$deliveryDate->setTime(0, 0, 0);
				
				$diff = $today->diff($deliveryDate);
				$daysDiff = (int)$diff->format('%r%a');
				
				if ($daysDiff === 0) {
					return 'Timișoara'; // Today
				} elseif ($daysDiff === 1) {
					return 'Centru Logistic'; // Tomorrow
				}
			} catch (\Exception $e) {
				// Invalid date, return empty
			}
		}
		
		// Check for text patterns
		$deliveryLower = strtolower($delivery);
		if (strpos($deliveryLower, 'azi') !== false || strpos($deliveryLower, 'today') !== false) {
			return 'Timișoara';
		}
		if (strpos($deliveryLower, 'maine') !== false || strpos($deliveryLower, 'tomorrow') !== false) {
			return 'Centru Logistic';
		}
		
		return '';
	}

	/**
	 * Format Livrare and Depozit for a variant based on supplier
	 * Returns array with keys: livrare, depozit
	 */
	private function formatDeliveryInfo(string $supplier, array $variant): array
	{
		$supplier = strtolower($supplier);
		$deliveryInfo = $variant['delivery']['info_text'] ?? '';
		$plantName = $variant['delivery']['plant_name'] ?? null;
		$depot = $variant['depot'] ?? null;
		$departamentCode = $variant['departamentCode'] ?? null;
		
		$result = [
			'livrare' => '',
			'depozit' => ''
		];

		// Materom
		if ($supplier === 'materom') {
			// Extract time from info_text (e.g., "astăzi la ora 10:00")
			$time = '';
			$text = strtolower(trim($deliveryInfo ?? ''));
			if (preg_match('/(\d{1,2}:\d{2})/', $text, $matches)) {
				$time = ltrim($matches[1], '0');
			}
			
			// Depozit logic
			if ($plantName === 'Timișoara') {
				$result['depozit'] = 'TM';
			} elseif ($plantName === 'Centru Logistic') {
				$result['depozit'] = 'Mures';
			} else {
				$result['depozit'] = $plantName ?: '-';
			}
			
			// Livrare logic
			if ($plantName === 'Timișoara') {
				$result['livrare'] = $time ? "Azi {$time}" : 'azi';
			} elseif ($plantName === 'Centru Logistic') {
				$result['livrare'] = $time ? "Maine {$time}" : 'maine';
			} else {
				$result['livrare'] = $deliveryInfo ?: 'Verifica stoc';
			}
		}
		
		// Elit
		elseif ($supplier === 'elit') {
			$result['livrare'] = 'Verifica stoc';
			$result['depozit'] = '';
		}
		
		// Autopartner
		elseif ($supplier === 'autopartner') {
			// Process DepartmentCode
			$processedDeptCode = $departamentCode ?? '';
			if ($processedDeptCode === 'CN') {
				$processedDeptCode = 'Maine 8:00';
			} elseif ($processedDeptCode === '120' || $processedDeptCode === '72') {
				$processedDeptCode = 'Poimaine 8:00';
			}
			
			$result['livrare'] = $processedDeptCode ?: 'Verifica stoc';
			$result['depozit'] = '';
		}
		
		// Autototal
		elseif ($supplier === 'autototal') {
			// Get warehouse from availability (stored in plant_name or need to get from variant)
			// For Autototal, warehouse comes from availability[0]['warehouse']
			// leavesAt comes from availability[0]['leavesAt'] which is stored in delivery['info_text']
			$warehouse = $plantName ?? ''; // This should be set from availability[0]['warehouse']
			
			// Format leavesAt (stored in delivery['info_text'])
			if (preg_match('/(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}):\d{2}/i', $deliveryInfo, $matches)) {
				$datePart = $matches[1];
				$timePart = ltrim($matches[2], '0');
				
				$deliveryDate = new \DateTime($datePart);
				$today = new \DateTime();
				$today->setTime(0, 0, 0);
				$deliveryDate->setTime(0, 0, 0);
				
				$diff = $today->diff($deliveryDate);
				$daysDiff = (int)$diff->format('%r%a');
				
				if ($daysDiff === 0) {
					$result['livrare'] = $timePart ? "Azi {$timePart}" : 'Azi';
				} elseif ($daysDiff === 1) {
					$result['livrare'] = $timePart ? "Mâine {$timePart}" : 'Mâine';
				} elseif ($daysDiff === 2) {
					$result['livrare'] = $timePart ? "Poimâine {$timePart}" : 'Poimâine';
				} else {
					$result['livrare'] = $timePart ? "{$daysDiff} zile {$timePart}" : "{$daysDiff} zile";
				}
			} else {
				$result['livrare'] = $deliveryInfo ?: 'Verifica stoc';
			}
			
			$result['depozit'] = $warehouse ?: '-';
		}
		
		// Autonet
		elseif ($supplier === 'autonet') {
			// For Autonet, DeliveryDate is in delivery['info_text'] and Code is in depot
			// Format DeliveryDate (stored in delivery['info_text'])
			if (preg_match('/(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}):\d{2}/i', $deliveryInfo, $matches)) {
				$datePart = $matches[1];
				$timePart = ltrim($matches[2], '0');
				
				$deliveryDate = new \DateTime($datePart);
				$today = new \DateTime();
				$today->setTime(0, 0, 0);
				$deliveryDate->setTime(0, 0, 0);
				
				$diff = $today->diff($deliveryDate);
				$daysDiff = (int)$diff->format('%r%a');
				
				if ($daysDiff === 0) {
					$result['livrare'] = $timePart ? "Azi {$timePart}" : 'Azi';
				} elseif ($daysDiff === 1) {
					$result['livrare'] = $timePart ? "Mâine {$timePart}" : 'Mâine';
				} elseif ($daysDiff === 2) {
					$result['livrare'] = $timePart ? "Poimâine {$timePart}" : 'Poimâine';
				} else {
					$result['livrare'] = $timePart ? "{$daysDiff} zile {$timePart}" : "{$daysDiff} zile";
				}
			} else {
				$result['livrare'] = $deliveryInfo ?: 'Verifica stoc';
			}
			
			// Depozit is the Code from DeliveryData
			$result['depozit'] = $depot ?: '-';
		}
		
		return $result;
	}

	/**
	 * @return list<string>
	 */
	private function parseWarehouseSlotsFromCartItem(array $item): array
	{
		$depozit = trim((string) ($item['depozit'] ?? ''));
		if ($depozit === '' || strcasecmp($depozit, '-') === 0) {
			return [];
		}
		$parts = preg_split('/\s*\+\s*/u', $depozit) ?: [];
		$parts = array_values(array_filter(array_map('trim', $parts), static function (string $p): bool {
			return $p !== '';
		}));

		return $parts;
	}

	/**
	 * @return list<array{qty: int, culoare: string, furnizor: string}>
	 */
	private function expandSupplierCartItemForTmpRows(array $item): array
	{
		$supplier = strtolower(trim((string) ($item['supplier'] ?? '')));
		$qty = max(1, (int) ($item['qty'] ?? 1));
		$baseFurnizor = $this->getSupplierCode($supplier);
		$slots = $this->parseWarehouseSlotsFromCartItem($item);

		if (!in_array($supplier, ['autonet', 'autototal'], true) || count($slots) <= 1) {
			$culoare = $this->getColorFromDeliveryInfo($item);

			return [[
				'qty' => $qty,
				'culoare' => $culoare,
				'furnizor' => $baseFurnizor,
			]];
		}

		$out = [];
		for ($i = 0; $i < $qty; $i++) {
			$slotIdx = min($i, count($slots) - 1);
			$depot = $slots[$slotIdx];
			$itemForColor = $item;
			$itemForColor['depozit'] = $depot;
			$itemForColor['qty'] = 1;
			$culoare = $this->getColorFromDeliveryInfo($itemForColor);
			$furnizor = $baseFurnizor;
			if ($depot !== '') {
				$furnizor .= ' / ' . $depot;
			}
			$out[] = [
				'qty' => 1,
				'culoare' => $culoare,
				'furnizor' => $furnizor,
			];
		}

		return $out;
	}

	private function addProductsToDbAndTemp(array $items)
	{
		$session_id = session()->getId();

		foreach ($items as $item) {
			$produs = Produse::updateOrCreate(
				['cod_produs' => $item['product_code']],
				[
					'denumire' => $item['product_name'],
					'pret' => $item['price'],
					'created_at' => Carbon::now()->timestamp + (2 * 3600)
				]
			);

			Tmp::where('session_id', $session_id)
				->where('id_produs', $produs->idprodus)
				->delete();

			$rows = $this->expandSupplierCartItemForTmpRows($item);
			foreach ($rows as $row) {
				Tmp::create([
					'session_id' => $session_id,
					'id_produs' => $produs->idprodus,
					'cantitate_tmp' => $row['qty'],
					'pret_tmp' => $item['price'],
					'culoare' => $row['culoare'],
					'furnizor' => $row['furnizor'],
				]);
			}
		}
	}
	
	private function resolveProductName(array &$product): void
	{
		if (!empty($product['name'])) {
			return;
		}

		if (!empty($product['db_name'])) {
			$product['name'] = $product['db_name'];
			return;
		}

		if (!empty($product['manufacturer'])) {
			$product['name'] = $product['manufacturer'];
		}
	}
	
	/**
	 * Calculate pricing for a variant based on supplier
	 * Returns array with raw_price, calculated_price, and price_breakdown
	 */
	private function calculateVariantPricing(string $supplier, float $rawPrice, string $currency = 'RON', array $variant = []): array
	{
		$supplier = strtolower($supplier);
		
		// Store original price before any adjustments
		$originalPrice = $rawPrice;
		
		// Default markup multiplier (35%)
		$markupMultiplier = 1.35;
		
		// Handle Autopartner special pricing logic
		if ($supplier === 'autopartner') {
			$markupMultiplier = 1.45; // 45% markup for Autopartner
			
			// Check if deposit is included in price
			$depositIncluded = $variant['deposit_included'] ?? false;
			$depositPrice = (float) ($variant['deposit_price'] ?? 0);
			
			if ($depositIncluded && $depositPrice > 0) {
				// Real price = Price - DepositPrice (for calculation purposes)
				$rawPrice = $rawPrice - $depositPrice;
			}
		}
		
		// Handle ELIT special pricing logic
		if ($supplier === 'elit') {
			$markupMultiplier = 1.40; // 40% markup for ELIT
		}
		
		// Base acquisition price (with VAT)
		$acquisitionPrice = ceil($rawPrice * 1.21);
		
		// Final selling price: (base price) * 1.21 (VAT) * markup multiplier
		$calculatedPrice = ceil($rawPrice * 1.21 * $markupMultiplier);
		
		// Price breakdown for tooltip
		$priceBreakdown = [
			'acquisition' => $acquisitionPrice,
			'plus_10' => ceil($acquisitionPrice * 1.10),
			'plus_20' => ceil($acquisitionPrice * 1.20),
			'plus_30' => ceil($acquisitionPrice * 1.30),
			'final' => $calculatedPrice
		];
		
		return [
			'raw_price' => $rawPrice, // Adjusted price (after deposit deduction if applicable)
			'original_price' => $originalPrice, // Original price from API (before deposit deduction)
			'calculated_price' => $calculatedPrice,
			'acquisition_price' => $acquisitionPrice,
			'price_breakdown' => $priceBreakdown,
			'currency' => $currency
		];
	}
	
	/**
	 * Apply pricing calculations to all variants in a product
	 */
	private function applyPricingToProduct(array &$product): void
	{
		foreach ($product['suppliers'] as $supplierName => &$supplierData) {
			if (!isset($supplierData['variants']) || !is_array($supplierData['variants'])) {
				continue;
			}
			
			foreach ($supplierData['variants'] as &$variant) {
				if (!isset($variant['price'])) {
					continue;
				}
				
				$rawPrice = (float) $variant['price'];
				$currency = $variant['currency'] ?? 'RON';
				
				// Pass the full variant array to allow access to deposit_included and deposit_price
				$pricing = $this->calculateVariantPricing($supplierName, $rawPrice, $currency, $variant);
				
				// Add calculated prices to variant
				$variant['raw_price'] = $pricing['raw_price'];
				$variant['calculated_price'] = $pricing['calculated_price'];
				$variant['acquisition_price'] = $pricing['acquisition_price'];
				$variant['price_breakdown'] = $pricing['price_breakdown'];
				
				// Keep original price and currency for backward compatibility
				// The frontend can now use calculated_price instead of calculating it
				// Store original price if it was adjusted (e.g., Autopartner deposit deduction)
				if (isset($pricing['original_price'])) {
					$variant['original_price'] = $pricing['original_price'];
				}
			}
		}
	}
}