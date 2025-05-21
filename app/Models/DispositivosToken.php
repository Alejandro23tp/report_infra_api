<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DispositivosToken extends Model
{
    use HasFactory;

    protected $table = 'dispositivos_tokens';

    protected $fillable = [
        'usuario_id',
        'fcm_token',
        'dispositivo_id',
        'dispositivo_nombre',
        'ultimo_uso'
    ];

    protected $casts = [
        'ultimo_uso' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
