<?php

namespace App\Providers;

use App\Services\FanCourier\FanCourierService;
use Illuminate\Support\ServiceProvider;

class FanCourierServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(FanCourierService::class, function ($app) {
            return new FanCourierService();
        });
    }

    public function boot()
    {
        //
    }
}