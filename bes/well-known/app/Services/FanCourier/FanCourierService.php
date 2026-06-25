<?php

namespace App\Services\FanCourier;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\ApiCredential;

class FanCourierService
{
    protected $username;
    protected $password;
    protected $clientId;
    protected $apiUrl;
    protected $client;

    public function __construct()
    {
/*         $this->username = config('services.fancourier.username');
        $this->password = config('services.fancourier.password');
        $this->clientId = config('services.fancourier.client_id');
        $this->apiUrl = 'https://api.fancourier.ro/'; */
        $this->username = $this->getCredential('fancourier', 'username');
        $this->password = $this->getCredential('fancourier', 'password');
        $this->clientId = $this->getCredential('fancourier', 'client_id');
        $this->apiUrl   = $this->getCredential('fancourier', 'api_url', 'https://api.fancourier.ro/');
        
        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'verify' => false,
        ]);

        // Generate token immediately
        $this->token = $this->getToken();
    }
	
    protected function getToken()
    {
        try {
            $response = $this->client->request('POST', 'login', [
                'query' => [
                    'username' => $this->username,
                    'password' => $this->password,
                ],
            ]);

            $data = json_decode((string)$response->getBody(), true);

            if (!empty($data['data'])) {
                return $data['data']['token'];
            }

            Log::error('FanCourier token generation failed', ['response' => $data]);
            return null;

        } catch (GuzzleException $e) {
            Log::error('FanCourier token generation exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    protected function getCredentials()
    {
        return [
            'username' => $this->username,
            'password' => $this->password,
            'client_id' => $this->clientId,
        ];
    }

    protected function makeRequest($method, $endpoint, $params = [])
    {
        try {
            $options = [];
            $credentials = $this->getCredentials();
            
            if ($method === 'GET') {
                $options['query'] = array_merge($credentials, $params);
            } else {
                $options['form_params'] = array_merge($credentials, $params);
            }
            
            $response = $this->client->request($method, $endpoint, $options);
            $responseBody = (string) $response->getBody();
            
            return json_decode($responseBody, true) ?: ['raw_response' => $responseBody];
        } catch (GuzzleException $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Make a request that returns binary data (like PDF)
     */
    protected function makeBinaryRequest($method, $endpoint, $params = [])
    {
        try {
            $options = [];
            $credentials = $this->getCredentials();
            
            if ($method === 'GET') {
                $options['query'] = array_merge($credentials, $params);
            } else {
                $options['form_params'] = array_merge($credentials, $params);
            }
            
            // Add headers for binary content
            $options['headers'] = [
                'Accept' => '*/*',
            ];
            
            $response = $this->client->request($method, $endpoint, $options);
            return [
                'success' => true,
                'content' => (string) $response->getBody(),
                'content_type' => $response->getHeaderLine('Content-Type'),
            ];
        } catch (GuzzleException $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }
	
    protected function makeRequestWithToken($method, $endpoint, $params = [])
    {
        if (!$this->token) {
            return [
                'error' => true,
                'message' => 'No token available',
            ];
        }

        try {
            $options = [];

            if ($method === 'GET') {
                $options['query'] = $params;
            } else {
                $options['form_params'] = $params;
            }

            $options['headers'] = [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
            ];

            $response = $this->client->request($method, $endpoint, $options);

            return json_decode((string)$response->getBody(), true);

        } catch (GuzzleException $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    // शहरों की सूची प्राप्त करें
    public function getCities()
    {
        // नया अपडेटेड एंडपॉइंट
        return $this->makeRequest('GET', 'web-api/addresses/counties');
    }
	
	public function getServices()
	{
        return $this->makeRequestWithToken('GET', 'reports/services');
	}

    // काउंटी द्वारा शहरों की सूची प्राप्त करें
    public function getCitiesByCounty($countyId)
    {
        return $this->makeRequest('GET', "web-api/addresses/cities/$countyId");
    }

    // AWB बनाएं
    public function createAwb(array $data)
    {
        return $this->makeRequest('POST', 'web-api/awb/create', $data);
    }
	
/* 	public function trackMultipleAwbs(array $awbNumbers, string $language = 'ro')
	{
		if (!$this->token) {
			return [
				'error' => true,
				'message' => 'No token available',
			];
		}

		try {
			$options['query'] = [
				'clientId' => $this->clientId,
				'language' => $language,
			];

			// Add each AWB as repeated awb[] parameter
			foreach ($awbNumbers as $awb) {
				$options['query']['awb'][] = $awb;
			}

			$options['headers'] = [
				'Authorization' => 'Bearer ' . $this->token,
				'Accept' => 'application/json',
			];

			$response = $this->client->request('GET', 'reports/awb/tracking', $options);

			return json_decode((string)$response->getBody(), true);
		} catch (GuzzleException $e) {
			return [
				'error' => true,
				'message' => $e->getMessage(),
			];
		}
	} */
	
	public function trackMultipleAwbs(array $awbNumbers, string $language = 'ro')
	{
		$params = [
			'clientId' => $this->clientId,
			'language' => $language,
			'awb'      => $awbNumbers, // will expand to awb[]=123&awb[]=456
		];

		return $this->makeRequestWithToken('GET', 'reports/awb/tracking', $params, true);
	}
 
    // AWB ट्रैक करें
    public function trackAwb(string $awbNumber)
    {
        return $this->makeRequest('GET', "web-api/awb/track/$awbNumber");
    }

    // मूल्य जानकारी प्राप्त करें
    public function getPrices(array $data)
    {
        return $this->makeRequest('POST', 'web-api/tariffs/calculate', $data);
    }

    // पिकअप पॉइंट्स प्राप्त करें
    public function getPickupPoints()
    {
        return $this->makeRequest('GET', 'web-api/addresses/pickup-points');
    }
	
	public function getNewPickupPoints()
	{
		$types = ['office', 'fanbox', 'paypoint'];
		$allPoints = [];

		foreach ($types as $type) {
			$params = ['type' => $type];
			$response = $this->makeRequestWithToken(
				'GET',
				'https://api.fancourier.ro/reports/pickup-points',
				$params,
				true
			);
			
			if (is_array($response) && isset($response['data']) && is_array($response['data'])) {
				// add type info if missing
				foreach ($response['data'] as $point) {
					$point['type'] = $type;
					$allPoints[] = $point;
				}
			}
		}

		return $allPoints;
		//return $this->makeRequestWithToken('GET', 'https://api.fancourier.ro/reports/pickup-points', $params , true);
	}
    
    /**
     * Get AWB PDF document
     * 
     * @param string $awbNumber The AWB number to get the PDF for
     * @param bool $saveToFile Whether to save the PDF to a file or return the binary content
     * @param string $savePath Path where to save the file (if $saveToFile is true)
     * @return array Response with PDF content or file path
     */
    public function getAwbPdf(string $awbNumber, bool $saveToFile = false, string $savePath = 'awbs')
    {
        $response = $this->makeBinaryRequest('GET', "web-api/awb/print-awb/$awbNumber");
        
        if (!empty($response['error'])) {
            return $response;
        }
        
        if ($saveToFile) {
            $filename = $awbNumber . '.pdf';
            
            // Save to storage
            Storage::disk('public')->put("$savePath/$filename", $response['content']);
            
            return [
                'success' => true,
                'file_path' => Storage::disk('public')->url("$savePath/$filename"),
                'message' => 'AWB PDF saved successfully',
            ];
        }
        
        return $response;
    }
    
    /**
     * Download multiple AWBs as PDF
     * 
     * @param array $awbNumbers Array of AWB numbers to download
     * @return array Response with PDF content
     */
    public function downloadMultipleAwbs(array $awbNumbers)
    {
        $data = [
            'awbs' => implode(',', $awbNumbers)
        ];
        
        return $this->makeBinaryRequest('POST', 'web-api/awb/print-awbs', $data);
    }
	
    protected function getCredential(string $service, string $key, $default = null): ?string
    {
        $record = ApiCredential::where('service_name', $service)
            ->where('data_key', $key)
            ->first();

        return $record->data_value ?? config("services.$service.$key") ?? $default;
    }
}