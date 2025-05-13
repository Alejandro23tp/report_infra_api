<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'es_autogenerada',
    ];

    // Relación con los reportes
    public function reportes()
    {
        return $this->hasMany(Reporte::class); // Relación uno a muchos
    }
}
