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

    /**
     * Notifica a los usuarios sobre un nuevo reporte
     */
    public function notificarNuevoReporte(Reporte $reporte)
    {
        try {
            Log::info('Iniciando notificación de nuevo reporte', ['reporte_id' => $reporte->id]);

            // Notificar al creador
            if ($reporte->usuario_id) {
                $this->fcmService->sendToUser(
                    $reporte->usuario_id,
                    'Reporte Creado',
                    'Tu reporte ha sido creado exitosamente',
                    'reporte_nuevo_' . $reporte->id
                );
            }

            // Notificar a otros usuarios
            $otrosUsuarios = User::where('id', '!=', $reporte->usuario_id)
                                ->where('rol', 'usuario')
                                ->get();

            foreach ($otrosUsuarios as $usuario) {
                // Guardar la notificación en la base de datos
                Notificacion::create([
                    'usuario_id' => $usuario->id,
                    'reporte_id' => $reporte->id,
                    'titulo' => 'Nuevo Reporte en la Zona',
                    'mensaje' => 'Se ha reportado un nuevo problema en tu área',
                    'leido' => false
                ]);
                
                // Enviar notificación push a todos los dispositivos del usuario
                $this->fcmService->sendToUser(
                    $usuario->id,
                    'Nuevo Reporte en la Zona',
                    'Se ha reportado un nuevo problema en tu área',
                    'reporte_nuevo_' . $reporte->id
                );
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error en notificación: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Notifica a los usuarios sobre un cambio de estado en un reporte
     */
    public function notificarCambioEstado(Reporte $reporte, $estadoAnterior)
    {
        try {
            Log::info('Notificando cambio de estado', [
                'reporte_id' => $reporte->id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $reporte->estado
            ]);

            // Notificar al creador del reporte
            if ($reporte->usuario_id) {
                // Guardar notificación en BD
                Notificacion::create([
                    'usuario_id' => $reporte->usuario_id,
                    'reporte_id' => $reporte->id,
                    'titulo' => 'Actualización de Estado',
                    'mensaje' => "Tu reporte ha cambiado de estado: $estadoAnterior → {$reporte->estado}",
                    'leido' => false
                ]);
                
                // Enviar notificación push a todos los dispositivos del usuario
                $this->fcmService->sendToUser(
                    $reporte->usuario_id,
                    'Actualización de Estado',
                    "Tu reporte ha cambiado de estado: $estadoAnterior → {$reporte->estado}",
                    'reporte_estado_' . $reporte->id
                );
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error notificando cambio de estado: ' . $e->getMessage());
            return false;
        }
    }
}
