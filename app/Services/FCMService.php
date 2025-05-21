<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Illuminate\Support\Facades\Log;
use App\Models\DispositivosToken;

class FCMService
{
    protected $messaging;

    public function __construct()
    {
        try {
            $factory = (new Factory)->withServiceAccount(config('firebase.projects.app.credentials'));
            $this->messaging = $factory->createMessaging();
        } catch (\Exception $e) {
            Log::error('Error inicializando FCM: ' . $e->getMessage());
        }
    }

    /**
     * Envía una notificación a un solo token FCM
     */
    public function sendNotification($token, $title, $body, $messageId = null)
    {
        try {
            info('Iniciando envío de notificación FCM');
            info('Token FCM: ' . $token);
            
            $messageId = $messageId ?? uniqid('fcm_');
            info('ID de mensaje: ' . $messageId);

            $message = CloudMessage::withTarget('token', $token)
                ->withNotification([
                    'title' => $title,
                    'body' => $body
                ])
                ->withData([
                    'messageId' => $messageId
                ]);

            $this->messaging->send($message);
            info('Notificación enviada exitosamente');
            return true;
        } catch (\Exception $e) {
            Log::error('Error enviando notificación FCM: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envía notificaciones a múltiples tokens FCM
     */
    public function sendMultipleNotifications($tokens, $title, $body, $messageId = null)
    {
        try {
            info('Iniciando envío de notificación FCM a múltiples dispositivos');
            info('Cantidad de tokens: ' . count($tokens));
            
            $messageId = $messageId ?? uniqid('fcm_');
            info('ID de mensaje: ' . $messageId);

            $successCount = 0;
            $failureCount = 0;

            foreach ($tokens as $token) {
                try {
                    $message = CloudMessage::withTarget('token', $token)
                        ->withNotification([
                            'title' => $title,
                            'body' => $body
                        ])
                        ->withData([
                            'messageId' => $messageId
                        ]);

                    $this->messaging->send($message);
                    $successCount++;
                    
                    // Actualizar último uso del token
                    DispositivosToken::where('fcm_token', $token)
                        ->update(['ultimo_uso' => now()]);
                } catch (\Exception $e) {
                    Log::error('Error enviando notificación a token: ' . $token . ' - ' . $e->getMessage());
                    $failureCount++;
                    
                    // Si el error es por token inválido, considerar eliminarlo
                    if (strpos($e->getMessage(), 'invalid-argument') !== false || 
                        strpos($e->getMessage(), 'registration-token-not-registered') !== false) {
                        DispositivosToken::where('fcm_token', $token)->delete();
                        Log::info('Token inválido eliminado: ' . $token);
                    }
                }
            }

            info("Notificaciones enviadas: $successCount éxitos, $failureCount fallos");
            return [
                'success' => $successCount > 0,
                'success_count' => $successCount,
                'failure_count' => $failureCount
            ];
        } catch (\Exception $e) {
            Log::error('Error general enviando notificaciones FCM: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envía notificaciones a todos los dispositivos de un usuario
     */
    public function sendToUser($userId, $title, $body, $messageId = null)
    {
        try {
            $tokens = DispositivosToken::where('usuario_id', $userId)
                ->pluck('fcm_token')
                ->toArray();
                
            if (empty($tokens)) {
                info('No hay tokens FCM para el usuario: ' . $userId);
                return false;
            }
            
            return $this->sendMultipleNotifications($tokens, $title, $body, $messageId);
        } catch (\Exception $e) {
            Log::error('Error enviando notificación a usuario: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envía notificaciones a todos los usuarios con un rol específico
     */
    public function sendToUsersWithRole($role, $title, $body, $messageId = null, $excludeUserId = null)
    {
        try {
            $query = DispositivosToken::join('usuario', 'dispositivos_tokens.usuario_id', '=', 'usuario.id')
                ->where('usuario.rol', $role);
                
            if ($excludeUserId) {
                $query->where('dispositivos_tokens.usuario_id', '!=', $excludeUserId);
            }
            
            $tokens = $query->pluck('dispositivos_tokens.fcm_token')->toArray();
            
            if (empty($tokens)) {
                info('No hay tokens FCM para usuarios con rol: ' . $role);
                return false;
            }
            
            return $this->sendMultipleNotifications($tokens, $title, $body, $messageId);
        } catch (\Exception $e) {
            Log::error('Error enviando notificación a usuarios con rol: ' . $e->getMessage());
            return false;
        }
    }
}