<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:usuario',  // Cambiar 'users' a 'usuario'
            'password' => 'required|string|min:6',
            'cedula' => 'required|string|unique:usuario',  // Cambiar 'users' a 'usuario'
            'direccion' => 'required|string',
            'rol' => 'required|string|in:admin,usuario'  // Cambiar 'user' a 'usuario'
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(), 400);
        }

        $user = User::create([
            'nombre' => $request->nombre,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'cedula' => $request->cedula,
            'direccion' => $request->direccion,
            'rol' => $request->rol
        ]);

        return response()->json([
            'message' => 'Usuario registrado exitosamente',
            'user' => $user
        ], 201);
    }
}
