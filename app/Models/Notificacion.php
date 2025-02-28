<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    //

    use HasFactory;

    protected $fillable = [
        'usuario_id',
        'reporte_id',
        'titulo',
        'mensaje',
        'leido',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function reporte()
    {
        return $this->belongsTo(Reporte::class);
    }
}
