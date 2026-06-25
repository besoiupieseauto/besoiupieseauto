<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Sameday\Requests\SamedayGetServicesRequest;
use Sameday\Requests\SamedayGetPickupPointsRequest;
use Sameday\Responses\SamedayGetPickupPointsResponse;
use Sameday\Responses\SamedayPostAwbEstimationResponse;
use Sameday\Requests\SamedayPostAwbEstimationRequest;

use Sameday\Requests\SamedayPostAwbRequest;
use Sameday\Objects\ParcelDimensionsObject;

use Sameday\Objects\Types\AwbPaymentType;
use Sameday\Objects\Types\PackageType;
use Sameday\Objects\Types\DeliveryIntervalObject;
use Sameday\Sameday;
use Sameday\Objects\PostAwb\Request\EntityObject;
use Sameday\Objects\PostAwb\Request\AwbRecipientEntityObject;
use Sameday\Objects\PostAwb\Request\CompanyEntityObject;
use Sameday\SamedayClient;


class SamedayController extends Controller
{
    protected $client;

    /**
     * SamedayController constructor.
     *
     */
    public function __construct()
    {
        $this->client = app('sameday');
    }


    /**
     * get services
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServices()
    {
        try {
            // create object of SamedayGetServicesRequest
            $request = new SamedayGetServicesRequest();

            // build HTTP request object using buildRequest() method
            $httpRequest = $request->buildRequest();
            $rawResponse = $this->client->sendRequest($httpRequest);

            // from raw response build SamedayGetServicesResponse object
            // using SamedayGetServicesResponse constructor
            // and pass the request
            $servicesResponse = new \Sameday\Responses\SamedayGetServicesResponse($request, $rawResponse);

            $services = [];

            foreach ($servicesResponse->getServices() as $service) {
                // Use methods available in ServiceObject to get the data
                // and build the response array
                $serviceData = [
                    'id' => $service->getId(), 
                    'name' => $service->getName(),
                    'code' => $service->getCode(),
                    'delivery_type' => [
                        'id' => $service->getDeliveryType()->getId(),
                        'name' => $service->getDeliveryType()->getName()
                    ],
                    'default' => $service->isDefault()
                ];

                // attach OptionalTaxes with only available methods
                $optionalTaxes = [];

                foreach ($service->getOptionalTaxes() as $tax) {
                    $optionalTaxes[] = [
                        'id' => $tax->getId(),
                        'name' => $tax->getName(),
                        'code' => $tax->getCode()
                    ];
                }

                $serviceData['optional_taxes'] = $optionalTaxes;

                // Add the service data to the services array
                $serviceData['delivery_intervals'] = [];
                $services[] = $serviceData;
            }

            return response()->json([
                'success' => true,
                'data' => $services
            ]);
        }
        catch (\Exception $e) {
            Log::error('SamedayController Exception: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * get pickup points
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPickupPoints(Request $request)
    {
        try {
            $city = $request->input('city', null);
            $pickupRequest = new SamedayGetPickupPointsRequest();

            $httpRequest = $pickupRequest->buildRequest();

            $rawResponse = $this->client->sendRequest($httpRequest);
            $pickupResponse = new SamedayGetPickupPointsResponse($pickupRequest, $rawResponse);

            $pickupPoints = [];
            foreach ($pickupResponse->getPickupPoints() as $point) {
                if($point->getId() == 84024 || $point->getId() == 311014) {
                    $pickupPoints[] = [
                        'id' => $point->getId(),
                        'name' => $point->getAlias() ? $point->getAlias() : '',
                        'city' => $point->getCity()->getName(), //use name from CityObject
                        'county' => $point->getCounty()->getName(), //use name from CountyObject
                        'address' => $point->getAddress(),
                        'default' => $point->isDefault()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $pickupPoints
            ]);
        } catch (\Exception $e) {
            Log::error('SamedayController getPickupPoints Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * get teriff
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculatePrice(Request $request)
	{
		try {
			Log::info('Shipping price calculation request', $request->all());

            // Get parameters
			$courier = $request->input('courier', 'both');
			$serviceId = (int)$request->input('service', '7');
			$weight = (float)$request->input('weight', 1);
			$county = $request->input('county', 'Timis');
			$city = $request->input('city', 'Valcani');
			$ramburs = (float)$request->input('ramburs', 0);
			$request['packageWeight'] = 1;
            $pickupPointId = (int)$request->input('pickupPointId', 84024);

			// Package dimensions
			//$parcel = new ParcelDimensionsObject(1);
			$parcel = new ParcelDimensionsObject($weight);

			// Create the company object - correct order of parameters
			$company = new CompanyEntityObject('a');

			// Create the recipient object - correct order of parameters
			$recipient = new AwbRecipientEntityObject(
			    $city
			    , $county
			    , ''
			    , ''
			    , ''
			    , ''
			    , $company
			);

            $recipientLog = [$city, $county, '', '', '', '', $company];
			Log::info('Sameday tariff recipient', $recipientLog);

            $samedayPostAwbEstimationRequest = new SamedayPostAwbEstimationRequest(
                $pickupPointId
                , null
                , new PackageType(PackageType::PARCEL)
                , [$parcel]
                , $serviceId
                , new AwbPaymentType(AwbPaymentType::CLIENT)
                , $recipient
                , 0
                , 10.0
                , null
                , []
                , null
			);

            $httpRequest = $samedayPostAwbEstimationRequest->buildRequest();
            $rawResponse = $this->client->sendRequest($httpRequest);
			$rawBody = json_decode((string)$rawResponse->getBody(), true);

			if (!isset($rawBody['amount'])) {
				throw new \Exception("Unexpected API response");
			}
			$samedayPrice = $rawBody['amount'];
            
            //$samedayTeriffPrice = new SamedayPostAwbEstimationResponse($samedayPostAwbEstimationRequest, $rawResponse);
			//$samedayPrice = $samedayTeriffPrice->getCost();//$sameday->postAwbEstimation($samedayPostAwbEstimationRequest);

			Log::info('Calculated shipping prices', [
				'sameday' => $samedayPrice,
				'ramburs' => $ramburs,
				'weight' => $weight,
				'county' => $county,
				'city' => $city
			]);

			return response()->json([
				'success' => true,
				'data' => [
					'samedayService' => [
						'price' => $samedayPrice,
						'currency' => 'RON'
					]
				]
			]);
		} catch (\Exception $e) {

			Log::error('Price calculation error', [
				'message' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);
			
			return response()->json([
				'success' => false,
				'message' => $e->getMessage()
			], 500);
		}
	}

   
    /**
     * AWB  Create
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createAwb(Request $request)
    {
        try {
            Log::info('SamedayController createAwb called', [
                'data' => $request->except(['_token'])
            ]);

            // Validate request
            $validated = $request->validate([
                'serviceId' => 'required|integer',
                'pickupPointId' => 'required|integer',
                'recipientName' => 'required|string|max:255',
                'recipientAddress' => 'required|string|max:255',
                'recipientCity' => 'required|string|max:255',
                'recipientCounty' => 'required|string|max:255',
                'recipientPhone' => 'required|string',
                'packageWeight' => 'required|numeric|min:0.1',
                'cashOnDelivery' => 'sometimes|numeric|min:0'
            ]);

            // Format phone number - only allowed chars: numbers, (, ), +, -, .
            $phoneNumber = preg_replace('/[^0-9\.\(\)\+\-]/', '', $validated['recipientPhone']);

            // Ensure it's within the allowed length
            if (strlen($phoneNumber) < 4) {
                $phoneNumber = '0723000000'; // Default phone number
            }

            // Format county - only allowed: letters, numbers, spaces, points, commas, brackets, hyphens
            $countyString = preg_replace('/[^a-zA-Z0-9\s\.\,\(\)\-]/', '', $validated['recipientCounty']);

            // Minimum 2 characters required
            if (strlen($countyString) < 2) {
                $countyString = 'Timis'; // Default county
            }

            // Default email (required by API)
            $email = 'client@example.com';

            // Create the recipient object - correct order of parameters
            $recipient = new AwbRecipientEntityObject(
                $validated['recipientName'],
                $phoneNumber,
                $validated['recipientCity'],
                $validated['recipientAddress'],
                $countyString,
                '000000',
                null,
            );

            // Package dimensions
            $parcel = new ParcelDimensionsObject(
                (float)$validated['packageWeight']
            );

            
            // AWB Request Object
            $awbRequest = new SamedayPostAwbRequest(
                $validated['pickupPointId'],
                null,
                new PackageType(PackageType::PARCEL),
                [$parcel],
                $validated['serviceId'],
                new AwbPaymentType(AwbPaymentType::CLIENT),
                $recipient,
                0, // Insured value
                $request->input('cashOnDelivery', 0), // Cash on delivery value
                null,
                null,
                [null],
                null,
                null, // Reference
                $request->input('observation', 'Atentie-Fragil, Livrare urgenta') // Observation with default
            );

            // Send the request
            $awbResponse = $this->client->postAwb($awbRequest);
            
            return response()->json([
                'success' => true,
                'awb_number' => $awbResponse->getAwbNumber()
            ]);
        } catch (\Exception $e) {
            Log::error('SamedayController createAwb error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'AWB generation error: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * AWB  Status Get
     */
    public function getAwbStatus(Request $request, $awbNumber)
    {
        try {
            $validated = $request->validate([
                'awbNumber' => 'required|string',
            ]);

            $packageWeight = 0.1;
            // Package dimensions
            $parcel = new ParcelDimensionsObject(
                (float) $packageWeight
            );

            $recipient = new AwbRecipientEntityObject(
                '',
                '09779939990',
                '',
                '',
                '',
                '000000',
                null,
            );

            // SameDay API Request
            $awbStatusRequest = new SamedayPostAwbEstimationRequest(
                84024,
                null,
                new PackageType(PackageType::PARCEL),
                [$parcel],
                7,
                new AwbPaymentType(AwbPaymentType::CLIENT),
                $recipient,
                0
            );

            $awbStatus = $this->client->getAwbStatusHistory($awbStatusRequest);

            return response()->json([
                'success' => true,
                'data' => $awbStatus->getSummary()
            ]);
        }
        catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}