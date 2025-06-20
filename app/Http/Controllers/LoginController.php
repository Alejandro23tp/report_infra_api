<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        try {
            // Primero intentar autenticar al usuario
            if (!$token = JWTAuth::attempt($credentials)) {
                // Si falla, verificar si el usuario existe
                $user = User::where('email', $credentials['email'])->first();
                
                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Las credenciales proporcionadas no son válidas.'
                    ], 401);
                }
                
                // Si el usuario existe pero la contraseña es incorrecta
                return response()->json([
                    'success' => false,
                    'message' => 'La contraseña ingresada es incorrecta.'
                ], 401);
            }
            
            // Si la autenticación fue exitosa, obtener el usuario
            $user = JWTAuth::user();
            
            // Verificar si el usuario está activo
            if ($user->activo != 1) {
                // Invalidar el token si el usuario está inactivo
                JWTAuth::invalidate(JWTAuth::getToken());
                return response()->json([
                    'success' => false,
                    'message' => 'Tu cuenta ha sido desactivada. Por favor, contacta al administrador.'
                ], 403);
            }
            
        } catch (JWTException $e) {
            return response()->json(['error' => 'No se pudo crear el token'], 500);
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => $user
        ]);
    }

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['message' => 'Logout exitoso']);
        } catch (JWTException $e) {
            return response()->json(['error' => 'No se pudo realizar el logout'], 500);
        }
    }

    public function refresh()
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            $user = JWTAuth::setToken($token)->toUser();
            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'user' => $user
            ]);
        } catch (JWTException $e) {
            return response()->json(['error' => 'No se pudo refrescar el token'], 500);
        }
    }
}
