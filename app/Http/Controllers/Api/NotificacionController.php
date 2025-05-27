<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SuscriptorNotificacion;
use App\Models\Reporte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class NotificacionController extends Controller
{
    /**
     * Suscribir un correo para recibir notificaciones
     */
    public function suscribir(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'nombre' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $suscriptor = SuscriptorNotificacion::updateOrCreate(
                ['email' => $request->email],
                [
                    'nombre' => $request->nombre ?? null,
                    'activo' => true
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Suscripción exitosa',
                'data' => $suscriptor
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al suscribir: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }


    /**
     * Cancelar suscripción de notificaciones
     */
    public function cancelarSuscripcion($email)
    {
        try {
            $suscriptor = SuscriptorNotificacion::where('email', $email)->first();

            if (!$suscriptor) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la suscripción con el correo proporcionado'
                ], 404);
            }

            $suscriptor->update(['activo' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Suscripción cancelada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al cancelar suscripción: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtener lista de suscriptores (solo para administradores)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listarSuscriptores()
    {
        try {
            $suscriptores = SuscriptorNotificacion::orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $suscriptores
            ]);

        } catch (\Exception $e) {
            Log::error('Error al listar suscriptores: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la lista de suscriptores',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Enviar notificación de nuevo reporte a los suscriptores
     */
    public function notificarNuevoReporte(Reporte $reporte)
    {
        // Obtener todos los suscriptores activos
        $suscriptores = SuscriptorNotificacion::where('activo', true)->get();
        
        if ($suscriptores->isEmpty()) {
            Log::info('No hay suscriptores activos para notificar');
            return false;
        }

        $client = new Client([
            'timeout' => 30, // Tiempo de espera de 30 segundos
            'connect_timeout' => 10, // Tiempo de conexión de 10 segundos
        ]);

        $enviados = 0;
        $errores = [];
        $batchSize = 10; // Número de correos a enviar por lote
        $suscriptoresChunks = $suscriptores->chunk($batchSize);
        
        // Preparar el contenido del correo (común para todos los destinatarios)
        $asunto = "Nuevo Reporte #{$reporte->id} - {$reporte->categoria->nombre}";
        $urgencia = ucfirst($reporte->urgencia);
        
        $baseHtmlContent = "
            <h2>Nuevo Reporte #{$reporte->id}</h2>
            <p>Se ha registrado un nuevo reporte en el sistema.</p>
            <p><strong>Categoría:</strong> {$reporte->categoria->nombre}</p>
            <p><strong>Descripción:</strong> {$reporte->descripcion}</p>
            <p><strong>Nivel de urgencia:</strong> {$urgencia}</p>
            <p><strong>Fecha de registro:</strong> {$reporte->created_at->format('d/m/Y H:i')}</p>
            <p>Puedes ver más detalles en la plataforma.</p>
            <p>---</p>
            <p>Este es un un mensaje automático, por favor no responder a este correo.</p>
        ";

        // Procesar los suscriptores en lotes
        foreach ($suscriptoresChunks as $chunk) {
            $promises = [];
            
            foreach ($chunk as $suscriptor) {
                try {
                    $promises[] = $client->postAsync('https://api.brevo.com/v3/smtp/email', [
                        'headers' => [
                            'accept' => 'application/json',
                            'api-key' => env('BREVO_API_KEY'),
                            'content-type' => 'application/json',
                        ],
                        'json' => [
                            'sender' => [
                                'name' => 'Sistema de Reportes',
                                'email' => env('MAIL_FROM_ADDRESS')
                            ],
                            'to' => [
                                [
                                    'email' => $suscriptor->email,
                                    'name' => $suscriptor->nombre ?? 'Usuario'
                                ]
                            ],
                            'subject' => $asunto,
                            'htmlContent' => $baseHtmlContent
                        ]
                    ])->then(
                        function ($response) use ($suscriptor, &$enviados) {
                            $enviados++;
                            Log::info("Notificación enviada a: {$suscriptor->email}");
                            return true;
                        },
                        function ($e) use ($suscriptor, &$errores) {
                            $errorMsg = "Error al enviar a {$suscriptor->email}: " . $e->getMessage();
                            Log::error($errorMsg);
                            $errores[] = $errorMsg;
                            return false;
                        }
                    );
                } catch (\Exception $e) {
                    $errorMsg = "Error al preparar notificación para {$suscriptor->email}: " . $e->getMessage();
                    Log::error($errorMsg);
                    $errores[] = $errorMsg;
                }
            }

            // Esperar a que se completen todas las promesas del lote actual
            if (!empty($promises)) {
                try {
                    \GuzzleHttp\Promise\Utils::settle($promises)->wait();
                    // Pequeña pausa entre lotes para no saturar la API
                    if (count($suscriptoresChunks) > 1) {
                        sleep(1);
                    }
                } catch (\Exception $e) {
                    Log::error("Error en el lote de envíos: " . $e->getMessage());
                }
            }
        }

        // Registrar resumen
        $resumen = "Notificaciones enviadas: $enviados de {$suscriptores->count()}. Errores: " . count($errores);
        if (!empty($errores)) {
            Log::warning("Resumen de errores en notificaciones: " . implode(" | ", array_slice($errores, 0, 5)) . 
                        (count($errores) > 5 ? "... y " . (count($errores) - 5) . " más" : ""));
        }
        Log::info($resumen);

        return $enviados > 0;
    }
}
