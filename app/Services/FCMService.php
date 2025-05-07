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
        $factory = (new Factory)->withServiceAccount(config('firebase.projects.app.credentials'));
        $this->messaging = $factory->createMessaging();
    }

    public function sendNotification($token, $title, $body)
    {
        $message = CloudMessage::withTarget('token', $token)
            ->withNotification([
                'title' => $title,
                'body' => $body,
            ]);

        try {
            $this->messaging->send($message);
            return true;
        } catch (\Exception $e) {
            Log::error('Error al enviar notificación: ' . $e->getMessage());
            return false;
        }
    }
}