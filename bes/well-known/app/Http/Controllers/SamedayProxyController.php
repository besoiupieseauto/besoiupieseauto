<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;

class SamedayProxyController extends Controller
{
    public function proxyRequest(Request $request, $endpoint = null)
    {
        // Replace with a working European proxy service if needed
        $proxyUrl = 'https://proxy.example.com'; // You'd need to find a suitable proxy
        
        $client = new Client([
            'base_uri' => 'https://api.sameday.ro',
            'timeout' => 30,
            'verify' => false,
            'proxy' => $proxyUrl
        ]);
        
        $method = strtolower($request->method());
        $options = [];
        
        // Add request body for POST/PUT
        if (in_array($method, ['post', 'put', 'patch'])) {
            $options['json'] = $request->all();
        }
        
        // Add query parameters
        if ($request->query()) {
            $options['query'] = $request->query();
        }
        
        // Add headers
        $options['headers'] = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
        
        // For authentication endpoint
        if ($endpoint === 'auth/login') {
            $options['json'] = [
                'user' => config('sameday.auth_user', 'besoiupieseautoAPI'),
                'password' => config('sameday.auth_password', 'MXV/zuLmJg==')
            ];
        }
        
        try {
            $response = $client->request($method, '/api/' . ($endpoint ?? ''), $options);
            
            return response()->json(
                json_decode((string) $response->getBody(), true)
            )->withHeaders($response->getHeaders());
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function auth()
    {
        return $this->proxyRequest(request(), 'client/auth/login');
    }
    
    public function pickupPoints()
    {
        return $this->proxyRequest(request(), 'pickup-points');
    }
}