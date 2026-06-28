<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Sms;

class SmsService
{
    /**
     * Send SMS using WhosMS API
     *
     * @param string $phoneNumber
     * @param string $message
     * @param string|null $sender
     * @return array
     */
    public function sendViaWhosms($phoneNumber, $message, $sender = null)
    {
        try {
            // Get configuration
            $user = env('SMS_API_USER', 'userultau');
            $pass = env('SMS_API_PASS', 'parolata');
            $senderName = $sender ?: env('SMS_SENDER_ID', 'ClubBiliard');
            
            // Format phone number
            $phoneNumber = $this->formatPhoneNumber($phoneNumber);
            
            // Prepare URL for sending the SMS
            $url = "http://www.whosms.ro/send.php?";
            $url .= "user=" . $user;
            $url .= "&pass=" . $pass;
            $url .= "&dela=" . $senderName;
            $url .= "&catre=" . $phoneNumber;
            $url .= "&mesaj=" . urlencode($message);
            $url .= "&json=1"; // Request JSON response
            
            // Send request and get response
            $response = file_get_contents($url);
            
            // Parse JSON response
            $result = json_decode($response, true);
            
            // Log response
            Log::info('WhosMS API response', [
                'phone' => $phoneNumber,
                'response' => $result
            ]);
            
            // Check if SMS was sent successfully
            if ($result && isset($result['status']) && $result['status'] == '1') {
                return [
                    'success' => true,
                    'details' => [
                        'id' => $result['id'] ?? null,
                        'parts' => $result['parti'] ?? null,
                        'cost' => $result['cost'] ?? null
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => isset($result['mesaj']) ? $result['mesaj'] : 'Unknown error'
                ];
            }
        } catch (\Exception $e) {
            Log::error('WhosMS API error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send SMS using MSGHub API
     *
     * @param string $phoneNumber
     * @param string $message
     * @return array
     */
    public function sendViaMsghub($phoneNumber, $message)
    {
        try {
            // Get configuration
            $api_endpoint = env('MSGHUB_API_ENDPOINT', 'https://api-test.msghub.cloud/send');
            $api_key = env('MSGHUB_API_KEY', '');
            $api_secret = env('MSGHUB_API_SECRET', '');
            $service_id = env('MSGHUB_SERVICE_ID', '2219');
            $sc = env('MSGHUB_SC', '3737');
            
            // Format phone number
            $phoneNumber = $this->formatPhoneNumber($phoneNumber);
            
            // Prepare data for API request
            $data = [
                'msisdn'      => $phoneNumber,
                'sc'          => $sc,
                'text'        => $message,
                'service_id'  => $service_id,
            ];
            
            // Convert data to JSON
            $data_json = json_encode($data);
            
            // Create signature
            $signature = hash_hmac('sha512', $data_json, $api_secret);
            
            // Set headers
            $headers = [
                "Content-Type: application/json",
                "x-api-key: {$api_key}",
                "x-api-sign: {$signature}",
                "Expect: ",
            ];
            
            // Initialize cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_endpoint);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
            
            // Execute cURL request
            $response = curl_exec($ch);
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Close cURL
            curl_close($ch);
            
            // Parse JSON response
            $result = json_decode($response, true);
            
            // Log the response
            Log::info('MSGHub API response', [
                'phone' => $phoneNumber,
                'response' => $result,
                'status_code' => $status_code
            ]);
            
            // Check if SMS was sent successfully (based on the response structure)
            if ($result && $status_code == 200 && isset($result['success']) && $result['success']) {
                return [
                    'success' => true,
                    'details' => $result
                ];
            } else {
                return [
                    'success' => false,
                    'error' => isset($result['message']) ? $result['message'] : 'Unknown error'
                ];
            }
        } catch (\Exception $e) {
            Log::error('MSGHub API error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Format phone number to the required format
     *
     * @param string $phoneNumber
     * @return string
     */
    private function formatPhoneNumber($phoneNumber)
    {
        // Remove spaces and special characters
        $phoneNumber = preg_replace('/\s+/', '', $phoneNumber);
        $phoneNumber = str_replace(['+', '-', '(', ')', '.'], '', $phoneNumber);
        
        // If starts with 0, remove it and add country code
        if (substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = '4' . substr($phoneNumber, 1); // Add Romanian country code (40)
        }
        
        // Check if the phone number starts with country code, if not add it
        if (substr($phoneNumber, 0, 2) !== '40') {
            $phoneNumber = '40' . $phoneNumber;
        }
        
        return $phoneNumber;
    }
    
    /**
     * Send SMS using the configured provider
     *
     * @param string $phoneNumber
     * @param string $message
     * @param string|null $sender
     * @return array
     */
    public function send($phoneNumber, $message, $sender = null)
    {
        // Get the configured SMS provider or default to whosms
        $provider = env('SMS_PROVIDER', 'whosms');
        
        if ($provider === 'msghub') {
            return $this->sendViaMsghub($phoneNumber, $message);
        } else {
            return $this->sendViaWhosms($phoneNumber, $message, $sender);
        }
    }
    
    /**
     * Save SMS record to database
     *
     * @param int $orderId
     * @param string $phoneNumber
     * @param string $message
     * @param float $cost
     * @param int $status
     * @return bool
     */
    public function saveSmsRecord($orderId, $phoneNumber, $message, $cost = 0, $status = 1)
    {
        try {
            // Create a new SMS record
            Sms::create([
                'idcomanda' => $orderId,
                'telefon' => $phoneNumber,
                'mesaj' => $message,
                'status' => $status,
                'cost' => $cost,
                'data' => now(),
                'data_exp' => now(),
                'idprimit' => 0,
                'idcomanda_ext' => $orderId
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error saving SMS record: ' . $e->getMessage());
            return false;
        }
    }
}