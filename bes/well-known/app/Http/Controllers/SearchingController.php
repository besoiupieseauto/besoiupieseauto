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
use App\Services\SupplierSearchNew\RunSupplierSearchNewAction;

use App\Models\Tmp;
use App\Models\Comenzi;
use App\Models\Produse;
use App\Models\User;
use App\Models\SupplierSavedCart;
use App\Models\SupplierCart;
use App\Models\Employee;
use App\Models\SupplierOrder;
use App\Models\AutototalExcludedCartItem;
use App\Models\Promotion;
use App\Models\MessageTemplate;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Helpers\RotatedPdf;
use Illuminate\Support\Facades\Http;

use Carbon\Carbon;

class SearchingController extends Controller
{
	protected $materomService;
	protected $supplierSearchNewAction;
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
		AutoPartnerService $autoPartnerService,
		RunSupplierSearchNewAction $supplierSearchNewAction
	)
    {
		$this->materomService = $materomService;
		$this->elitService = $elitService;
		$this->autonetService = $autonetService;
		$this->autototalService = $autototalService;
		$this->autoPartnerService = $autoPartnerService;
		$this->supplierSearchNewAction = $supplierSearchNewAction;
    }
	
    public function index()
    {	
							/* $items[] = [
								'PartNo'   => '3189 000 025',
								'Quantity' => 2
							];
						
							$items[] = [
								'TDBrandId'   => 101,
								'TDArticleNo' => '181371',
								'Quantity'    => 1
							]; */
/* 		$items[] = [
								'PartNo'   => 'FO15CF54',
								'Quantity' => 2
							];
						$response = $this->autonetService->getDeliveryData($items);
						dd($response); */
/* 			$response = $this->materomService->partSearchV4('11562172');
						echo "<pre>";print_R($response);die('asd'); */
						
/* 				$v2Products[] = [
					'productCode' => '029NS219910001',
					'quantity' => 1
				];
				
				$apiResponse = $this->autoPartnerService
						->productsAvailabilityV2($v2Products, false);
					dd($apiResponse); */
					
/* 				$response = $this->autototalService
								->checkAvailability('309152NRF', 2);
							dd($response); */
							
							
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
					->where('code_parts', $query)
					->get();

				$v2Products = [];

				foreach ($rows as $row) {
					// Raw code as stored in DB (may contain dots / spaces)
					$rawMainCode = $row->mainart_code_parts ?? '';

					// Code used for calling the AutoPartner API – keep original
					$apiCodeBase = trim(str_replace(' ', '', $rawMainCode));

					// Normalized base code used for grouping across suppliers:
					// remove brand-specific TH prefix (for CALORSTAT by Vernet),
					// then strip dots, spaces and common separators so that
					// "13.04607203.2" and "13046072032" end up identical.
					$normalizedBase = $rawMainCode;
					if (strcasecmp($row->mainart_brands ?? '', 'CALORSTAT by Vernet') === 0) {
						$normalizedBase = preg_replace('/^TH/i', '', $normalizedBase);
					}
					$normalizedBase = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $normalizedBase);
					$code = trim($normalizedBase);

					if ($code === '') {
						continue;
					}

					// Canonical code (digits only) is what we want to show in UI
					$originalCodeMap[$code] = $code;

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
						'productCode' => $this->autoPartnerService->applyPrefix($row->mainart_brands, $apiCodeBase),
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

					foreach ($availabilityList as $item) {

						$apiCode = $item['ProductCode'] ?? '';
						// Strip FE / brand prefixes, then remove dots / spaces / separators
						$normalizedFromApi = $this->autoPartnerService->normalizeCode($apiCode);
						$code = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $normalizedFromApi); // 🔴 normalize for grouping

						if (!isset($productsMap[$code])) {
							// Try to find in database using normalized code
							$dbRow = DB::table('parts_catalog')
								->where('code_parts', $code)
								->orWhere('mainart_code_parts', $code)
								->first();
							
							// If not found, try matching with normalized base code (without spaces/dashes)
							if (!$dbRow) {
								$dbRow = DB::table('parts_catalog')
									->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(code_parts, ' ', ''), '-', ''), '/', ''), '|', ''), '\\\\', '') = ?", [$code])
									->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mainart_code_parts, ' ', ''), '-', ''), '/', ''), '|', ''), '\\\\', '') = ?", [$code])
									->first();
							}
							
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
								$normalizedFromDb = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) ($dbRow->mainart_code_parts ?? ''));
								$originalCodeMap[$code] = $normalizedFromDb ?: $code;
							}
						} else {
							// Update manufacturer and db_name if they're null and we can find them in DB
							if ((empty($productsMap[$code]['manufacturer']) || empty($productsMap[$code]['db_name'])) && !isset($originalCodeMap[$code])) {
								$dbRow = DB::table('parts_catalog')
									->where('code_parts', $code)
									->orWhere('mainart_code_parts', $code)
									->first();
								
								if (!$dbRow) {
									$dbRow = DB::table('parts_catalog')
										->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(code_parts, ' ', ''), '-', ''), '/', ''), '|', ''), '\\\\', '') = ?", [$code])
										->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mainart_code_parts, ' ', ''), '-', ''), '/', ''), '|', ''), '\\\\', '') = ?", [$code])
										->first();
								}
								
								if ($dbRow) {
									if (empty($productsMap[$code]['manufacturer']) && $dbRow->mainart_brands) {
										$productsMap[$code]['manufacturer'] = $dbRow->mainart_brands;
									}
									if (empty($productsMap[$code]['db_name']) && $dbRow->mainart_name) {
										$productsMap[$code]['db_name'] = $dbRow->mainart_name;
									}
									$normalizedFromDb = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) ($dbRow->mainart_code_parts ?? ''));
									$originalCodeMap[$code] = $normalizedFromDb ?: $code;
								}
							}
						}

						// ✅ ALWAYS override if API gives name
						if (!empty($item['ProductName'] ?? null)) {
							$productsMap[$code]['name'] = $item['ProductName'];
						} elseif (empty($productsMap[$code]['name']) && !empty($productsMap[$code]['db_name'])) {
							// Use db_name if API doesn't provide name
							$productsMap[$code]['name'] = $productsMap[$code]['db_name'];
						}
						
						if($query == $code){
							//dd([$query, $code]);
							$productsMap[$code]['name'] = 'null';
							$productsMap[$code]['manufacturer'] = '-';
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
						
							// Use normalized canonical code if available, otherwise fall back to normalized API code
							$orderCode = $originalCodeMap[$code] ?? $code;
							
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
			   - Use the same normalized code key as other suppliers so Materom
			     products combine with Elit, Autototal, etc. when they have the same part code.
			========================= */
			if (in_array('materom', $selectedSuppliers)) {

				$response = $this->materomService->partSearchV4($query);
				$materomProducts = $response['body'] ?? [];

				foreach ($materomProducts as $product) {
					$baseCode = trim($product['mfrpn'] ?? '');
					$baseCode = str_replace([' ', '-', '/', '|', '\\'], '', $baseCode);

					if ($baseCode === '') {
						continue;
					}

					$code = $baseCode;

					if (!isset($productsMap[$code])) {
						if (!isset($originalCodeMap[$code])) {
							$originalCodeMap[$code] = $product['mfrpn'] ?? $baseCode;
						}
						$productsMap[$code] = [
							'mfrpn'        => $baseCode,
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

					// Materom name/manufacturer only if not already set by another supplier
					if (empty($productsMap[$code]['name']) && !empty($product['name'])) {
						$productsMap[$code]['name'] = $product['name'];
					}
					if (empty($productsMap[$code]['manufacturer']) && !empty($product['manufacturer'])) {
						$productsMap[$code]['manufacturer'] = $product['manufacturer'];
					}

					// ✅ FILTER OUT RESEALED VARIANTS
					$variants = $product['pricingVariants'] ?? [];
					$variants = array_filter($variants, function($v) {
						return empty($v['is_resealed']) || $v['is_resealed'] != 1;
					});

					// Accumulate Materom variants into existing product (may already have Elit, Autototal, etc.)
					$existingVariants = $productsMap[$code]['suppliers']['materom']['variants'] ?? [];
					$productsMap[$code]['suppliers']['materom']['variants'] = array_values(
						array_merge($existingVariants, $variants)
					);
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
				
				// 1️⃣ Fetch matching parts from DB
				$rows = DB::table('parts_catalog')
					->where('code_parts', $query)
					->whereIn('mainart_brands', $brands)
					->get();

				// 2️⃣ Prepare items for AutoNet API
				$items  = [];
				$rowMap = []; // baseCode => DB row
				$mainartCodes = []; // collect mainart_code_parts

				if (!$rows->isEmpty()) {
					foreach ($rows as $row) {
						$baseCode = $row->mainart_code_parts;
						$mainartCodes[] = $baseCode;

						if (!empty($row->brand_id)) {
							$items[] = [
								'TDBrandId'   => $row->brand_id,
								'TDArticleNo' => $row->mainart_code_parts,
								'Quantity'    => 1
							];
						} else {
							$items[] = [
								'PartNo'   => $row->mainart_code_parts,
								'Quantity' => 1
							];
						}

						$rowMap[$baseCode] = $row;
					}
				} else {
					// No matching parts in parts_catalog for this query.
					// If the searched code exists in autonet_qwp_data, create a synthetic rowMap entry
					// so that Autonet results for pure QWP codes (e.g. WBP249) still produce a product.
					$qwpDirectRow = DB::table('autonet_qwp_data')
						->where('ArtNr', $query)
						->orWhere('RefNr', $query)
						->first();

					if ($qwpDirectRow) {
						$fakeRow = (object) [
							'mainart_code_parts' => $query,
							'mainart_brands'     => 'QWP',
							'mainart_name'       => 'QWP',
							'brand_id'           => null,
						];

						$rowMap[$query] = $fakeRow;
					}
				}
				
				$qwpRows = collect();

				if (!$rows->isEmpty()) {
					foreach ($rows as $row) {
						$matchedRows = DB::table('autonet_qwp_data')
							->where('RefNr', $row->mainart_code_parts)
							->where('ReferenceBrand', $row->mainart_brands)
							->get();

						if (!$matchedRows->isEmpty()) {
							$qwpRows = $qwpRows->merge($matchedRows);
						}
					}
				}

				if (!$qwpRows->isEmpty()) {
					foreach ($qwpRows as $qRow) {
						$items[] = [
							'PartNo'   => $qRow->ArtNr,
							'Quantity' => 1
						];
					}
				}

				// 3️⃣ Always also add searched code so Autonet is queried even when part is not in DB
				$items[] = [
					'PartNo'   => $query,
					'Quantity' => 1
				];
				
				//Here it's taking 6 secs

				// If for some reason items is still empty (defensive), skip AutoNet
				if (empty($items)) {
					// nothing for autonet
				} else {

					// 4️⃣ Split into chunks of 50 (AutoNet limit)
					$itemChunks = array_chunk($items, 50);

					foreach ($itemChunks as $chunk) {

						$response = $this->autonetService->getDeliveryData($chunk);
						//echo "<pre>";print_R($response);

						// Skip failed chunks, don't kill whole flow
						if (empty($response) || !is_array($response)) {
							continue;
						}

						// 5️⃣ EACH response item is a MAIN PART
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
							
							// If not found, look up in database using normalized code
							// First try direct match, then try normalized comparison
							if (!$row) {
								// Try direct match first with normalized code
								$dbRow = DB::table('parts_catalog')
									->where('code_parts', $normalizedCode)
									->orWhere('mainart_code_parts', $normalizedCode)
									->first();
								
								if ($dbRow) {
									$row = $dbRow;
									$originalCodeMap[$normalizedBaseCode] = $dbRow->mainart_code_parts;
								} else {
									// Try matching with normalized base code (without spaces/dashes)
									$dbRow = DB::table('parts_catalog')
										->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(code_parts, ' ', ''), '-', ''), '/', ''), '|', ''), '\\\\', '') = ?", [$normalizedBaseCode])
										->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mainart_code_parts, ' ', ''), '-', ''), '/', ''), '|', ''), '\\\\', '') = ?", [$normalizedBaseCode])
										->first();
									
									if ($dbRow) {
										$row = $dbRow;
										$originalCodeMap[$normalizedBaseCode] = $dbRow->mainart_code_parts;
									} else {
										// If direct match fails, query a subset and compare normalized codes
										// Query rows where code_parts or mainart_code_parts might match after normalization
										$searchPrefix = substr($normalizedBaseCode, 0, min(5, strlen($normalizedBaseCode)));
										$dbRows = DB::table('parts_catalog')
											->where('code_parts', 'LIKE', $searchPrefix . '%')
											->orWhere('mainart_code_parts', 'LIKE', $searchPrefix . '%')
											->limit(200)
											->get();
										
										// Find matching row by comparing normalized codes
										foreach ($dbRows as $dbRow) {
											// Normalize DB code using AutonetService to handle prefixes/suffixes
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
							}
							
							// If we still couldn't find a matching DB row, create a generic product entry
							if (!$row) {
								if (!isset($productsMap[$normalizedBaseCode])) {
									$productsMap[$normalizedBaseCode] = [
										'mfrpn'         => $normalizedBaseCode,
										'manufacturer'  => null,
										'db_name'       => null,
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
								// Ensure we have an original code mapping at least to the normalized API code
								if (!isset($originalCodeMap[$normalizedBaseCode])) {
									$originalCodeMap[$normalizedBaseCode] = $normalizedCode;
								}
							} else {
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
							}

							// If manufacturer and db_name still not found, try autonet_qwp_data
							// This covers cases when user searches a QWP code directly (either ArtNr or RefNr),
							// so we can still show the result even if there is no matching parts_catalog row.
							if (empty($productsMap[$normalizedBaseCode]['manufacturer']) && empty($productsMap[$normalizedBaseCode]['db_name'])) {
								$qwpRow = DB::table('autonet_qwp_data')
									->where('ArtNr', $normalizedCode)
									->orWhere('ArtNr', $normalizedBaseCode)
									->orWhere('RefNr', $normalizedCode)
									->orWhere('RefNr', $normalizedBaseCode)
									->first();

								if ($qwpRow) {
									$productsMap[$normalizedBaseCode]['manufacturer'] = 'QWP';
									$productsMap[$normalizedBaseCode]['db_name'] = 'QWP';
								}
							}

							// 6️⃣ DeliveryData = variants
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
								$rows = array_values(array_filter($deliveryData, static function ($d): bool {
									return is_array($d);
								}));
								if (count($rows) === 1) {
									$d = $rows[0];
									$productsMap[$normalizedBaseCode]['suppliers']['autonet']['variants'][] = [
										'supplier_stock' => (int) ($d['Quantity'] ?? 0),
										'price'          => (float) ($article['PriceWoVat'] ?? 0),
										'currency'       => $article['Currency'] ?? 'RON',
										'order_code'     => $orderCode,
										'depot'          => $d['Code'] ?? '',
										'is_blocked'     => false,
										'delivery'       => [
											'info_text'  => $d['DeliveryDate'] ?? '',
											'plant_name' => null
										],
										'multiple_qty'   => 1
									];
								} else {
									$aggregatedQty = 0;
									$codes = [];
									$firstDate = '';
									foreach ($rows as $d) {
										$aggregatedQty += (int) ($d['Quantity'] ?? 0);
										$c = trim((string) ($d['Code'] ?? ''));
										if ($c !== '') {
											$codes[$c] = true;
										}
										if ($firstDate === '' && !empty($d['DeliveryDate'])) {
											$firstDate = (string) $d['DeliveryDate'];
										}
									}
									$productsMap[$normalizedBaseCode]['suppliers']['autonet']['variants'][] = [
										'supplier_stock' => $aggregatedQty,
										'price'          => (float) ($article['PriceWoVat'] ?? 0),
										'currency'       => $article['Currency'] ?? 'RON',
										'order_code'     => $orderCode,
										'depot'          => $codes !== [] ? implode(' + ', array_keys($codes)) : '',
										'is_blocked'     => false,
										'delivery'       => [
											'info_text'  => $firstDate,
											'plant_name' => null
										],
										'multiple_qty'   => 1
									];
								}
							}
						}
					}
				}
			}
			
			
			/* =========================
			   AUTOTOTAL
			========================= */
			if (in_array('autototal', $selectedSuppliers)) {
				$seenAutoTotalVariants = [];
				$rows = DB::table('parts_catalog')
					->where('code_parts', $query)
					->get();

				// Also search with searched code directly (use raw query for API),
				// but normalize to a canonical base code (digits-only) for grouping
				$response = $this->autototalService->checkAvailability($rawQuery, 1);
				if (
					!empty($response['webApiResponse']['status']) &&
					$response['webApiResponse']['status'] == 1
				) {
					// Canonical key used to group Autototal with other suppliers:
					//  - remove brand/suffix artifacts via normalizeCode
					//  - then strip dots, spaces and common separators
					$normalized = $this->autototalService->normalizeCode($rawQuery);
					$baseCode = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $normalized);
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
					foreach ($rows as $row) {
						$baseCode = str_replace([' ', '-', '/', '|', '\\'], '', $row->mainart_code_parts);
						$originalCode = $row->mainart_code_parts;
						if ($row->mainart_brands === 'INA') {
							$originalCode = str_replace(' ', '', $originalCode);
						}
						
						// Map normalized code to original code
						$originalCodeMap[$baseCode] = $originalCode;

						// Build list of itemkeys to send to AutoTotal API: from autototal_data + from autototal_branduri_proprii (cod_sursa = mainart_code_parts)
						$itemsToQuery = []; // [ ['itemkey' => x, 'sup_brand' => y|null], ... ]
						$itemkeyFromData = $this->findAutototalItemkey($row->mainart_code_parts ?? '');
						if (!$itemkeyFromData && $originalCode !== ($row->mainart_code_parts ?? '')) {
							$itemkeyFromData = $this->findAutototalItemkey($originalCode);
						}
						if ($itemkeyFromData !== null && $itemkeyFromData !== '') {
							$itemsToQuery[$itemkeyFromData] = ['itemkey' => $itemkeyFromData, 'sup_brand' => null];
						}
						foreach ($this->getAutototalBranduriProprii($row->mainart_code_parts ?? '') as $bp) {
							$ik = $bp['itemkey'];
							$itemsToQuery[$ik] = ['itemkey' => $ik, 'sup_brand' => $bp['sup_brand'] ?? null];
						}
						if ($originalCode !== ($row->mainart_code_parts ?? '')) {
							foreach ($this->getAutototalBranduriProprii($originalCode) as $bp) {
								$ik = $bp['itemkey'];
								$itemsToQuery[$ik] = ['itemkey' => $ik, 'sup_brand' => $bp['sup_brand'] ?? null];
							}
						}
						$itemsToQuery = array_values($itemsToQuery);

						if (empty($itemsToQuery)) {
							continue; // no itemkey from autototal_data nor autototal_branduri_proprii
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

						foreach ($itemsToQuery as $item) {
							$itemkey = $item['itemkey'];
							$supBrand = $item['sup_brand'];
							

							$response = $this->autototalService
								->checkAvailability($itemkey, 1);
							//echo "<pre>";print_R([$itemkey,$response]);

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

							// For branduri_proprii codes: use API manufacturer or fallback to sup_brand
							$mainManufacturer = !empty($response['searchCode']['manufacturer'])
								? trim($response['searchCode']['manufacturer'])
								: ($supBrand !== null && $supBrand !== '' ? $supBrand : null);

							// Products from autototal_branduri_proprii get their own parent row (unique code = baseCode|brand)
							$isFromBranduriProprii = ($supBrand !== null && $supBrand !== '');
							$productKey = $baseCode;
							if ($isFromBranduriProprii) {
								$brandLabel = trim($mainManufacturer ?? $supBrand ?? 'unknown');
								$productKey = $baseCode . '|' . $brandLabel;
							}

							// Ensure product row exists for this key (new row for branduri_proprii)
							if (!isset($productsMap[$productKey])) {
								// For branduri_proprii: show API supplierCode in parent row (e.g. O0299DREIS), not the internal key
								$displayMfrpn = $productKey;
								if ($isFromBranduriProprii && !empty($response['searchCode']['supplierCode'])) {
									$displayMfrpn = trim($response['searchCode']['supplierCode']);
									$originalCodeMap[$productKey] = $displayMfrpn;
								}
								$productsMap[$productKey] = [
									'mfrpn'         => $displayMfrpn,
									'manufacturer'  => $isFromBranduriProprii ? ($mainManufacturer ?? $supBrand) : ($row->mainart_brands ?? null),
									'db_name'       => $isFromBranduriProprii ? ($response['searchCode']['name'] ?? null) : ($row->mainart_name ?? null),
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
								$finalOrderCode = $originalCodeMap[$productKey] ?? $originalCodeMap[$baseCode] ?? $normalizedOrderCode;
								$variantCodeForOrder = $itemkey ?: $orderCode;
								
								$price     = (float) ($response['searchCode']['price'] ?? 0);
								$uniqueKey = $orderCode . '|' . $price;

								if (!isset($seenAutoTotalVariants[$productKey][$uniqueKey])) {
									$seenAutoTotalVariants[$productKey][$uniqueKey] = true;

									$productsMap[$productKey]['suppliers']['autototal']['variants'][] = [
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
										'manufacturer'  => $mainManufacturer ?? $response['searchCode']['manufacturer'] ?? null,
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

									if (isset($seenAutoTotalVariants[$productKey][$uniqueKey])) {
										continue;
									}

									$seenAutoTotalVariants[$productKey][$uniqueKey] = true;

									$crossManufacturer = !empty($cross['manufacturer']) ? trim($cross['manufacturer']) : $supBrand;

									$productsMap[$productKey]['suppliers']['autototal']['variants'][] = [
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
										'manufacturer'  => $crossManufacturer ?? null,
										'exchangePart'  => $response['searchCode']['exchangePart'] ?? '',
										'priceEP'       => (float) ($response['searchCode']['priceEP'] ?? 0),
										'is_main_result' => false
									];
								}
							}
						} // end foreach ($itemsToQuery as $item)
					}//die('asd');
				}
			}
			
			
			/* =========================
			   ELIT
			========================= */
			if (in_array('elit', $selectedSuppliers)) {

				// Also search with searched code directly
				$elitRows = DB::table('lkq_prices')
					->where('supplier_catalog_nr', $rawQuery)
					->get();

				if (!$elitRows->isEmpty()) {
					$baseCode = $query;
					$firstElitRow = $elitRows->first();

					if (!isset($productsMap[$baseCode])) {
						$productsMap[$baseCode] = [
							'mfrpn'        => $baseCode,
							'manufacturer' => $firstElitRow->brand_name ?? null,
							'db_name'      => $firstElitRow->description_ro ?? null,
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
					} else {
						if (empty($productsMap[$baseCode]['db_name']) && isset($firstElitRow->description_ro)) {
							$productsMap[$baseCode]['db_name'] = $firstElitRow->description_ro;
						}
						if (empty($productsMap[$baseCode]['manufacturer']) && isset($firstElitRow->brand_name)) {
							$productsMap[$baseCode]['manufacturer'] = $firstElitRow->brand_name;
						}
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
					->where('code_parts', $query)
					->get();

				if (!$rows->isEmpty()) {

					foreach ($rows as $row) {
						$baseCode = str_replace([' ', '-', '/', '|', '\\'], '', $row->mainart_code_parts);
						$originalCode = $row->mainart_code_parts; // Store original unchanged code
						
						if($row->mainart_brands == "ABAKUS"){
							$row->mainart_brands = "DEPO";
						}
						
						// Map normalized code to original code
						$originalCodeMap[$baseCode] = $originalCode;

						// Fetch from lkq_prices using supplier_catalog_nr (join on mainart_code_parts)
						$elitRows = DB::table('lkq_prices')
							->where('supplier_catalog_nr', $row->mainart_code_parts)
							->where('brand_name', $row->mainart_brands)
							->get();

						// Dacă avem match în lkq_prices, folosim description_ro ca nume de produs (în loc de $row->brands)
						$lkqDescription = null;
						if (!$elitRows->isEmpty()) {
							$firstElitRow   = $elitRows->first();
							$lkqDescription = $firstElitRow->description_ro ?? null;
						}

						if (!isset($productsMap[$baseCode])) {
							$productsMap[$baseCode] = [
								'mfrpn'        => $baseCode,
								'manufacturer' => $row->mainart_brands ?? null,
								'db_name'      => $lkqDescription ?? ($row->brands ?? null),
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
						} else {
							// Dacă produsul există deja, suprascriem numele cu description_ro când este disponibil
							if (!empty($lkqDescription)) {
								$productsMap[$baseCode]['db_name'] = $lkqDescription;
							} elseif (empty($productsMap[$baseCode]['db_name']) && isset($row->brands)) {
								$productsMap[$baseCode]['db_name'] = $row->brands;
							}
						}

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

	private function getSupplierCart(): array
	{
		$user = Auth::user();
		$userId = (int) ($user->Id ?? $user->id ?? 0);
		if ($userId <= 0) {
			return [];
		}

		$row = SupplierCart::query()->firstOrCreate(
			['user_id' => $userId],
			['cart' => []]
		);

		$cart = $row->cart;
		return is_array($cart) ? $cart : [];
	}

	private function saveSupplierCart(array $cart): void
	{
		$user = Auth::user();
		$userId = (int) ($user->Id ?? $user->id ?? 0);
		if ($userId <= 0) {
			return;
		}

		SupplierCart::query()->updateOrCreate(
			['user_id' => $userId],
			['cart' => $cart]
		);
	}

	private function clearSupplierCart(): void
	{
		$this->saveSupplierCart([]);
	}

	public function cartAdd(Request $request)
	{
		$request->validate([
			'supplier'      => 'required|string',
			'product_code'  => 'required|string',
			'mfrpn'         => 'nullable|string',
			'product_name'  => 'nullable|string',
			'manufacturer'  => 'nullable|string', 
			'variant_code'  => 'required|string',
			'api_lookup_code' => 'nullable|string',
			'searched_code' => 'nullable|string',
			'qty'           => 'required|integer|min:1',
			'price'         => 'required|numeric',
			'raw_price'     => 'nullable|numeric',
			'currency'      => 'required|string', 
			'plantname'      => 'nullable|string',
			'delivery'      => 'nullable|string',
			'livrare'       => 'nullable|string',
			'depozit'       => 'nullable|string',
			'dot_image_path' => 'nullable|string',
			'departamentcode' => 'nullable|string',
			'autonet_partno' => 'nullable|string',
		]);

		$supplier     = $request->supplier;
		$productCode  = $request->product_code;
		$mfrpn       = trim((string) ($request->input('mfrpn') ?? ''));
		$productName  = trim((string) ($request->input('product_name') ?? ''));
		if ($productName === '') {
			$productName = $productCode;
		}
		$manufacturer = $request->manufacturer ?? '';
		if ($supplier === 'materom') {
			$manufacturer = $this->normalizeMateromManufacturer($manufacturer);
		}
		$variantCode  = $request->variant_code;
		$apiLookupCode = trim((string) ($request->input('api_lookup_code') ?? ''));
		$searchedCode = trim((string) ($request->input('searched_code') ?? ''));
		$qty          = $request->qty;
		$price        = $request->price;
		$rawPrice     = $request->filled('raw_price') ? (float) $request->input('raw_price') : null;
		$currency     = $request->currency;
		$plantname     = $request->plantname;
		$delivery     = $request->delivery ?? '';
		$livrare      = $request->livrare ?? '-';
		$depozit      = $request->depozit ?? '-';
		$dotImagePath = $request->dot_image_path;
		$departamentCode = $request->departamentcode ?? '';
		$autonetPartNo = trim((string) ($request->input('autonet_partno') ?? ''));

		if ($supplier === 'autopartner') {
			$resolvedAp = $this->resolveAutopartnerAvailabilityProductCode([
				'product_code' => (string) $productCode,
				'variant_code' => (string) $variantCode,
				'order_code' => '',
				'qty' => (int) $qty,
			]);
			if ($resolvedAp !== '') {
				$productCode = $resolvedAp;
			}
		}
		if ($apiLookupCode === '') {
			$apiLookupCode = trim((string) $variantCode);
		}
		if ($supplier === 'materom') {
			// For Materom wishlist reload, prefer saved product mfrpn as API lookup code.
			$apiLookupCode = $mfrpn !== '' ? $mfrpn : trim((string) $productCode);
			if ($apiLookupCode === '') {
				$apiLookupCode = trim((string) $variantCode);
			}
		}

		// Get current cart from session
		$cart = $this->getSupplierCart();

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
			if ($apiLookupCode !== '') {
				$cart[$supplier][$cartKey]['api_lookup_code'] = $apiLookupCode;
			}
			if ($searchedCode !== '') {
				$cart[$supplier][$cartKey]['searched_code'] = $searchedCode;
			}
			if ($supplier === 'autonet' && $autonetPartNo !== '') {
				$cart[$supplier][$cartKey]['autonet_partno'] = $autonetPartNo;
			}

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
				'api_lookup_code' => $apiLookupCode,
				'searched_code' => $searchedCode,
				'qty' => $qty,
				'price' => $price,
				'raw_price' => $rawPrice,
				'currency' => $currency,
				'delivery' => $delivery,
				'plantraw' => $plantname,
				'plantname' => $plantImage,
				'livrare' => $livrare,
				'depozit' => $depozit,
				'departamentCode' => $departamentCode,
				'autonet_partno' => $supplier === 'autonet' ? $autonetPartNo : '',

				// IMPORTANT: order_code MUST be Materom order_code
				'order_code' => $this->updateOrderCodeQuantity($variantCode, $qty),
			];
		}

		// Save back to session
		$this->saveSupplierCart($cart);

		return response()->json([
			'message' => 'Product added to cart successfully',
			'cart'    => $cart,
		]);
	}
	
	public function cartShow()
	{
		$cart = $this->getSupplierCart();

		if (!empty($cart['materom']) && is_array($cart['materom'])) {
			$materomChanged = false;
			foreach ($cart['materom'] as $key => $item) {
				if (!is_array($item)) {
					continue;
				}
				$norm = $this->normalizeMateromManufacturer($item['manufacturer'] ?? null);
				if (($item['manufacturer'] ?? '') !== $norm) {
					$cart['materom'][$key]['manufacturer'] = $norm;
					$materomChanged = true;
				}
			}
			if ($materomChanged) {
				$this->saveSupplierCart($cart);
			}
		}

		$autototalExcludedItems = collect();
		$autototalExcludedDailySummary = collect();
		
		$total = 0;
		foreach ($cart as $supplierItems) {
			foreach ($supplierItems as $item) {
				if (!is_array($item)) {
					continue;
				}
				$total += (float) ($item['price'] ?? 0) * (int) ($item['qty'] ?? 1);
			}
		}

		$autototalExcludedItems = AutototalExcludedCartItem::query()
			->where('supplier', 'autototal')
			->orderByDesc('id')
			->get();

			// Refresh latest unit price from AutoTotal Availability API
			// (needed because AutoTotal prices can change over time).
			if ($autototalExcludedItems->isNotEmpty()) {
				$meta = [];
				$seen = [];

				// For each item: try raw itemkey first, then fallback to a cleaned version
				// only if raw failed to return a valid price.
				foreach ($autototalExcludedItems as $item) {
					$rawItemkey = trim((string) ($item->itemkey ?? $item->variant_code ?? ''));
					if ($rawItemkey === '') continue;

					$orderFrom = (string) ($item->order_from ?? 'UTVIN');
					$storageKey = $orderFrom . '|' . $rawItemkey;
					if (isset($seen[$storageKey])) continue;

					$seen[$storageKey] = true;

					// "Cleaning" tries to normalize formats like "09.9464.14" or " 09 9464 14 "
					// into the compact form expected by AutoTotal, but we only use it as a retry.
					$cleanItemkey = preg_replace('/[.\s\-\/|\\\\]+/', '', $rawItemkey);
					if (!is_string($cleanItemkey) || $cleanItemkey === '') {
						$cleanItemkey = $rawItemkey;
					}

					$meta[] = [
						'storageKey' => $storageKey,
						'rawItemkey' => $rawItemkey,
						'cleanItemkey' => $cleanItemkey,
						'orderFrom' => $orderFrom,
					];
				}

				$priceByKey = [];
				$retryMeta = [];
				$chunkSize = 20; // avoid huge concurrent pools

				// 1) Raw try
				for ($offset = 0; $offset < count($meta); $offset += $chunkSize) {
					$chunkMeta = array_slice($meta, $offset, $chunkSize);
					$chunkRequests = array_map(function ($m) {
						return [
							'itemkey' => $m['rawItemkey'],
							'quantity' => 1, // unit price
							'orderFrom' => $m['orderFrom'],
						];
					}, $chunkMeta);

					$responses = $this->autototalService->checkAvailabilityBatch(
						$chunkRequests,
						12,
						4
					);

					foreach ($chunkMeta as $j => $m) {
						$resp = $responses[$j] ?? null;
						if (!is_array($resp)) {
							$retryMeta[] = $m;
							continue;
						}

						$status = $resp['webApiResponse']['status'] ?? null;
						if ((int) $status !== 1) {
							$retryMeta[] = $m;
							continue;
						}

						$price = (float) ($resp['searchCode']['price'] ?? 0);
						if ($price <= 0) {
							$retryMeta[] = $m;
							continue;
						}

						$priceByKey[$m['storageKey']] = $price;
					}
				}

				// 2) Retry cleaned only for items that failed raw.
				//    Avoid retrying if clean == raw.
				$retryMeta2 = [];
				$seenRetry = [];
				foreach ($retryMeta as $m) {
					if (($m['cleanItemkey'] ?? '') === ($m['rawItemkey'] ?? '')) continue;
					if (isset($seenRetry[$m['storageKey']])) continue;
					$seenRetry[$m['storageKey']] = true;
					$retryMeta2[] = $m;
				}

				for ($offset = 0; $offset < count($retryMeta2); $offset += $chunkSize) {
					$chunkMeta = array_slice($retryMeta2, $offset, $chunkSize);
					$chunkRequests = array_map(function ($m) {
						return [
							'itemkey' => $m['cleanItemkey'],
							'quantity' => 1,
							'orderFrom' => $m['orderFrom'],
						];
					}, $chunkMeta);

					$responses = $this->autototalService->checkAvailabilityBatch(
						$chunkRequests,
						12,
						4
					);

					foreach ($chunkMeta as $j => $m) {
						$resp = $responses[$j] ?? null;
						if (!is_array($resp)) continue;

						$status = $resp['webApiResponse']['status'] ?? null;
						if ((int) $status !== 1) continue;

						$price = (float) ($resp['searchCode']['price'] ?? 0);
						if ($price <= 0) continue;

						$priceByKey[$m['storageKey']] = $price;
					}
				}

				// Update in-memory prices so modal totals are using latest price.
				foreach ($autototalExcludedItems as $item) {
					$itemkey = trim((string) ($item->itemkey ?? $item->variant_code ?? ''));
					if ($itemkey === '') continue;

					$orderFrom = (string) ($item->order_from ?? 'UTVIN');
					$k = $orderFrom . '|' . $itemkey;

					if (isset($priceByKey[$k])) {
						$item->price = $priceByKey[$k];
					}
				}
			}

		$autototalExcludedDailySummary = $autototalExcludedItems
			->groupBy(function ($item) {
				$date = optional($item->created_at)->format('Y-m-d') ?? 'N/A';
				$location = trim((string)($item->order_from ?? ''));
				$location = $location !== '' ? $location : '-';
				return $date . '||' . $location;
			})
			->map(function ($items, $groupKey) {
				[$date, $location] = array_pad(explode('||', (string)$groupKey, 2), 2, '-');
				$itemsCount = $items->count();
				$totalAmount = $items->sum(function ($item) {
					$price = (float) ($item->price ?? 0);
					$qty = (int) ($item->qty ?? 1);
					return $price * $qty;
				});
				$currencies = $items
					->pluck('currency')
					->filter()
					->unique()
					->values()
					->implode(', ');

				return [
					'date' => $date,
					'items_count' => $itemsCount,
					'total_amount' => $totalAmount,
					'locations' => $location !== '' ? $location : '-',
					'location_key' => $location !== '' ? $location : '-',
					'currencies' => $currencies !== '' ? $currencies : 'RON',
				];
			})
			->sortByDesc(function ($row) {
				return ($row['date'] ?? '') . '|' . ($row['locations'] ?? '');
			})
			->values();

		$user = Auth::user();
		$canAddSiteProduse = $user && (($user->rol ?? '') === 'manager' || $user->hasPermission('produse'));

		return view('searching.cart', [
			'cart' => $cart,
			'total' => $total,
			'autototalExcludedItems' => $autototalExcludedItems,
			'autototalExcludedDailySummary' => $autototalExcludedDailySummary,
			'canAddSiteProduse' => $canAddSiteProduse,
		]);
	}

	public function loadExcludedAutototalCartByDay(Request $request)
	{
		$request->validate([
			'date' => 'required|date_format:Y-m-d',
			'order_from' => 'nullable|string|max:32',
		]);

		$selectedLocation = trim((string) $request->input('order_from', ''));
		$excludedItemsQuery = AutototalExcludedCartItem::query()
			->where('supplier', 'autototal')
			->whereDate('created_at', $request->date)
			->orderByDesc('id');

		if ($selectedLocation !== '' && $selectedLocation !== '-') {
			$excludedItemsQuery->where('order_from', $selectedLocation);
		} elseif ($selectedLocation === '-') {
			$excludedItemsQuery->where(function ($q) {
				$q->whereNull('order_from')
					->orWhere('order_from', '');
			});
		}

		$excludedItems = $excludedItemsQuery->get();

		if ($excludedItems->isEmpty()) {
			return redirect()->back()->with('error', 'No excluded AutoTotal items found for selected date');
		}

		return redirect()->route('searching.excludedAutototalCartShow', [
				'date' => $request->date,
				'order_from' => $selectedLocation,
			])
			->with('success', 'Excluded AutoTotal items loaded in dedicated cart');
	}

	/**
	 * Remove all persisted excluded AutoTotal rows for one summary row (same calendar day + order_from as in modal).
	 */
	public function deleteSavedAutototalExcludedDay(Request $request)
	{
		$request->validate([
			'date' => 'required|date_format:Y-m-d',
			'order_from' => 'nullable|string|max:32',
		]);

		$selectedLocation = trim((string) $request->input('order_from', ''));
		$query = AutototalExcludedCartItem::query()
			->where('supplier', 'autototal')
			->whereDate('created_at', $request->date);

		if ($selectedLocation !== '' && $selectedLocation !== '-') {
			$query->where('order_from', $selectedLocation);
		} elseif ($selectedLocation === '-') {
			$query->where(function ($q) {
				$q->whereNull('order_from')
					->orWhere('order_from', '');
			});
		}

		$deleted = $query->delete();

		if ($deleted === 0) {
			return redirect()->back()->with('warning', 'Nu s-au găsit înregistrări de șters pentru această dată și locație.');
		}

		return redirect()->back()->with('success', 'Intrările excluse AutoTotal pentru această dată au fost șterse (' . $deleted . ').');
	}

	public function excludedAutototalCartShow(Request $request)
	{
		$selectedDate = $request->query('date');
		$selectedOrderFrom = trim((string) $request->query('order_from', ''));

		$excludedItemsQuery = AutototalExcludedCartItem::query()
			->where('supplier', 'autototal')
			->orderByDesc('id');

		if (!empty($selectedDate)) {
			$excludedItemsQuery->whereDate('created_at', $selectedDate);
		}

		if ($selectedOrderFrom !== '' && $selectedOrderFrom !== '-') {
			$excludedItemsQuery->where('order_from', $selectedOrderFrom);
		} elseif ($selectedOrderFrom === '-') {
			$excludedItemsQuery->where(function ($q) {
				$q->whereNull('order_from')
					->orWhere('order_from', '');
			});
		}

		$excludedCart = $excludedItemsQuery->get()->map(function ($excludedItem) {
			return [
				'id' => $excludedItem->id,
				'cart_item_key' => $excludedItem->cart_item_key,
				'supplier' => 'autototal',
				'product_code' => $excludedItem->product_code,
				'product_name' => $excludedItem->product_name,
				'manufacturer' => $excludedItem->manufacturer,
				'variant_code' => $excludedItem->variant_code,
				'itemkey' => $excludedItem->itemkey ?? $excludedItem->variant_code,
				'qty' => (int)($excludedItem->qty ?? 1),
				'price' => (float)($excludedItem->price ?? 0),
				'currency' => $excludedItem->currency ?: 'RON',
				'stock' => $excludedItem->stock ?? $excludedItem->supplier_stock ?? null,
				'livrare' => $excludedItem->livrare ?? null,
				'depozit' => $excludedItem->depozit ?? $excludedItem->plantname ?? null,
				'order_from' => $excludedItem->order_from ?: 'UTVIN',
			];
		})->values();

		// Refresh latest unit price from AutoTotal Availability API (unit price).
		if ($excludedCart->isNotEmpty()) {
			$meta = [];
			$seen = [];

			foreach ($excludedCart as $item) {
				$rawItemkey = trim((string) ($item['itemkey'] ?? $item['variant_code'] ?? ''));
				if ($rawItemkey === '') continue;

				$orderFrom = (string) ($item['order_from'] ?? 'UTVIN');
				$storageKey = $orderFrom . '|' . $rawItemkey;
				if (isset($seen[$storageKey])) continue;
				$seen[$storageKey] = true;

				$cleanItemkey = preg_replace('/[.\s\-\/|\\\\]+/', '', $rawItemkey);
				if (!is_string($cleanItemkey) || $cleanItemkey === '') {
					$cleanItemkey = $rawItemkey;
				}

				$meta[] = [
					'storageKey' => $storageKey,
					'rawItemkey' => $rawItemkey,
					'cleanItemkey' => $cleanItemkey,
					'orderFrom' => $orderFrom,
				];
			}

			$priceByKey = [];
			$retryMeta = [];
			$chunkSize = 20;

			// 1) Raw try
			for ($offset = 0; $offset < count($meta); $offset += $chunkSize) {
				$chunkMeta = array_slice($meta, $offset, $chunkSize);
				$chunkRequests = array_map(function ($m) {
					return [
						'itemkey' => $m['rawItemkey'],
						'quantity' => 1,
						'orderFrom' => $m['orderFrom'],
					];
				}, $chunkMeta);

				$responses = $this->autototalService->checkAvailabilityBatch(
					$chunkRequests,
					12,
					4
				);

				foreach ($chunkMeta as $j => $m) {
					$resp = $responses[$j] ?? null;
					if (!is_array($resp)) {
						$retryMeta[] = $m;
						continue;
					}

					$status = $resp['webApiResponse']['status'] ?? null;
					if ((int) $status !== 1) {
						$retryMeta[] = $m;
						continue;
					}

					$price = (float) ($resp['searchCode']['price'] ?? 0);
					if ($price <= 0) {
						$retryMeta[] = $m;
						continue;
					}

					$priceByKey[$m['storageKey']] = $price;
				}
			}

			// 2) Retry cleaned where clean != raw
			$retryMeta2 = [];
			$seenRetry = [];
			foreach ($retryMeta as $m) {
				if (($m['cleanItemkey'] ?? '') === ($m['rawItemkey'] ?? '')) continue;
				if (isset($seenRetry[$m['storageKey']])) continue;
				$seenRetry[$m['storageKey']] = true;
				$retryMeta2[] = $m;
			}

			for ($offset = 0; $offset < count($retryMeta2); $offset += $chunkSize) {
				$chunkMeta = array_slice($retryMeta2, $offset, $chunkSize);
				$chunkRequests = array_map(function ($m) {
					return [
						'itemkey' => $m['cleanItemkey'],
						'quantity' => 1,
						'orderFrom' => $m['orderFrom'],
					];
				}, $chunkMeta);

				$responses = $this->autototalService->checkAvailabilityBatch(
					$chunkRequests,
					12,
					4
				);

				foreach ($chunkMeta as $j => $m) {
					$resp = $responses[$j] ?? null;
					if (!is_array($resp)) continue;

					$status = $resp['webApiResponse']['status'] ?? null;
					if ((int) $status !== 1) continue;

					$price = (float) ($resp['searchCode']['price'] ?? 0);
					if ($price <= 0) continue;

					$priceByKey[$m['storageKey']] = $price;
				}
			}

			$excludedCart = $excludedCart->map(function ($item) use ($priceByKey) {
				$itemkey = trim((string) ($item['itemkey'] ?? $item['variant_code'] ?? ''));
				if ($itemkey === '') return $item;

				$orderFrom = (string) ($item['order_from'] ?? 'UTVIN');
				$k = $orderFrom . '|' . $itemkey;

				if (isset($priceByKey[$k])) {
					$item['price'] = $priceByKey[$k];
				}
				return $item;
			});
		}

		$total = $excludedCart->sum(function ($item) {
			$price = (float)($item['price'] ?? 0);
			$qty = (int)($item['qty'] ?? 1);
			return $price * $qty;
		});

		$availableLocations = $excludedCart
			->pluck('order_from')
			->filter()
			->unique()
			->values();

		$defaultOrderFrom = $availableLocations->first() ?: 'UTVIN';

		return view('searching.excluded_autototal_cart', [
			'excludedCart' => $excludedCart,
			'total' => $total,
			'selectedDate' => $selectedDate,
			'selectedOrderFrom' => $selectedOrderFrom,
			'availableLocations' => $availableLocations,
			'defaultOrderFrom' => $defaultOrderFrom,
		]);
	}

	public function placeExcludedAutototalOrder(Request $request)
	{
		$request->validate([
			'order_from' => 'required|in:UTVIN,TIMISOARA',
			'selected_item_ids' => 'required|array|min:1',
			'selected_item_ids.*' => 'required|integer',
			'date' => 'nullable|date_format:Y-m-d',
			'order_from_context' => 'nullable|string|max:32',
		]);

		$redirectParams = array_filter([
			'date' => $request->input('date'),
			'order_from' => $request->input('order_from_context'),
		], fn ($v) => $v !== null && $v !== '');

		$selectedIds = collect($request->input('selected_item_ids', []))
			->map(fn ($id) => (int) $id)
			->filter(fn ($id) => $id > 0)
			->unique()
			->values();

		$itemsToOrder = AutototalExcludedCartItem::query()
			->where('supplier', 'autototal')
			->whereIn('id', $selectedIds->all())
			->orderBy('id')
			->get();

		if ($itemsToOrder->isEmpty()) {
			return redirect()->back()->with('error', 'Please select at least one item to place the order');
		}

		$orderItems = [];
		foreach ($itemsToOrder as $item) {
			$resolvedItemkey = $this->resolveAutototalOrderItemkeyFromCartItem([
				'product_code' => $item->product_code ?? '',
				'manufacturer' => $item->manufacturer ?? '',
				'variant_code' => $item->variant_code ?? '',
			]);
			$itemkeyToOrder = trim((string) ($resolvedItemkey ?? ''));
			if ($itemkeyToOrder === '') {
				$itemkeyToOrder = trim((string) ($item->variant_code ?? ''));
			}
			if ($itemkeyToOrder === '') {
				$itemkeyToOrder = trim((string) ($item->product_code ?? ''));
			}
			if ($itemkeyToOrder === '') {
				$label = $item->product_name ?? $item->product_code ?? 'product';
				return redirect()->back()->with('error', "Missing order code for {$label} (AutoTotal)");
			}

			$orderItems[] = [
				'ITEMKEY' => $itemkeyToOrder,
				'QUANTITY' => (int)($item->qty ?? 1),
			];
		}
		//dd($orderItems);

		try {
			$response = $this->autototalService->createOrder(
				$orderItems,
				null,
				'Order from excluded AutoTotal cart',
				'1',
				$request->order_from
			);
			
			

			if (!$response || empty($response['success'])) {
				return redirect()->back()->with('error', 'AutoTotal order failed');
			}

			SupplierOrder::updateOrCreate(
				[
					'supplier' => 'autototal',
					'order_number' => $response['order_id'],
				],
				[
					'raw_response' => json_encode($response),
				]
			);

			// Remove ordered rows from persistent excluded storage as well.
			AutototalExcludedCartItem::query()
				->where('supplier', 'autototal')
				->whereIn('id', $selectedIds->all())
				->delete();

			return redirect()->route('searching.excludedAutototalCartShow', $redirectParams)
				->with('success', 'AutoTotal order placed successfully');
		} catch (\Exception $e) {
			return redirect()->back()->with('error', 'AutoTotal error: ' . $e->getMessage());
		}
	}

	public function removeExcludedAutototalCartItem(Request $request)
	{
		$request->validate([
			'item_id' => 'required|integer',
			'date' => 'nullable|date_format:Y-m-d',
			'order_from_context' => 'nullable|string|max:32',
		]);

		$itemId = (int) $request->input('item_id');

		$deleted = AutototalExcludedCartItem::query()
			->where('supplier', 'autototal')
			->where('id', $itemId)
			->delete();

		if ($deleted === 0) {
			return redirect()->back()->with('error', 'Item was not found in excluded cart');
		}

		$redirectParams = array_filter([
			'date' => $request->input('date'),
			'order_from' => $request->input('order_from_context'),
		], fn ($v) => $v !== null && $v !== '');

		return redirect()->route('searching.excludedAutototalCartShow', $redirectParams)
			->with('success', 'Item removed from excluded cart');
	}
	
	public function cartUpdate(Request $request)
	{
		$cart = $this->getSupplierCart();
		
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

			$this->saveSupplierCart($cart);
		}

		return response()->json(['success' => true]);
	}

	public function cartRemove(Request $request)
	{
		$cart = $this->getSupplierCart();
		
		$request->validate([
			'supplier' => 'required|string',
			'key' => 'required|string'
		]);

		unset($cart[$request->supplier][$request->key]);

		// Remove supplier bucket if empty
		if (empty($cart[$request->supplier])) {
			unset($cart[$request->supplier]);
		}

		$this->saveSupplierCart($cart);

		return response()->json(['success' => true]);
	}
	
	public function cartUpdateVariant(Request $request)
	{
		$request->validate([
			'supplier' => 'required|string',
			'key' => 'required|string',
			'variant_code' => 'required|string'
		]);

		$cart = $this->getSupplierCart();

		if (!isset($cart[$request->supplier][$request->key])) {
			return response()->json(['error' => 'Item not found'], 404);
		}

		$cart[$request->supplier][$request->key]['variant_code'] = $request->variant_code;
		$cart[$request->supplier][$request->key]['order_code']   = $request->variant_code;

		$this->saveSupplierCart($cart);

		return response()->json(['success' => true]);
	}

	public function cartUpdateProductName(Request $request)
	{
		$request->validate([
			'supplier' => 'required|string',
			'key' => 'required|string',
			'product_name' => 'required|string|max:255',
		]);

		$cart = $this->getSupplierCart();
		if (!isset($cart[$request->supplier][$request->key])) {
			return response()->json(['error' => 'Item not found'], 404);
		}

		$cart[$request->supplier][$request->key]['product_name'] = trim((string) $request->product_name);

		$this->saveSupplierCart($cart);

		return response()->json(['success' => true]);
	}

	public function cartUpdateManufacturer(Request $request)
	{
		$request->validate([
			'supplier' => 'required|string',
			'key' => 'required|string',
			'manufacturer' => 'nullable|string|max:255',
		]);

		$cart = $this->getSupplierCart();
		if (!isset($cart[$request->supplier][$request->key])) {
			return response()->json(['error' => 'Item not found'], 404);
		}

		$m = trim((string) ($request->input('manufacturer') ?? ''));
		if ($request->supplier === 'materom') {
			$m = $this->normalizeMateromManufacturer($m);
		}
		$cart[$request->supplier][$request->key]['manufacturer'] = $m;

		$this->saveSupplierCart($cart);

		return response()->json(['success' => true]);
	}
	
	public function placeOrder(Request $request)
	{
		$cart = $this->getSupplierCart();
		$cartOriginal = $cart;
		$useOrderItemSelection = false;
		$selectedItemSet = [];
		$autopartnerWarnings = [];
		$autopartnerErrorDescriptions = [
			'01' => 'Inner system error - contact Administrator.',
			'02' => 'Incorrect credentials.',
			'03' => 'Bad request.',
			'03/10' => 'List of ordered products cannot be null or empty.',
			'03/14' => 'OWS not confirmed. Please confirm OWS in catalog or via WebService.',
			'03/16' => 'Product is blocked / Products are blocked.',
			'03/22' => 'Order quantity cannot be negative.',
			'03/30' => 'Maximum number of positions in order is 500.',
			'03/31' => 'Products list cannot contain duplicates.',
			'05' => 'You have unconfirmed invoices.',
			'05/02' => 'Your order has not been accepted due to unconfirmed invoices! Please confirm invoices!',
		];

		$request->validate([
			'order_from'  => 'required|in:UTVIN,TIMISOARA',
			'import_from' => 'required|in:UTVIN,TIMISOARA,EXTERNE,NuImporta',
			'order_item_keys' => 'sometimes|array',
			'order_item_keys.*' => 'string|max:512',
			'autototal_excluded_keys' => 'sometimes|array',
			'autototal_excluded_keys.*' => 'string|max:255',
		]);

		$importFrom = $request->import_from;
		$orderFrom = $request->order_from;
		$autototalExcludedKeys = collect($request->input('autototal_excluded_keys', []))
			->filter(fn($v) => is_string($v) && trim($v) !== '')
			->values()
			->all();
		
		$skipImport = ($importFrom === 'NuImporta');

		$orderItemKeysInput = $request->input('order_item_keys', null);
		$useOrderItemSelection = is_array($orderItemKeysInput);
		if ($useOrderItemSelection) {
			$filteredSelectedKeys = array_values(array_filter(
				$orderItemKeysInput,
				fn ($v) => is_string($v) && trim($v) !== '' && str_contains($v, '|')
			));

			if (empty($filteredSelectedKeys)) {
				return redirect()->back()->with('warning', 'Niciun produs selectat. Coșul rămâne neschimbat.');
			}

			$selectedItemSet = array_fill_keys($filteredSelectedKeys, true);

			// Keep only items selected via checkboxes.
			$cart = [];
			foreach ($cartOriginal as $supplier => $items) {
				foreach ($items as $key => $item) {
					$compoundKey = (string) $supplier . '|' . (string) $key;
					if (isset($selectedItemSet[$compoundKey])) {
						$cart[$supplier][$key] = $item;
					}
				}
			}
		}

		if (empty($cart)) {
			return redirect()->back()->with('error', 'Cart is empty');
		}

		// Start a fresh order draft for every supplier import action.
		// Without this, stale tmp rows from a previous order in the same session
		// can appear together with the newly imported products.
		if (!$skipImport) {
			$sessionId = session()->getId();
			Tmp::where('session_id', $sessionId)->delete();
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
				// Local catalogue (/produse): no supplier API — import to temp/order flow only.
				if ($supplier === 'site_produse') {
					if (!$skipImport) {
						$itemsToImport = array_values($items);

						// Comenzi → Utvin tm externe (EXTERNE): stoc local — alb + furnizor Stoc
						//if ($importFrom === 'EXTERNE') {
							foreach ($itemsToImport as $k => $row) {
								$itemsToImport[$k]['tmp_furnizor'] = 'Stoc';
								$itemsToImport[$k]['tmp_culoare'] = 'FFFFFF';
							}
						//}
						$this->addProductsToDbAndTemp($itemsToImport);
					}
					unset($cart[$supplier]);
					continue;
				}

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

					if (!$skipImport) {
						$this->addProductsToDbAndTemp(array_values($items));
					}
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

					// Save order to DB (order_number is NOT NULL in supplier_orders).
					// InsertOrder can return HTTP 200 with RestInsertOrderResult.OrderNumber = null
					// (e.g. position ErrorCode) while the order still appears in Autopartner UI.
					$order = (array) ($response['data']['RestInsertOrderResult'] ?? []);
					$apErrorCodes = [];
					$topLevelErrorCode = trim((string) ($order['ErrorCode'] ?? ''));
					if ($topLevelErrorCode !== '') {
						$apErrorCodes[$topLevelErrorCode] = true;
					}

					$distributeRows = $order['Positions']['Distribute'] ?? [];
					if (is_array($distributeRows)) {
						foreach ($distributeRows as $distRow) {
							$positionErrorCode = trim((string) (($distRow['ErrorCode'] ?? '')));
							if ($positionErrorCode !== '') {
								$apErrorCodes[$positionErrorCode] = true;
							}
						}
					}

					if (!empty($apErrorCodes)) {
						$apMessages = [];
						foreach (array_keys($apErrorCodes) as $apCode) {
							$apMessage = $autopartnerErrorDescriptions[$apCode] ?? ('Unknown AutoPartner error code: ' . $apCode);
							$apMessages[] = $apCode . ': ' . $apMessage;
						}
						$autopartnerWarnings[] = 'AutoPartner returned: ' . implode(' | ', $apMessages);
					}

					$apiOrderNumber = trim((string) ($order['OrderNumber'] ?? ''));
					$storageOrderNumber = $apiOrderNumber !== ''
						? $apiOrderNumber
						: ('AP-PENDING-' . date('YmdHis') . '-' . substr(sha1(json_encode($order)), 0, 10));

					$order['_stored_order_number'] = $storageOrderNumber;
					$order['_api_order_number_missing'] = $apiOrderNumber === '';

					if ($apiOrderNumber === '') {
						Log::warning('Autopartner InsertOrder returned empty OrderNumber', [
							'order_from' => $orderFrom,
							'storage_order_number' => $storageOrderNumber,
							'result' => $order,
						]);
					}

					SupplierOrder::updateOrCreate(
						['supplier' => 'autopartner', 'order_number' => $storageOrderNumber],
						['raw_response' => $order]
					);

					if (!$skipImport) {
						$this->addProductsToDbAndTemp(array_values($items));
					}
				}

				if ($supplier === 'autonet') {
					// Build order items array for Autonet API
					$orderItems = [];
					foreach ($items as $item) {
						$partNo = trim((string) ($item['autonet_partno'] ?? ''));
						if ($partNo === '') {
							$partNo = trim((string) ($item['variant_code'] ?? ''));
						}
						if ($partNo === '') {
							$label = $item['product_name'] ?? $item['product_code'] ?? 'product';
							return redirect()->back()->with('error', "Missing PartNo for {$label} (Autonet)");
						}
						$orderItems[] = [
							'PartNo'   => $partNo, // Prefer exact API PartNo when available
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
						if (!$skipImport) {
							$this->addProductsToDbAndTemp(array_values($items));
						}
						unset($cart['autonet']);

					} catch (\Exception $e) {
						Log::error('Autonet placeOrder failed', ['exception' => $e->getMessage()]);
						return redirect()->back()->with('error', 'Autonet order failed: ' . $e->getMessage());
					}
				}

				if ($supplier === 'autototal') {
					$allAutototalItems = $items;

					// Split items into excluded vs to-order (based on cart keys posted from UI)
					$excludedItems = [];
					$itemsToOrder = [];

					foreach ($items as $key => $item) {
						if (in_array((string)$key, $autototalExcludedKeys, true)) {
							$excludedItems[$key] = $item;
						} else {
							$itemsToOrder[$key] = $item;
						}
					}

					// Persist excluded items for later use, then remove them from cart
					if (!empty($excludedItems)) {
						$userId = Auth::user()->Id ?? null;
						$excludedTableColumns = [];
						try {
							$excludedTableColumns = DB::getSchemaBuilder()->getColumnListing('autototal_excluded_cart_items');
						} catch (\Throwable $e) {
							$excludedTableColumns = [];
						}

						foreach ($excludedItems as $key => $item) {
							$cartItemKey = (string) $key;
							$newQty = (int)($item['qty'] ?? 1);
							$newPrice = isset($item['raw_price'])
								? (float) $item['raw_price']
								: (isset($item['price']) ? (float) $item['price'] : null);
							$variantCode = $item['variant_code'] ?? null;
						// itemkey is the AutoTotal Availability request key.
						// In current payload we only have variant_code; if later you add a dedicated itemkey into UI/cart payload, it will be used automatically.
						$apiItemkey = $item['itemkey'] ?? $variantCode;
							$productCode = $item['product_code'] ?? null;

							$existingQuery = AutototalExcludedCartItem::query()
								->where('supplier', 'autototal')
								->where('order_from', $orderFrom);

							if ($userId === null) {
								$existingQuery->whereNull('user_id');
							} else {
								$existingQuery->where('user_id', $userId);
							}

							if (!empty($variantCode)) {
								$existingQuery->where('variant_code', $variantCode);
							} else {
								$existingQuery->where('product_code', $productCode);
							}

							$existing = $existingQuery->orderByDesc('id')->first();

							// Same product + same location => aggregate qty.
							// We intentionally do NOT require "same price": excluded cart prices
							// are refreshed later from AutoTotal availability, and unit price can
							// change slightly between adds (rounding/updated API results).
							if ($existing) {
								$updatePayload = [
									// Persist api itemkey for later price refresh.
									'product_name' => $item['product_name'] ?? null,
									'manufacturer' => $item['manufacturer'] ?? null,
									'qty' => ((int)$existing->qty) + $newQty,
									'currency' => $item['currency'] ?? null,
								];
								if (!empty($variantCode)) {
									$updatePayload['variant_code'] = $variantCode;
								}
								if (empty($variantCode) && !empty($productCode)) {
									$updatePayload['product_code'] = $productCode;
								}
								if ($newPrice !== null) {
									$updatePayload['price'] = $newPrice;
								}
								if (in_array('itemkey', $excludedTableColumns, true)) {
									$updatePayload['itemkey'] = $apiItemkey;
								}
								if (in_array('stock', $excludedTableColumns, true)) {
									$updatePayload['stock'] = $item['stock'] ?? $item['supplier_stock'] ?? null;
								}
								if (in_array('livrare', $excludedTableColumns, true)) {
									$updatePayload['livrare'] = $item['livrare'] ?? null;
								}
								if (in_array('depozit', $excludedTableColumns, true)) {
									$updatePayload['depozit'] = $item['depozit'] ?? $item['plantname'] ?? null;
								}
								$existing->update($updatePayload);
								continue;
							}

							$newCartItemKey = $cartItemKey;
							$hasSameKey = AutototalExcludedCartItem::query()
								->when($userId === null, fn($q) => $q->whereNull('user_id'), fn($q) => $q->where('user_id', $userId))
								->where('cart_item_key', $newCartItemKey)
								->exists();

							if ($hasSameKey) {
								$newCartItemKey = $cartItemKey . '#' . $orderFrom . '#' . now()->format('YmdHisv');
							}

							$createPayload = [
								'user_id' => $userId,
								'order_from' => $orderFrom,
								'cart_item_key' => $newCartItemKey,
								'supplier' => 'autototal',
								'product_code' => $productCode,
								'variant_code' => $variantCode,
								// Persist api itemkey for later price refresh.
								'itemkey' => $apiItemkey,
								'product_name' => $item['product_name'] ?? null,
								'manufacturer' => $item['manufacturer'] ?? null,
								'qty' => $newQty,
								'price' => $newPrice,
								'currency' => $item['currency'] ?? null,
							];
							if (!in_array('itemkey', $excludedTableColumns, true)) {
								unset($createPayload['itemkey']);
							}
							if (in_array('stock', $excludedTableColumns, true)) {
								$createPayload['stock'] = $item['stock'] ?? $item['supplier_stock'] ?? null;
							}
							if (in_array('livrare', $excludedTableColumns, true)) {
								$createPayload['livrare'] = $item['livrare'] ?? null;
							}
							if (in_array('depozit', $excludedTableColumns, true)) {
								$createPayload['depozit'] = $item['depozit'] ?? $item['plantname'] ?? null;
							}
							AutototalExcludedCartItem::create($createPayload);
						}

						// remove excluded from session cart so they won't be ordered/imported
						foreach (array_keys($excludedItems) as $excludedKey) {
							unset($cart['autototal'][$excludedKey]);
						}

						// refresh $items to only those still in cart
						$items = $cart['autototal'] ?? [];
					}

					// If nothing left to order for Autototal, skip API call
					if (empty($itemsToOrder)) {
						if (!$skipImport) {
							// Even when all are excluded from API, keep importing them to temp tables
							$this->addProductsToDbAndTemp($allAutototalItems);
						}
						unset($cart['autototal']);
						continue;
					}

					// Build AutoTotal order items (ATE => mapped itemkey, others => variant_code)
					$orderItems = [];

					foreach ($itemsToOrder as $item) {
					$searchedCodeRaw = trim((string) ($item['searched_code'] ?? ''));
					$apiLookupCodeRaw = trim((string) ($item['api_lookup_code'] ?? ''));
					$searchedCodeNormalized = $this->normalizeAutototalCodeForCompare($searchedCodeRaw);
					$apiLookupCodeNormalized = $this->normalizeAutototalCodeForCompare($apiLookupCodeRaw);

					// If user searched and selected exact AutoTotal lookup code (attCode),
					// order with that same code; otherwise keep legacy itemkey resolution.
					if (
						$searchedCodeNormalized !== '' &&
						$apiLookupCodeNormalized !== '' &&
						$searchedCodeNormalized === $apiLookupCodeNormalized
					) {
						$itemkeyToOrder = $apiLookupCodeRaw;
					} else {
						$resolvedItemkey = $this->resolveAutototalOrderItemkeyFromCartItem($item);
						$itemkeyToOrder = trim((string) ($resolvedItemkey ?? ''));
					}
						if ($itemkeyToOrder === '') {
							$itemkeyToOrder = trim((string) ($item['variant_code'] ?? ''));
						}
						if ($itemkeyToOrder === '') {
							$itemkeyToOrder = trim((string) ($item['product_code'] ?? ''));
						}
						if ($itemkeyToOrder === '') {
							$label = $item['product_name'] ?? $item['product_code'] ?? 'product';
							return redirect()->back()->with('error', "Missing order code for {$label} (AutoTotal)");
						}

						$orderItems[] = [
							'ITEMKEY'   => $itemkeyToOrder,
							'QUANTITY' => (int) $item['qty'],
						];
					}
//dd($orderItems);
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

						if (!$skipImport) {
							// Add all AutoTotal products to temp tables, including excluded ones
							$this->addProductsToDbAndTemp($allAutototalItems);
						}

						// Remove supplier cart
						unset($cart['autototal']);

					} catch (\Exception $e) {
						Log::error('AutoTotal placeOrder failed', ['exception' => $e->getMessage()]);
						return redirect()->back()
							->with('error', 'AutoTotal error: ' . $e->getMessage());
					}
				}

				// Remove processed supplier from cart
				unset($cart[$supplier]);

			} catch (\Exception $e) {
				Log::error('placeOrder supplier failed', ['supplier' => $supplier, 'exception' => $e->getMessage()]);
				return redirect()->back()->with('error', "{$supplier} order failed: " . $e->getMessage());
			}
		}

		// Import only selected (checked) rows to local order page (tmp).
		if (!$skipImport && $useOrderItemSelection) {
			$checkedBySupplier = [];
			foreach ($cartOriginal as $supplier => $items) {
				foreach ($items as $key => $item) {
					$compoundKey = (string) $supplier . '|' . (string) $key;
					if (isset($selectedItemSet[$compoundKey])) {
						$checkedBySupplier[$supplier][$key] = $item;
					}
				}
			}

			foreach ($checkedBySupplier as $supplier => $items) {
				$itemsToImport = array_values($items);
				if ($supplier === 'site_produse') {
					foreach ($itemsToImport as $k => $row) {
						$itemsToImport[$k]['tmp_furnizor'] = 'Stoc';
						$itemsToImport[$k]['tmp_culoare'] = 'FFFFFF';
					}
				}
				if (!empty($itemsToImport)) {
					$this->addProductsToDbAndTemp($itemsToImport);
				}
			}
		}

		// Update session cart: keep only items not selected for ordering.
		if ($useOrderItemSelection) {
			$remainingCart = [];
			foreach ($cartOriginal as $supplier => $items) {
				foreach ($items as $key => $item) {
					$compoundKey = (string) $supplier . '|' . (string) $key;
					if (!isset($selectedItemSet[$compoundKey])) {
						$remainingCart[$supplier][$key] = $item;
					}
				}
			}

			// Clean empty supplier buckets
			foreach ($remainingCart as $supplier => $items) {
				if (empty($items)) {
					unset($remainingCart[$supplier]);
				}
			}

			if (empty($remainingCart)) {
				$this->clearSupplierCart();
			} else {
				$this->saveSupplierCart($remainingCart);
			}
		} else {
			// Backward compatible behavior when selection isn't sent.
			if (empty($cart)) {
				$this->clearSupplierCart();
			} else {
				$this->saveSupplierCart($cart);
			}
		}

		// Redirect according to import location
		switch ($importFrom) {
			case 'UTVIN':
				$redirectResponse = redirect()->route('orders.create', ['type' => 'utvin', 'from' => 'supplier'])
					->with('success', 'Orders placed and products imported to UTVIN');
				break;
			case 'TIMISOARA':
				$redirectResponse = redirect()->route('orders.create', ['from' => 'supplier'])
					->with('success', 'Orders placed and products imported to TIMIȘOARA');
				break;
			case 'EXTERNE':
				$redirectResponse = redirect()->route('comenzi.create', ['from' => 'supplier'])
					->with('success', 'Orders placed and products imported to EXTERNE');
				break;
			case 'NuImporta':
				$redirectResponse = redirect()->route('searching.new.index', ['from' => 'supplier'])
					->with('success', 'Orders placed');
				break;
			default:
				return redirect()->back()->with('error', 'Invalid import location');
		}

		if (!empty($autopartnerWarnings)) {
			$redirectResponse->with('autopartner_order_warning', implode("\n", array_unique($autopartnerWarnings)));
		}

		return $redirectResponse;
	}
	
    public function saveWishlist(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'vin' => 'nullable|string|max:255',
            'wishlist_item_keys' => 'sometimes|array',
            'wishlist_item_keys.*' => 'string|max:512',
        ]);

        $cart = $this->getSupplierCart();
        $cartOriginal = $cart;
        $wishlistKeys = collect($request->input('wishlist_item_keys', []))
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->values();
        $selectedSet = [];

        // If selection was posted, save only checked rows from cart.
        if ($wishlistKeys->isNotEmpty()) {
            foreach ($wishlistKeys as $compoundKey) {
                if ($compoundKey === 'NONE') {
                    continue;
                }
                $selectedSet[$compoundKey] = true;
            }

            $filteredCart = [];
            foreach ($cart as $supplier => $items) {
                foreach ($items as $key => $item) {
                    $compound = (string) $supplier . '|' . (string) $key;
                    if (isset($selectedSet[$compound])) {
                        $filteredCart[$supplier][$key] = $item;
                    }
                }
            }
            $cart = $filteredCart;
        }

        if (empty($cart)) {
            return back()->with('error', 'No checked products to save.');
        }

		// Persist stable match keys for AutoTotal so wishlist reload can match
		// even when the exact saved variant code is rotated by API feeds.
		foreach ($cart as $supplier => $items) {
			foreach ((array) $items as $key => $item) {
				if (!is_array($item)) {
					continue;
				}
				$itemSupplier = strtolower(trim((string) ($item['supplier'] ?? $supplier)));
				if ($itemSupplier !== 'autototal') {
					continue;
				}

				if (trim((string) ($item['wishlist_match_code'] ?? '')) === '') {
					$item['wishlist_match_code'] = (string) ($item['product_code'] ?? '');
				}
				if (trim((string) ($item['itemkey'] ?? '')) === '') {
					$resolvedItemkey = $this->resolveAutototalOrderItemkeyFromCartItem($item);
					if (is_string($resolvedItemkey) && trim($resolvedItemkey) !== '') {
						$item['itemkey'] = trim($resolvedItemkey);
					}
				}
				$cart[$supplier][$key] = $item;
			}
		}

        // Always insert a new row so the same user can keep multiple lists with the same name.
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

		// Keep unchecked products in cart; remove only rows saved in the offer.
		if ($wishlistKeys->isNotEmpty()) {
			$remainingCart = [];
			foreach ($cartOriginal as $supplier => $items) {
				foreach ($items as $key => $item) {
					$compoundKey = (string) $supplier . '|' . (string) $key;
					if (!isset($selectedSet[$compoundKey])) {
						$remainingCart[$supplier][$key] = $item;
					}
				}
			}

			// Clean empty supplier buckets.
			foreach ($remainingCart as $supplier => $items) {
				if (empty($items)) {
					unset($remainingCart[$supplier]);
				}
			}

			if (empty($remainingCart)) {
				$this->clearSupplierCart();
			} else {
				$this->saveSupplierCart($remainingCart);
			}
		} else {
			// Backward compatibility when selection keys are not posted.
			$this->clearSupplierCart();
		}

        return back()->with('success', 'Cart saved successfully!');
    }

	/**
	 * Match saved cart variant_code to a fresh search variant (order_code / variant_code).
	 * Strict equality fails when e.g. saved STA9582RK vs refreshed 9582RK after normalizeCode.
	 */
	private function wishlistSavedVariantMatchesFreshVariant(string $savedVariantCode, array $variant, string $itemSupplier): bool
	{
		$saved = trim((string) $savedVariantCode);
		if ($saved === '') {
			return false;
		}

		$candidates = [
			(string) ($variant['order_code'] ?? ''),
			(string) ($variant['variant_code'] ?? ''),
		];

		foreach ($candidates as $c) {
			$c = trim($c);
			if ($c === '') {
				continue;
			}
			if ($saved === $c) {
				return true;
			}

			// Materom order_code can differ only by qty suffix: qty:1 vs qty:
			$savedQtyAgnostic = preg_replace('/qty:\d+$/', 'qty:', $saved);
			$cQtyAgnostic = preg_replace('/qty:\d+$/', 'qty:', $c);
			if ($savedQtyAgnostic !== '' && $savedQtyAgnostic === $cQtyAgnostic) {
				return true;
			}

			$normSaved = preg_replace('/[.\s\-\/|\\\\]+/', '', $saved);
			$normC = preg_replace('/[.\s\-\/|\\\\]+/', '', $c);
			if ($normSaved !== '' && $normSaved === $normC) {
				return true;
			}

			if (strtolower($itemSupplier) === 'autopartner') {
				$ns = preg_replace('/[.\s\-\/|\\\\]+/', '', $this->autoPartnerService->normalizeCode($saved));
				$nc = preg_replace('/[.\s\-\/|\\\\]+/', '', $this->autoPartnerService->normalizeCode($c));
				if ($ns !== '' && $ns === $nc) {
					return true;
				}
			}

			if (strtolower($itemSupplier) === 'autonet') {
				$ns = preg_replace('/[.\s\-\/|\\\\]+/', '', $this->autonetService->normalizeCode($saved));
				$nc = preg_replace('/[.\s\-\/|\\\\]+/', '', $this->autonetService->normalizeCode($c));
				if ($ns !== '' && $ns === $nc) {
					return true;
				}
			}

			if (strtolower($itemSupplier) === 'autototal') {
				$ns = preg_replace('/[.\s\-\/|\\\\]+/', '', $this->autototalService->normalizeCode($saved));
				$nc = preg_replace('/[.\s\-\/|\\\\]+/', '', $this->autototalService->normalizeCode($c));
				if ($ns !== '' && $ns === $nc) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Autonet availability/catalog lookup uses variant_code (supplier article), not product_code.
	 */
	private function loadWishlistAutonetResolveSearchCode(array $row): string
	{
		$item = (array) ($row['item'] ?? []);
		$code = trim((string) ($item['api_lookup_code'] ?? ''));
		if ($code !== '') {
			return $code;
		}
		$code = trim((string) ($item['autonet_partno'] ?? ''));
		if ($code !== '') {
			return $code;
		}
		$code = trim((string) ($item['variant_code'] ?? ''));
		if ($code !== '') {
			return $code;
		}
		$code = trim((string) ($item['order_code'] ?? ''));
		if ($code !== '') {
			return $code;
		}

		return trim((string) ($row['raw_code'] ?? ''));
	}

	/**
	 * Prefetch all Autonet delivery rows in one (or chunked) GetDeliveryData call(s) and DB whereIn lookups.
	 *
	 * @return array<int, array|null> flatItems index => API article row or null
	 */
	private function loadWishlistPrefetchAutonet(array $flatItems): array
	{
		$rows = [];
		foreach ($flatItems as $idx => $row) {
			if (($row['item_supplier'] ?? '') !== 'autonet') {
				continue;
			}
			$item = (array) ($row['item'] ?? []);
			$searchCode = $this->loadWishlistAutonetResolveSearchCode($row);
			if ($searchCode === '') {
				continue;
			}
			$rows[] = [
				'idx' => $idx,
				'searchCode' => $searchCode,
				'qty' => max(1, (int) ($item['qty'] ?? 1)),
			];
		}
		if ($rows === []) {
			return [];
		}

		$searchCodes = array_values(array_unique(array_column($rows, 'searchCode')));
		$catalogMap = DB::table('parts_catalog')
			->select('brand_id', 'mainart_code_parts')
			->whereIn('mainart_code_parts', $searchCodes)
			->get()
			->keyBy('mainart_code_parts');

		$missingForQwp = array_values(array_filter($searchCodes, static fn ($c) => !isset($catalogMap[$c])));
		$qwpMap = [];
		if ($missingForQwp !== []) {
			$qwpMap = DB::table('autonet_qwp_data')
				->whereIn('ArtNr', $missingForQwp)
				->get()
				->keyBy('ArtNr');
		}

		$apiItems = [];
		foreach ($rows as $r) {
			$searchCode = $r['searchCode'];
			$requestQty = max(1, (int) ($r['qty'] ?? 1));
			if (isset($catalogMap[$searchCode])) {
				$catRow = $catalogMap[$searchCode];
				if (!empty($catRow->brand_id)) {
					$apiItems[] = [
						'TDBrandId' => $catRow->brand_id,
						'TDArticleNo' => $catRow->mainart_code_parts,
						'Quantity' => $requestQty,
					];
				} else {
					$apiItems[] = [
						'PartNo' => $catRow->mainart_code_parts,
						'Quantity' => $requestQty,
					];
				}
			} elseif (isset($qwpMap[$searchCode])) {
				$apiItems[] = [
					'PartNo' => $qwpMap[$searchCode]->ArtNr,
					'Quantity' => $requestQty,
				];
			} else {
				$apiItems[] = ['PartNo' => $searchCode, 'Quantity' => $requestQty];
			}
		}

		$allArticles = [];
		try {
			foreach (array_chunk($apiItems, 50) as $chunk) {
				$resp = $this->autonetService->getDeliveryData($chunk);
				foreach ((array) $resp as $article) {
					if (is_array($article)) {
						$allArticles[] = $article;
					}
				}
			}
		} catch (\Throwable $e) {
			return array_fill_keys(array_column($rows, 'idx'), null);
		}

		$out = [];
		foreach ($rows as $r) {
			$idx = $r['idx'];
			$searchCode = $r['searchCode'];
			$matched = null;
			foreach ($allArticles as $article) {
				if (!is_array($article)) {
					continue;
				}
				if ($this->loadWishlistAutonetArticleMatchesSearch($article, $searchCode)) {
					$matched = $article;
					break;
				}
			}
			$out[$idx] = $matched;
		}

		return $out;
	}

	private function loadWishlistAutonetArticleMatchesSearch(array $article, string $searchCode): bool
	{
		$apiPartNo = $this->autonetService->mapAutonetLemforderPartNoToCatalogStyle(
			trim((string) ($article['PartNo'] ?? ''))
		);
		if ($apiPartNo === '') {
			return false;
		}
		$targetNormalized = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $this->autonetService->normalizeCode($searchCode));
		$apiNormalized = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $this->autonetService->normalizeCode($apiPartNo));

		return ($searchCode !== '' && strcasecmp((string) $searchCode, $apiPartNo) === 0)
			|| ($targetNormalized !== '' && $targetNormalized === $apiNormalized);
	}

	/**
	 * Map a normalized (digits-only) mainart to parts_catalog mainart_code_parts with hyphens kept
	 * (space-stripped only), same as RunSupplierSearchNewAction::buildAutopartnerProducts.
	 */
	private function autopartnerCatalogMainartFromNormalized(string $code): ?string
	{
		$digits = preg_replace('/[.\s\-\/|\\\\]+/', '', $code);
		if ($digits === '' || strlen($digits) < 3) {
			return null;
		}

		$row = DB::table('parts_catalog')
			->whereRaw(
				"REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(mainart_code_parts,''), ' ', ''), '-', ''), '.', ''), '/', ''), '\\\\', '') = ?",
				[$digits]
			)
			->first();

		if ($row && !empty($row->mainart_code_parts)) {
			return trim(str_replace(' ', '', (string) $row->mainart_code_parts));
		}

		return null;
	}

	/**
	 * Autopartner ProductsAvailabilityV2 expects the supplier article code as returned by the API
	 * (e.g. 82-1205). Fixes JS bug "821205" + qty 1 => "8212051" and maps digit-only codes via parts_catalog.
	 */
	private function resolveAutopartnerAvailabilityProductCode(array $item): string
	{
		$qty = (int) ($item['qty'] ?? 1);
		if ($qty < 1) {
			$qty = 1;
		}

		$fixConcatSuffix = function (string $v, string $pc) use ($qty): string {
			$v = trim($v);
			$pc = trim($pc);
			if ($v === '') {
				return '';
			}
			$v = preg_replace('/qty:\d+$/', '', $v);
			$v = trim($v);
			if ($v === '') {
				return '';
			}
			// Legacy JS: payload.variant_code = baseVariantCode + qty  →  "821205" + 1 => "8212051"
			if ($qty <= 9 && $pc !== '' && $v === $pc . (string) $qty) {
				return $pc;
			}
			if ($qty <= 9 && preg_match('/^(\d+)(\d)$/', $v, $m) && (int) $m[2] === $qty) {
				$stripped = $m[1];
				if ($v === $pc || $stripped === $pc || ($pc !== '' && $stripped === $pc)) {
					return $stripped;
				}
			}

			return $v;
		};

		$pcIn = trim((string) ($item['product_code'] ?? ''));

		foreach (['variant_code', 'order_code'] as $key) {
			$v = $fixConcatSuffix((string) ($item[$key] ?? ''), $pcIn);
			if ($v === '') {
				continue;
			}
			$mapped = $this->autopartnerCatalogMainartFromNormalized($v);
			if ($mapped !== null) {
				return $mapped;
			}
			if ($v !== '') {
				return $v;
			}
		}

		$fallback = $fixConcatSuffix($pcIn, $pcIn);
		if ($fallback === '') {
			return '';
		}
		$mapped = $this->autopartnerCatalogMainartFromNormalized($fallback);

		return $mapped !== null ? $mapped : $fallback;
	}

	/**
	 * @return array<int, array|null> flatItems index => availability row or null
	 */
	private function loadWishlistPrefetchAutopartner(array $flatItems): array
	{
		$rows = [];
		foreach ($flatItems as $idx => $row) {
			if (($row['item_supplier'] ?? '') !== 'autopartner') {
				continue;
			}
			$item = (array) ($row['item'] ?? []);
			$rawCode = trim((string) ($row['raw_code'] ?? ''));
			if ($rawCode === '') {
				continue;
			}
			// Wishlist reload should mirror /searching-new selected line and use
			// the exact saved API lookup code only (no catalog expansion).
			$requestCodes = [$rawCode];
			$rows[] = [
				'idx' => $idx,
				'rawCode' => $rawCode,
				'qty' => (float) ($item['qty'] ?? 1),
				'requestCodes' => $requestCodes,
			];
		}
		if ($rows === []) {
			return [];
		}

		$productMap = [];
		foreach ($rows as $r) {
			foreach ((array) ($r['requestCodes'] ?? []) as $requestCode) {
				$requestCode = trim((string) $requestCode);
				if ($requestCode === '') {
					continue;
				}
				$existingQty = (float) ($productMap[$requestCode]['quantity'] ?? 0);
				$productMap[$requestCode] = [
					'productCode' => $requestCode,
					'quantity' => max($existingQty, (float) ($r['qty'] ?? 1)),
				];
			}
		}
		$products = array_values($productMap);

		try {
			$apResponse = $this->autoPartnerService->productsAvailabilityV2($products, false);
		} catch (\Throwable $e) {
			return array_fill_keys(array_column($rows, 'idx'), null);
		}

		$availabilityList = (array) ($apResponse['data']['RestProductsAvailabilityV2Result']['Availability'] ?? []);
		$out = [];

		foreach ($rows as $r) {
			$idx = $r['idx'];
			$targetRawCode = $r['rawCode'];
			$targetNormalizedSet = [];
			foreach ((array) ($r['requestCodes'] ?? []) as $requestCode) {
				$normalized = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $this->autoPartnerService->normalizeCode((string) $requestCode));
				if (is_string($normalized) && $normalized !== '') {
					$targetNormalizedSet[$normalized] = true;
				}
			}
			$targetNormalized = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $this->autoPartnerService->normalizeCode($targetRawCode));
			if (is_string($targetNormalized) && $targetNormalized !== '') {
				$targetNormalizedSet[$targetNormalized] = true;
			}
			$matched = null;
			foreach ($availabilityList as $apItem) {
				if (!is_array($apItem)) {
					continue;
				}
				$apiCode = trim((string) ($apItem['ProductCode'] ?? ''));
				$apiNormalized = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $this->autoPartnerService->normalizeCode($apiCode));
				$isSameProduct =
					($targetRawCode !== '' && strcasecmp($targetRawCode, $apiCode) === 0)
					|| ($apiNormalized !== '' && isset($targetNormalizedSet[$apiNormalized]));
				if (!$isSameProduct) {
					continue;
				}
				$matched = $apItem;
				// Prefer exact ProductCode match first; otherwise keep normalized fallback.
				if ($targetRawCode !== '' && strcasecmp($targetRawCode, $apiCode) === 0) {
					break;
				}
			}
			$out[$idx] = $matched;
		}

		return $out;
	}

	private function loadWishlistAutonetBuildVariant(array $article, array $item, string $searchCode): array
	{
		$deliveryData = (array) ($article['DeliveryData'] ?? []);
		$maxStock = 0;
		$totalStock = 0;
		$bestDeliveryDate = '';
		$bestDepot = '';
		foreach ($deliveryData as $delivery) {
			if (!is_array($delivery)) {
				continue;
			}
			$stock = (int) ($delivery['Quantity'] ?? 0);
			if ($stock > 0) {
				$totalStock += $stock;
			}
			if ($stock > $maxStock) {
				$maxStock = $stock;
				$bestDeliveryDate = trim((string) ($delivery['DeliveryDate'] ?? ''));
				$bestDepot = trim((string) ($delivery['Code'] ?? ''));
			}
		}

		// Mirror SupplierSearchNew DeliveryFormatter (autonet) so cart shows same
		// availability text and dot color as /searching-new.
		$livrare = 'Verifica stoc';
		if (preg_match('/(\d{4}-\d{2}-\d{2})[Tt](\d{2}:\d{2}):\d{2}/i', $bestDeliveryDate, $matches)) {
			$timePart = ltrim($matches[2], '0');
			$deliveryDate = new \DateTime($matches[1]);
			$today = new \DateTime();
			$today->setTime(0, 0, 0);
			$deliveryDate->setTime(0, 0, 0);
			$diff = $today->diff($deliveryDate);
			$daysDiff = (int) $diff->format('%r%a');

			if ($daysDiff === 0) {
				$livrare = $timePart ? "Azi {$timePart}" : 'Azi';
			} elseif ($daysDiff === 1) {
				$livrare = $timePart ? "Mâine {$timePart}" : 'Mâine';
			} elseif ($daysDiff === 2) {
				$livrare = $timePart ? "Poimâine {$timePart}" : 'Poimâine';
			} else {
				$livrare = $timePart ? "{$daysDiff} zile {$timePart}" : "{$daysDiff} zile";
			}
		} else {
			$livrare = $bestDeliveryDate !== '' ? $bestDeliveryDate : 'Verifica stoc';
		}

		$depozit = $bestDepot !== '' ? $bestDepot : '-';
		$resolvedStock = $totalStock > 0 ? $totalStock : $maxStock;

		return [
			// Wishlist reload stock check must use total available quantity across
			// all delivery slots (e.g. 2 + 1), not only the biggest single slot.
			'supplier_stock' => $resolvedStock,
			'stock' => $resolvedStock,
			'price' => (float) ($article['PriceWoVat'] ?? 0),
			'currency' => (string) ($article['Currency'] ?? 'RON'),
			'order_code' => (string) ($item['variant_code'] ?? $item['order_code'] ?? $searchCode),
			'delivery' => ['info_text' => $bestDeliveryDate, 'plant_name' => null],
			'livrare' => $livrare,
			'depozit' => $depozit,
		];
	}

	/**
	 * For AutoTotal wishlist load, re-check live availability using requested qty.
	 * This avoids false out-of-stock decisions caused by intermediate parsed stock.
	 */
	private function loadWishlistAutototalAvailableQty(array $item, string $rawCode, int $requestedQty): ?int
	{
		$candidates = [];
		foreach ([
			trim((string) ($item['itemkey'] ?? '')),
			trim((string) ($item['api_lookup_code'] ?? '')),
			trim((string) ($item['variant_code'] ?? '')),
			trim((string) $rawCode),
			trim((string) ($item['product_code'] ?? '')),
		] as $c) {
			if ($c !== '') {
				$candidates[$c] = true;
			}
		}

		if (empty($candidates)) {
			return null;
		}

		$qty = max(1, (int) $requestedQty);
		foreach (array_keys($candidates) as $itemkey) {
			try {
				$response = $this->autototalService->checkAvailability($itemkey, $qty);
			} catch (\Throwable $e) {
				continue;
			}

			$body = is_array($response) ? $response : [];
			$status = (int) ($body['webApiResponse']['status'] ?? 0);
			if ($status !== 1) {
				continue;
			}

			$availability = $body['searchCode']['availability'] ?? [];
			if (!is_array($availability)) {
				return 0;
			}

			$total = 0;
			foreach ($availability as $row) {
				if (!is_array($row)) {
					continue;
				}
				$total += max(0, (int) ($row['stock'] ?? 0));
			}

			return $total;
		}

		return null;
	}

	/**
	 * Wishlist load skips: include product / search code (and variant when useful) so users can identify lines.
	 */
	private function formatWishlistSkippedMessage(array $item, string $normalizedCode, string $rawCode, string $reasonSuffix): string
	{
		$label = trim((string) (($item['product_name'] ?? $item['product_code'] ?? $normalizedCode) ?: $normalizedCode));
		if ($label === '') {
			$label = '?';
		}

		$code = trim((string) ($rawCode !== '' ? $rawCode : ($item['product_code'] ?? '')));
		if ($code === '') {
			$code = trim((string) $normalizedCode);
		}
		if ($code === '' && !empty($item['idprodus'])) {
			$code = 'idprodus ' . (int) $item['idprodus'];
		}

		$variant = trim((string) ($item['variant_code'] ?? ''));

		$parts = $label;
		if ($code !== '') {
			$parts .= ' (cod: ' . $code . ')';
		}
		if ($variant !== '' && strcasecmp($variant, $code) !== 0) {
			$parts .= ' (variantă: ' . $variant . ')';
		}

		return $parts . $reasonSuffix;
	}

	public function loadWishlist($id)
	{
		$savedCart = SupplierSavedCart::findOrFail($id);

		$newCart = [];
		$skippedItems = [];
		$skippedApiDebug = [];
		$supportedSuppliers = ['autopartner', 'materom', 'autonet', 'autototal', 'elit', 'site_produse'];
		$flatItems = [];
		$searchBatches = [];

		foreach (($savedCart->cart ?? []) as $supplierGroup => $items) {
			foreach ((array) $items as $item) {
				$itemSupplier = strtolower(trim((string) ($item['supplier'] ?? $supplierGroup)));
				if (!in_array($itemSupplier, $supportedSuppliers, true)) {
					$productCodeRaw = (string) ($item['product_code'] ?? '');
					$detail = ' (supplier unsupported: supplier=' . $itemSupplier . ')';
					$skippedItems[] = $this->formatWishlistSkippedMessage((array) $item, '', $productCodeRaw, $detail);
					continue;
				}

				$productCodeRaw = trim((string) ($item['api_lookup_code'] ?? $item['product_code'] ?? ''));
				if ($itemSupplier === 'autototal') {
					// For AutoTotal wishlist reload, prefer stable raw identifiers in this order:
					// itemkey -> saved variant_code -> product_code. Using only cleaned product_code
					// can break direct availability lookups (e.g. CM05-2179 becoming CM052179).
					$autototalRawCandidates = [
						trim((string) ($item['itemkey'] ?? '')),
						trim((string) ($item['api_lookup_code'] ?? '')),
						trim((string) ($item['variant_code'] ?? '')),
						trim((string) ($item['product_code'] ?? '')),
					];
					foreach ($autototalRawCandidates as $candidate) {
						if ($candidate !== '') {
							$productCodeRaw = $candidate;
							break;
						}
					}
				} elseif ($itemSupplier === 'autopartner') {
					$resolvedAp = $this->resolveAutopartnerAvailabilityProductCode((array) $item);
					if ($resolvedAp !== '') {
						$productCodeRaw = $resolvedAp;
					}
				}
				$searchCode = $itemSupplier === 'materom'
					? $productCodeRaw
					: preg_replace('/[.\s\-\/|\\\\]+/', '', $productCodeRaw);
				if (!is_string($searchCode) || $searchCode === '') {
					$detail = ' (not found: supplier=' . $itemSupplier . ', empty_search_code=true)';
					$skippedItems[] = $this->formatWishlistSkippedMessage((array) $item, '', $productCodeRaw, $detail);
					continue;
				}

				$flatItems[] = [
					'group_supplier' => (string) $supplierGroup,
					'item_supplier' => $itemSupplier,
					'normalized_code' => $searchCode,
					'raw_code' => $productCodeRaw !== '' ? $productCodeRaw : $searchCode,
					'item' => $item,
				];

				if ($itemSupplier !== 'materom' && $itemSupplier !== 'site_produse') {
					if (!isset($searchBatches[$searchCode])) {
						$searchBatches[$searchCode] = [
							'raw_code' => $productCodeRaw !== '' ? $productCodeRaw : $searchCode,
							'suppliers' => [],
						];
					}
					$searchBatches[$searchCode]['suppliers'][$itemSupplier] = true;
				}
			}
		}

		$searchResultsByCode = [];
		foreach ($searchBatches as $normalizedCode => $batch) {
			$batchSuppliers = array_keys($batch['suppliers']);
			if ($batchSuppliers === []) {
				$searchResultsByCode[$normalizedCode] = [];
				continue;
			}

			try {
				$result = $this->supplierSearchNewAction->run(
					(string) ($batch['raw_code'] ?? $normalizedCode),
					$batchSuppliers,
					false,
					(string) ($batch['raw_code'] ?? $normalizedCode),
					true
				);
				$searchResultsByCode[$normalizedCode] = (array) (($result['payload']['products'] ?? []));
			} catch (\Throwable $e) {
				$searchResultsByCode[$normalizedCode] = [];
			}
		}

		$autonetPrefetched = $this->loadWishlistPrefetchAutonet($flatItems);
		$autopartnerPrefetched = $this->loadWishlistPrefetchAutopartner($flatItems);

		foreach ($flatItems as $flatIndex => $row) {
			$item = (array) ($row['item'] ?? []);
			$itemSupplier = (string) ($row['item_supplier'] ?? '');
			$groupSupplier = (string) ($row['group_supplier'] ?? $itemSupplier);
			$normalizedCode = (string) ($row['normalized_code'] ?? '');
			$rawCode = (string) ($row['raw_code'] ?? $normalizedCode);
			$savedVariantCode = (string) ($item['variant_code'] ?? '');

			$matchedVariant = null;
			$matchedProduct = null;
			$variantCandidateCodes = [];
			$autonetRawArticle = null;
			$autopartnerRawItem = null;

			if ($itemSupplier === 'materom') {
				try {
					$materomResult = $this->materomService->partSearchV4($rawCode);
					$materomBody = (array) ($materomResult['body'] ?? []);
					$requestedQtyMaterom = max(1, (int) ($item['qty'] ?? 1));
					$lookupRaw = trim((string) ($item['api_lookup_code'] ?? $rawCode));
					$lookupNorm = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $lookupRaw);

					$selectedProduct = null;
					foreach ($materomBody as $product) {
						if (!is_array($product)) {
							continue;
						}
						$productMfrpn = trim((string) ($product['mfrpn'] ?? ''));
						$productMatnr = trim((string) ($product['material'] ?? ''));
						$mfrpnNorm = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $productMfrpn);
						$matnrNorm = preg_replace('/[.\s\-\/|\\\\]+/', '', (string) $productMatnr);

						$isLookupMatch = $lookupRaw !== '' && (
							strcasecmp($lookupRaw, $productMfrpn) === 0
							|| strcasecmp($lookupRaw, $productMatnr) === 0
							|| ($lookupNorm !== '' && ($lookupNorm === $mfrpnNorm || $lookupNorm === $matnrNorm))
						);
						if ($isLookupMatch) {
							$selectedProduct = $product;
							break;
						}
					}

					if (!is_array($selectedProduct)) {
						// Backward fallback for older saved carts without api_lookup_code.
						foreach ($materomBody as $product) {
							if (!is_array($product)) {
								continue;
							}
							$variants = (array) ($product['pricingVariants'] ?? []);
							foreach ($variants as $variant) {
								if (!is_array($variant)) {
									continue;
								}
								$candidate = trim((string) (($variant['order_code'] ?? $variant['variant_code'] ?? '')));
								if ($candidate !== '') {
									$variantCandidateCodes[$candidate] = true;
								}
								if ($this->wishlistSavedVariantMatchesFreshVariant($savedVariantCode, $variant, $itemSupplier)) {
									$selectedProduct = $product;
									break 2;
								}
							}
						}
					}

					if (is_array($selectedProduct)) {
						$variants = (array) ($selectedProduct['pricingVariants'] ?? []);
						$totalAvailableAcrossVariants = 0;
						$remainingQty = $requestedQtyMaterom;
						$fulfillingVariant = null;
						$fallbackVariant = null;

						foreach ($variants as $variant) {
							if (!is_array($variant)) {
								continue;
							}
							$candidate = trim((string) (($variant['order_code'] ?? $variant['variant_code'] ?? '')));
							if ($candidate !== '') {
								$variantCandidateCodes[$candidate] = true;
							}

							$stock = max(0, (int) ($variant['stock'] ?? $variant['supplier_stock'] ?? 0));
							if ($stock <= 0) {
								continue;
							}

							if (!is_array($fallbackVariant)) {
								$fallbackVariant = $variant;
							}

							$totalAvailableAcrossVariants += $stock;
							$remainingQty -= $stock;
							$fulfillingVariant = $variant;
							if ($remainingQty <= 0) {
								break;
							}
						}

						if (is_array($fulfillingVariant) || is_array($fallbackVariant)) {
							$matchedVariant = is_array($fulfillingVariant) ? $fulfillingVariant : $fallbackVariant;
							$matchedVariant['stock'] = $totalAvailableAcrossVariants;
							$matchedVariant['supplier_stock'] = $totalAvailableAcrossVariants;
							$matchedProduct = $selectedProduct;
						}
					}
				} catch (\Throwable $e) {
					$matchedVariant = null;
				}
			} elseif ($itemSupplier === 'autonet') {
				try {
					$searchCode = $this->loadWishlistAutonetResolveSearchCode($row);
					$article = $autonetPrefetched[$flatIndex] ?? null;
					if (is_array($article)) {
						$autonetRawArticle = $article;
						$matchedVariant = $this->loadWishlistAutonetBuildVariant($article, $item, $searchCode);
						if (is_array($matchedVariant)) {
							$candidate = trim((string) (($matchedVariant['order_code'] ?? $matchedVariant['variant_code'] ?? '')));
							if ($candidate !== '') {
								$variantCandidateCodes[$candidate] = true;
							}
						}
						$matchedProduct = ['name' => $item['product_name'] ?? null, 'manufacturer' => $item['manufacturer'] ?? null];
					}
				} catch (\Throwable $e) {
					$matchedVariant = null;
				}
			} elseif ($itemSupplier === 'autopartner') {
				try {
					$apItem = $autopartnerPrefetched[$flatIndex] ?? null;
					if (!is_array($apItem)) {
						$matchedVariant = null;
					} else {
						$autopartnerRawItem = $apItem;
						$targetRawCode = trim((string) $rawCode);
						$states = (array) ($apItem['States'] ?? []);
						$stateByDept = [];
						foreach ($states as $state) {
							if (!is_array($state)) {
								continue;
							}
							$dept = trim((string) ($state['DepartmentCode'] ?? ''));
							$stock = max(0, (int) ($state['InStock'] ?? 0));
							if ($dept === '') {
								continue;
							}
							if (!isset($stateByDept[$dept])) {
								$stateByDept[$dept] = ['DepartmentCode' => $dept, 'InStock' => 0];
							}
							$stateByDept[$dept]['InStock'] += $stock;
						}

						$requestedQty = max(1, (int) ($item['qty'] ?? 1));
						$priorityDepts = ['CN', '120', '72'];
						$totalPreferredStock = 0;
						foreach ($priorityDepts as $dept) {
							$totalPreferredStock += (int) ($stateByDept[$dept]['InStock'] ?? 0);
						}

						$remaining = $requestedQty;
						$fulfillingDept = '';
						foreach ($priorityDepts as $dept) {
							$deptStock = (int) ($stateByDept[$dept]['InStock'] ?? 0);
							if ($deptStock <= 0) {
								continue;
							}
							$remaining -= $deptStock;
							$fulfillingDept = $dept;
							if ($remaining <= 0) {
								break;
							}
						}

						// Fallback to highest-stock state if no preferred department has stock.
						if ($fulfillingDept === '') {
							$maxDeptStock = -1;
							foreach ($stateByDept as $dept => $s) {
								$deptStock = (int) ($s['InStock'] ?? 0);
								if ($deptStock > $maxDeptStock) {
									$maxDeptStock = $deptStock;
									$fulfillingDept = $dept;
								}
							}
						}

						$savedDepartmentCode = (string) ($item['departamentCode'] ?? $item['departamentcode'] ?? '');
						// Prefer API DepartmentCode so availability/dot are updated.
						$departmentCode = trim((string) ($fulfillingDept !== '' ? $fulfillingDept : $savedDepartmentCode));

						// Mirror SupplierSearchNew DeliveryFormatter (autopartner).
						$livrare = 'Verifica stoc';
						if ($departmentCode === 'CN') {
							$livrare = 'Maine 8:00';
						} elseif ($departmentCode === '120' || $departmentCode === '72') {
							$livrare = 'Poimaine 8:00';
						}

						// In /searching-new, autopartner uses depozit empty.
						$depozit = '';

						$apiProductCode = trim((string) ($apItem['ProductCode'] ?? ''));
						$matchedVariant = [
							// Wishlist availability can be fulfilled from CN + 120 + 72 combined.
							'supplier_stock' => $totalPreferredStock,
							'stock' => $totalPreferredStock,
							'price' => (float) ($apItem['Price'] ?? 0),
							'currency' => (string) ($apItem['CurrencyCode'] ?? 'RON'),
							'departamentCode' => $departmentCode,
							'order_code' => $apiProductCode !== ''
								? $apiProductCode
								: (string) ($item['variant_code'] ?? $item['order_code'] ?? $targetRawCode),
							'delivery' => ['info_text' => $livrare, 'plant_name' => null],
							'livrare' => $livrare,
							'depozit' => $depozit,
						];
						$candidate = trim((string) ($matchedVariant['order_code'] ?? ''));
						if ($candidate !== '') {
							$variantCandidateCodes[$candidate] = true;
						}
						$matchedProduct = ['name' => $item['product_name'] ?? null, 'manufacturer' => $item['manufacturer'] ?? null];
					}
				} catch (\Throwable $e) {
					$matchedVariant = null;
				}
			} elseif ($itemSupplier === 'site_produse') {
				$idprodus = (int) ($item['idprodus'] ?? 0);
				$p = $idprodus ? Produse::find($idprodus) : null;
				if (!$p) {
					$detail = ' (produs magazin indisponibil: supplier=site_produse, idprodus=' . $idprodus . ')';
					$skippedItems[] = $this->formatWishlistSkippedMessage($item, $normalizedCode, $rawCode, $detail);
					continue;
				}

				$matchedVariant = [
					'order_code' => (string) ($item['variant_code'] ?? ($p->cod_produs ?? '')),
					'stock' => 999999,
					'supplier_stock' => 999999,
					'price' => (float) ($p->pret ?? 0),
					'currency' => 'RON',
					'livrare' => 'Magazin propriu',
					'depozit' => '-',
					'delivery' => ['info_text' => 'Magazin propriu', 'plant_name' => ''],
				];
				$matchedProduct = [
					'name' => $p->denumire,
					'manufacturer' => $item['manufacturer'] ?? '-',
				];
			} else {
				$products = (array) ($searchResultsByCode[$normalizedCode] ?? []);
				$stableKeysAutototal = [];
				if ($itemSupplier === 'autototal') {
					foreach ([
						(string) ($item['itemkey'] ?? ''),
						(string) ($item['wishlist_match_code'] ?? ''),
						(string) ($item['product_code'] ?? ''),
						(string) ($item['variant_code'] ?? ''),
					] as $k) {
						$k = trim($k);
						if ($k === '') {
							continue;
						}
						$nk = preg_replace('/[.\s\-\/|\\\\]+/', '', $this->autototalService->normalizeCode($k));
						if (is_string($nk) && $nk !== '') {
							$stableKeysAutototal[$nk] = true;
						}
					}
				}
				foreach ($products as $product) {
					if (!is_array($product)) {
						continue;
					}
					$suppliersData = $product['suppliers'] ?? [];
					if (!is_array($suppliersData)) {
						continue;
					}
					$supplierData = $suppliersData[$itemSupplier] ?? null;
					if (!is_array($supplierData)) {
						continue;
					}

					$variants = $supplierData['variants'] ?? [];
					if (!is_array($variants)) {
						continue;
					}

					foreach ($variants as $variant) {
						if (!is_array($variant)) {
							continue;
						}
						$candidate = trim((string) (($variant['order_code'] ?? $variant['variant_code'] ?? '')));
						if ($candidate !== '') {
							$variantCandidateCodes[$candidate] = true;
						}
						if ($itemSupplier === 'autototal' && !empty($stableKeysAutototal)) {
							$nCandidate = preg_replace('/[.\s\-\/|\\\\]+/', '', $this->autototalService->normalizeCode($candidate));
							if (is_string($nCandidate) && isset($stableKeysAutototal[$nCandidate])) {
								$matchedVariant = $variant;
								$matchedProduct = $product;
								break 2;
							}
						}
						if ($this->wishlistSavedVariantMatchesFreshVariant($savedVariantCode, $variant, $itemSupplier)) {
							$matchedVariant = $variant;
							$matchedProduct = $product;
							break 2;
						}
					}
				}

				// AutoTotal fallback: if exact old variant is gone, keep the line by
				// selecting the first in-stock current variant from returned candidates.
				if (!$matchedVariant && $itemSupplier === 'autototal') {
					$bestStock = -1;
					foreach ($products as $product) {
						if (!is_array($product)) {
							continue;
						}
						$suppliersData = $product['suppliers'] ?? [];
						if (!is_array($suppliersData)) {
							continue;
						}
						$supplierData = $suppliersData[$itemSupplier] ?? null;
						if (!is_array($supplierData)) {
							continue;
						}
						$variants = $supplierData['variants'] ?? [];
						if (!is_array($variants)) {
							continue;
						}
						foreach ($variants as $variant) {
							if (!is_array($variant)) {
								continue;
							}
							$stock = (int) ($variant['supplier_stock'] ?? $variant['stock'] ?? 0);
							if ($stock > $bestStock) {
								$bestStock = $stock;
								$matchedVariant = $variant;
								$matchedProduct = $product;
							}
						}
					}
				}
			}

			if (!$matchedVariant) {
				$candidates = array_slice(array_keys($variantCandidateCodes), 0, 6);
				$requestedQty = (int) ($item['qty'] ?? 1);
				$detail = ' (variant unavailable: supplier=' . $itemSupplier
					. ', search=' . ($normalizedCode !== '' ? $normalizedCode : '-')
					. ', requested_qty=' . $requestedQty
					. ', candidates=' . ($candidates ? implode('|', $candidates) : 'none')
					. ')';
				$skippedItems[] = $this->formatWishlistSkippedMessage($item, $normalizedCode, $rawCode, $detail);
				$skippedApiDebug[] = [
					'supplier' => $itemSupplier,
					'reason' => 'variant unavailable',
					'search_code' => $normalizedCode,
					'raw_code' => $rawCode,
					'saved_variant_code' => $savedVariantCode,
					'requested_qty' => $requestedQty,
					'candidate_codes' => $candidates,
					'api_response' => $itemSupplier === 'autonet'
						? ($autonetRawArticle ?? ['note' => 'No matched Autonet article found in current API response for this search code'])
						: ($itemSupplier === 'autopartner'
							? ($autopartnerRawItem ?? ['note' => 'No matched Autopartner availability item found in current API response for this search code'])
							: []),
				];
				continue;
			}

			$requestedQty = (int) ($item['qty'] ?? 1);
			if ($itemSupplier === 'site_produse') {
				$availableStock = PHP_INT_MAX;
			} elseif ($itemSupplier === 'materom') {
				$availableStock = (int) ($matchedVariant['stock'] ?? $matchedVariant['supplier_stock'] ?? 0);
			} else {
				$availableStock = (int) ($matchedVariant['supplier_stock'] ?? $matchedVariant['stock'] ?? 0);
			}
			if ($itemSupplier === 'autototal') {
				$liveAvailable = $this->loadWishlistAutototalAvailableQty($item, $rawCode, $requestedQty);
				if ($liveAvailable !== null) {
					$availableStock = $liveAvailable;
				}
			}
			if ($availableStock < $requestedQty) {
				$matchedCode = trim((string) (($matchedVariant['order_code'] ?? $matchedVariant['variant_code'] ?? '')));
				$detail = ' (out of stock: supplier=' . $itemSupplier
					. ', requested=' . $requestedQty
					. ', available=' . $availableStock
					. ', matched_code=' . ($matchedCode !== '' ? $matchedCode : '-')
					. ')';
				$skippedItems[] = $this->formatWishlistSkippedMessage($item, $normalizedCode, $rawCode, $detail);
				$skippedApiDebug[] = [
					'supplier' => $itemSupplier,
					'reason' => 'out of stock',
					'search_code' => $normalizedCode,
					'raw_code' => $rawCode,
					'saved_variant_code' => $savedVariantCode,
					'requested_qty' => $requestedQty,
					'available_qty' => $availableStock,
					'matched_code' => $matchedCode,
					'api_response' => $itemSupplier === 'autonet'
						? ($autonetRawArticle ?? ['note' => 'No matched Autonet article found in current API response for this search code'])
						: ($itemSupplier === 'autopartner'
							? ($autopartnerRawItem ?? ['note' => 'No matched Autopartner availability item found in current API response for this search code'])
							: ($itemSupplier === 'autototal'
								? ['note' => 'Checked live autototal availability with requested qty during wishlist load']
								: [])),
				];
				continue;
			}

			$savedSellingPrice = (float) ($item['price'] ?? 0);
			if ($savedSellingPrice <= 0) {
				$item['price'] = (float) ($matchedVariant['price'] ?? 0);
				$item['currency'] = (string) ($matchedVariant['currency'] ?? ($item['currency'] ?? 'RON'));
			} elseif (trim((string) ($item['currency'] ?? '')) === '') {
				$item['currency'] = (string) ($matchedVariant['currency'] ?? 'RON');
			}

			$item['variant_code'] = (string) ($matchedVariant['order_code'] ?? $savedVariantCode);
			if ($itemSupplier === 'autopartner' && trim((string) ($matchedVariant['order_code'] ?? '')) !== '') {
				$item['product_code'] = (string) $matchedVariant['order_code'];
			}

			$deliveryInfo = (string) ($matchedVariant['delivery']['info_text'] ?? ($item['delivery'] ?? ''));
			$plantRaw = (string) ($matchedVariant['delivery']['plant_name'] ?? ($item['plantraw'] ?? ''));
			$item['delivery'] = $deliveryInfo;
			$item['disponibilitate'] = $deliveryInfo;
			$item['plantraw'] = $plantRaw;
			$item['livrare'] = (string) ($matchedVariant['livrare'] ?? ($item['livrare'] ?? '-'));
			$item['depozit'] = (string) ($matchedVariant['depozit'] ?? ($item['depozit'] ?? '-'));

			// Update dot color/text consistently with /searching-new.
			if ($itemSupplier === 'autonet' || $itemSupplier === 'autototal') {
				// Autonet/AutoTotal dot is based on delivery date, parsed from deliveryInfo.
				$dotImg = '<img src="/image/red-dot.png" width="14" height="14" />';
				if (preg_match('/(\d{4}-\d{2}-\d{2})[Tt](\d{2}:\d{2}):\d{2}/i', $deliveryInfo, $matches)) {
					$deliveryDate = new \DateTime($matches[1]);
					$today = new \DateTime();
					$today->setTime(0, 0, 0);
					$deliveryDate->setTime(0, 0, 0);
					$diff = $today->diff($deliveryDate);
					$daysDiff = (int) $diff->format('%r%a');
					if ($daysDiff === 0) {
						$dotImg = '<img src="/image/green-dot.png" width="14" height="14" />';
					} elseif ($daysDiff === 1) {
						$dotImg = '<img src="/image/blue-dot.png" width="14" height="14" />';
					} elseif ($daysDiff === 2) {
						$dotImg = '<img src="/image/orange-dot.png" width="14" height="14" />';
					}
				}
				$item['plantname'] = $dotImg;
			} elseif ($itemSupplier === 'autopartner') {
				$dept = trim((string) ($matchedVariant['departamentCode'] ?? ($item['departamentCode'] ?? $item['departamentcode'] ?? '')));
				if ($dept === 'CN') {
					$item['plantname'] = '<img src="/image/blue-dot.png" width="14" height="14" />';
				} elseif ($dept === '120' || $dept === '72') {
					$item['plantname'] = '<img src="/image/orange-dot.png" width="14" height="14" />';
				} else {
					$item['plantname'] = '<img src="/image/red-dot.png" width="14" height="14" />';
				}
				$item['departamentCode'] = $dept;
			} elseif ($itemSupplier === 'site_produse') {
				$item['plantname'] = '<img src="/image/green-dot.png" width="14" height="14" />';
			} else {
				$plantLower = mb_strtolower(trim($plantRaw));
				if (strpos($plantLower, 'timi') !== false) {
					$item['plantname'] = '<img src="/image/green-dot.png" width="14" height="14" />';
				} elseif (strpos($plantLower, 'centru logistic') !== false || strpos($plantLower, 'mures') !== false) {
					$item['plantname'] = '<img src="/image/blue-dot.png" width="14" height="14" />';
				}
			}

			if (is_array($matchedProduct)) {
				if (empty($item['manufacturer']) && !empty($matchedProduct['manufacturer'])) {
					$item['manufacturer'] = (string) $matchedProduct['manufacturer'];
				}
				if (empty($item['product_name']) && !empty($matchedProduct['name'])) {
					$item['product_name'] = (string) $matchedProduct['name'];
				}
			}

			if (!isset($newCart[$groupSupplier]) || !is_array($newCart[$groupSupplier])) {
				$newCart[$groupSupplier] = [];
			}
			$item['qty'] = (int) ($item['qty'] ?? 1);
			if (trim((string) ($item['supplier'] ?? '')) === '') {
				$item['supplier'] = $itemSupplier;
			}
			if (trim((string) ($item['product_code'] ?? '')) === '' && $normalizedCode !== '') {
				$item['product_code'] = $normalizedCode;
			}
			$newCart[$groupSupplier][] = $item;
		}

		$this->saveSupplierCart($newCart);

		if (!empty($skippedItems)) {
			return redirect()->route('searching.cartShow')
				->with('warning', 'Some items were skipped while loading wishlist.')
				->with('skipped_items', $skippedItems)
				->with('skipped_api_debug', $skippedApiDebug);
		}

		return redirect()->route('searching.cartShow')
			->with('success', 'Wishlist loaded into your cart.');
	}

    public function listSavedWishlists()
    {
        $savedCarts = SupplierSavedCart::with('user')->orderByDesc('created_at')->orderByDesc('id')->get();
        return view('searching.saved_carts', compact('savedCarts'));
    }
	
	public function wishlistCreateOffer($id)
	{
		$savedCart = SupplierSavedCart::findOrFail($id);
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
            $to = [
                (string) ($savedCart->name ?? ''),
                'Telefon: ' . (trim((string) ($savedCart->phone ?? '')) !== '' ? (string) $savedCart->phone : '-'),
                'VIN: ' . (trim((string) ($savedCart->vin ?? '')) !== '' ? (string) $savedCart->vin : '-'),
            ];


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
						$nume_produs = trim((string) ($item['product_name'] ?? ''));
						$brand = trim((string) ($item['manufacturer'] ?? ''));
						if ($brand !== '') {
							$nume_produs .= ' (' . $brand . ')';
						}
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
        $savedCart = SupplierSavedCart::findOrFail($id);
        $savedCart->delete();
        return back()->with('success', 'Saved cart deleted.');
    }

    public function wishlistWhatsApp($id)
    {
        $savedCart = SupplierSavedCart::findOrFail($id);
        
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
                $brand = trim((string) ($item['manufacturer'] ?? ''));
                // Show brand after product name, same as PDF: "Product (BRAND)".
                if ($brand !== '' && $brand !== '-' && strcasecmp(trim((string) $productName), $brand) !== 0) {
                    $productName = trim((string) $productName) . ' (' . $brand . ')';
                }
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

	/**
	 * Materom availability lookup uses the base article (before '#').
	 * Example: "2441501#MATR#stocable#3000#11562172#qty:1" -> "2441501".
	 */
	private function normalizeMateromApiLookupCode(string $code): string
	{
		$code = trim($code);
		if ($code === '') {
			return '';
		}

		$parts = explode('#', $code, 2);
		$base = trim((string) ($parts[0] ?? ''));
		if ($base !== '') {
			return $base;
		}

		return $code;
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
	 * Fetch itemkey + sup_brand from autototal_branduri_proprii where cod_sursa = mainart_code_parts.
	 * Returns array of ['itemkey' => string, 'sup_brand' => string|null].
	 */
	private function getAutototalBranduriProprii(string $codSursa): array
	{
		$codSursa = trim($codSursa);
		if ($codSursa === '') {
			return [];
		}
		$normalized = str_replace([' ', '-', '/', '|', '\\'], '', $codSursa);
		try {
			$rows = DB::table('autototal_branduri_proprii')
				->select('itemkey', 'sup_brand')
				->where('cod_sursa', $codSursa)
				->orWhere('cod_sursa', $normalized)
				->orWhereRaw(
					"REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(cod_sursa, ' ', ''), '-', ''), '/', ''), '|', ''), '\\\\', '') = ?",
					[$normalized]
				)
				->get();
			$out = [];
			foreach ($rows as $r) {
				$ik = isset($r->itemkey) ? (is_string($r->itemkey) ? trim($r->itemkey) : (string) $r->itemkey) : '';
				if ($ik !== '') {
					$out[] = [
						'itemkey'  => $ik,
						'sup_brand' => isset($r->sup_brand) ? trim((string) $r->sup_brand) : null,
					];
				}
			}
			return $out;
		} catch (\Throwable $e) {
			return [];
		}
	}

	private function normalizeAutototalBrand(?string $brand): string
	{
		$brand = strtoupper(trim((string) $brand));
		if ($brand === '') {
			return '';
		}

		return preg_replace('/[^A-Z0-9]/', '', $brand) ?? '';
	}

	private function normalizeAutototalCodeForCompare(?string $code): string
	{
		$code = trim((string) $code);
		if ($code === '') {
			return '';
		}

		return preg_replace(
			'/[.\s\-\/|\\\\]+/',
			'',
			(string) $this->autototalService->normalizeCode($code)
		) ?? '';
	}

	private function autototalManufacturerMatchesSupBrand(?string $manufacturer, ?string $supBrand): bool
	{
		$target = $this->normalizeAutototalBrand($supBrand);
		if ($target === '') {
			return false;
		}

		$actual = $this->normalizeAutototalBrand($manufacturer);

		return $actual !== '' && $actual === $target;
	}

	/**
	 * Resolve AutoTotal order ITEMKEY from catalog tables only.
	 * Checks autototal_data (art_article_nr) first, then autototal_branduri_proprii (cod_sursa + sup_brand).
	 */
	private function resolveAutototalOrderItemkeyFromCartItem(array $item): ?string
	{
		$productCode = trim((string) ($item['product_code'] ?? ''));
		$manufacturer = isset($item['manufacturer']) ? trim((string) $item['manufacturer']) : '';
		$variantCode = trim((string) ($item['variant_code'] ?? ''));

		$codCandidates = [];
		if ($productCode !== '') {
			$codCandidates[] = $productCode;
			if ($manufacturer === 'INA') {
				$codCandidates[] = str_replace(' ', '', $productCode);
			}
		}
		if ($variantCode !== '' && !in_array($variantCode, $codCandidates, true)) {
			$codCandidates[] = $variantCode;
		}
		$codCandidates = array_values(array_unique(array_filter($codCandidates, static fn ($c) => $c !== '')));

		foreach ($codCandidates as $cod) {
			$ik = $this->findAutototalItemkey($cod);
			if ($ik !== null) {
				return $ik;
			}
		}

		foreach ($codCandidates as $cod) {
			$brandRows = $this->getAutototalBranduriProprii($cod);
			if (empty($brandRows)) {
				continue;
			}

			foreach ($brandRows as $br) {
				$supBrand = isset($br['sup_brand']) ? trim((string) $br['sup_brand']) : '';
				if ($supBrand === '') {
					continue;
				}
				if ($this->autototalManufacturerMatchesSupBrand($manufacturer, $supBrand)) {
					return $br['itemkey'];
				}
			}

			$emptySupBrandKeys = [];
			foreach ($brandRows as $br) {
				$supBrand = isset($br['sup_brand']) ? trim((string) $br['sup_brand']) : '';
				if ($supBrand === '') {
					$emptySupBrandKeys[] = $br['itemkey'];
				}
			}
			if (count($emptySupBrandKeys) === 1) {
				return $emptySupBrandKeys[0];
			}
			if (count($brandRows) === 1) {
				return $brandRows[0]['itemkey'];
			}
		}

		return null;
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
			'elit' => 'ET',
			'site_produse' => 'ET',
		];
		
		return $mapping[$supplier] ?? 'MA'; // Default to MA if not found
	}

	/**
	 * Whether the current user may add / list site catalogue (produse) lines on the cart.
	 */
	private function userCanAddSiteProduseFromCatalog(): bool
	{
		$user = Auth::user();
		if (!$user) {
			return false;
		}
		if (($user->rol ?? '') === 'manager') {
			return true;
		}
		return $user->hasPermission('produse');
	}

	/**
	 * JSON list of produse rows for the cart picker (paginated + search; never loads full catalogue).
	 */
	public function siteProduseListForCart(Request $request)
	{
		if (!$this->userCanAddSiteProduseFromCatalog()) {
			abort(403, 'Nu ai acces la lista de produse.');
		}

		$perPage = (int) $request->input('per_page', 10);
		$perPage = min(50, max(1, $perPage));
		$page = max(1, (int) $request->input('page', 1));

		$query = Produse::query()->select(['idprodus', 'cod_produs', 'denumire', 'pret', 'TVA', 'um']);

		$search = trim((string) $request->input('q', ''));
		if ($search !== '') {
			$escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
			$like = '%' . $escaped . '%';
			$query->where(function ($q) use ($like) {
				$q->where('cod_produs', 'like', $like)
					->orWhere('denumire', 'like', $like);
			});
		}

		$paginator = $query->orderBy('denumire')->paginate($perPage, ['*'], 'page', $page);

		return response()->json([
			'success' => true,
			'products' => $paginator->items(),
			'total' => $paginator->total(),
			'per_page' => $paginator->perPage(),
			'current_page' => $paginator->currentPage(),
			'last_page' => $paginator->lastPage(),
			'from' => $paginator->firstItem(),
			'to' => $paginator->lastItem(),
		]);
	}

	/**
	 * Add a row from the local produse catalogue to the cart (no supplier API).
	 * Session key: suppliercart['site_produse'].
	 */
	public function cartAddSiteProduse(Request $request)
	{
		if (!$this->userCanAddSiteProduseFromCatalog()) {
			abort(403, 'Nu ai acces la această acțiune.');
		}

		$request->validate([
			'idprodus' => 'required|integer|min:1',
			'qty' => 'nullable|integer|min:1|max:99999',
			'price' => 'nullable|numeric|min:0',
		]);

		$qty = (int) ($request->input('qty') ?? 1);
		if ($qty < 1) {
			$qty = 1;
		}

		$produs = Produse::findOrFail((int) $request->input('idprodus'));

		$dbPret = (float) ($produs->pret ?? 0);
		$unitPrice = $dbPret;
		if ($request->has('price') && $request->input('price') !== '' && $request->input('price') !== null) {
			$unitPrice = max(0.0, (float) $request->input('price'));
		}

		$cart = $this->getSupplierCart();
		$supplier = 'site_produse';

		$productCode = trim((string) ($produs->cod_produs ?? ''));
		if ($productCode === '') {
			return response()->json([
				'success' => false,
				'message' => 'Produsul nu are cod.',
			], 422);
		}

		$productName = trim((string) ($produs->denumire ?? ''));
		if ($productName === '') {
			$productName = $productCode;
		}

		$variantCode = $productCode;
		$cartKey = 'sp-' . (int) $produs->idprodus;

		$plantImage = '<img src="/image/green-dot.png" width="14" height="14" />';

		if (!isset($cart[$supplier])) {
			$cart[$supplier] = [];
		}

		if (isset($cart[$supplier][$cartKey])) {
			$cart[$supplier][$cartKey]['qty'] += $qty;
			$cart[$supplier][$cartKey]['price'] = $unitPrice;
			$cart[$supplier][$cartKey]['order_code'] = $this->updateOrderCodeQuantity(
				$cart[$supplier][$cartKey]['order_code'],
				$cart[$supplier][$cartKey]['qty']
			);
		} else {
			$cart[$supplier][$cartKey] = [
				'supplier' => $supplier,
				'idprodus' => (int) $produs->idprodus,
				'product_code' => $productCode,
				'product_name' => $productName,
				'manufacturer' => '-',
				'variant_code' => $variantCode,
				'qty' => $qty,
				'price' => $unitPrice,
				'currency' => 'RON',
				'delivery' => '',
				'plantraw' => 'Magazin propriu',
				'plantname' => $plantImage,
				'livrare' => 'Magazin propriu',
				'depozit' => '-',
				'departamentCode' => '',
				'order_code' => $this->updateOrderCodeQuantity($variantCode, $qty),
			];
		}

		$this->saveSupplierCart($cart);

		return response()->json([
			'success' => true,
			'message' => 'Produs adăugat în coș.',
			'cart' => $cart,
		]);
	}
	
	/**
	 * Extract plant name from delivery info and return appropriate color
	 * Works for all suppliers: materom, autototal, autonet, autopartner
	 */
	private function getColorFromDeliveryInfo(array $item): string
	{
		$supplier = strtolower($item['supplier'] ?? '');
		if ($supplier === 'site_produse') {
			return '7CFC00';
		}
		$plantraw = $item['plantraw'] ?? '';
		$delivery = $item['delivery'] ?? '';
		$livrare = $item['livrare'] ?? '';
		
		/* ===== MATEROM ===== */
		if ($supplier === 'materom') {
			$normalize = static function (string $value): string {
				$value = mb_strtolower($value, 'UTF-8');
				return str_replace(
					['ă', 'â', 'î', 'ș', 'ş', 'ț', 'ţ'],
					['a', 'a', 'i', 's', 's', 't', 't'],
					$value
				);
			};

			$plantrawNormalized = $normalize((string) $plantraw);
			$livrareNormalized = $normalize((string) $livrare);
			$deliveryInfo = '';
			if (is_array($delivery)) {
				$deliveryInfo = (string) ($delivery['info_text'] ?? '');
			} elseif (is_string($delivery)) {
				$deliveryInfo = $delivery;
			}
			$deliveryNormalized = $normalize($deliveryInfo);
			$combined = trim($livrareNormalized . ' ' . $deliveryNormalized);

			// Plant-based fast mapping for Materom.
			if (str_contains($plantrawNormalized, 'timisoara')) {
				return '7CFC00'; // Green - today
			} elseif (str_contains($plantrawNormalized, 'centru logistic')) {
				return 'ADD8E6'; // Blue - tomorrow
			}
			
			// Text-based mapping (covers "Azi", "astăzi", "Mâine").
			if (preg_match('/\b(azi|astazi)\b/u', $combined)) {
				return '7CFC00';
			}
			if (preg_match('/\bmaine\b/u', $combined)) {
				return 'ADD8E6';
			}

			// "2 zile" => orange, ">3 zile" => red.
			// For ranges like "2-4 zile", first number (2) keeps it in orange bucket.
			if (preg_match('/(\d+)\s*(?:-|to)?\s*(\d+)?\s*zile/iu', $combined, $matches)) {
				$fromDays = (int) ($matches[1] ?? 0);
				if ($fromDays === 2) {
					return 'F5A000';
				}
				if ($fromDays > 3) {
					return 'FF0000';
				}
			}

			return 'FF0000'; // Default red for unknown/slow deliveries
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
	 * Split depozit / warehouse text from cart (e.g. "RTDS + RBSE", "Tm. + Buc.-IMGB") into slots.
	 *
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
	 * Autonet / Autototal: multiple warehouse slots in one line — map each ordered unit to a slot
	 * (1st qty → 1st depot, 2nd qty → 2nd depot; qty 1 → 1st depot only). Other suppliers: one tmp row.
	 * furnizor is always the short supplier code only (MA, AP, AN, AT, ET) — never warehouse text.
	 *
	 * @return list<array{qty: int, culoare: string, furnizor: string}>
	 */
	private function expandSupplierCartItemForTmpRows(array $item): array
	{
		$supplier = strtolower(trim((string) ($item['supplier'] ?? '')));
		$qty = max(1, (int) ($item['qty'] ?? 1));
		$baseFurnizor = (isset($item['tmp_furnizor']) && (string) $item['tmp_furnizor'] !== '')
			? (string) $item['tmp_furnizor']
			: $this->getSupplierCode($supplier);
		$slots = $this->parseWarehouseSlotsFromCartItem($item);

		if (!in_array($supplier, ['autonet', 'autototal'], true) || count($slots) <= 1) {
			$culoare = (isset($item['tmp_culoare']) && (string) $item['tmp_culoare'] !== '')
				? (string) $item['tmp_culoare']
				: $this->getColorFromDeliveryInfo($item);

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
			$culoare = (isset($item['tmp_culoare']) && (string) $item['tmp_culoare'] !== '')
				? (string) $item['tmp_culoare']
				: $this->getColorFromDeliveryInfo($itemForColor);
			$out[] = [
				'qty' => 1,
				'culoare' => $culoare,
				'furnizor' => $baseFurnizor,
			];
		}

		return $out;
	}

	private function addProductsToDbAndTemp(array $items)
	{
		$session_id = session()->getId();

		foreach ($items as $item) {
			// 1️⃣ Add or update product in 'produse' table
			$produs = Produse::updateOrCreate(
				['cod_produs' => $item['product_code']],
				[
					'denumire' => $item['product_name'],
					'pret' => $item['price'],
					'created_at' => Carbon::now()->timestamp + (2 * 3600)
				]
			);

			// 2️⃣ Replace tmp rows for this product (supports multiple lines per product for multi-depot)
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

	/**
	 * Materom API labels brands as e.g. "VALEO - OEM" or "SKF - AM"; strip the suffix for Marcă (order flow unchanged).
	 */
	private function normalizeMateromManufacturer($manufacturer): string
	{
		$s = $this->materomManufacturerToString($manufacturer);
		if ($s === '') {
			return '';
		}
		$prev = null;
		while ($prev !== $s) {
			$prev = $s;
			$s = preg_replace('/\s*[-–—]\s*(OEM|AM|OE)\s*$/iu', '', $s);
			$s = trim($s);
		}

		return $s;
	}

	private function materomManufacturerToString($value): string
	{
		if ($value === null) {
			return '';
		}
		if (is_string($value)) {
			return trim($value);
		}
		if (is_array($value)) {
			$parts = [];
			foreach ($value as $v) {
				if (is_string($v) && trim($v) !== '') {
					$parts[] = trim($v);
				} elseif (is_scalar($v)) {
					$parts[] = trim((string) $v);
				}
			}

			return trim(implode(' ', $parts));
		}
		if (is_scalar($value)) {
			return trim((string) $value);
		}

		return '';
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