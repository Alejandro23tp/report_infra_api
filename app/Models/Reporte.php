<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Reaccion;
use App\Traits\TracksReportChanges;

class Reporte extends Model
{
    use TracksReportChanges;
    
    protected $fillable = [
        'usuario_id',
        'categoria_id',
        'descripcion',
        'ubicacion',
        'imagen_url',
        'estado',
        'urgencia',
        'nota_admin',
        'asignado_a'
    ];

    protected $casts = [
        'ubicacion' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s'
    ];
    
    /**
     * Prepara una fecha para la serialización de arrays / JSON.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Obtiene el usuario que creó el reporte.
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
    
    /**
     * Obtiene el usuario al que está asignado el reporte.
     */
    public function asignadoA()
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    /**
     * Obtiene la categoría del reporte.
     */
    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Obtiene las reacciones del reporte.
     */
    public function reacciones(): HasMany
    {
        return $this->hasMany(Reaccion::class, 'reporte_id');
    }

    /**
     * Obtiene los comentarios del reporte (solo comentarios principales, no respuestas).
     */
    public function comentarios(): HasMany
    {
        return $this->hasMany(Comentario::class, 'reporte_id')
            ->whereNull('padre_id');
    }

    /**
     * Obtiene todos los comentarios del reporte, incluyendo respuestas.
     */
    public function todosLosComentarios(): HasMany
    {
        return $this->hasMany(Comentario::class, 'reporte_id');
    }
    
    /**
     * Obtiene el historial de cambios del reporte.
     */
    public function historial()
    {
        return $this->hasMany(SeguimientoReporte::class, 'reporte_id')
            ->orderBy('created_at', 'desc');
    }
}
