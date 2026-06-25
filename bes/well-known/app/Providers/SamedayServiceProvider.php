<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Sameday\SamedayClient;
use Sameday\HttpClients\SamedayCurlHttpClient;
use Sameday\HttpClients\SamedayCurl;

class SamedayServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('sameday', function ($app) {
            $curl = new SamedayCurl();
            $httpClient = new SamedayCurlHttpClient($curl);
            
            // होस्ट URL को तीसरे पैरामीटर के रूप में पास करें
            return new SamedayClient(
                config('services.sameday.username'),
                config('services.sameday.password'),
                config('services.sameday.host', 'https://api.sameday.ro'),
                null, // platformName
                null, // platformVersion
                $httpClient
            );
        });
    }
}