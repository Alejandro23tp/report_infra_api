<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeguimientoReporte extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporte_id',
        'usuario_id',
        'comentario',
    ];

    public function reporte()
    {
        return $this->belongsTo(Reporte::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

}
