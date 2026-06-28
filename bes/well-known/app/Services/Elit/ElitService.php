<?php

namespace App\Services\Elit;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ElitService
{
    /**
     * Base URL for Business Service
     */
    private const BUSINESS_SERVICE_URL = 'https://econnector.elit.cz/InterCompany-1.87/BusinessService';

    /**
     * Base URL for Buyer Service
     */
    private const BUYER_SERVICE_URL = 'https://econnector.elit.cz/InterCompany-1.87/BuyerService';

    /**
     * Service credentials
     */
    private string $companyName;
    private string $login;
    private string $password;
    private string $applicationId;

    /**
     * ElitService constructor.
     *
     * @param string $companyName
     * @param string $login
     * @param string $password
     * @param string $applicationId
     */
    public function __construct() 
	{
        $this->companyName = (string) env('ELIT_COMPANY_NAME', 'ELIT_RO');
        $this->login = (string) env('ELIT_LOGIN', '');
        $this->password = (string) env('ELIT_PASSWORD', '');
        $this->applicationId = (string) env('ELIT_APPLICATION_ID', 'eshop');
    }

    /**
     * Get item information aggregated by time (Product Availability)
     *
     * @param array $items Array of items with 'itemNo' and 'qty'
     * @param string|null $requestId Optional request ID
     * @return array
     * @throws Exception
     */
    public function getItemInfoAgregByTime(array $items, ?string $requestId = null): array
    {
        try {
            $xml = $this->buildProductAvailXml($items, $requestId);

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml',
            ])->send('POST', self::BUSINESS_SERVICE_URL, [
                'body' => $xml,
            ]);

            if ($response->failed()) {
                Log::error('Elit API Error - ProductAvail', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception('Elit API request failed: ' . $response->status());
            }

            return $this->parseSoapResponse($response->body());
        } catch (Exception $e) {
            Log::error('Elit Service Error - getItemInfoAgregByTime', [
                'message' => $e->getMessage(),
                'items' => $items,
            ]);
            throw $e;
        }
    }

    /**
     * Get item information
     *
     * @param string $itemNo Item number
     * @param int $qty Quantity
     * @param string|null $supplierCode Optional supplier code
     * @return array
     * @throws Exception
     */
    public function getItemInfo(string $itemNo, int $qty, ?string $supplierCode = null): array
    {
        try {
            $xml = $this->buildGetItemInfoXml($itemNo, $qty, $supplierCode);

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml',
            ])->send('POST', self::BUYER_SERVICE_URL, [
                'body' => $xml,
            ]);

            if ($response->failed()) {
                Log::error('Elit API Error - getItemInfo', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception('Elit API request failed: ' . $response->status());
            }

            return $this->parseSoapResponse($response->body());
        } catch (Exception $e) {
            Log::error('Elit Service Error - getItemInfo', [
                'message' => $e->getMessage(),
                'itemNo' => $itemNo,
                'qty' => $qty,
            ]);
            throw $e;
        }
    }

    /**
     * Build XML for Product Availability request
     *
     * @param array $items
     * @param string|null $requestId
     * @return string
     */
    private function buildProductAvailXml(array $items, ?string $requestId = null): string
    {
        $itemsXml = '';
        foreach ($items as $item) {
            $itemNo = htmlspecialchars($item['itemNo'] ?? '', ENT_XML1, 'UTF-8');
            $qty = htmlspecialchars((string)($item['qty'] ?? 1), ENT_XML1, 'UTF-8');
            
            $itemsXml .= "            <items>\n";
            $itemsXml .= "               <itemNo>{$itemNo}</itemNo>\n";
            $itemsXml .= "               <qty>{$qty}</qty>\n";
            $itemsXml .= "            </items>\n";
        }

        $requestIdXml = $requestId ? "<RequestId>" . htmlspecialchars($requestId, ENT_XML1, 'UTF-8') . "</RequestId>\n" : '';

        $xml = <<<XML
			<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecat="http://ecat.elit.cz/">
			   <soapenv:Header/>
			   <soapenv:Body>
				  <ecat:getItemInfoAgregByTime>
					 <arg0>
						<CompanyName>{$this->companyName}</CompanyName>
						<Login>{$this->login}</Login>
						<Password>{$this->password}</Password>
						{$requestIdXml}
						<applicationId>{$this->applicationId}</applicationId>
			{$itemsXml}
					 </arg0>
				  </ecat:getItemInfoAgregByTime>
			   </soapenv:Body>
			</soapenv:Envelope>
			XML;

        return $xml;
    }

    /**
     * Build XML for Get Item Info request
     *
     * @param string $itemNo
     * @param int $qty
     * @param string|null $supplierCode
     * @return string
     */
    /**
     * Return URL, body and headers for a getItemInfo request (for use in HTTP pool).
     */
    public function getItemInfoRequestConfig(string $itemNo, int $qty = 1, ?string $supplierCode = null): array
    {
        return [
            'url' => self::BUYER_SERVICE_URL,
            'method' => 'POST',
            'body' => $this->buildGetItemInfoXml($itemNo, $qty, $supplierCode),
            'headers' => ['Content-Type' => 'text/xml'],
        ];
    }

    private function buildGetItemInfoXml(string $itemNo, int $qty, ?string $supplierCode = null): string
    {
        $itemNoEscaped = htmlspecialchars($itemNo, ENT_XML1, 'UTF-8');
        $qtyEscaped = htmlspecialchars((string)$qty, ENT_XML1, 'UTF-8');
        $supplierCodeXml = $supplierCode 
            ? "<supplierCode>" . htmlspecialchars($supplierCode, ENT_XML1, 'UTF-8') . "</supplierCode>\n" 
            : '';

        $xml = <<<XML
			<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:buy="http://buyer.elit.cz/">
			   <soapenv:Header/>
			   <soapenv:Body>
				  <buy:getItemInfo>
					 <company>{$this->companyName}</company>
					 <login>{$this->login}</login>
					 <password>{$this->password}</password>
					 <itemNo>{$itemNoEscaped}</itemNo>
					 <qty>{$qtyEscaped}</qty>
					 {$supplierCodeXml}
				  </buy:getItemInfo>
			   </soapenv:Body>
			</soapenv:Envelope>
			XML;

        return $xml;
    }

    /**
     * Parse SOAP XML response
     *
     * @param string $xmlResponse
     * @return array
     */
    private function parseSoapResponse(string $xmlResponse): array
    {
        try {
            // Remove namespaces for easier parsing
            $xmlResponse = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $xmlResponse);
            
            $xml = simplexml_load_string($xmlResponse);
            
            if ($xml === false) {
                // If XML parsing fails, return raw response
                return [
                    'success' => false,
                    'raw_response' => $xmlResponse,
                    'error' => 'Failed to parse XML response',
                ];
            }

            // Convert to array
            $array = json_decode(json_encode($xml), true);

            return [
                'success' => true,
                'data' => $array,
                'raw_response' => $xmlResponse,
            ];
        } catch (Exception $e) {
            Log::error('Elit Service - XML Parse Error', [
                'message' => $e->getMessage(),
                'response' => substr($xmlResponse, 0, 500),
            ]);

            return [
                'success' => false,
                'raw_response' => $xmlResponse,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Set credentials dynamically
     *
     * @param string $companyName
     * @param string $login
     * @param string $password
     * @param string|null $applicationId
     * @return $this
     */
    public function setCredentials(
        string $companyName,
        string $login,
        string $password,
        ?string $applicationId = null
    ): self {
        $this->companyName = $companyName;
        $this->login = $login;
        $this->password = $password;
        
        if ($applicationId !== null) {
            $this->applicationId = $applicationId;
        }

        return $this;
    }
}