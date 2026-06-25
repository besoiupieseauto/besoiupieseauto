<?php

namespace App\Services\Autonet;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AutonetService
{
    /**
     * Base URL for production environment
     */
    private const PRODUCTION_URL = 'https://wes.autonet-group.com';

    /**
     * Base URL for staging environment
     */
    private const STAGING_URL = 'https://wes-stage.autonet-group.com';

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var string
     */
    private $taxCode;

    /**
     * @var string
     */
    private $securityToken;

    /**
     * @var string|null
     */
    private $branch;

    /**
     * @var bool
     */
    private $useStaging;

    /**
     * AutonetService constructor.
     *
     * @param string $taxCode Customer's VAT number
     * @param string $securityToken Customer's authentication token
     * @param string|null $branch Branch code for shipment
     * @param bool $useStaging Use staging environment
     */
    public function __construct(
		string $taxCode = 'RO31298897',
		string $securityToken = '08VGHXNPAH',
		string $orderFrom = 'TIMISOARA',
		bool $useStaging = false
    ) {
        $this->taxCode = $taxCode;
        $this->securityToken = $securityToken;
		$this->branch = $this->mapBranch($orderFrom);
        $this->useStaging = $useStaging;
        $this->baseUrl = $useStaging ? self::STAGING_URL : self::PRODUCTION_URL;
    }

    /**
     * Get HTTP headers for API requests
     *
     * @return array
     */
    private function getHeaders(): array
    {
        $headers = [
            'TAX-CODE' => $this->taxCode,
            'SECURITY-TOKEN' => $this->securityToken,
            //'Content-Type' => 'application/json; charset=utf-8',
        ];

        if ($this->branch) {
            $headers['BRANCH'] = $this->branch;
        }

        return $headers;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getHeadersForSearch(): array
    {
        return $this->getHeaders();
    }
	
	private function mapBranch(string $orderFrom): ?string
	{
		return match (strtoupper($orderFrom)) {
			'TIMISOARA' => 'MAG1',
			'UTVIN'     => 'MAG2',
			default     => 'MAG1',
		};
	}

	public function setBranch(?string $branch): self
	{
		$this->branch = $branch;
		return $this;
	}

	/**
	 * Get branch code
	 */
	public function getBranch(): ?string
	{
		return $this->branch;
	}

    /**
     * Make HTTP request to the API
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint
     * @param array|null $data Request body data
     * @return array
     * @throws Exception
     */
/*     private function makeRequest(string $method, string $endpoint, ?array $data = null): array
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
            
            $request = Http::withHeaders($this->getHeaders());

            if ($method === 'GET') {
                $response = $request->get($url, $data ?? []);
            } else {
                $response = $request->{$method}($url, $data ?? []);
            }

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            Log::error('Autonet API Error', [
                'url' => $url,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            throw new Exception('API request failed with status: ' . $response->status());
        } catch (Exception $e) {
            Log::error('Autonet Service Exception', [
                'message' => $e->getMessage(),
                'endpoint' => $endpoint,
            ]);
            throw $e;
        }
    } */
	
	private function makeRequest(string $method, string $endpoint, ?array $data = null): array
	{
		$url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

		$request = Http::withHeaders($this->getHeaders())
			->asJson(); // ⭐ THIS LINE FIXES 412 ⭐

		if ($method === 'GET') {
			$response = $request->get($url, $data ?? []);
		} else {
			$response = $request->post($url, $data ?? []);
		}

		if ($response->successful()) {
			return $response->json() ?? [];
		}

		Log::error('Autonet API Error', [
			'url' => $url,
			'status' => $response->status(),
			'response' => $response->body(),
		]);

		throw new Exception(
			'Autonet API failed: ' . $response->status() . ' - ' . $response->body()
		);
	}


    /**
     * Create dynamic order
     * Creates an order with maximum up to 400 requested articles per request.
     *
     * @param string $externalOrderNumber Customer's unique order number
     * @param array $orderItems Array of order items [['PartNo' => '...', 'Quantity' => 2.0], ...]
     * @return array
     * @throws Exception
     */
    public function createDynamicOrder(string $externalOrderNumber, array $orderItems, string $orderFrom): array
    {
		$this->setBranch($this->mapBranch($orderFrom));
		
        $data = [
			'ExternalOrderNumber' => $externalOrderNumber,
            'OrderItems' => $orderItems,
        ];

        return $this->makeRequest('POST', 'order/create/dynamic', $data);
    }

    /**
     * Create reservation order
     * Reserves goods without ordering them.
     *
     * @param array $orderItems Array of order items [['PartNo' => '...', 'Quantity' => 2.0], ...]
     * @return array
     * @throws Exception
     */
    public function createReservationOrder(array $orderItems): array
    {
        return $this->makeRequest('POST', 'ArticleOrder/CreateOrder', $orderItems);
    }

    /**
     * Confirm reservation order
     * Creates order from the reservations.
     *
     * @param string $orderNumber Order number from reservation
     * @param string $externalOrderNumber Customer's unique order number (must be unique)
     * @return array
     * @throws Exception
     */
    public function confirmReservationOrder(string $orderNumber, string $externalOrderNumber): array
    {
        $data = [
            'OrderNumber' => $orderNumber,
            'ExternalOrderNumber' => $externalOrderNumber,
        ];

        return $this->makeRequest('POST', 'ArticleOrder/ConfirmOrder', $data);
    }

    /**
     * Create order from reservations
     * Creates orders from reservations. ExternalOrderNumber must be filled.
     *
     * @param string $externalOrderNumber Customer's unique order number
     * @param array $orderItems Array of order items [['PartNo' => '...', 'Quantity' => 1.0], ...]
     * @return array
     * @throws Exception
     */
    public function createOrder(string $externalOrderNumber, array $orderItems): array
    {
        $data = [
            'ExternalOrderNumber' => $externalOrderNumber,
            'OrderItems' => $orderItems,
        ];

        return $this->makeRequest('POST', 'Order/CreateOrder', $data);
    }

    /**
     * Cancel order
     * Cancels a reservation or an order.
     *
     * @param string $orderNumber Order number to cancel
     * @return array
     * @throws Exception
     */
    public function cancelOrder(string $orderNumber): array
    {
        $data = [
            'OrderNumber' => $orderNumber,
        ];

        return $this->makeRequest('POST', 'ArticleOrder/CancelOrder', $data);
    }

    /**
     * Retrieve article order info
     * Provides order information.
     *
     * @param string $orderNumber Order number
     * @return array
     * @throws Exception
     */
    public function getOrderInfo(string $orderNumber): array
    {
        $data = [
            'OrderNumber' => $orderNumber,
        ];

        return $this->makeRequest('POST', 'ArticleOrder/GetOrderInfo', $data);
    }

    /**
     * Get availability
     * Provides availabilities and other info for the requested articles.
     * You can search by Autonet article number (PartNo) or TecDoc article (TDBrandId and TDArticleNo).
     *
     * @param array $articles Array of articles to check:
     *   - ['PartNo' => '...'] for Autonet article
     *   - ['TDBrandId' => 26, 'TDArticleNo' => '...'] for TecDoc article
     * @return array
     * @throws Exception
     */
    public function getAvailability(array $articles): array
    {
        return $this->makeRequest('POST', 'ArticleOffer/GetAvailability', $articles);
    }

    /**
     * Get price list with delivery info and alternative parts
     * Get delivery infos. You must send PartNo or TecDoc article (TDBrandId and TDArticleNo).
     *
     * @param string|null $partNo Autonet article number
     * @param int|null $tdBrandId TecDoc brand id
     * @param string|null $tdArticleNo TecDoc article id
     * @param float $quantity The quantity (mandatory)
     * @return array
     * @throws Exception
     */
    public function getDeliveryInfos(
        ?string $partNo = null,
        ?int $tdBrandId = null,
        ?string $tdArticleNo = null,
        float $quantity = 1.0
    ): array {
        $data = ['Quantity' => $quantity];

        if ($partNo) {
            $data['PartNo'] = $partNo;
        } elseif ($tdBrandId && $tdArticleNo) {
            $data['TDBrandId'] = $tdBrandId;
            $data['TDArticleNo'] = $tdArticleNo;
        } else {
            throw new Exception('Either PartNo or both TDBrandId and TDArticleNo must be provided');
        }

        return $this->makeRequest('POST', 'Quotation/GetDeliveryInfos', $data);
    }

    /**
     * Download list price
     * Downloads an archive file with the list prices.
     *
     * @param string $key API key for CSV download
     * @return \Illuminate\Http\Client\Response
     * @throws Exception
     */
    public function downloadListPrice(string $key)
    {
        try {
            $url = rtrim($this->baseUrl, '/') . '/csv/?key=' . urlencode($key);
            
            $response = Http::withHeaders([
                'TAX-CODE' => $this->taxCode,
                'SECURITY-TOKEN' => $this->securityToken,
            ])->get($url);

            if ($response->successful()) {
                return $response;
            }

            throw new Exception('Failed to download list price. Status: ' . $response->status());
        } catch (Exception $e) {
            Log::error('Autonet Download List Price Exception', [
                'message' => $e->getMessage(),
                'key' => $key,
            ]);
            throw $e;
        }
    }

    /**
     * Get delivery data
     * Get delivery data for up to 50 items per request.
     * You can send PartNo or TecDoc article (TDBrandId and TDArticleNo).
     *
     * @param array $items Array of items:
     *   - ['PartNo' => '...', 'Quantity' => 1.0] for Autonet article
     *   - ['TDBrandId' => 30, 'TDArticleNo' => '...', 'Quantity' => 1.0] for TecDoc article
     * @return array
     * @throws Exception
     */
    public function getDeliveryData(array $items): array
    {
        // Validate that each item has Quantity
        foreach ($items as $item) {
            if (!isset($item['Quantity'])) {
                throw new Exception('Each item must have a Quantity field');
            }
        }

        return $this->makeRequest('POST', 'GetDeliveryData', $items);
    }

    /**
     * Get delivery data for multiple chunks in parallel.
     * Each chunk must have max 50 items (AutoNet limit).
     *
     * @param array $chunks Array of item arrays
     * @return array Responses in the same order as chunks
     */
    public function getDeliveryDataBatch(array $chunks, ?int $timeout = null, ?int $connectTimeout = null): array
    {
        if (empty($chunks)) {
            return [];
        }

        $url = rtrim($this->baseUrl, '/') . '/GetDeliveryData';
        $headers = $this->getHeaders();

        $searchTimeout = $timeout ?? 15;
        $searchConnectTimeout = $connectTimeout ?? 5;

        $responses = Http::pool(fn ($pool) => collect($chunks)
            ->map(fn ($chunk, $index) => $pool
                ->as((string) $index)
                ->withHeaders($headers)
                ->asJson()
                ->timeout($searchTimeout)
                ->connectTimeout($searchConnectTimeout)
                ->post($url, $chunk))
            ->all()
        );

        $result = [];
        foreach ($chunks as $index => $chunk) {
            $response = $responses[(string) $index] ?? null;
            if ($response && $response->successful()) {
                $result[$index] = $response->json() ?? [];
            } else {
                if ($response) {
                    Log::error('Autonet batch chunk failed', [
                        'chunk_index' => $index,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
                $result[$index] = [];
            }
        }

        return $result;
    }

	public function applyPrefix(string $brand, string $code): string
	{
		$brand = strtoupper($brand);
		return match ($brand) {
			// Prefix brands
			'GKN'      => 'GKN' . $code,
			'KYB'      => 'KY' . $code,
			'SPIDAN'   => 'SP' . $code,
			'STABILUS' => 'STA' . $code,
			'NISSENS' => 'NS' . $code,

			// Suffix with space
			'AIRTEX'       => $code . ' AIR',
			'AUTLOG'       => $code . ' AT',
			'COFLE'        => $code . ' CO',
			'CS GERMANY'   => $code . ' LS',
			'DAYCO'        => $code . '-DY',
			'HITACHI'      => $code . ' HI',
			'LESJÖFORS'    => $code . ' LESJ',
			'LPR'          => $code . ' LPR',
			'MEAT & DORIA' => $code . ' MD',
			'MEYLE'        => $code . ' MY',
			'MOBILETRON'   => $code . ' MB',
			'NRF'          => $code . ' NRF',
			'POLCAR'       => $code . ' POL',
			'PRASCO'       => $code . ' PRA',
			'TEXTAR'       => $code . ' TEX',
			'TOPRAN'       => $code . ' TO',

			// Suffix without space
			'ASSO'       => $code . 'AS',
			'BILSTEIN'   => $code . 'BS',
			'BUGIAD'     => $code . 'BUG',
			'CIFAM'      => $code . 'CF',
			'CORTECO'    => $code . 'CO',
			'ELRING'     => $code . 'EL',
			'ELSTOCK'    => $code . 'EL',
			'FAE'        => $code . 'FA',
			'GATES'      => $code . '-GT',
			'HEPU'       => $code . 'HE',
			default      => $code,
		};
	}

	public function normalizeCode(string $apiCode): string
	{
		// Remove all prefixes and suffixes we defined above
		$apiCode = preg_replace(
			'/^(GKN|KYB|KY|SP|STA|NS|M)/i', '', // remove prefix
			$apiCode
		);

		$apiCode = preg_replace(
			'/(\sAIR|\sCO|\sLS|\sHI|\sLESJ|\sLPR|\sMD|\sMY|\sMB|\sNRF|\sPOL|\sPRA|\sTEX|\sTO|\sER|AS|BS|BUG|A|CF|CO|EL|FA|-GT|AT|-DY|HEP|HE|FE|LMI)$/i',
			'',
			$apiCode
		);

		return $apiCode;
	}

	/**
	 * Autonet API returns LEMFÖRDER articles with suffix "LMI" (e.g. 38956LMI);
	 * parts_catalog stores the same article as "38956 01".
	 * Call before normalizeCode() / DB mapping so keys match (Autonet + LEMFÖRDER).
	 */
	public function mapAutonetLemforderPartNoToCatalogStyle(string $partNo): string
	{
		$partNo = trim($partNo);
		if ($partNo === '' || !preg_match('/LMI$/i', $partNo)) {
			return $partNo;
		}

		return (string) preg_replace('/LMI$/i', ' 01', $partNo);
	}

    /**
     * Response code constants for order operations
     */
    public const RESPONSE_CODE_SUCCESS = 0;
    public const RESPONSE_CODE_NO_ERROR = -1;
    public const RESPONSE_CODE_INVALID_SECURITY_TOKEN = 1;
    public const RESPONSE_CODE_INVALID_TAX_CODE = 2;
    public const RESPONSE_CODE_INVALID_IP = 3;
    public const RESPONSE_CODE_INVALID_INPUT = 4;
    public const RESPONSE_CODE_INVALID_CUSTOMER = 5;
    public const RESPONSE_CODE_CREDIT_LIMIT_EXCEEDED = 6;
    public const RESPONSE_CODE_CUSTOMER_INVALID = 7;
    public const RESPONSE_CODE_CREDIT_LIMIT = 8;
    public const RESPONSE_CODE_TOO_MANY_ITEMS = 9;
    public const RESPONSE_CODE_INVALID_EXTERNAL_ORDER_NUMBER = 10;
    public const RESPONSE_CODE_EXTERNAL_ORDER_ALREADY_REGISTERED = 11;
    public const RESPONSE_CODE_INVALID_BRANCH = 12;
    public const RESPONSE_CODE_INVALID_ORDER_NUMBER = 12;
    public const RESPONSE_CODE_EXTERNAL_ORDER_NUMBER_INVALID = 13;
    public const RESPONSE_CODE_ORDER_CANT_BE_CANCELED = 15;
    public const RESPONSE_CODE_ORDER_ALREADY_CANCELED = 16;
    public const RESPONSE_CODE_CUSTOMER_INACTIVE = 17;
    public const RESPONSE_CODE_ORDER_ALREADY_CONFIRMED = 18;
    public const RESPONSE_CODE_MAX_DAILY_ORDERS_EXCEEDED = 19;
    public const RESPONSE_CODE_UNAUTHORIZED = 20;
    public const RESPONSE_CODE_INVALID_BRANCH_CODE = 21;
    public const RESPONSE_CODE_ARTICLE_NOT_FOUND = 22;
    public const RESPONSE_CODE_EXTERNAL_ORDER_NUMBER_REGISTERED = 23;
    public const RESPONSE_CODE_RESERVATION_FAILED = 24;
    public const RESPONSE_CODE_ORDER_CONFIRMATION_REJECTED = 25;
    public const RESPONSE_CODE_CANCEL_RESERVATION_REJECTED = 26;
    public const RESPONSE_CODE_STOCK_PICKING_ERROR = 28;
    public const RESPONSE_CODE_MULTIPLE_SALES_QUANTITY = 31;
    public const RESPONSE_CODE_ORDER_UNDER_PROCESSING = 34;
    public const RESPONSE_CODE_OUT_OF_SERVICE = 35;
    public const RESPONSE_CODE_ARTICLE_BLOCKED = 36;
    public const RESPONSE_CODE_INVALID_ARTICLE = 37;
    public const RESPONSE_CODE_BARE_MINIMUM_SALES_QUANTITY = 38;
    public const RESPONSE_CODE_INVALID_REQUESTED_QUANTITY = 39;
    public const RESPONSE_CODE_REQUEST_FAILED = 99;
    public const RESPONSE_CODE_ERROR = 100;

    /**
     * Response code constants for order items
     */
    public const ITEM_CODE_SUCCESS = 0;
    public const ITEM_CODE_NO_ERROR = -1;
    public const ITEM_CODE_ARTICLE_NOT_FOUND = 1;
    public const ITEM_CODE_INVALID_ARTICLE = 2;
    public const ITEM_CODE_ARTICLE_BLOCKED = 2;
    public const ITEM_CODE_OUT_OF_STOCK = 7;
    public const ITEM_CODE_OUT_OF_STOCK_ALT = 11;
    public const ITEM_CODE_ARTICLE_NOT_FOUND_ALT = 22;
    public const ITEM_CODE_ARTICLE_BLOCKED_ALT = 36;
    public const ITEM_CODE_INVALID_ARTICLE_ALT = 37;
    public const ITEM_CODE_BARE_MINIMUM_SALES_QUANTITY = 38;
    public const ITEM_CODE_INVALID_REQUESTED_QUANTITY = 39;
    public const ITEM_CODE_ERROR = 100;
}