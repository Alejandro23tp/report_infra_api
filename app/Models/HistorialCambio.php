<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialCambio extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporte_id',
        'usuario_id',
        'estado_anterior',
        'estado_nuevo',
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
