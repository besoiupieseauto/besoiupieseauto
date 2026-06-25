<?php

namespace App\Services\Materom;

use Illuminate\Support\Facades\Http;
 
class MateromService
{
    protected $baseUrl;

    /**
     * @param string $token   Your Materom API bearer token
     * @param string $baseUrl Base API URL (default: https://api.materom.ro/api)
     */
    public function __construct()
    {
		$this->utvinToken  = (string) env('MATEROM_TOKEN_UTVIN', '1831|7hJ6PE3I5OLAWJ8JnoY6rTqXpPkemvdFgzbQWWpw');
		$this->timisoaraToken  = (string) env('MATEROM_TOKEN_TIMISOARA', '1838|K12GuqnoQgfQJpdHkuavNXo1kyPIGcsWCha1Uket');
        $this->baseUrl = (string) env('MATEROM_API_URL', 'https://api.materom.ro/api');
    }
	
	private function resolveToken(?string $orderFrom = null): string
	{
		return match ($orderFrom) {
			'UTVIN'      => $this->utvinToken,
			'TIMISOARA'  => $this->timisoaraToken,
			default      => $this->timisoaraToken, // default token (search, read, etc.)
		};
	}

	public function getBaseUrl(): string
	{
		return $this->baseUrl;
	}

	public function getSearchToken(?string $orderFrom = null): string
	{
		return $this->resolveToken($orderFrom);
	}

    /* ================================================================
     * Low-level HTTP helpers
     * ================================================================ */

    private function buildHeaders(string $token): array
    {
        return [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
        ];
    }

    /**
     * @return array{http_code:int,body:mixed,raw:string}
     */
    private function get(string $path, array $query = [], ?string $orderFrom = null): array
    {
	
        $url = $this->baseUrl . $path;
		$token = $this->resolveToken($orderFrom);

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $this->buildHeaders($token),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $raw = curl_exec($ch);

        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $err);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true);

        return [
            'http_code' => $httpCode,
            'body'      => $decoded,
            'raw'       => $raw,
        ];
    }

    /**
     * @return array{http_code:int,body:mixed,raw:string}
     */
    private function post(string $path, array $body, ?string $orderFrom = null): array
    {
        $url = $this->baseUrl . $path;
		$token = $this->resolveToken($orderFrom);
		//echo "<pre>";print_r($token);die('asd');

        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('JSON encoding failed: ' . json_last_error_msg());
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $this->buildHeaders($token),
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $raw = curl_exec($ch);

        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $err);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true);

        return [
            'http_code' => $httpCode,
            'body'      => $decoded,
            'raw'       => $raw,
        ];
    }

    /**
     * @return array{http_code:int,body:mixed,raw:string}
     */
    private function delete(string $path, array $body = [], ?string $orderFrom = null): array
    {
        $url = $this->baseUrl . $path;
		$token = $this->resolveToken($orderFrom);

        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('JSON encoding failed: ' . json_last_error_msg());
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => $this->buildHeaders($token),
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $raw = curl_exec($ch);

        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $err);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true);

        return [
            'http_code' => $httpCode,
            'body'      => $decoded,
            'raw'       => $raw,
        ];
    }

    /* ================================================================
     * Part Search v4
     * ================================================================ */

    /**
     * Part Search v4: /v4/part_search/global
     *
     * @param string   $term  AM/OE/Materom SKU (e.g. 'hu7262x')
     * @param int|null $payer Optional payer ID for price calculation
     */
    public function partSearchV4(string $term, ?int $payer = null): array
    {
        $query = ['term' => $term];
        if ($payer !== null) {
            $query['payer'] = $payer;
        }

        // GET https://api.materom.ro/api/v4/part_search/global?term=...
        return $this->get('/v4/part_search/global', $query);
    }
	
    /**
     * Part Search v2: /v2/part_search/global
     *
     * @param string   $term  AM/OE/Materom SKU (e.g. 'hu7262x')
     * @param int|null $payer Optional payer ID for price calculation
     */
    public function partSearchV2(string $term, ?int $payer = null): array
    {
        $query = ['term' => $term];
/*         if ($payer !== null) {
            $query['payer'] = $payer;
        } */

        // GET https://api.materom.ro/api/v2/part_search/global?term=...
        return $this->get('/v2/part_search/global', $query);
    }
	
    /**
     * Part Search v1: /v1/part_search/global
     *
     * @param string   $term  AM/OE/Materom SKU (e.g. 'hu7262x')
     * @param int|null $payer Optional payer ID for price calculation
     */
    public function partSearchV1(string $term, ?int $payer = null): array
    {
        $query = ['term' => $term];
/*         if ($payer !== null) {
            $query['payer'] = $payer;
        } */

        // GET https://api.materom.ro/api/v1/part_search/global?term=...
        return $this->get('/v1/part_search/global', $query);
    }
	
    /**
     * Part Search v3: /v3/part_search/global
     *
     * @param string   $term  AM/OE/Materom SKU (e.g. 'hu7262x')
     * @param int|null $payer Optional payer ID for price calculation
     */
    public function partSearchV3(string $term, ?int $payer = null): array
    {
        $query = ['term' => $term];
        if ($payer !== null) {
            $query['payer'] = $payer;
        }

        // GET https://api.materom.ro/api/v3/part_search/global?term=...
        return $this->get('/v3/part_search/global', $query);
    }

    /* ================================================================
     * Orders v2 (SAP standard orders)
     * ================================================================ */

    /**
     * Create standard SAP order v2: /v2/sap/orders/standard
     *
     * Standard order API based on Materom API's PartSearch/Global endpoint.
     * Order codes are generated by the Materom PartSearch endpoint (v1, v2).
     *
     * @param string[] $items   Order codes from PartSearch (e.g. "12840#MATR#stocable#1000#11641010#qty:1")
     *                          Format: "{customer_number}#{sales_org}#{type}#{warehouse}#{sku}#qty:{quantity}"
     *                          or "stocable#1000#DPCL1#qty:1:amount:23" for points amount
     * @param string|null $details Optional order details (e.g. "API TEST")
     * @param array|null $dropShipping Optional drop shipping information:
     *                                 [
     *                                   'full_name' => 'John Doe',
     *                                   'email' => 'john@doe.ro',
     *                                   'country_iso' => 'RO',
     *                                   'city' => 'Bucharest',
     *                                   'street_with_number' => 'Street 123',
     *                                   'zip_code' => '12345'
     *                                 ]
     *
     * @return array{http_code:int,body:mixed,raw:string}
     *         Returns 201 on success, 200 with partial=true if some orders failed
     */
    public function createStandardOrderV2(
        array $items,
        ?string $details = null,
        ?array $dropShipping = null,
		?string $orderFrom = null
    ): array {
        if (empty($items)) {
            throw new \InvalidArgumentException('createStandardOrderV2 requires at least one item (order_code).');
        }
		
        $payload = [
            'items' => array_values($items),
        ];

        if ($details !== null) {
            $payload['details'] = $details;
        }

        if ($dropShipping !== null) {
            // Validate drop_shipping structure
            $requiredFields = ['full_name', 'email', 'country_iso', 'city', 'street_with_number', 'zip_code'];
            foreach ($requiredFields as $field) {
                if (!isset($dropShipping[$field])) {
                    throw new \InvalidArgumentException("drop_shipping requires '{$field}' field.");
                }
            }
            
            $payload['drop_shipping'] = [
                'full_name' => $dropShipping['full_name'],
                'email' => $dropShipping['email'],
                'country_iso' => $dropShipping['country_iso'],
                'city' => $dropShipping['city'],
                'street_with_number' => $dropShipping['street_with_number'],
                'zip_code' => $dropShipping['zip_code'],
            ];
        }

        // POST https://api.materom.ro/api/v2/sap/orders/standard
        return $this->post('/v2/sap/orders/standard', $payload, $orderFrom);
    }

    /* ================================================================
     * Orders v4 (SAP standard orders)
     * ================================================================ */

    /**
     * Create standard SAP order v4: /v4/sap/orders/standard
     *
     * @param string[]       $items          Order codes from PartSearch (pricingVariants[].order_code)
     * @param string|null    $details        e.g. "API TEST"
     * @param string|int|null $payer         Optional payer (e.g. customer number)
     * @param array|null     $vehicleDetails Optional vehicle_details:
     *                                       [
     *                                         'make'            => 'BMW',
     *                                         'model'           => 'X5',
     *                                         'vin'             => 'WBA...',
     *                                         'plate'           => 'B123ABC',
     *                                         'additional_data' => '78000 km',
     *                                       ]
     */
    public function createStandardOrderV4(
        array $items,
        ?string $details = null,
        $payer = null,
        ?array $vehicleDetails = null
    ): array {
        if (empty($items)) {
            throw new InvalidArgumentException('createStandardOrderV4 requires at least one item (order_code).');
        }

        $payload = [
            'items' => array_values($items),
        ];

        if ($details !== null) {
            $payload['details'] = $details;
        }

        if ($payer !== null) {
            $payload['payer'] = (string) $payer;
        }

        if ($vehicleDetails !== null) {
            $payload['vehicle_details'] = $vehicleDetails;
        }

        // POST https://api.materom.ro/api/v4/sap/orders/standard
        return $this->post('/v4/sap/orders/standard', $payload);
    }

    /* ================================================================
     * Read Orders (global SAP read)
     * ================================================================ */

    /**
     * Read Orders: /sap/orders/read
     *
     * @param int|string $orderNumber Materom SAP order number
     * @param string     $channel     Channel, defaults to 'materom'
     */
    public function readOrder($orderNumber, string $channel = 'materom'): array
    {
        $query = [
            'order_number' => $orderNumber,
            'channel'      => $channel,
        ];

        // GET https://api.materom.ro/api/sap/orders/read?order_number=...&channel=materom
        return $this->get('/sap/orders/read', $query);
    }

    /* ================================================================
     * 21984-specific endpoints (v1)
     * ================================================================ */

    /**
     * 21984 get_stock: /api/v1/21984/get_stock
     *
     * @param int|string|null $productId
     * @param string|null     $ean
     */
    public function getStock21984($productId = null, ?string $ean = null): array
    {
        if ($productId === null && $ean === null) {
            throw new InvalidArgumentException('You must provide productId or ean to getStock21984');
        }

        $query = [];
        if ($productId !== null) {
            $query['productId'] = $productId;
        }
        if ($ean !== null) {
            $query['ean'] = $ean;
        }

        // GET https://api.materom.ro/api/v1/21984/get_stock?productId=...&ean=...
        return $this->get('/v1/21984/get_stock', $query);
    }

    /**
     * 21984 create_order: /api/v1/21984/create_order
     *
     * @param array  $items  Items array: each
     *                       [
     *                         'product-id'         => int|string,
     *                         'product-amount'     => int|string,
     *                         'sales-plant'        => string,
     *                         'sales-organization' => string,
     *                       ]
     * @param string $atpOrderId
     */
    public function createOrder21984(
        array $items,
        string $atpOrderId,
        string $deliveryFirstname,
        string $deliveryLastname,
        string $deliveryStreet,
        string $deliveryNumber,
        string $deliveryZip,
        string $deliveryCity,
        string $deliveryCountry,
        string $deliveryPhone,
        string $deliveryEmail,
        ?string $deliveryDescription = null
    ): array {
        if (empty($items)) {
            throw new InvalidArgumentException('createOrder21984 requires at least one item');
        }

        $payload = [
            'items'                  => array_values($items),
            'atp-order-id'           => $atpOrderId,
            'delivery-firstname'     => $deliveryFirstname,
            'delivery-lastname'      => $deliveryLastname,
            'delivery-street'        => $deliveryStreet,
            'delivery-number'        => $deliveryNumber,
            'delivery-zip'           => $deliveryZip,
            'delivery-city'          => $deliveryCity,
            'delivery-country'       => $deliveryCountry,
            'delivery-phone-number'  => $deliveryPhone,
            'delivery-email'         => $deliveryEmail,
        ];

        // The docs mention "address-description"; example uses "delivery-description"
        if ($deliveryDescription !== null) {
            $payload['delivery-description'] = $deliveryDescription;
        }

        // POST https://api.materom.ro/api/v1/21984/create_order
        return $this->post('/v1/21984/create_order', $payload);
    }

    /**
     * 21984 get_order_status: /api/v1/21984/get_order_status
     *
     * @param string $orderId           Materom order number (max 18 chars)
     * @param string $salesOrganization e.g. "MATR"
     */
    public function getOrderStatus21984(string $orderId, string $salesOrganization): array
    {
        $query = [
            'order-id'           => $orderId,
            'sales-organization' => $salesOrganization,
        ];

        // GET https://api.materom.ro/api/v1/21984/get_order_status?order-id=...&sales-organization=...
        return $this->get('/v1/21984/get_order_status', $query);
    }

    /**
     * 21984 cancel_order: /api/v1/21984/cancel_order
     *
     * @param string      $orderId Materom order number (max 18 chars)
     * @param string|null $comment Optional reason
     */
    public function cancelOrder21984(string $orderId, ?string $comment = null): array
    {
        $payload = [
            'order-id' => $orderId,
        ];

        if ($comment !== null && $comment !== '') {
            $payload['comment'] = $comment;
        }

        // DELETE https://api.materom.ro/api/v1/21984/cancel_order
        return $this->delete('/v1/21984/cancel_order', $payload);
    }

    /**
     * 21984 get_tracking_numbers: /api/v1/21984/get_tracking_numbers
     *
     * @param string|null $orderId Optional order-id
     */
    public function getTrackingNumbers21984(?string $orderId = null): array
    {
        $query = [];
        if ($orderId !== null && $orderId !== '') {
            $query['order-id'] = $orderId;
        }

        // GET https://api.materom.ro/api/v1/21984/get_tracking_numbers[?order-id=...]
        return $this->get('/v1/21984/get_tracking_numbers', $query);
    }
}