<?php

namespace App\Services\AutoPartner;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * AutoPartner Customer API Service
 * 
 * This service handles all interactions with the AutoPartner Customer API v2.13
 * Production Environment Configuration
 */
class AutoPartnerService
{
    protected $baseUrl;
    protected $clientCode;
    protected $wsPassword;
    protected $clientPassword;
    protected $timeout;
    protected $token;
    protected $tokenExpiresAt;

    /**
     * Initialize the service with production credentials
     */
    public function __construct()
    {
        // Production environment credentials
        // REST/JSON endpoint: https://customerapi.autopartner.dev/CustomerAPI.svc/rest/MethodName
        // Testing: https://customerapitest.autopartner.dev/CustomerAPI.svc/rest/MethodName
        $this->baseUrl = env('AUTOPARTNER_BASE_URL', 'https://customerapi.autopartner.dev/CustomerAPI.svc/rest');
        $this->clientCode = '3208129'; /*Utvin*/
        //$this->clientCode = '3241732'; /*Timisoara*/
        $this->wsPassword = 'hg6%^hbnjku5FG():j';
        $this->clientPassword = '83a192d415527f43511f557fcfaaf999'; /*Utvin*/
        //$this->clientPassword = '3b734975829a3e5f70d4ba3eca309b7b'; /*Timisoara*/
        $this->timeout = 30;
    }
	
	protected function setClientCredentialsByOrderFrom(string $orderFrom): void
	{
		match ($orderFrom) {
			'UTVIN' => [
				$this->clientCode = '3208129',
				$this->clientPassword = '83a192d415527f43511f557fcfaaf999',
			],
			'TIMISOARA' => [
				$this->clientCode = '3241732',
				$this->clientPassword = '3b734975829a3e5f70d4ba3eca309b7b',
			],
			default => null,
		};
	}

    /**
     * Get authentication credentials for requests
     * AutoPartner API uses clientCode, wsPassword, and clientPassword for authentication
     * 
     * @return array
     */
    protected function getAuthCredentials()
    {
        return [
            'clientCode' => $this->clientCode,
            'wsPassword' => $this->wsPassword,
            'clientPassword' => $this->clientPassword,
        ];
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getAuthCredentialsForRequest(): array
    {
        return $this->getAuthCredentials();
    }

    /**
     * Get authentication token (if API uses token-based auth)
     * 
     * @return string|null
     * @throws Exception
     */
    protected function getAuthToken()
    {
        // Check if we have a valid cached token
        if ($this->token && $this->tokenExpiresAt && now()->lt($this->tokenExpiresAt)) {
            return $this->token;
        }

        // Try to get from cache
        $cachedToken = Cache::get('autopartner_token');
        if ($cachedToken) {
            $this->token = $cachedToken;
            return $this->token;
        }

        // Request new token using credentials
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->baseUrl . '/auth/token', $this->getAuthCredentials());

            if ($response->successful()) {
                $data = $response->json();
                $this->token = $data['token'] ?? $data['access_token'] ?? null;
                $expiresIn = $data['expires_in'] ?? 3600;
                $this->tokenExpiresAt = now()->addSeconds($expiresIn - 60); // Refresh 1 minute early
                
                // Cache the token
                Cache::put('autopartner_token', $this->token, now()->addSeconds($expiresIn - 60));
                
                return $this->token;
            }

            throw new Exception('Failed to authenticate: ' . $response->body());
        } catch (Exception $e) {
            Log::error('AutoPartner authentication failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Make authenticated HTTP request
     * 
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return array
     * @throws Exception
     */
    protected function makeRequest(string $method, string $endpoint, array $data = [], array $headers = [])
    {
        try {
            // AutoPartner REST API uses POST for all methods
            // Merge authentication credentials with request data
            $authCredentials = $this->getAuthCredentials();
            $requestData = array_merge($authCredentials, $data);
            
            // REST API requires Content-Type: application/json header
            $request = Http::timeout($this->timeout)
                ->withHeaders(array_merge([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ], $headers));

            // Endpoint format: /MethodName (e.g., /ProductAvailability)
            $url = $this->baseUrl . $endpoint;
            
            // Log the request for debugging
            Log::debug('AutoPartner API Request', [
                'method' => 'POST',
                'url' => $url,
                'endpoint' => $endpoint,
                'data_keys' => array_keys($requestData),
            ]);

            // All AutoPartner REST API methods use POST
            $response = $request->post($url, $requestData);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'status' => $response->status(),
                ];
            }

            // Handle different error status codes
            if ($response->status() === 401) {
                // Authentication failed, clear cache and retry once
                Cache::forget('autopartner_token');
                $this->token = null;
                return $this->makeRequest($method, $endpoint, $data, $headers);
            }

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? $response->body(),
                'status' => $response->status(),
                'data' => $response->json(),
            ];
        } catch (Exception $e) {
            Log::error('AutoPartner API request failed', [
                'method' => $method,
                'url' => $url ?? ($this->baseUrl . $endpoint),
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'base_url' => $this->baseUrl,
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 500,
                'url_attempted' => $url ?? ($this->baseUrl . $endpoint),
            ];
        }
    }

    // ============================================================================
    // CUSTOMER ENDPOINTS
    // ============================================================================

    /**
     * Get list of customers
     * 
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getCustomers(array $filters = [], int $page = 1, int $perPage = 20)
    {
        $params = array_merge($filters, [
            'page' => $page,
            'per_page' => $perPage,
        ]);

        return $this->makeRequest('GET', '/customers', $params);
    }

    /**
     * Get customer by ID
     * 
     * @param int|string $customerId
     * @return array
     */
    public function getCustomer($customerId)
    {
        return $this->makeRequest('GET', "/customers/{$customerId}");
    }

    /**
     * Create a new customer
     * 
     * @param array $customerData
     * @return array
     */
    public function createCustomer(array $customerData)
    {
        return $this->makeRequest('POST', '/customers', $customerData);
    }

    /**
     * Update customer
     * 
     * @param int|string $customerId
     * @param array $customerData
     * @return array
     */
    public function updateCustomer($customerId, array $customerData)
    {
        return $this->makeRequest('PUT', "/customers/{$customerId}", $customerData);
    }

    /**
     * Partially update customer
     * 
     * @param int|string $customerId
     * @param array $customerData
     * @return array
     */
    public function patchCustomer($customerId, array $customerData)
    {
        return $this->makeRequest('PATCH', "/customers/{$customerId}", $customerData);
    }

    /**
     * Delete customer
     * 
     * @param int|string $customerId
     * @return array
     */
    public function deleteCustomer($customerId)
    {
        return $this->makeRequest('DELETE', "/customers/{$customerId}");
    }

    /**
     * Search customers
     * 
     * @param string $query
     * @param array $filters
     * @return array
     */
    public function searchCustomers(string $query, array $filters = [])
    {
        $params = array_merge($filters, [
            'q' => $query,
        ]);

        return $this->makeRequest('GET', '/customers/search', $params);
    }

    /**
     * Get customer by email
     * 
     * @param string $email
     * @return array
     */
    public function getCustomerByEmail(string $email)
    {
        return $this->makeRequest('GET', '/customers', ['email' => $email]);
    }

    /**
     * Get customer by phone
     * 
     * @param string $phone
     * @return array
     */
    public function getCustomerByPhone(string $phone)
    {
        return $this->makeRequest('GET', '/customers', ['phone' => $phone]);
    }

    // ============================================================================
    // CUSTOMER ADDRESSES
    // ============================================================================

    /**
     * Get customer addresses
     * 
     * @param int|string $customerId
     * @return array
     */
    public function getCustomerAddresses($customerId)
    {
        return $this->makeRequest('GET', "/customers/{$customerId}/addresses");
    }

    /**
     * Add customer address
     * 
     * @param int|string $customerId
     * @param array $addressData
     * @return array
     */
    public function addCustomerAddress($customerId, array $addressData)
    {
        return $this->makeRequest('POST', "/customers/{$customerId}/addresses", $addressData);
    }

    /**
     * Update customer address
     * 
     * @param int|string $customerId
     * @param int|string $addressId
     * @param array $addressData
     * @return array
     */
    public function updateCustomerAddress($customerId, $addressId, array $addressData)
    {
        return $this->makeRequest('PUT', "/customers/{$customerId}/addresses/{$addressId}", $addressData);
    }

    /**
     * Delete customer address
     * 
     * @param int|string $customerId
     * @param int|string $addressId
     * @return array
     */
    public function deleteCustomerAddress($customerId, $addressId)
    {
        return $this->makeRequest('DELETE', "/customers/{$customerId}/addresses/{$addressId}");
    }

    // ============================================================================
    // CUSTOMER ORDERS
    // ============================================================================

    /**
     * Get customer orders
     * 
     * @param int|string $customerId
     * @param array $filters
     * @return array
     */
    public function getCustomerOrders($customerId, array $filters = [])
    {
        return $this->makeRequest('GET', "/customers/{$customerId}/orders", $filters);
    }

    /**
     * Get customer order by ID
     * 
     * @param int|string $customerId
     * @param int|string $orderId
     * @return array
     */
    public function getCustomerOrder($customerId, $orderId)
    {
        return $this->makeRequest('GET', "/customers/{$customerId}/orders/{$orderId}");
    }

    /**
     * Create customer order
     * 
     * @param int|string $customerId
     * @param array $orderData
     * @return array
     */
    public function createCustomerOrder($customerId, array $orderData)
    {
        return $this->makeRequest('POST', "/customers/{$customerId}/orders", $orderData);
    }

    // ============================================================================
    // CUSTOMER VEHICLES
    // ============================================================================

    /**
     * Get customer vehicles
     * 
     * @param int|string $customerId
     * @return array
     */
    public function getCustomerVehicles($customerId)
    {
        return $this->makeRequest('GET', "/customers/{$customerId}/vehicles");
    }

    /**
     * Add customer vehicle
     * 
     * @param int|string $customerId
     * @param array $vehicleData
     * @return array
     */
    public function addCustomerVehicle($customerId, array $vehicleData)
    {
        return $this->makeRequest('POST', "/customers/{$customerId}/vehicles", $vehicleData);
    }

    /**
     * Update customer vehicle
     * 
     * @param int|string $customerId
     * @param int|string $vehicleId
     * @param array $vehicleData
     * @return array
     */
    public function updateCustomerVehicle($customerId, $vehicleId, array $vehicleData)
    {
        return $this->makeRequest('PUT', "/customers/{$customerId}/vehicles/{$vehicleId}", $vehicleData);
    }

    /**
     * Delete customer vehicle
     * 
     * @param int|string $customerId
     * @param int|string $vehicleId
     * @return array
     */
    public function deleteCustomerVehicle($customerId, $vehicleId)
    {
        return $this->makeRequest('DELETE', "/customers/{$customerId}/vehicles/{$vehicleId}");
    }

    // ============================================================================
    // CUSTOMER DOCUMENTS
    // ============================================================================

    /**
     * Get customer documents
     * 
     * @param int|string $customerId
     * @return array
     */
    public function getCustomerDocuments($customerId)
    {
        return $this->makeRequest('GET', "/customers/{$customerId}/documents");
    }

    /**
     * Upload customer document
     * 
     * @param int|string $customerId
     * @param array $documentData
     * @return array
     */
    public function uploadCustomerDocument($customerId, array $documentData)
    {
        return $this->makeRequest('POST', "/customers/{$customerId}/documents", $documentData);
    }

    /**
     * Delete customer document
     * 
     * @param int|string $customerId
     * @param int|string $documentId
     * @return array
     */
    public function deleteCustomerDocument($customerId, $documentId)
    {
        return $this->makeRequest('DELETE', "/customers/{$customerId}/documents/{$documentId}");
    }

    // ============================================================================
    // CUSTOMER NOTES
    // ============================================================================

    /**
     * Get customer notes
     * 
     * @param int|string $customerId
     * @return array
     */
    public function getCustomerNotes($customerId)
    {
        return $this->makeRequest('GET', "/customers/{$customerId}/notes");
    }

    /**
     * Add customer note
     * 
     * @param int|string $customerId
     * @param array $noteData
     * @return array
     */
    public function addCustomerNote($customerId, array $noteData)
    {
        return $this->makeRequest('POST', "/customers/{$customerId}/notes", $noteData);
    }

    /**
     * Update customer note
     * 
     * @param int|string $customerId
     * @param int|string $noteId
     * @param array $noteData
     * @return array
     */
    public function updateCustomerNote($customerId, $noteId, array $noteData)
    {
        return $this->makeRequest('PUT', "/customers/{$customerId}/notes/{$noteId}", $noteData);
    }

    /**
     * Delete customer note
     * 
     * @param int|string $customerId
     * @param int|string $noteId
     * @return array
     */
    public function deleteCustomerNote($customerId, $noteId)
    {
        return $this->makeRequest('DELETE', "/customers/{$customerId}/notes/{$noteId}");
    }

    // ============================================================================
    // CUSTOMER COMMUNICATION
    // ============================================================================

    /**
     * Get customer communication history
     * 
     * @param int|string $customerId
     * @return array
     */
    public function getCustomerCommunications($customerId)
    {
        return $this->makeRequest('GET', "/customers/{$customerId}/communications");
    }

    /**
     * Send message to customer
     * 
     * @param int|string $customerId
     * @param array $messageData
     * @return array
     */
    public function sendCustomerMessage($customerId, array $messageData)
    {
        return $this->makeRequest('POST', "/customers/{$customerId}/communications", $messageData);
    }

    // ============================================================================
    // CUSTOMER STATISTICS
    // ============================================================================

    /**
     * Get customer statistics
     * 
     * @param int|string $customerId
     * @return array
     */
    public function getCustomerStatistics($customerId)
    {
        return $this->makeRequest('GET', "/customers/{$customerId}/statistics");
    }

    /**
     * Get customer activity history
     * 
     * @param int|string $customerId
     * @param array $filters
     * @return array
     */
    public function getCustomerActivity($customerId, array $filters = [])
    {
        return $this->makeRequest('GET', "/customers/{$customerId}/activity", $filters);
    }

    // ============================================================================
    // BULK OPERATIONS
    // ============================================================================

    /**
     * Bulk create customers
     * 
     * @param array $customersData
     * @return array
     */
    public function bulkCreateCustomers(array $customersData)
    {
        return $this->makeRequest('POST', '/customers/bulk', ['customers' => $customersData]);
    }

    /**
     * Bulk update customers
     * 
     * @param array $customersData
     * @return array
     */
    public function bulkUpdateCustomers(array $customersData)
    {
        return $this->makeRequest('PUT', '/customers/bulk', ['customers' => $customersData]);
    }

    /**
     * Export customers
     * 
     * @param array $filters
     * @return array
     */
    public function exportCustomers(array $filters = [])
    {
        return $this->makeRequest('GET', '/customers/export', $filters);
    }

    // ============================================================================
    // UTILITY METHODS
    // ============================================================================

    /**
     * Validate customer data
     * 
     * @param array $customerData
     * @return array
     */
    public function validateCustomer(array $customerData)
    {
        return $this->makeRequest('POST', '/customers/validate', $customerData);
    }

    /**
     * Check if customer exists
     * 
     * @param string $email
     * @param string|null $phone
     * @return bool
     */
    public function customerExists(string $email, ?string $phone = null)
    {
        $params = ['email' => $email];
        if ($phone) {
            $params['phone'] = $phone;
        }

        $response = $this->makeRequest('GET', '/customers/check', $params);
        return $response['success'] && !empty($response['data']);
    }

    /**
     * Get API status/health check
     * 
     * @return array
     */
    public function getApiStatus()
    {
        return $this->makeRequest('GET', '/status');
    }

    /**
     * Get API version information
     * 
     * @return array
     */
    public function getApiVersion()
    {
        return $this->makeRequest('GET', '/version');
    }

    // ============================================================================
    // PRODUCT/PARTS SEARCH ENDPOINTS
    // ============================================================================

    /**
     * Search products/parts by product code
     * Uses ProductAvailability API method
     * 
     * @param string $productCode
     * @param array $filters
     * @return array
     */
    public function searchProductsByCode(string $productCode, array $filters = [])
    {
        $params = array_merge($filters, [
            'productCode' => $productCode,
        ]);

        // Use the ProductAvailability method from the API
        return $this->productAvailability($params);
    }

    /**
     * Search products/parts by VIN number
     * 
     * @param string $vin
     * @param array $filters
     * @return array
     */
    public function searchProductsByVIN(string $vin, array $filters = [])
    {
        $params = array_merge($filters, [
            'vin' => $vin,
        ]);

        return $this->makeRequest('GET', '/products/search', $params);
    }

    /**
     * Search products/parts by registration number
     * 
     * @param string $registrationNumber
     * @param array $filters
     * @return array
     */
    public function searchProductsByRegistration(string $registrationNumber, array $filters = [])
    {
        $params = array_merge($filters, [
            'registrationNumber' => $registrationNumber,
        ]);

        return $this->makeRequest('GET', '/products/search', $params);
    }

    /**
     * Search products/parts by name
     * 
     * @param string $name
     * @param array $filters
     * @return array
     */
    public function searchProductsByName(string $name, array $filters = [])
    {
        $params = array_merge($filters, [
            'name' => $name,
        ]);

        return $this->makeRequest('GET', '/products/search', $params);
    }

    /**
     * Search products/parts by KBA number
     * 
     * @param string $kbaNumber
     * @param array $filters
     * @return array
     */
    public function searchProductsByKBA(string $kbaNumber, array $filters = [])
    {
        $params = array_merge($filters, [
            'kbaNumber' => $kbaNumber,
        ]);

        return $this->makeRequest('GET', '/products/search', $params);
    }

    /**
     * Search products/parts by OE number (Original Equipment)
     * 
     * @param string $oeNumber
     * @param array $filters
     * @return array
     */
    public function searchProductsByOE(string $oeNumber, array $filters = [])
    {
        $params = array_merge($filters, [
            'oeNumber' => $oeNumber,
        ]);

        return $this->makeRequest('GET', '/products/search', $params);
    }

    /**
     * Search tires
     * 
     * @param array $tireParams (width, profile, diameter, etc.)
     * @return array
     */
    public function searchTires(array $tireParams)
    {
        return $this->makeRequest('GET', '/products/tires', $tireParams);
    }

    /**
     * General product/parts search with multiple criteria
     * 
     * @param array $searchCriteria
     * @return array
     */
    public function searchProducts(array $searchCriteria)
    {
        return $this->makeRequest('GET', '/products/search', $searchCriteria);
    }

    /**
     * Get product details by ID
     * 
     * @param int|string $productId
     * @return array
     */
    public function getProduct($productId)
    {
        return $this->makeRequest('GET', "/products/{$productId}");
    }

    /**
     * Get product catalog
     * 
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getProductCatalog(array $filters = [], int $page = 1, int $perPage = 20)
    {
        $params = array_merge($filters, [
            'page' => $page,
            'per_page' => $perPage,
        ]);

        return $this->makeRequest('GET', '/products/catalog', $params);
    }

    /**
     * Get product categories
     * 
     * @return array
     */
    public function getProductCategories()
    {
        return $this->makeRequest('GET', '/products/categories');
    }

    /**
     * Get products by category
     * 
     * @param int|string $categoryId
     * @param array $filters
     * @return array
     */
    public function getProductsByCategory($categoryId, array $filters = [])
    {
        $params = array_merge($filters, [
            'categoryId' => $categoryId,
        ]);

        return $this->makeRequest('GET', '/products', $params);
    }

    /**
     * Get product availability/stock
     * 
     * @param int|string $productId
     * @return array
     */
    public function getProductAvailability($productId)
    {
        return $this->makeRequest('GET', "/products/{$productId}/availability");
    }

    /**
     * Get product pricing
     * 
     * @param int|string $productId
     * @param array $filters
     * @return array
     */
    public function getProductPricing($productId, array $filters = [])
    {
        return $this->makeRequest('GET', "/products/{$productId}/pricing", $filters);
    }

    /**
     * Get product images
     * 
     * @param int|string $productId
     * @return array
     */
    public function getProductImages($productId)
    {
        return $this->makeRequest('GET', "/products/{$productId}/images");
    }

    /**
     * Get product compatibility (vehicle compatibility)
     * 
     * @param int|string $productId
     * @return array
     */
    public function getProductCompatibility($productId)
    {
        return $this->makeRequest('GET', "/products/{$productId}/compatibility");
    }

    /**
     * Get product alternatives/substitutes
     * 
     * @param int|string $productId
     * @return array
     */
    public function getProductAlternatives($productId)
    {
        return $this->makeRequest('GET', "/products/{$productId}/alternatives");
    }

    // ============================================================================
    // API METHODS FROM PDF DOCUMENTATION
    // ============================================================================

    /**
     * ProductAvailability - Check availability of a single product
     * 
     * @param array $productData
     * @return array
     */
    public function productAvailability(array $productData)
    {
        return $this->makeRequest('POST', '/ProductAvailability', $productData);
    }

    /**
     * ProductsAvailability - Check availability of multiple products
     * 
     * @param array $productsData
     * @return array
     */
    public function productsAvailability(array $productsData)
    {
        return $this->makeRequest('POST', '/ProductsAvailability', $productsData);
    }

    /**
     * Invoices - Get invoices list
     * 
     * @param array $filters
     * @return array
     */
    public function invoices(array $filters = [])
    {
        return $this->makeRequest('POST', '/Invoices', $filters);
    }

    /**
     * InvoicePositions - Get invoice positions/details
     * 
     * @param int|string $invoiceId
     * @param array $filters
     * @return array
     */
    public function invoicePositions($invoiceId, array $filters = [])
    {
        $params = array_merge($filters, ['invoiceId' => $invoiceId]);
        return $this->makeRequest('POST', '/InvoicePositions', $params);
    }

    /**
     * DownloadInvoicePDF - Download invoice as PDF
     * 
     * @param int|string $invoiceId
     * @return array
     */
    public function downloadInvoicePDF($invoiceId)
    {
        return $this->makeRequest('POST', '/DownloadInvoicePDF', ['invoiceId' => $invoiceId]);
    }

    /**
     * ConfirmInvoices - Confirm invoices
     * 
     * @param array $invoiceData
     * @return array
     */
    public function confirmInvoices(array $invoiceData)
    {
        return $this->makeRequest('POST', '/ConfirmInvoices', $invoiceData);
    }

    /**
     * DeliveryDocuments - Get delivery documents
     * 
     * @param array $filters
     * @return array
     */
    public function deliveryDocuments(array $filters = [])
    {
        return $this->makeRequest('POST', '/DeliveryDocuments', $filters);
    }

    /**
     * DeliveryDocumentPositions - Get delivery document positions
     * 
     * @param int|string $deliveryDocumentId
     * @param array $filters
     * @return array
     */
    public function deliveryDocumentPositions($deliveryDocumentId, array $filters = [])
    {
        $params = array_merge($filters, ['deliveryDocumentId' => $deliveryDocumentId]);
        return $this->makeRequest('POST', '/DeliveryDocumentPositions', $params);
    }

    /**
     * DownloadWzPDF - Download delivery document (WZ) as PDF
     * 
     * @param int|string $deliveryDocumentId
     * @return array
     */
    public function downloadWzPDF($deliveryDocumentId)
    {
        return $this->makeRequest('POST', '/DownloadWzPDF', ['deliveryDocumentId' => $deliveryDocumentId]);
    }

    /**
     * InsertOrder - Create/insert a new order
     * 
     * @param array $orderData
     * @return array
     */
	public function insertOrder(array $orderData, string $orderFrom)
	{
		$originalClientCode = $this->clientCode;
		$originalClientPassword = $this->clientPassword;

		try {
			$this->setClientCredentialsByOrderFrom($orderFrom);

			return $this->makeRequest('POST', '/InsertOrder', $orderData);
		} finally {
			// ALWAYS restore original credentials
			$this->clientCode = $originalClientCode;
			$this->clientPassword = $originalClientPassword;
		}
	}

    /**
     * RealizationForecast - Get realization forecast
     * 
     * @param array $filters
     * @return array
     */
    public function realizationForecast(array $filters = [])
    {
        return $this->makeRequest('POST', '/RealizationForecast', $filters);
    }

    /**
     * Logistic - Get logistic information
     * 
     * @param array $filters
     * @return array
     */
    public function logistic(array $filters = [])
    {
        return $this->makeRequest('POST', '/Logistic', $filters);
    }

    /**
     * DownloadOWS - Download OWS (Order Work Sheet) document
     * 
     * @param int|string $orderId
     * @return array
     */
    public function downloadOWS($orderId)
    {
        return $this->makeRequest('POST', '/DownloadOWS', ['orderId' => $orderId]);
    }

    /**
     * AcceptOWS - Accept OWS (Order Work Sheet)
     * 
     * @param array $owsData
     * @return array
     */
    public function acceptOWS(array $owsData)
    {
        return $this->makeRequest('POST', '/AcceptOWS', $owsData);
    }

    /**
     * CheckZSNumbers - Check ZS numbers
     * 
     * @param array $zsNumbers
     * @return array
     */
    public function checkZSNumbers(array $zsNumbers)
    {
        return $this->makeRequest('POST', '/CheckZSNumbers', $zsNumbers);
    }

    /**
     * ProofOfDelivery - Get proof of delivery
     * 
     * @param array $filters
     * @return array
     */
    public function proofOfDelivery(array $filters = [])
    {
        return $this->makeRequest('POST', '/ProofOfDelivery', $filters);
    }

    /**
     * DefaultDocument - Get default document
     * 
     * @param array $filters
     * @return array
     */
    public function defaultDocument(array $filters = [])
    {
        return $this->makeRequest('POST', '/DefaultDocument', $filters);
    }

    /**
     * ChangePassword - Change password
     * 
     * @param array $passwordData
     * @return array
     */
    public function changePassword(array $passwordData)
    {
        return $this->makeRequest('POST', '/ChangePassword', $passwordData);
    }

    /**
     * InsertOrderTecDoc - Insert order using TecDoc
     * 
     * @param array $orderData
     * @return array
     */
    public function insertOrderTecDoc(array $orderData)
    {
        return $this->makeRequest('POST', '/InsertOrderTecDoc', $orderData);
    }

    /**
     * ProductAvailabilityTecDoc - Check product availability using TecDoc
     * 
     * @param array $productData
     * @return array
     */
    public function productAvailabilityTecDoc(array $productData)
    {
        return $this->makeRequest('POST', '/ProductAvailabilityTecDoc', $productData);
    }

    /**
     * ProductsAvailabilityTecDoc - Check multiple products availability using TecDoc
     * 
     * @param array $productsData
     * @return array
     */
    public function productsAvailabilityTecDoc(array $productsData)
    {
        return $this->makeRequest('POST', '/ProductsAvailabilityTecDoc', $productsData);
    }

    /**
     * RealizationForecastTecDoc - Get realization forecast using TecDoc
     * 
     * @param array $filters
     * @return array
     */
    public function realizationForecastTecDoc(array $filters = [])
    {
        return $this->makeRequest('POST', '/RealizationForecastTecDoc', $filters);
    }

    /**
     * TransportCosts - Get transport costs
     * 
     * @param array $transportData
     * @return array
     */
    public function transportCosts(array $transportData)
    {
        return $this->makeRequest('POST', '/TransportCosts', $transportData);
    }

    /**
     * ProductsAvailableToReturn - Get products available for return
     * 
     * @param array $filters
     * @return array
     */
    public function productsAvailableToReturn(array $filters = [])
    {
        return $this->makeRequest('POST', '/ProductsAvailableToReturn', $filters);
    }

    /**
     * CreateReturn - Create a return
     * 
     * @param array $returnData
     * @return array
     */
    public function createReturn(array $returnData)
    {
        return $this->makeRequest('POST', '/CreateReturn', $returnData);
    }

    /**
     * GenerateReturnDocumentsPDF - Generate return documents as PDF
     * 
     * @param int|string $returnId
     * @return array
     */
    public function generateReturnDocumentsPDF($returnId)
    {
        return $this->makeRequest('POST', '/GenerateReturnDocumentsPDF', ['returnId' => $returnId]);
    }

    // ============================================================================
    // V2 API METHODS - Using classes from sections 3.32 to 3.40
    // ============================================================================

    /**
     * ProductAvailabilityV2 - Get availability for ONE product (Section 2.27)
     * Uses ProductInfoV2 (3.32) as input, returns ProductAvailabilityV2Response (3.33)
     * 
     * @param string $productCode Product code (e.g., "70-0033")
     * @param float $quantity Quantity to check (default: 1)
     * @param bool $onlySite If true, only returns data from client department (default: false)
     * @return array Response containing ProductAvailabilityV2Response with AvailabilityV2
     */
    public function productAvailabilityV2(string $productCode, float $quantity = 1, bool $onlySite = false)
    {
        // ProductInfoV2 structure (Section 3.32)
        $productInfo = [
            'productCode' => $productCode,
            'quantity' => $quantity,
        ];

        $requestData = [
            'product' => $productInfo,
            'onlySite' => $onlySite,
        ];

        return $this->makeRequest('POST', '/ProductAvailabilityV2', $requestData);
    }

    /**
     * ProductsAvailabilityV2 - Get availability for MULTIPLE products (Section 2.28)
     * Uses List<ProductInfoV2> (3.32) as input, returns ProductsAvailabilityV2Response (3.35)
     * 
     * @param array $products Array of products, each with 'productCode' and 'quantity'
     *                        Example: [['productCode' => 'GDB1330', 'quantity' => 1], ...]
     * @param bool $onlySite If true, only returns data from client department (default: false)
     * @return array Response containing ProductsAvailabilityV2Response with List<AvailabilityV2>
     */
    public function productsAvailabilityV2(array $products, bool $onlySite = false)
    {
        // Validate and structure ProductInfoV2 array (Section 3.32)
        $productsList = [];
        foreach ($products as $product) {
            $productsList[] = [
                'productCode' => $product['productCode'] ?? $product['product_code'] ?? '',
                'quantity' => $product['quantity'] ?? 1,
            ];
        }

        $requestData = [
            'products' => $productsList,
            'onlySite' => $onlySite,
        ];

        return $this->makeRequest('POST', '/ProductsAvailabilityV2', $requestData);
    }

    /**
     * GPSR - Get producer information for products (Section 2.29)
     * Returns GpsrResponse (3.36) with List<GPRS> (3.37)
     * 
     * @param array $productCodes Array of product codes (strings)
     *                            Example: ['GDB1330', 'GDB1328']
     * @return array Response containing GpsrResponse with GpsrData (List<GPRS>)
     */
    public function gpsr(array $productCodes)
    {
        $requestData = [
            'products' => $productCodes,
        ];

        return $this->makeRequest('POST', '/GPSR', $requestData);
    }

    /**
     * ProductAvailabilityTecDocV2 - Get availability for ONE TecDoc product (Section 2.30)
     * Uses ProductInfoTecDoc as input, returns ProductAvailabilityTecDocV2Response (3.39)
     * 
     * @param string $manufacturerArticleNumber Manufacturer article number (e.g., "Gdb1330")
     * @param string $tecDocFeederNumber TecDoc feeder number (e.g., "0161")
     * @param float $quantity Quantity to check (default: 1)
     * @param bool $onlySite If true, only returns data from client department (default: false)
     * @return array Response containing ProductAvailabilityTecDocV2Response with AvailabilityTecDocV2
     */
    public function productAvailabilityTecDocV2(
        string $manufacturerArticleNumber,
        string $tecDocFeederNumber,
        float $quantity = 1,
        bool $onlySite = false
    ) {
        $productInfo = [
            'manufacturerArticleNumber' => $manufacturerArticleNumber,
            'tecDocFeederNumber' => $tecDocFeederNumber,
            'quantity' => $quantity,
        ];

        $requestData = [
            'product' => $productInfo,
            'onlySite' => $onlySite,
        ];

        return $this->makeRequest('POST', '/ProductAvailabilityTecDocV2', $requestData);
    }

    /**
     * ProductsAvailabilityTecDocV2 - Get availability for MULTIPLE TecDoc products (Section 2.31)
     * Uses List<ProductInfoTecDoc> as input, returns ProductsAvailabilityTecDocV2Response (3.40)
     * 
     * @param array $products Array of TecDoc products, each with:
     *                        - 'manufacturerArticleNumber' (string)
     *                        - 'tecDocFeederNumber' (string)
     *                        - 'quantity' (float, default: 1)
     * @param bool $onlySite If true, only returns data from client department (default: false)
     * @return array Response containing ProductsAvailabilityTecDocV2Response with List<AvailabilityTecDocV2>
     */
    public function productsAvailabilityTecDocV2(array $products, bool $onlySite = false)
    {
        // Structure ProductInfoTecDoc array
        $productsList = [];
        foreach ($products as $product) {
            $productsList[] = [
                'manufacturerArticleNumber' => $product['manufacturerArticleNumber'] 
                    ?? $product['manufacturer_article_number'] 
                    ?? $product['articleNumber'] 
                    ?? '',
                'tecDocFeederNumber' => $product['tecDocFeederNumber'] 
                    ?? $product['tec_doc_feeder_number'] 
                    ?? $product['feederNumber'] 
                    ?? '',
                'quantity' => $product['quantity'] ?? 1,
            ];
        }

        $requestData = [
            'products' => $productsList,
            'onlySite' => $onlySite,
        ];

        return $this->makeRequest('POST', '/ProductsAvailabilityTecDocV2', $requestData);
    }

    // ============================================================================
    // HELPER METHODS - For working with response data structures
    // ============================================================================

    /**
     * Extract AvailabilityV2 data from ProductAvailabilityV2Response
     * 
     * @param array $response Response from productAvailabilityV2()
     * @return array|null AvailabilityV2 data or null if error
     */
    public function extractAvailabilityV2(array $response)
    {
        if (!$response['success']) {
            return null;
        }

        $data = $response['data'] ?? [];
        
        // Handle different response structures
        if (isset($data['RestProductAvailabilityV2Result'])) {
            return $data['RestProductAvailabilityV2Result']['Availability'] ?? null;
        }
        
        if (isset($data['Availability'])) {
            return $data['Availability'];
        }

        return $data['availability'] ?? null;
    }

    /**
     * Extract List<AvailabilityV2> from ProductsAvailabilityV2Response
     * 
     * @param array $response Response from productsAvailabilityV2()
     * @return array List of AvailabilityV2 data or empty array if error
     */
    public function extractAvailabilityV2List(array $response)
    {
        if (!$response['success']) {
            return [];
        }

        $data = $response['data'] ?? [];
        
        // Handle different response structures
        if (isset($data['RestProductsAvailabilityV2Result'])) {
            return $data['RestProductsAvailabilityV2Result']['Availability'] ?? [];
        }
        
        if (isset($data['Availability'])) {
            return is_array($data['Availability']) ? $data['Availability'] : [];
        }

        return $data['availability'] ?? [];
    }

    /**
     * Extract GpsrData (List<GPRS>) from GpsrResponse
     * 
     * @param array $response Response from gpsr()
     * @return array List of GPRS data or empty array if error
     */
    public function extractGpsrData(array $response)
    {
        if (!$response['success']) {
            return [];
        }

        $data = $response['data'] ?? [];
        
        // Handle different response structures
        if (isset($data['RestGPSRResult'])) {
            return $data['RestGPSRResult']['GpsrData'] ?? [];
        }
        
        if (isset($data['GpsrData'])) {
            return is_array($data['GpsrData']) ? $data['GpsrData'] : [];
        }

        return $data['gpsrData'] ?? [];
    }

    /**
     * Extract AvailabilityTecDocV2 from ProductAvailabilityTecDocV2Response
     * 
     * @param array $response Response from productAvailabilityTecDocV2()
     * @return array|null AvailabilityTecDocV2 data or null if error
     */
    public function extractAvailabilityTecDocV2(array $response)
    {
        if (!$response['success']) {
            return null;
        }

        $data = $response['data'] ?? [];
        
        // Handle different response structures
        if (isset($data['RestProductAvailabilityTecDocV2Result'])) {
            return $data['RestProductAvailabilityTecDocV2Result']['Availability'] ?? null;
        }
        
        if (isset($data['Availability'])) {
            return $data['Availability'];
        }

        return $data['availability'] ?? null;
    }

    /**
     * Extract List<AvailabilityTecDocV2> from ProductsAvailabilityTecDocV2Response
     * 
     * @param array $response Response from productsAvailabilityTecDocV2()
     * @return array List of AvailabilityTecDocV2 data or empty array if error
     */
    public function extractAvailabilityTecDocV2List(array $response)
    {
        if (!$response['success']) {
            return [];
        }

        $data = $response['data'] ?? [];
        
        // Handle different response structures
        if (isset($data['RestProductsAvailabilityTecDocV2Result'])) {
            return $data['RestProductsAvailabilityTecDocV2Result']['Availability'] ?? [];
        }
        
        if (isset($data['Availability'])) {
            return is_array($data['Availability']) ? $data['Availability'] : [];
        }

        return $data['availability'] ?? [];
    }

    /**
     * Check if response has error
     * 
     * @param array $response API response
     * @return bool True if error exists
     */
    public function hasError(array $response)
    {
        if (!$response['success']) {
            return true;
        }

        $data = $response['data'] ?? [];
        $errorCode = $data['ErrorCode'] ?? $data['errorCode'] ?? '';
        
        return !empty($errorCode);
    }

    /**
     * Get error code from response
     * 
     * @param array $response API response
     * @return string|null Error code or null if no error
     */
    public function getErrorCode(array $response)
    {
        if (!$response['success']) {
            return $response['error'] ?? 'UNKNOWN_ERROR';
        }

        $data = $response['data'] ?? [];
        $errorCode = $data['ErrorCode'] ?? $data['errorCode'] ?? '';
        
        return !empty($errorCode) ? $errorCode : null;
    }
	
	public function applyPrefix(string $brand, string $code): string
	{
		return match (strtoupper($brand)) {
			'ALKAR' => 'ALK' . $code,
			'BERU' => 'BER' . $code,
			'FAE'  => 'FAE' . $code,
			'GKN'  => 'GKN' . $code,
			'KYB'  => 'KYB' . $code,
			'NRF'  => 'NRF' . $code,
			'TYC'  => 'TYC' . $code,
			'STABILUS'  => 'STA' . $code,
			'NISSENS' => 'NIS' . $code,
			'FEBI', 'FEBI BILSTEIN' => 'FE'  . $code,
			'VALEO' => 'VAL' . $code,
			default => $code,
		};
	}

	public function normalizeCode(string $apiCode): string
	{
		// Strip brand prefixes applied in applyPrefix() so e.g. STA9582RK (STABILUS) groups with 9582RK (Autototal/catalog).
		return preg_replace('/^(ALK|BER|FAE|GKN|KYB|NRF|TYC|FE|VAL|NIS|STA)/i', '', $apiCode);
	}
}