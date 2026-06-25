<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\ApiCredential;

class SmartBillService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $userEmail;

    public function __construct()
    {
		$this->apiUrl = $this->getCredential('smartbill', 'api_url');
		$this->apiKey = $this->getCredential('smartbill', 'api_key');
		$this->userEmail = $this->getCredential('smartbill', 'user_email');
		
/*      $this->apiUrl = config('services.smartbill.api_url'); // Configurable
        $this->apiKey = config('services.smartbill.api_key');
        $this->userEmail = config('services.smartbill.user_email'); */
    }

    /**
     * Create an invoice in SmartBill
     *
     * @param array $invoiceData
     * @return array|null
     */
    public function createInvoice(array $invoiceData): ?array
    {
        $endpoint = $this->apiUrl . '/invoice'; // Example endpoint

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->userEmail . ':' . $this->apiKey),
            'Accept' => 'application/json',
			'Content-Type' => 'application/json',
        ])->post($endpoint, $invoiceData);

        if ($response->successful()) {
            return $response->json();
        }

        \Log::error('SmartBill API Error', [
            'response' => $response->body(),
        ]);

        return null;
    }
	
	public function reverseInvoice(array $invoiceData): ?array
    {
        $endpoint = $this->apiUrl . '/invoice/reverse'; // Example endpoint

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->userEmail . ':' . $this->apiKey),
            'Accept' => 'application/json',
			'Content-Type' => 'application/json',
        ])->post($endpoint, $invoiceData);

        if ($response->successful()) {
            return $response->json();
        }

        \Log::error('SmartBill API Error', [
            'response' => $response->body(),
        ]);

        return null;
	}

    /**
     * Optional: Retrieve invoice by ID
     */
	public function getInvoice(string $cif, string $seriesName, string $number)
	{
		$endpoint = $this->apiUrl . "/invoice/pdf";

		$response = Http::withHeaders([
			'Authorization' => 'Basic ' . base64_encode($this->userEmail . ':' . $this->apiKey),
			'Content-Type'  => 'application/xml',
			'Accept'        => 'application/octet-stream',
		])->get($endpoint, [
			'cif'        => $cif,
			'seriesname' => $seriesName,
			'number'     => $number,
		]);

		if ($response->successful()) {
			return $response->body(); // return raw PDF binary
		}

		return null; // no PDF found
	}
	
	public function createPayment(array $paymentData): ?array
	{
		$endpoint = $this->apiUrl . '/payment';

		$response = Http::withHeaders([
			'Authorization' => 'Basic ' . base64_encode($this->userEmail . ':' . $this->apiKey),
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
		])->post($endpoint, $paymentData);

		if ($response->successful()) {
			return $response->json();
		}
		
		//dd([$response->body(),$paymentData]);

		\Log::error('SmartBill Payment API Error', [
			'response' => $response->body(),
			'data' => $paymentData,
		]);

		return null;
	}
	
	private function getCredential(string $service, string $key): string
	{
		$record = ApiCredential::where('service_name', $service)
			->where('data_key', $key)
			->first();

		// If missing, fallback to .env (optional)
		if (!$record) {
			return config("services.$service.$key");
		}

		return $record->data_value ?? ''; // decrypted automatically via accessor
	}
}
