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
            'rol' => 'user',
            'email_verified_at' => now(),
            'remember_token' => 'zWk5NG9MY7',
        ]);
    }
}
