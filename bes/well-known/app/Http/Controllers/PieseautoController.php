<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\Localitate;
use App\Models\Comenzi;
use App\Models\ComenziExt;
use App\Models\Client;
use App\Services\AnafService;

use App\Models\User;
use App\Models\UserPermission;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class PieseautoController extends Controller
{
    protected $anafService;

    public function __construct(AnafService $anafService)
    {
        $this->anafService = $anafService;
    }
	
    public function index()
    {
        return view('pieseauto.index');
    }
	
	public function fetchOrders(Request $request)
    {
		$account = $request->input('account');
		if($account == 1){
			$url = 'https://www.pieseauto.ro/ctl.php?a=exportOrders&auth=2d006gduutg34KL37G-utZnwiEzAtiRTk';
		}else{
			$url = 'https://www.pieseauto.ro/ctl.php?a=exportOrders&auth=54a643gNWvkzK048TAuUdwyXrmqDZQUJM8';
		}

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/141.0.0.0 Safari/537.36",
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/javascript, */*; q=0.01',
                'Referer: https://www.pieseauto.ro/',
                'Origin: https://www.pieseauto.ro'
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode != 200) {
            return response()->json([
                'error' => 'Unable to fetch',
                'details' => "HTTP code $httpCode, cURL error: $curlErr"
            ], 500);
        }

        return response($response, 200)->header('Content-Type', 'application/json');
    }
	
	public function import(Request $request) {
		$orders = $request->orders;
		$destination = $request->destination ?? 'external'; // utvin, tm, external

		if (empty($orders)) {
			return response()->json(['success' => false, 'message' => 'No orders selected.'], 400);
		}

		DB::beginTransaction();
 
		try {
			$transportProducts = [];
			
			foreach ($orders as $orderData) {
				// --- Handle Client ---
				$client = \App\Models\Client::firstOrNew([
					'telefon' => $orderData['buyer_phone']
				]);
				
				
				$company_billing_data = $orderData['company_billing_data']['company_cui'] ?? null;
				$client->companie    = $orderData['company_billing_data']['company_name'] ?? $client->companie;
				$client->regcom    = $orderData['company_billing_data']['company_reg'] ?? $client->regcom;
				if($company_billing_data){
					$client->adresa     = $orderData['company_billing_data']['company_address'] ?? $client->adresa;
					
					// Map billing city if available
					$company_cui = $orderData['company_billing_data']['company_cui'] ?? null;
					$billingCityId = null;
					if ($company_cui) {
						$cuiRaw = $orderData['company_billing_data']['company_cui'];
						$cui = preg_replace('/\D/', '', $cuiRaw);
						$anaf = $this->getAnafInfoPrivate($cui);
						if(!empty($anaf) && is_array($anaf) && isset($anaf['found'])){
							$scod_Postal = $this->removeDiacritics($anaf['found'][0]['adresa_sediu_social']['scod_Postal']);
							if ($scod_Postal) {
								$withZero = '0'.$scod_Postal;
								$scodData = \DB::table('coduri_postale')
										->whereIn('cod', [$scod_Postal, $withZero])
										->first();
								if($scodData){
									$anafcity = $this->removeDiacritics($scodData->localitate);
									$anafcounty = $this->removeDiacritics($scodData->judet);
									
									$anafcity = isset($anafcity) 
										? str_replace('-', ' ', $anafcity) 
										: null;

									$anafcounty = isset($anafcounty) 
										? str_replace('-', ' ', $anafcounty) 
										: null;

									$city = Localitate::where('localitate', $anafcity)->where('judet', $anafcounty)->first();
									if (!$city) {
										// Manually get next idlocatie
										$lastLocalitate = Localitate::orderBy('idlocatie', 'desc')->first();
										$nextId = $lastLocalitate ? $lastLocalitate->idlocatie + 1 : 1;

										$city = Localitate::create([
											'idlocatie' => $nextId,
											'localitate' => $anafcity,
											'judet' => $anafcounty,
											'codrutare' => 4000,
										]);
									}else{
										$nextId = $city->idlocatie;
									}

									$billingCityId = $nextId;
								}
							}
							if(!empty($anaf['found'][0]['date_generale']['nrRegCom'])){
								$client->regcom = $anaf['found'][0]['date_generale']['nrRegCom'];
							}
							if(!empty($anaf['found'][0]['date_generale']['denumire'])){
								$client->companie = $anaf['found'][0]['date_generale']['denumire'];
							}
							if(!empty($anaf['found'][0]['date_generale']['adresa'])){
								$client->adresa = $anaf['found'][0]['date_generale']['adresa'];
							}
						}
					}
					
					$client->idlocalitate = $billingCityId;
					
					$client->adresa_facturare   = $orderData['shipping_address'] ?? $client->adresa_facturare;
					
					// Map shipping city to idlocalitate
					$shippingCityName = isset($orderData['shipping_city']) 
						? str_replace('-', ' ', $orderData['shipping_city']) 
						: null;

					$shippingCounty = isset($orderData['shipping_county']) 
						? str_replace('-', ' ', $orderData['shipping_county']) 
						: null;
					$shippingCityId = null;
					if ($shippingCityName) {
						$city = Localitate::where('localitate', $shippingCityName)->where('judet', $shippingCounty)->first();
						if (!$city) {
							// Manually get next idlocatie
							$lastLocalitate = Localitate::orderBy('idlocatie', 'desc')->first();
							$nextId = $lastLocalitate ? $lastLocalitate->idlocatie + 1 : 1;

							$city = Localitate::create([
								'idlocatie' => $nextId,
								'localitate' => $shippingCityName,
								'judet' => isset($orderData['shipping_county']) ? ucfirst(strtolower($orderData['shipping_county'])) : '',
								'codrutare' => 4000,
							]);
						}else{
							$nextId = $city->idlocatie;
						}

						$shippingCityId = $nextId;
					}
					$client->localitate_facturare = $shippingCityId;
				}else{
					$client->adresa     = $orderData['shipping_address'] ?? $client->adresa;
					
					// Map shipping city to idlocalitate
					$shippingCityName = isset($orderData['shipping_city']) 
						? str_replace('-', ' ', $orderData['shipping_city']) 
						: null;

					$shippingCounty = isset($orderData['shipping_county']) 
						? str_replace('-', ' ', $orderData['shipping_county']) 
						: null;
					$shippingCityId = null;
					if ($shippingCityName) {
						$city = Localitate::where('localitate', $shippingCityName)->where('judet', $shippingCounty)->first();
						if (!$city) {
							// Manually get next idlocatie
							$lastLocalitate = Localitate::orderBy('idlocatie', 'desc')->first();
							$nextId = $lastLocalitate ? $lastLocalitate->idlocatie + 1 : 1;

							$city = Localitate::create([
								'idlocatie' => $nextId,
								'localitate' => $shippingCityName,
								'judet' => isset($orderData['shipping_county']) ? ucfirst(strtolower($orderData['shipping_county'])) : '',
								'codrutare' => 4000,
							]);
						}else{
							$nextId = $city->idlocatie;
						}

						$shippingCityId = $nextId;
					}
					$client->idlocalitate = $shippingCityId;
				}
				
				
				
				
				
				
				
				
				
				
				
/* 				// Map shipping city to idlocalitate
				$shippingCityName = isset($orderData['shipping_city']) 
					? str_replace('-', ' ', $orderData['shipping_city']) 
					: null;

				$shippingCounty = isset($orderData['shipping_county']) 
					? str_replace('-', ' ', $orderData['shipping_county']) 
					: null;
				$shippingCityId = null;
				if ($shippingCityName) {
					$city = Localitate::where('localitate', $shippingCityName)->where('judet', $shippingCounty)->first();
					if (!$city) {
						// Manually get next idlocatie
						$lastLocalitate = Localitate::orderBy('idlocatie', 'desc')->first();
						$nextId = $lastLocalitate ? $lastLocalitate->idlocatie + 1 : 1;

						$city = Localitate::create([
							'idlocatie' => $nextId,
							'localitate' => $shippingCityName,
							'judet' => isset($orderData['shipping_county']) ? ucfirst(strtolower($orderData['shipping_county'])) : '',
							'codrutare' => 4000,
						]);
					}else{
						$nextId = $city->idlocatie;
					}

					$shippingCityId = $nextId;
				} */

				$client->cif    = $orderData['company_billing_data']['company_cui'] ?? $client->cif;
				$client->nume    = $orderData['buyer_name'] ?? $client->nume;
				//$client->adresa     = $orderData['company_billing_data']['company_address'] ?? $client->adresa_facturare;
				//$client->localitate_facturare = $shippingCityId;
				//$client->adresa_facturare   = $orderData['shipping_address'] ?? $client->adresa;

				/* // Map billing city if available
				$billingCityName = $orderData['company_billing_data']['company_cui'] ?? null;
				$billingCityId = null;
				if ($billingCityName) {
					$cuiRaw = $orderData['company_billing_data']['company_cui'];
					$cui = preg_replace('/\D/', '', $cuiRaw);
					$anaf = $this->getAnafInfoPrivate($cui);
					//dd($anaf);
					if(!empty($anaf) && is_array($anaf) && isset($anaf['found'])){
						$scod_Postal = $this->removeDiacritics($anaf['found'][0]['adresa_sediu_social']['scod_Postal']);
						if ($scod_Postal) {
							$scodData = \DB::table('coduri_postale')
									->where('cod', $scod_Postal)
									->first();
							if($scodData){
								$anafcity = $this->removeDiacritics($scodData->localitate);
								$anafcounty = $this->removeDiacritics($scodData->judet);

								$city = Localitate::where('localitate', $anafcity)->first();
								if (!$city) {
									// Manually get next idlocatie
									$lastLocalitate = Localitate::orderBy('idlocatie', 'desc')->first();
									$nextId = $lastLocalitate ? $lastLocalitate->idlocatie + 1 : 1;

									$city = Localitate::create([
										'idlocatie' => $nextId,
										'localitate' => $anafcity,
										'judet' => $anafcounty,
										'codrutare' => 4000,
									]);
								}else{
									$nextId = $city->idlocatie;
								}

								$billingCityId = $nextId;
								
								if(!empty($anaf['found'][0]['date_generale']['nrRegCom'])){
									$client->regcom = $anaf['found'][0]['date_generale']['nrRegCom'];
								}
								if(!empty($anaf['found'][0]['date_generale']['denumire'])){
									$client->companie = $anaf['found'][0]['date_generale']['denumire'];
								}
							}
						}
					}
				} */
				
				
				
				

				//$client->idlocalitate = $billingCityId;
				$client->telefon = $orderData['buyer_phone'] ?? $client->telefon;
				$client->created_at = Carbon::now()->timestamp + (2 * 3600);
				$client->save();
				

				// --- Handle Order ---
				/* $orderDate = isset($orderData['order_date']) 
					? \Carbon\Carbon::createFromTimestamp($orderData['order_date'])->format('Y-m-d')
					: now()->format('Y-m-d'); */
				$orderDate = now()->format('Y-m-d');
				
				$products = $orderData['items'] ?? null;
				if (!empty($products)) {
					$totalOrder = 0; // recalc total only from selected items
					foreach ($products as $item) {
						$totalOrder += (float)($item['unit_price_ron'] ?? 0) * ($item['quantity'] ?? 1);
					}
					// Add shipping cost if exists
					//$totalOrder += (float)($orderData['shipping_cost_ron'] ?? 0);
				} else {
					$totalOrder = 1; // fallback if no items
				}

				// Determine table/model based on destination
				if (in_array(strtolower($destination), ['utvin','tm'])) {
					$orderModel = new \App\Models\Comenzi();
					$orderModel->locatie_mgz = strtolower($destination) === 'utvin' ? 2 : 1; // 2=UTVIN, 1=TM

					// Generate a new idcomanda manually
					$lastOrder = DB::table('comenzi')->orderBy('idcomanda', 'desc')->first();
					$orderId = $lastOrder ? $lastOrder->idcomanda + 1 : 1;
				} else {
					$orderModel = new \App\Models\ComenziExt();

					$lastOrder = DB::table('comenzi_ext')->orderBy('idcomanda', 'desc')->first();
					$orderId = $lastOrder ? $lastOrder->idcomanda + 1 : 1;
				}
				
				$orderModel->idcomanda = $orderId;
				$orderModel->idclient     = $client->idclienti;
				$orderModel->userid = Auth::user()->Id;
				$orderModel->data         = $orderDate;
				$orderModel->idmasina     = 1; // default car ID
				$orderModel->total        = $totalOrder;
				$orderModel->stare        = 1;
				$orderModel->cont_awb     = $orderData['courier_awb'] ?? '';
				$orderModel->observations = $orderData['buyer_comments'] ?? '';
				$orderModel->created_at   = Carbon::now()->timestamp + (2 * 3600);
				$orderModel->save();
				
				// --- Add default product directly ---
				if ($products && count($products) > 0) {
					foreach ($products as $item) {
						$title = trim(strtolower($item['title'] ?? ''));
						$isTransport = str_contains($title, 'transport') || str_contains($title, 'courier') || str_contains($title, 'delivery');
						
						if ($isTransport) {
							if (isset($transportProducts[$title])) {
								$product = $transportProducts[$title];
							} else {
								$product = \App\Models\Produse::firstOrCreate(
									['denumire' => $item['title'], 'pret' => $item['unit_price_ron']],
									[
										'denumire' => $item['title'] ?? 'Transport',
										'pret' => $item['unit_price_ron'] ?? 0,
										'created_at' => Carbon::now()->timestamp + (2 * 3600),
									]
								);
								$transportProducts[$title] = $product;
							}
						} else {
							// 1️⃣ Check if product exists (by product_id or product_id)
							$product = \App\Models\Produse::where('cod_produs', $item['product_id'])
								->first();

							if (!$product) {
								// 2️⃣ Create new product if not found
								$product = new \App\Models\Produse();
								$product->denumire        = $item['title'] ?? 'Unnamed Product';
								$product->cod_produs  = $item['product_id'] ?? null;
								$product->pret        = $item['unit_price_ron'] ?? 0;
								$product->created_at = Carbon::now()->timestamp + (2 * 3600);
								$product->save();
							}
						}

						// 3️⃣ Add product to the order detail
						$detailTable = in_array(strtolower($destination), ['utvin', 'tm']) ? 'detaliu' : 'detaliu_ext';
						DB::table($detailTable)->insert([
							'idcomanda' => $orderId,
							'idprodus'  => $product->idprodus, 
							'cantitate' => $item['quantity'] ?? 1,
							'pret'      => $item['unit_price_ron'] ?? 0,
							'furnizor'  => $item['furnizor'] ?? '__',
							'culoare'   => $item['disponibilitate'] ?? 'FFFFFF',
							'created_at'=> Carbon::now()->timestamp + (2 * 3600),
						]);
					}
				}else{
					$detailTable = in_array(strtolower($destination), ['utvin', 'tm']) ? 'detaliu' : 'detaliu_ext';
					DB::table($detailTable)->insert([
						'idcomanda' => $orderId,
						'idprodus'  => 66790,
						'cantitate' => 1,
						'pret'      => 0,
						'furnizor'  => '__',
						'culoare'   => 'FFFFFF',
						'created_at'=> Carbon::now()->timestamp + (2 * 3600),
					]);
				}
			}

			DB::commit();

			return response()->json([
				'success' => true,
				'message' => 'Orders and clients imported successfully.'
			]);
		} catch (\Exception $e) {
			DB::rollBack();
			Log::error('Error importing PieseAuto orders: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

			return response()->json([
				'success' => false,
				'message' => 'Error importing orders: ' . $e->getMessage()
			], 500);
		}
	}
	
    private function getAnafInfoPrivate($cui)
    {   
        if (empty($cui)) {
            return 'CUI is required';
        }

        // Get company info from ANAF API
        $result = $this->anafService->getCompanyInfo($cui);
		if (!empty($result['found'])) {
			foreach ($result['found'] as &$company) {
				$company['coduri_postale'] = [];

				// For social address
				$scod = $company['adresa_sediu_social']['scod_Postal'] ?? null;
				if ($scod) {
					$scodData = \DB::table('coduri_postale')
						->where('cod', $scod)
						->first();
					$company['coduri_postale']['scod_Postal'] = $scodData;
				}

				// For fiscal address
				$dcod = $company['adresa_domiciliu_fiscal']['dcod_Postal'] ?? null;
				if ($dcod) {
					$dcodData = \DB::table('coduri_postale')
						->where('cod', $dcod)
						->first();
					$company['coduri_postale']['dcod_Postal'] = $dcodData;
				}
			}
		}
        
        // Return the response directly as in the original anaf.php
        return $result;
    }
	
	private function removeDiacritics($string) {
		$translit = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
		return $translit;
	}
}