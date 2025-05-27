<?php

namespace App\Providers;

use App\Events\NuevoReporteCreado;
use App\Listeners\NotificarSuscriptoresNuevoReporte;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        NuevoReporteCreado::class => [
            NotificarSuscriptoresNuevoReporte::class,
        ],
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }


    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
