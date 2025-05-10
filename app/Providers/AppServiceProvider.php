<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\NotificacionService;
use App\Services\FCMService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NotificacionService::class, function ($app) {
            return new NotificacionService($app->make(FCMService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
