<?php

namespace App\Services;

use Fancourier\Fancourier;
use App\Models\ApiCredential;

class FanCourierService
{
    protected $clientId;
    protected $username;
    protected $password;
    protected $token;
    protected $fan;

    public function __construct()
    {
/*         $this->clientId = env('FAN_CLIENT_ID');
        $this->username = env('FAN_USERNAME');
        $this->password = env('FAN_PASSWORD'); */
        $this->clientId = $this->getCredential('fancourier', 'client_id');
        $this->username = $this->getCredential('fancourier', 'username');
        $this->password = $this->getCredential('fancourier', 'password');
        $this->token = cache()->get('fan_token', '');
        
        $this->fan = new Fancourier(
            $this->clientId,
            $this->username,
            $this->password,
            $this->token
        );
    }

    public function getClient()
    {
        // Update token if blank or expired
        $token = $this->fan->getToken();
        cache()->put('fan_token', $token, now()->addHours(23));
        
        return $this->fan;
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