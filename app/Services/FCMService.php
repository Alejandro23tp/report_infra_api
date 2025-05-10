<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Illuminate\Support\Facades\Log;

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
}