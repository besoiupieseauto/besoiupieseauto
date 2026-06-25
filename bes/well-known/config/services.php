<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    
    
	// config/services.php फाइल में जोड़ें
	'fancourier' => [
		'username' => env('FANCOURIER_USERNAME'),
		'password' => env('FANCOURIER_PASSWORD'),
		'client_id' => env('FANCOURIER_CLIENT_ID'),
		'api_url' => env('FANCOURIER_API_URL', 'https://api.fancourier.ro'),
	],


	'sameday' => [
        'username' => env('SAMEDAY_USERNAME'),
        'password' => env('SAMEDAY_PASSWORD'),
        'host' => env('SAMEDAY_HOST', 'https://api.sameday.ro'),
        'testing' => env('SAMEDAY_TESTING', true)
    ],

	'smartbill' => [
		'api_url' => env('SMARTBILL_API_URL', 'https://api.smartbill.ro'),
		'api_key' => env('SMARTBILL_API_KEY'),
		'user_email' => env('SMARTBILL_USER_EMAIL'),
	],

	'sms' => [
		'api_key' => env('SMS_API_KEY'),
		'api_url' => env('SMS_API_URL', 'https://api.smsapi.ro/sms.do'),
	],
];
