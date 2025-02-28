<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    //
    Use HasFactory;

    protected $fillable = [
        'nombre',
        'correo',
        'cedula',
        'direccion',
        'contraseña',
        'rol',
    ];

    protected $hidden = [
        'contraseña',
    ];
}
