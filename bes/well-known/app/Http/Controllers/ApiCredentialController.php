<?php
namespace App\Http\Controllers;

use App\Models\ApiCredential;
use App\Models\MessageTemplate;
use Illuminate\Http\Request;

class ApiCredentialController extends Controller
{
    public function index()
    {
        // Fetch all credentials from DB
        $credentials = ApiCredential::all()->groupBy('service_name')->map(fn($items) => $items->keyBy('data_key'));

        // Define default keys with fallback to .env
        $defaultServices = [
            'sameday' => [
                'username' => env('SAMEDAY_USERNAME', ''),
                'password' => env('SAMEDAY_PASSWORD', ''),
                'testing' => env('SAMEDAY_TESTING', ''),
                'host' => env('SAMEDAY_HOST', ''),
                'auth_user' => env('SAMEDAY_AUTH_USER', ''),
                'auth_password' => env('SAMEDAY_AUTH_PASSWORD', ''),
                'host_url' => env('SAMEDAY_HOST_URL', ''),
            ],
            'fancourier' => [
                'username' => env('FANCOURIER_USERNAME', ''),
                'password' => env('FANCOURIER_PASSWORD', ''),
                'client_id' => env('FANCOURIER_CLIENT_ID', ''),
                'api_url' => env('FANCOURIER_API_URL', ''),
            ],
            'smartbill' => [
                'api_url' => env('SMARTBILL_API_URL', ''),
                'api_key' => env('SMARTBILL_API_KEY', ''),
                'user_email' => env('SMARTBILL_USER_EMAIL', ''),
            ],
			'sms' => [
				'api_key' => env('SMS_API_KEY', ''),
				'api_url' => env('SMS_API_URL', ''),
			],
            'orders' => [
                'created_at_offset_hours' => '0',
            ],
            // add other services here if needed
        ];

        // Merge DB values with defaults (DB overrides .env)
        $services = [];
        foreach ($defaultServices as $service => $fields) {
            foreach ($fields as $key => $defaultValue) {
                // If DB value exists, use it; else fallback to default (.env)
                $services[$service][$key] = isset($credentials[$service][$key])
                    ? $credentials[$service][$key]
                    : (object)['data_value' => $defaultValue];
            }
        }

        // Message templates for WhatsApp and SMS
        $whatsappTemplates = MessageTemplate::allWithDefaults('whatsapp');
        $smsTemplates = MessageTemplate::allWithDefaults('sms');

        return view('api_credentials.index', [
            'services'          => $services,
            'whatsappTemplates' => $whatsappTemplates,
            'smsTemplates'      => $smsTemplates,
        ]);
    }

    public function updateAll(Request $request)
    {
        // Save API credentials
        foreach ($request->except('_token', 'templates') as $service => $fields) {
            foreach ($fields as $key => $value) {
                ApiCredential::updateOrCreate(
                    ['service_name' => $service, 'data_key' => $key],
                    ['data_value' => $value ?? '']
                );
            }
        }

        // Save message templates (WhatsApp and SMS)
        $whatsappTemplates = $request->input('whatsapp_templates', []);
        foreach ($whatsappTemplates as $code => $body) {
            MessageTemplate::updateOrCreate(
                ['channel' => 'whatsapp', 'code' => $code],
                [
                    'name'     => ucwords(str_replace('_', ' ', $code)),
                    'template' => $body ?? '',
                ]
            );
        }

        $smsTemplates = $request->input('sms_templates', []);
        foreach ($smsTemplates as $code => $body) {
            MessageTemplate::updateOrCreate(
                ['channel' => 'sms', 'code' => $code],
                [
                    'name'     => ucwords(str_replace('_', ' ', $code)),
                    'template' => $body ?? '',
                ]
            );
        }

        return back()->with('success', 'Settings updated successfully.');
    }
}