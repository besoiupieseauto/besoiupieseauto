<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
 
class AnafService
{
    /**
     * Get company information by CUI/CNP from ANAF API
     *
     * @param int $cui Company identification number
     * @return array Response from ANAF
     */
    public function getCompanyInfo($cui)
    {
        try {
            $cui = intval($cui);
            $datacurenta = date('Y-m-d');
            
            // Format JSON exactly as in the original code
            //$json = "[{\"cui\":$cui,\"data\":\"$datacurenta\"}]";
			$json = json_encode([
				['cui' => $cui, 'data' => $datacurenta]
			]);
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->withBody($json, 'application/json')
              ->post('https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva');
              //->post('https://webservicesp.anaf.ro/api/registruroefactura/v1/interogare');
            
            if ($response->successful()) {
                // Return the JSON response from ANAF
                return $response->json();
            }
            
            return [
                'message' => 'ERROR',
                'error' => 'API request failed: ' . $response->status()
            ];
        } catch (\Exception $e) {
            return [
                'message' => 'ERROR',
                'error' => $e->getMessage()
            ];
        }
    }
}