<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reporte extends Model
{
    use HasFactory;

    protected $fillable = [
        'usuario_id',
        'categoria_id',
        'descripcion',
        'ubicacion',
        'estado',
        'urgencia',
        'imagen_url',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
    
    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }
    
}
