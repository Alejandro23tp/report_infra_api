<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comentario extends Model
{
    use HasFactory;

    protected $table = 'comentarios';

    protected $fillable = [
        'usuario_id',
        'reporte_id',
        'contenido',
        'padre_id' // Para comentarios anidados
    ];

    protected $casts = [
        'usuario_id' => 'integer',
        'reporte_id' => 'integer',
        'padre_id' => 'integer'
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function reporte()
    {
        return $this->belongsTo(Reporte::class, 'reporte_id');
    }

    public function respuestas()
    {
        return $this->hasMany(Comentario::class, 'padre_id');
    }
}
