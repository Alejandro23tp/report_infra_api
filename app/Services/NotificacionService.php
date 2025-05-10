<?php

namespace App\Services;

use App\Models\{User, Notificacion, Reporte};
use Illuminate\Support\Facades\Log;

class NotificacionService
{
    protected $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    public function notificarNuevoReporte(Reporte $reporte)
    {
        try {
            Log::info('Iniciando notificación de nuevo reporte', ['reporte_id' => $reporte->id]);

            // Notificar al creador
            $creador = User::find($reporte->usuario_id);
            if ($creador && $creador->fcm_token) {
                $messageId = uniqid('fcm_');
                $this->fcmService->sendNotification(
                    $creador->fcm_token,
                    'Reporte Creado',
                    'Tu reporte ha sido creado exitosamente',
                    $messageId
                );
            }

            // Notificar a otros usuarios
            $otrosUsuarios = User::where('id', '!=', $reporte->usuario_id)
                                ->whereNotNull('fcm_token')
                                ->where('fcm_token', '!=', '')
                                ->get();

            foreach ($otrosUsuarios as $usuario) {
                $messageId = uniqid('fcm_');
                $this->fcmService->sendNotification(
                    $usuario->fcm_token,
                    'Nuevo Reporte en la Zona',
                    'Se ha reportado un nuevo problema en tu área',
                    $messageId
                );
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error en notificación: ' . $e->getMessage());
            return false;
        }
    }
}
