<?php

namespace App\Http\Controllers;

use App\Models\Reporte;
use App\Models\SeguimientoReporte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReporteHistorialController extends Controller
{
    /**
     * Obtener el historial de cambios de un reporte
     *
     * @param int $reporteId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($reporteId)
    {
        $reporte = Reporte::findOrFail($reporteId);
        
        // Verificar que el usuario autenticado tenga acceso al reporte
        $usuario = Auth::user();
        if ($reporte->usuario_id !== $usuario->id && 
            $reporte->asignado_a !== $usuario->id &&
            $usuario->rol !== 'admin') {
            return response()->json([
                'message' => 'No tienes permiso para ver el historial de este reporte.'
            ], 403);
        }
        
        $historial = $reporte->seguimientos()
            ->with('usuario:id,nombre,email')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($seguimiento) {
                $data = [
                    'id' => $seguimiento->id,
                    'tipo' => $seguimiento->tipo,
                    'usuario' => $seguimiento->usuario ? [
                        'id' => $seguimiento->usuario->id,
                        'nombre' => $seguimiento->usuario->nombre,
                        'email' => $seguimiento->usuario->email,
                    ] : null,
                    'fecha' => $seguimiento->created_at->toDateTimeString(),
                    'fecha_humano' => $seguimiento->created_at->diffForHumans(),
                ];
                
                // Si es un cambio de estado, decodificar el JSON
                if ($seguimiento->tipo === 'cambio_estado' && $seguimiento->comentario) {
                    $data['cambios'] = json_decode($seguimiento->comentario, true);
                } else {
                    $data['comentario'] = $seguimiento->comentario;
                }
                
                return $data;
            });
            
        return response()->json([
            'reporte_id' => $reporte->id,
            'historial' => $historial
        ]);
    }
    
    /**
     * Agregar un comentario al historial del reporte
     *
     * @param Request $request
     * @param int $reporteId
     * @return \Illuminate\Http\JsonResponse
     */
    public function agregarComentario(Request $request, $reporteId)
    {
        $request->validate([
            'comentario' => 'required|string|min:5|max:2000',
        ]);
        
        $reporte = Reporte::findOrFail($reporteId);
        $usuario = Auth::user();
        
        // Verificar que el usuario autenticado tenga acceso al reporte
        if ($reporte->usuario_id !== $usuario->id && 
            $reporte->asignado_a !== $usuario->id &&
            $usuario->rol !== 'admin') {
            return response()->json([
                'message' => 'No tienes permiso para agregar comentarios a este reporte.'
            ], 403);
        }
        
        $seguimiento = new SeguimientoReporte([
            'usuario_id' => Auth::id(),
            'tipo' => 'comentario',
            'comentario' => $request->comentario,
        ]);
        
        $reporte->seguimientos()->save($seguimiento);
        
        return response()->json([
            'message' => 'Comentario agregado correctamente',
            'historial' => $seguimiento->load('usuario:id,nombre,apellido,email')
        ], 201);
    }
}
