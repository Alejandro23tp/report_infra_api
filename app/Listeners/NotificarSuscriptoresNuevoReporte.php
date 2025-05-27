<?php

namespace App\Listeners;

use App\Events\NuevoReporteCreado;
use App\Http\Controllers\Api\NotificacionController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotificarSuscriptoresNuevoReporte implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the queued listener may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(NuevoReporteCreado $event): void
    {
        try {
            $notificacionController = new NotificacionController();
            $notificacionController->notificarNuevoReporte($event->reporte);
        } catch (\Exception $e) {
            Log::error('Error al enviar notificaciones de nuevo reporte: ' . $e->getMessage());
            // Re-lanzar la excepción para que se reintente si está en cola
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(NuevoReporteCreado $event, $exception): void
    {
        Log::error('Error al procesar notificación de nuevo reporte: ' . $exception->getMessage());
    }
}
