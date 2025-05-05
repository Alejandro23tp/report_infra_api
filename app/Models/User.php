<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'usuario';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'email',
        'password',
        'cedula',
        'direccion',
        'rol',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'nombre' => $this->nombre,
            'email' => $this->email,
            'rol' => $this->rol,
            'cedula' => $this->cedula
        ];
    }

    /**
     * Get the user's reactions.
     */
    public function reacciones()
    {
        return $this->hasMany(Reaccion::class, 'usuario_id');  // Cambiar 'user_id' a 'usuario_id'
    }

    /**
     * Get the user's comments.
     */
    public function comentarios()
    {
        return $this->hasMany(Comentario::class, 'usuario_id');  // Cambiar 'user_id' a 'usuario_id'
    }

    /**
     * Get the user's reports.
     */
    public function reportes()
    {
        return $this->hasMany(Reporte::class, 'usuario_id');  // Cambiar 'user_id' a 'usuario_id'
    }
}

