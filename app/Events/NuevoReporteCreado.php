<?php

namespace App\Events;

use App\Models\Reporte;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NuevoReporteCreado
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $reporte;

    /**
     * Create a new event instance.
     *
     * @param Reporte $reporte
     * @return void
     */
    public function __construct(Reporte $reporte)
    {
        $this->reporte = $reporte;
    }
}
