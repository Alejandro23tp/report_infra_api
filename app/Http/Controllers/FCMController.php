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
        $request->validate([
            'token' => 'required|string'
        ]);

        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }

        // Más logging para debug
        info('Auth user ID: ' . Auth::id());
        
        try {
            // Intentar actualizar usando el modelo directamente
            User::where('id', Auth::id())->update([
                'fcm_token' => $request->input('token')
            ]);
            
            return response()->json(['message' => 'Token FCM guardado']);
        } catch (\Exception $e) {
            info('Error updating FCM token: ' . $e->getMessage());
            return response()->json(['error' => 'Error al guardar el token'], 500);
        }
    }
}
