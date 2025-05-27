<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuscriptorNotificacion extends Model
{
    protected $table = 'suscriptores_notificaciones';
    
    protected $fillable = [
        'email',
        'nombre',
        'activo'
    ];
    
    protected $casts = [
        'activo' => 'boolean',
        'fecha_suscripcion' => 'datetime',
    ];
}
