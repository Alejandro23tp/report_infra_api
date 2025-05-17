<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        return $this->belongsTo(User::class, 'usuario_id'); // Cambiado a User
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }
}
