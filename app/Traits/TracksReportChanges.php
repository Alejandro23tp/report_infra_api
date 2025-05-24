<?php

namespace App\Traits;

use App\Models\SeguimientoReporte;
use Illuminate\Support\Facades\Auth;

/**
 * Trait para manejar el seguimiento de cambios en los reportes
 */
trait TracksReportChanges
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    protected static function bootTracksReportChanges()
    {
        static::updated(function ($model) {
            $model->trackChanges();
        });
    }

    /**
     * Track changes to the model.
     *
     * @return void
     */
    protected function trackChanges()
    {
        $original = $this->getOriginal();
        $changes = [];
        
        // Campos que queremos rastrear
        $trackedFields = [
            'estado',
            'urgencia',
            'asignado_a',
            'responsable_id',
            'categoria_id',
            'titulo',
            'descripcion'
        ];
        
        foreach ($trackedFields as $field) {
            if (array_key_exists($field, $original) && $this->$field != $original[$field]) {
                $changes[] = [
                    'campo' => $field,
                    'valor_anterior' => $original[$field],
                    'nuevo_valor' => $this->$field
                ];
            }
        }
        
        if (empty($changes)) {
            return;
        }
        
        $usuarioId = Auth::id() ?? 1; // Usar el ID del usuario autenticado o 1 (sistema) si no hay usuario
        
        // Crear el registro de seguimiento
        $this->seguimientos()->create([
            'usuario_id' => $usuarioId,
            'tipo' => 'cambio_estado',
            'comentario' => json_encode([
                'cambios' => $changes,
                'nota' => $this->nota_admin ?? 'Sin notas adicionales'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    /**
     * Get all of the seguimientos for the reporte.
     */
    public function seguimientos()
    {
        return $this->hasMany(\App\Models\SeguimientoReporte::class, 'reporte_id');
    }
}
