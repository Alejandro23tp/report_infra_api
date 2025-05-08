<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class FCMController extends Controller
{
    public function subscribe(Request $request)
    {
        // Validar que el token esté presente y sea una cadena
        $request->validate([
            'token' => 'required|string'
        ]);

        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Más logging para debug
        info('Auth user ID: ' . Auth::id());

        // Verificar si el token ya está registrado para otro usuario
        $existingUser = User::where('fcm_token', $request->input('token'))
                            ->where('id', '!=', Auth::id())
                            ->first();
        if ($existingUser) {
            info('Token FCM ya registrado para otro usuario: ' . $existingUser->id);
            return response()->json(['error' => 'Token FCM ya está registrado para otro usuario'], 409);
        }

        // Verificar si el usuario ya tiene el mismo token
        $currentUser = User::find(Auth::id());
        if ($currentUser->fcm_token === $request->input('token')) {
            return response()->json(['message' => 'Token FCM ya está registrado para este usuario']);
        }

        try {
            // Intentar actualizar el token en la base de datos
            $updated = User::where('id', Auth::id())->update([
                'fcm_token' => $request->input('token')
            ]);

            if (!$updated) {
                info('No se pudo actualizar el token FCM para el usuario: ' . Auth::id());
                return response()->json(['error' => 'No se pudo guardar el token'], 500);
            }

            info('Token FCM guardado exitosamente para el usuario: ' . Auth::id());
            return response()->json(['message' => 'Token FCM guardado']);
        } catch (\Exception $e) {
            info('Error updating FCM token: ' . $e->getMessage());
            return response()->json(['error' => 'Error al guardar el token'], 500);
        }
    }
}