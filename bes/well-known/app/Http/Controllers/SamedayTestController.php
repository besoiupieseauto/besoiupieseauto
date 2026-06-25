<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SamedayTestController extends Controller
{
    public function testDirectConnection()
    {
        // In development, return mock successful response
        if (app()->environment('local', 'development')) {
            return response()->json([
                'success' => true,
                'message' => 'Mock Sameday API connection successful',
                'data' => [
                    'token' => 'mock_development_token_'.time(),
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+1 day'))
                ]
            ]);
        }
        
        // In production, try the real connection
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.sameday.ro/api/client/auth/login');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'user' => config('sameday.auth_user', 'besoiupieseautoAPI'),
                'password' => config('sameday.auth_password', 'MXV/zuLmJg==')
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            
            return response()->json([
                'success' => !$error,
                'error' => $error,
                'response' => $response
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}