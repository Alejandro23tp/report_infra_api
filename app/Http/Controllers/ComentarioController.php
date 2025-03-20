<?php

namespace App\Http\Controllers;

use App\Models\Comentario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ComentarioController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Log de datos recibidos
            Log::info('Datos recibidos:', $request->all());

            $validated = $request->validate([
                'reporte_id' => 'required|exists:reportes,id',
                'contenido' => 'required|string|max:1000',
                'padre_id' => 'nullable|exists:comentarios,id',
                'usuario_id' => 'required|exists:users,id'
            ]);

            // Log de datos validados
            Log::info('Datos validados:', $validated);

            $comentario = Comentario::create($validated);

            $comentarioCargado = $comentario->load('usuario:id,nombre');

            return response()->json([
                'status' => 'success',
                'message' => 'Comentario creado exitosamente',
                'comentario' => $comentarioCargado
            ]);

        } catch (\Exception $e) {
            Log::error('Error en store comentario: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function getComentarios($reporteId)
    {
        try {
            $comentarios = Comentario::where('reporte_id', $reporteId)
                ->whereNull('padre_id')
                ->with(['usuario:id,nombre', 'respuestas.usuario:id,nombre'])  // Cambiar 'name' por 'nombre'
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($comentario) {
                    return [
                        'id' => $comentario->id,
                        'contenido' => $comentario->contenido,
                        'usuario' => [
                            'id' => $comentario->usuario->id,
                            'nombre' => $comentario->usuario->nombre  // Cambiar 'name' por 'nombre'
                        ],
                        'created_at' => $comentario->created_at,
                        'respuestas' => $comentario->respuestas->map(function($respuesta) {
                            return [
                                'id' => $respuesta->id,
                                'contenido' => $respuesta->contenido,
                                'usuario' => [
                                    'id' => $respuesta->usuario->id,
                                    'nombre' => $respuesta->usuario->nombre  // Cambiar 'name' por 'nombre'
                                ],
                                'created_at' => $respuesta->created_at
                            ];
                        })->values()->toArray()
                    ];
                })
                ->values()
                ->toArray();

            return response()->json([
                'status' => 'success',
                'data' => $comentarios
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getComentarios: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $comentario = Comentario::findOrFail($id);
        $comentario->delete();
        return response()->json(['message' => 'Comentario eliminado']);
    }
}
