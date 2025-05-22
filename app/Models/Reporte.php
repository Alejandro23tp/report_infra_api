<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Reaccion;

class Reporte extends Model
{
    protected $fillable = [
        'usuario_id',
        'categoria_id',
        'descripcion',
        'ubicacion',
        'imagen_url',
        'estado',
        'urgencia',
        'nota_admin'
    ];

    protected $casts = [
        'ubicacion' => 'array'
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

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
}
