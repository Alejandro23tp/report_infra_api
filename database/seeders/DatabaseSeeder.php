<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'nombre' => 'Test User',
            'email' => 'test@example.com',
            'cedula' => '1234567890',
            'direccion' => 'Test Address',
            'password' => Hash::make('password'),
            'rol' => 'admin',
            'email_verified_at' => now(),
            'remember_token' => 'zWk5NG9MY7',
        ]);

        User::create([
            'nombre' => 'Alejandro',
            'email' => 'alejandrolias84@gmail.com',
            'cedula' => '2400321622',
            'direccion' => 'Libertad',
            'password' => Hash::make('1234567890'),
            'rol' => 'usuario',
            'email_verified_at' => now(),
            'remember_token' => 'zWk5NG9MY8',
        ]);
    }
    
}
