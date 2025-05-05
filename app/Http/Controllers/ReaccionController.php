<?php

namespace App\Http\Controllers;

use App\Models\Reaccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReaccionController extends Controller
{
    public function toggle(Request $request)
    {
        try {
            $request->validate([
                'reporte_id' => 'required|exists:reportes,id',
                'tipo_reaccion' => 'required|integer|min:1|max:5',
                'usuario_id' => 'required|exists:usuario,id'
            ]);

            $reaccion = Reaccion::where('usuario_id', $request->usuario_id)
                ->where('reporte_id', $request->reporte_id)
                ->first();

            if ($reaccion) {
                if ($reaccion->tipo_reaccion == $request->tipo_reaccion) {
                    $reaccion->delete();
                    return response()->json(['message' => 'Reacción eliminada']);
                }
                $reaccion->tipo_reaccion = $request->tipo_reaccion;
                $reaccion->save();
                return response()->json(['message' => 'Reacción actualizada']);
            }

            Reaccion::create([
                'usuario_id' => $request->usuario_id,
                'reporte_id' => $request->reporte_id,
                'tipo_reaccion' => $request->tipo_reaccion
            ]);

            return response()->json(['message' => 'Reacción agregada']);
        } catch (\Exception $e) {
            Log::error('Error en toggle reacción: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getReacciones($reporteId)
    {
        try {
            $reacciones = Reaccion::where('reporte_id', $reporteId)
                ->with('usuario:id,nombre')
                ->get();

            $reaccionesPorTipo = [];
            foreach ($reacciones->groupBy('tipo_reaccion') as $tipo => $grupo) {
                $reaccionesPorTipo[] = [
                    'tipo' => $tipo,
                    'count' => $grupo->count(),
                    'usuarios' => $grupo->map(function ($reaccion) {
                        return [
                            'id' => $reaccion->usuario->id,
                            'nombre' => $reaccion->usuario->nombre
                        ];
                    })->values()->toArray()
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => $reaccionesPorTipo
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getReacciones: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
