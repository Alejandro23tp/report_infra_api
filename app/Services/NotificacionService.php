<?php

namespace App\Services;

use App\Models\{User, Notificacion, Reporte};
use App\Services\FCMService; // Ensure FCMService is imported
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

    public function notificarCambioEstado(Reporte $reporte, string $estadoAnterior)
    {
        try {
            Log::info('Notificando cambio de estado', [
                'reporte_id' => $reporte->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $reporte->estado
            ]);

            // Notificar al creador del reporte
            $creador = User::find($reporte->usuario_id);
            if ($creador && $creador->fcm_token) {
                $messageId = uniqid('fcm_');
                $this->fcmService->sendNotification(
                    $creador->fcm_token,
                    'Estado de Reporte Actualizado',
                    "Tu reporte ha cambiado a estado: {$reporte->estado}",
                    $messageId
                );
            }

            // Notificar a otros usuarios cercanos
            $otrosUsuarios = User::where('id', '!=', $reporte->usuario_id)
                                ->whereNotNull('fcm_token')
                                ->where('fcm_token', '!=', '')
                                ->get();

            foreach ($otrosUsuarios as $usuario) {
                $messageId = uniqid('fcm_');
                $this->fcmService->sendNotification(
                    $usuario->fcm_token,
                    'Actualización de Reporte Cercano',
                    "Un reporte en tu área ha cambiado a estado: {$reporte->estado}",
                    $messageId
                );
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error en notificación de cambio de estado: ' . $e->getMessage());
            return false;
        }
    }
}
