<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reaccion extends Model
{
    use HasFactory;

    protected $table = 'reacciones';
    
    protected $fillable = [
        'usuario_id',
        'reporte_id',
        'tipo_reaccion' // 1: like, 2: love, 3: angry, etc.
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function reporte()
    {
        return $this->belongsTo(Reporte::class, 'reporte_id');
    }
}
