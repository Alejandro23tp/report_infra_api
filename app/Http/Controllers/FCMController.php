<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\DispositivosToken;
use App\Services\FCMService;
use Illuminate\Support\Facades\Log;

class FCMController extends Controller
{
    /**
     * Registra un token FCM para el dispositivo del usuario actual
     */
    public function subscribe(Request $request)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'token' => 'required|string',
                'dispositivo_id' => 'required|string' // Añadir validación para dispositivo_id
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token FCM y dispositivo_id son requeridos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Guardar o actualizar el token en la tabla de dispositivos
            DispositivosToken::updateOrCreate(
                [
                    'usuario_id' => $user->id,
                    'dispositivo_id' => $request->dispositivo_id // Usar dispositivo_id como parte de la clave
                ],
                [
                    'fcm_token' => $request->token, // Mover fcm_token a los valores actualizables
                    'ultimo_uso' => now(),
                    'dispositivo_nombre' => $request->header('User-Agent', 'desconocido')
                ]
            );

            Log::info('Token FCM registrado', [
                'usuario_id' => $user->id,
                'dispositivo_id' => $request->dispositivo_id,
                'token' => substr($request->token, 0, 10) . '...'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token FCM registrado correctamente'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al registrar token FCM: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar token FCM',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el estado de las notificaciones para el usuario actual
     */
    public function checkNotificationStatus(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Validar que el dispositivo_id esté presente
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'dispositivo_id' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El ID del dispositivo es requerido',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar si existe un token para este usuario Y este dispositivo específico
            $hasFcmToken = DispositivosToken::where('usuario_id', $user->id)
                                          ->where('dispositivo_id', $request->dispositivo_id)
                                          ->exists();

            return response()->json([
                'success' => true,
                'notifications_enabled' => true, // Siempre habilitado en el backend
                'fcm_token' => $hasFcmToken
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener estado de notificaciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estado de notificaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function unsubscribe(Request $request)
    {
        // Validar que el dispositivo_id esté presente
        $request->validate([
            'dispositivo_id' => 'required|string'
        ]);

        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        try {
            // Eliminar el token para este dispositivo
            $deleted = DispositivosToken::where('usuario_id', Auth::id())
                ->where('dispositivo_id', $request->dispositivo_id)
                ->delete();
                
            if ($deleted) {
                info('Token FCM eliminado para el dispositivo: ' . $request->dispositivo_id);
                return response()->json(['message' => 'Notificaciones desactivadas para este dispositivo']);
            } else {
                return response()->json(['message' => 'No se encontró registro para este dispositivo'], 404);
            }
        } catch (\Exception $e) {
            info('Error al eliminar token FCM: ' . $e->getMessage());
            return response()->json(['error' => 'Error al desactivar notificaciones'], 500);
        }
    }
    
    public function listDevices()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $dispositivos = DispositivosToken::where('usuario_id', $user->id)
                ->select(['id', 'dispositivo_id', 'dispositivo_nombre', 'ultimo_uso'])
                ->orderBy('ultimo_uso', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'dispositivos' => $dispositivos
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al listar dispositivos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al listar dispositivos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
}
