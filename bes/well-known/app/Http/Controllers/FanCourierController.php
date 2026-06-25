<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\FanCourier\FanCourierService;

use Sameday\Requests\SamedayGetServicesRequest;
use Sameday\Sameday;
use Sameday\SamedayClient;

use Fancourier\Fancourier;
use Fancourier\Request\GetCosts;
use Fancourier\Objects\AwbIntern;
use Fancourier\Request\CreateAwb;
use Fancourier\Request\CreateCourierOrder;


class FanCourierController extends Controller
{
	protected $fanCourier;
    /**
     * FanCourierController constructor.
     *
     */
    public function __construct(FanCourierService $fanCourier)
    {
        //add any necessary middleware or initialization here
		$this->fanCourier = $fanCourier;
        $this->client = new Sameday(
            new SamedayClient(
                env('SAMEDAY_USERNAME'), // set this in your .env file
                env('SAMEDAY_PASSWORD'),
                null,
                env('SAMEDAY_TESTING', 'testing') === 'production'
            )
        );
    }

    /**
     * get services
     * @return \Illuminate\Http\JsonResponse
     */
	public function getServices()
	{
		try {
			$services = $this->fanCourier->getServices();

			// Log result for debugging
			Log::info('Fan Courier services', [
				'services' => $services
			]);

			// Return JSON response
			return response()->json([
				'success' => true,
				'data' => $services
			]);
		} catch (\Exception $e) {
			Log::error('Fan Courier services error', [
				'message' => $e->getMessage(),
				'trace'   => $e->getTraceAsString()
			]);

			return response()->json([
				'success' => false,
				'message' => 'Error fetching services: ' . $e->getMessage()
			], 500);
		}
	}

    /**
     * Generate AWB using FanCourier
     */
    public function createFanCourierAwb(Request $request)
    {
        try {
            Log::info('FanCourier AWB Request Received', [
                'data' => array_merge(
                    $request->except(['_token']), 
                    ['sensitive_data_masked' => true]
                ),
            ]);
                        
			$validated = $request->validate([
				'tipserviciu' => 'sometimes|string|max:50',
				'contfan' => 'sometimes|string|max:50',
				'tel_awb_cmd' => 'required|string|max:20',
				'judet_awb_cmd' => 'required|string|max:50',
				'local_awb_cmd' => 'required|string|max:100',
				'adresa_awb_cmd' => 'required|string|max:255',
				'mod_id_awb' => 'sometimes|numeric',
				'ramburs_awb_cmd' => 'sometimes|numeric|min:0',
				'greutate_awb_cmd' => 'sometimes|numeric|min:0.2|max:100',
				
				'courier_type' => 'sometimes|string',
				'courier_pickup_date' => 'sometimes|nullable|string',
				'fanbox_size' => 'sometimes|string',
				// require PUDO for FANBox/CollectPoint
				'pickup_point_id' => 'required_if:tipserviciu,FANbox,CollectPoint,FANbox Cont Colector,CollectPoint Cont Colector|string',
				'pickupLocation' => 'sometimes|string',
				'pudo_dropoff' => 'sometimes|boolean',
				
				'courier_pickup_slot' => 'sometimes|nullable|string',
			]);

            // account type (default to 'Utvin')
            $accountType = $request->input('contfan', 'Utvin');
                    
            // service type (default to 'Standard')
            $serviceType = $request->input('tipserviciu', 'Standard');
            // If a pickup point is provided but service isn't FANbox/CollectPoint, auto-switch to FANBox
            if ($request->filled('pickup_point_id')) {
                $sk = strtolower($serviceType);
                if (!in_array($sk, ['fanbox', 'collectpoint', 'fanbox cont colector', 'collectpoint cont colector', 'standard', 'cont colector'])) {
                    $serviceType = 'FANBox';
                }
            }
            $nr_colet = max(1, (int)$request->input('colet_awb_cmd', 1));
            $nr_plic = max(0, (int)$request->input('plic_awb_cmd', 0));
            $greutate = max(0.1, (float)$request->input('greutate_awb_cmd', 1));
            $ramburs = max(0, (float)$request->input('ramburs_awb_cmd', 0));
            $companie = trim($request->input('comp_awb_cmd', 'Destinatar'));
            $telefon = $validated['tel_awb_cmd'];
            $judet = $validated['judet_awb_cmd'];
            $courier_type = $validated['courier_type'];
			$courier_pickup_date = $validated['courier_pickup_date'] ?? null;
			$courier_pickup_slot = $validated['courier_pickup_slot'] ?? null;
            $localitate = $validated['local_awb_cmd'];
            $adresa = $validated['adresa_awb_cmd'];
            $contact = trim($request->input('nume_awb_cmd', 'Destinatar'));
            $plataexpeditie = $request->input('plataexpeditie', 'expeditor');
            $restit = $request->filled('restit_awb_cmd') ? $request->input('restit_awb_cmd') : '';

            $observatie = "";
            if ($request->has('obs1')) {
                $obs1 = strip_tags($request->input('obs1', ''), ENT_QUOTES);
                $observatie .= $obs1 . "/";
            }

            if ($request->has('obs2')) {
                $obs2 = strip_tags($request->input('obs2', ''), ENT_QUOTES);
                $observatie .= $obs2;
            }

            if ($accountType == 'Utvin') {
                $fan = Fancourier::utvinInstance();
            }
            else if ($accountType == 'Timisoara') {
                $fan = Fancourier::timisInstance();
            }
            else if ($accountType == 'Test') {
                $fan = Fancourier::testInstance();
            }
            else {
                return response()->json([
                    'success' => false,
                    'message' => 'unknoon account type'
                ], 500);
            }
			
			$sizes = [
				'L' => ['length' => 44.3, 'height' => 40.4, 'width' => 45],
				'M' => ['length' => 44.3, 'height' => 19.6, 'width' => 45],
				'S' => ['length' => 44.3, 'height' => 9.2,  'width' => 45],
			];

			$size = $sizes[$validated['fanbox_size'] ?? 'S'];

            $awb = new AwbIntern();

            $awb->setService($serviceType)
            ->setParcels($nr_colet)
            ->setEnvelopes($nr_plic)
            ->setWeight($greutate)
			->setSizes($size['length'], $size['height'], $size['width'])
            ->setDeclaredValue(0);
            
            // COD handling for different service types
            $serviceKey = strtolower($serviceType);
            if (in_array($serviceKey, ['fanbox cont colector', 'collectpoint cont colector'])) {
                // Cont Colector services REQUIRE COD - ensure minimum value of 1
                $ramburs = max(1, $ramburs);
                $awb->setReimbursement($ramburs);
            } elseif (!in_array($serviceKey, ['fanbox', 'collectpoint'])) {
                // Regular services can have COD
                $awb->setReimbursement($ramburs);
            }
            // Note: Regular FANbox and CollectPoint services do not allow COD
            
            $awb->setNotes("$observatie")
            ->setContents("piese auto")
            ->setRecipientName("$companie")
            ->setPhone("$telefon")
            ->setCounty("$judet")
            ->setCity("$localitate")
            ->setStreet("$adresa")
            ->setContactPerson("$contact")
            ->setPaymentType("$plataexpeditie")
            ->setRefund("$restit")
            ->setReturnPayment("expeditor");
			
			$serviceKey = strtolower($serviceType);
			if (in_array($serviceKey, ['fanbox', 'collectpoint', 'fanbox cont colector', 'collectpoint cont colector', 'standard', 'cont colector']) && !empty($request->input('pickup_point_id'))) {
				$pudoId = $request->input('pickup_point_id'); // this must be a valid PUDO ID
				if (!$pudoId) {
					return response()->json([
						'success' => false,
						'message' => 'pickup_point_id is required for FANBox/CollectPoint services'
					], 422);
				}
				// Fetch pickup point details and force destination address to PUDO address
				try {
					$points = $this->fanCourier->getNewPickupPoints();
					Log::info('Available pickup points', ['points' => $points]);
					$pudo = null;
					foreach ($points as $pt) {
						if (!empty($pt['id']) && (string)$pt['id'] === (string)$pudoId) {
							$pudo = $pt; break;
						}
					}
					
					if (!$pudo) {
						Log::error('Pickup point not found', [
							'requested_id' => $pudoId,
							'available_ids' => array_column($points, 'id')
						]);
						return response()->json([
							'success' => false,
							'message' => 'Invalid pickup_point_id: not found'
						], 422);
					}
					Log::info('Found pickup point', ['pudo' => $pudo]);

					// If service is FANbox, ensure type matches
					if (
						($serviceKey === 'fanbox' || $serviceKey === 'office') && 
						isset($pudo['type']) && 
						!in_array(strtolower($pudo['type']), ['fanbox', 'office'])
					) {
						return response()->json([
							'success' => false,
							'message' => 'pickup_point_id must be a FANbox locker for FANbox service'
						], 422);
					}
					$addr = $pudo['address'] ?? [];
					$county = $addr['county'] ?? $judet;
					$city = $addr['locality'] ?? $localitate;
					$streetName = $addr['street'] ?? $adresa;
					$streetNo = $addr['streetNo'] ?? '';
					$postalCode = $addr['zipCode'] ?? '';
					$awb->setCounty($county)
						->setCity($city)
						->setStreet($streetName);
					if (!empty($streetNo)) {
						$awb->setNumber($streetNo);
					}
					if (!empty($postalCode)) {
						$awb->setPostalCode($postalCode);
					}
				} catch (\Exception $e) {
					// If pickup points API fails, continue with provided address
				}
				
				//dd($pudoId);
                // For FANBox, set the pickup location ID (not name)
				$awb->setPickupLocation($pudoId);
				
				// FANBox options are mandatory: W for drop-off, V for pickup
				if ($serviceKey != "collectpoint" && $serviceKey != 'collectpoint cont colector'){
					if($serviceKey == "standard" || $serviceKey == "cont colector"){
						$awb->addOption('D');
					}else{
						if ($request->boolean('pudo_dropoff', false)) {
							if (!empty($pudo['name'])) {
								$awb->setDropOffLocation($pudo['name']);
							}
							$awb->addOption('W');
						} else {
							$awb->addOption('V');
						}
					}
				}
			}
			
/* 			if (in_array($serviceType, ['FANbox', 'CollectPoint'])) {
				$pudoId = $request->input('pickup_point_id'); // this must be a valid PUDO ID
				if (!$pudoId) {
					return response()->json([
						'success' => false,
						'message' => 'pickup_point_id is required for FANBox/CollectPoint services'
					], 422);
				}
				$awb->setPickupLocation($pudoId);
			} else {
				$awb->setlocation($request->input('pickup_point_id')); 
			} */

			if ($request->has('opt1')) {
				$awb->addOption('A');
			}

			if ($request->has('opt2')) {
				$awb->addOption('S');
			}

			if ($request->has('opt3')) {
				$awb->addOption('D');
			}
			//dd($awb);

		    $request = new CreateAwb();
						
		    $request->addAwb($awb);
            $response = $fan->createAwb($request);

            $awbNumber = '';
            $errors = [];
            if ($response->isOk()) {
			    $responseAll = $response->getAll();

    			foreach ($responseAll as $responseItem) {
    				if ($responseItem->hasErrors()) {
					    $errors []= $responseItem->getErrors();
					}
                    else {
					    $awbNumber = $responseItem->getAwb();
                    }
                }
            } else {
                try {
                    $responseAll = method_exists($response, 'getAll') ? $response->getAll() : null;
                    Log::error('FanCourier createAwb not OK', [
                        'response_all' => $responseAll
                    ]);
                    // Try to collect any errors from items
                    if (is_iterable($responseAll)) {
                        foreach ($responseAll as $responseItem) {
                            if (method_exists($responseItem, 'hasErrors') && $responseItem->hasErrors()) {
                                $errors []= $responseItem->getErrors();
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('FanCourier createAwb error and could not serialize response', [
                        'message' => $e->getMessage()
                    ]);
                }
            }
                                    
            if (!empty($awbNumber)) {
				if(!empty($courier_pickup_date)){
					$courierOrder = new CreateCourierOrder();
					$courierOrder
						->setAwb($awbNumber)
						->setRecipientName($companie)
						->setPhone($telefon)
						->setCounty($judet)
						->setCity($localitate)
						->setStreet($adresa)
						->setContactPerson($contact)
						->setPickupDate($courier_pickup_date ?? date('Y-m-d'))
						->setParcels($nr_colet)
						->setEnvelopes($nr_plic)
						->setSizes($size['length'], $size['height'], $size['width']);
						
						$pickupHours = ['15:00', '17:00'];
						switch ($courier_pickup_slot) {
							case '1': // Mon–Fri slot
								$pickupHours = ['15:00', '17:00'];
								break;
							case '2': // Saturday slot
								$pickupHours = ['12:00', '14:00'];
								break;
							default:
								$pickupHours = ['15:00', '17:00'];
								break;
						}
						//dd([$courier_pickup_slot, $pickupHours]);
						$courierOrder->setPickupHours($pickupHours[0], $pickupHours[1]);
						$response = $fan->createCourierOrder($courierOrder);
						//dd($response);

					if ($response->isOk()) {
						$orderNumber = $response->getData();
						return response()->json([
							'success' => true,
							'awb_number' => $awbNumber,
							'order_number' => $orderNumber,
							'message' => 'AWB and Courier Order created successfully'
						]);
					} else {
						//$errors = $response->getErrors();
						return response()->json([
							'success' => false,
							'message' => 'Error placing courier order',
							'errors' => $response
						], 500);
					}
				}
				
                return response()->json([
                    'success' => true,
                    'awb_number' => $awbNumber,
                    'message' => 'AWB generat cu succes'
                ]);
            }
            else {
                $errorMessage = 'Eroare la generarea AWB: Format de răspuns neașteptat';
                Log::error('FanCourier returned unexpected format', [
                    'errors' => $errors,
                    'response_type' => gettype($errors)
                ]);
				
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'raw_response' => $errors
                ], 500);
            }
        }
        catch (\Illuminate\Validation\ValidationException $e) {
            
            Log::error('FanCourier AWB Validation Failed', [
                'errors' => $e->errors(),
                'failed_fields' => array_keys($e->errors())
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        }
        catch (\Exception $e) {
            
            Log::error('FanCourier AWB Generation Complete Failure', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'error_class' => get_class($e)
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Eroare critică la generarea AWB: ' . $e->getMessage()
            ], 500);
        }
    }

    
    /**
     * Calculate Fan Courier shipping price
     */
    public function calculatePrice(Request $request)
    {
        try {
            // log request data
            Log::info('Fan Courier:', $request->except(['_token']));
            
            $serviceName = 'Standard'; 
            $fan = Fancourier::utvinInstance();

            $costRequest = new GetCosts();

            $costRequest->setService('Cont Colector')
            ->setParcels($request->colete ?? 1)
            ->setWeight($request->greutate ?? 1)
            ->setCounty($request->judet_dest ?? "Timis")
            ->setCity($request->localitate_dest ?? "Valcani");
            //->setDeclaredValue($request->val_decl ?? 0);

            $response = $fan->getCosts($costRequest);

            if ($response->isOk()) {
                //get tariff and round the value to 2 decimal places
                $tariff = floatval($response->getCostTotal());
                $price = round($tariff, 2);
            }
            else {
                // error response
                return response()->json([
                    'success' => false,
                    'message' => 'Error calculating price'
                ], 500);
            }

            // log result
            Log::info('Fan Courier tariff result', [
                'price' => $price,
                'service' => $serviceName
            ]);
            
            // return JSON response
            return response()->json([
                'success' => true,
                'data' => [
                    'fanService' => [
                        'price' => $price,
                        'currency' => 'RON'
                    ],
                    'price' => $price
                ]
            ]);
            
        } catch (\Exception $e) {
            // log error response
            Log::error('Fan Courier मूल्य गणना त्रुटि', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // error response
            return response()->json([
                'success' => false,
                'message' => 'Error calculating price: ' . $e->getMessage()
            ], 500);
        }
    }
	
	
	public function cronFancourierTracking()
	{		
		$orders = DB::table('comenzi_ext')
			->select('idcomanda', 'awb')
			->whereNotNull('awb')
			->where('awb', '!=', '___')
 			->where(function ($q) {
				$q->whereNull('courier_status')
				  ->orWhere('courier_status', '!=', 'Livrat');
			})
			->orderBy('idcomanda', 'desc')
			->pluck('awb')
			->values()
			->toArray();
		
		if (empty($orders)) {
			return response()->json([
				'status' => 'error',
				'message' => 'No AWBs found'
			]);
		}
	
		$chunks = array_chunk($orders, 50);
		foreach ($chunks as $awbNumbers) {
			$samedayAwbs = [];
			$fancourierAwbs = [];
			
			foreach ($awbNumbers as $awb) {
				if (preg_match('/^1ONB/i', $awb)) {
					$samedayAwbs[] = $awb;
				} elseif (is_numeric($awb)) {
					$fancourierAwbs[] = $awb;
				}
			}
	
			if (!empty($fancourierAwbs)) {
				$services = $this->fanCourier->trackMultipleAwbs($fancourierAwbs);
				if (!empty($services['data'])) {
					foreach ($services['data'] as $tracking) {
						$statusTXT = "";
						if (!empty($tracking['awbNumber']) && !empty($tracking['events'])) {
							$lastEvent = end($tracking['events']); // Get last status
							$statusTXT = $lastEvent['name'] . "\n" . $lastEvent['location'] . " - " . $lastEvent['date'];
							$updateData = ['courier_status' => $statusTXT ?? null];
							if($lastEvent['name'] == "Livrat"){
								$updateData['stare'] = 4;
							}
							if($lastEvent['name'] == "Retur"){
								$updateData['stare'] = 6;
							}
							DB::table('comenzi_ext')
								->where('awb', $tracking['awbNumber'])
								->update($updateData);
						}
					}
				}
			}
			
			if (!empty($samedayAwbs)) {
				foreach ($samedayAwbs as $awb) {
					try {
						$SamedayGetStatusSyncRequest = new \Sameday\Requests\SamedayGetAwbStatusHistoryRequest($awb);
						$couierStatusResponse = $this->client->getAwbStatusHistory($SamedayGetStatusSyncRequest);
						$summary = $couierStatusResponse->getSummary();
						$history = $couierStatusResponse->getHistory();
						$statusTXT = "";
						if (!empty($history)) {
							$latest = end($history);
							$name     = $latest->getLabel();
							$location = $latest->getCounty(); // location
							$date     = $latest->getDate()->format('Y-m-d H:i:s'); // formatted date

							$statusTXT = $name . "\n" . $location . " - " . $date;
						}
						
						$status = $summary->isCanceled() ? 'Anulat' : ($summary->isDelivered() ? 'Livrat' : 'În tranzit');
						
						$updateData = ['courier_status' => $statusTXT ?? null];
						if($status == "Livrat"){
							$updateData['stare'] = 4;
						}
						DB::table('comenzi_ext')
							->where('awb', $awb)
							->update($updateData);
					} catch (\Sameday\Exceptions\SamedayNotFoundException $e) {
					} catch (\Exception $e) {
					}
				}
			}
		}
	}
	
	public function getFancourierPickupPoints()
	{
		try {
			$pickupPoints = $this->fanCourier->getNewPickupPoints();

			// Log result for debugging
			Log::info('Fan Courier pickup points', [
				'pickup_points' => $pickupPoints
			]);

			// Return JSON response
			return response()->json([
				'success' => true,
				'data' => $pickupPoints
			]);
		} catch (\Exception $e) {
			Log::error('Fan Courier pickup points error', [
				'message' => $e->getMessage(),
				'trace'   => $e->getTraceAsString()
			]);

			return response()->json([
				'success' => false,
				'message' => 'Error fetching pickup points: ' . $e->getMessage()
			], 500);
		}
	}


    /**
     * Calculate Sameday shipping price (fallback)
     */
    protected function calculateSamedayPrice($request)
    {
        try {
            // Sameday कंट्रोलर मौजूद है तो उसका उपयोग करें
            if (class_exists(\App\Http\Controllers\SamedayController::class)) {
                $samedayController = app()->make(\App\Http\Controllers\SamedayController::class);
                if (method_exists($samedayController, 'calculatePrice')) {
                    return $samedayController->calculatePrice($request);
                }
            }
            
            // फॉलबैक - डिफॉल्ट प्राइस
            return response()->json([
                'success' => true,
                'data' => [
                    'samedayService' => [
                        'price' => 15.00, 
                        'currency' => 'RON'
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Sameday Price Calculation Fallback Error', [
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error calculating Sameday price: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extracts AWB number from various response formats
     */
    private function extractAwbNumber($result)
    {
        $awbNumber = null;
        
        if (is_array($result) && !empty($result)) {
            $firstResult = reset($result);
            
            if (is_object($firstResult)) {
                $awbNumber = 
                    $firstResult->awb ?? 
                    $firstResult->{'stdClass'}->awb ?? 
                    null;
            } 
            elseif (is_array($firstResult)) {
                $awbNumber = 
                    $firstResult['awb'] ?? 
                    $firstResult['stdClass']['awb'] ?? 
                    null;
            }
        } 
        elseif (is_string($result)) {
            // विभिन्न प्रकार के स्ट्रिंग फॉर्मेट से AWB नंबर एक्सट्रैक्ट करने के पैटर्न
            $patterns = [
                '/(\d+,\d+,\d+)/',     // CSV फॉर्मेट (id,parcels,awb)
                '/(\d{6,15})/',        // AWB नंबर (6-15 डिजिट्स)
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $result, $matches)) {
                    if ($pattern === '/(\d+,\d+,\d+)/') {
                        // CSV फॉर्मेट से तीसरा वैल्यू AWB नंबर होता है
                        $parts = explode(',', $matches[1]);
                        $awbNumber = $parts[2] ?? null;
                    } else {
                        $awbNumber = $matches[1] ?? null;
                    }
                    
                    if ($awbNumber) break;
                }
            }
        }
        
        return $awbNumber;
    }
    
    /**
     * Get counties from FanCourier API
     */
    public function getCounties()
    {
        try {
            // Make direct HTTP request to FanCourier API
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', 'https://api.fancourier.ro/reports/counties', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 30,
                'verify' => false,
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            // Extract the actual data array from the API response
            $counties = isset($data['data']) ? $data['data'] : [];
            
            return response()->json([
                'success' => true,
                'data' => $counties
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching FanCourier counties: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching counties: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get localities from FanCourier API
     */
    public function getLocalities()
    {
        try {
            // Make direct HTTP request to FanCourier API
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', 'https://api.fancourier.ro/reports/localities', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 30,
                'verify' => false,
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            // Extract the actual data array from the API response
            $localities = isset($data['data']) ? $data['data'] : [];
            
            return response()->json([
                'success' => true,
                'data' => $localities
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching FanCourier localities: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching localities: ' . $e->getMessage()
            ], 500);
        }
    }
}