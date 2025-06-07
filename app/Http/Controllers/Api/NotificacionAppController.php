<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificacionAppController extends Controller
{
    /**
     * Obtener todas las notificaciones del usuario autenticado
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $notificaciones = Notificacion::where('usuario_id', $user->id)
            ->with('reporte') // Cargar la relación con el reporte
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $notificaciones
        ]);
    }

    /**
     * Marcar una notificación como leída
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function marcarComoLeida(Request $request, $id)
    {
        $user = Auth::user();
        
        $notificacion = Notificacion::where('id', $id)
            ->where('usuario_id', $user->id)
            ->first();
            
        if (!$notificacion) {
            return response()->json([
                'success' => false,
                'message' => 'Notificación no encontrada o no autorizada'
            ], 404);
        }
        
        if (!$notificacion->leido) {
            $notificacion->update(['leido' => true]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Notificación marcada como leída',
            'data' => $notificacion
        ]);
    }
    
    /**
     * Obtener el conteo de notificaciones no leídas
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function contarNoLeidas(Request $request)
    {
        $user = Auth::user();
        
        $count = Notificacion::where('usuario_id', $user->id)
            ->where('leido', false)
            ->count();
            
        return response()->json([
            'success' => true,
            'count' => $count
        ]);
    }
    
    /**
     * Marcar todas las notificaciones como leídas
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function marcarTodasComoLeidas(Request $request)
    {
        $user = Auth::user();
        
        $actualizadas = Notificacion::where('usuario_id', $user->id)
            ->where('leido', false)
            ->update(['leido' => true]);
            
        return response()->json([
            'success' => true,
            'message' => "$actualizadas notificaciones marcadas como leídas"
        ]);
    }
}
