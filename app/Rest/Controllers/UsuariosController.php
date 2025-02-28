<?php

namespace App\Rest\Controllers;

use App\Models\Usuario;
use App\Rest\Controller as RestController;
use App\Rest\Resources\UsuarioResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsuariosController extends RestController
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<\Lomkit\Rest\Http\Resource>
     */
    public static $resource = UsuarioResource::class;

    /**
     * Inicio de sesión para usuarios.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request): \Illuminate\Http\JsonResponse
    {
        // Buscar al usuario por correo electrónico
        $usuario = Usuario::where('email', $request->email)->first();

        // Verificar credenciales
        if ($usuario && Hash::check($request->password, $usuario->password)) {
            return response()->json([
                'mensaje' => 'Usuario encontrado',
                'cant' => 1,
                'data' => $usuario
            ]);
        } else {
            return response()->json([
                'mensaje' => 'Usuario no encontrado',
                'cant' => 0,
                'data' => null
            ]);
        }
    }

    /**
     * Cambiar la contraseña de un usuario.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cambiarPassword(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password_actual' => 'required',
            'nuevo_password' => 'required|min:8|confirmed'
        ]);

        // Buscar al usuario por correo electrónico
        $usuario = Usuario::where('email', $request->email)->first();

        // Validar contraseña actual
        if ($usuario && Hash::check($request->password_actual, $usuario->password)) {
            $usuario->update([
                'password' => Hash::make($request->nuevo_password)
            ]);

            return response()->json([
                'mensaje' => 'Contraseña actualizada con éxito',
                'data' => $usuario
            ]);
        } else {
            return response()->json([
                'mensaje' => 'Contraseña actual incorrecta o usuario no encontrado',
                'data' => null
            ]);
        }
    }

    /**
     * Buscar un usuario por email.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function buscarPorEmail(Request $request): \Illuminate\Http\JsonResponse
    {
        $usuario = Usuario::where('email', $request->email)->first();

        if ($usuario) {
            return response()->json([
                'mensaje' => 'Usuario encontrado',
                'data' => $usuario
            ]);
        } else {
            return response()->json([
                'mensaje' => 'Usuario no encontrado',
                'data' => null
            ]);
        }
    }

    /**
     * Listar usuarios con un rol específico.
     *
     * @param string $rol
     * @return \Illuminate\Http\JsonResponse
     */
    public function listarPorRol(string $rol): \Illuminate\Http\JsonResponse
    {
        $usuarios = Usuario::where('rol', $rol)->get();

        if ($usuarios->isEmpty()) {
            return response()->json([
                'mensaje' => 'No se encontraron usuarios con este rol',
                'data' => []
            ]);
        }

        return response()->json([
            'mensaje' => 'Usuarios encontrados',
            'data' => $usuarios
        ]);
    }
    
        /**
     * Listar todos los usuarios.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listarTodos(): \Illuminate\Http\JsonResponse
    {
        $usuarios = Usuario::all();

        if ($usuarios->isEmpty()) {
            return response()->json([
                'mensaje' => 'No se encontraron usuarios',
                'data' => []
            ]);
        }

        return response()->json([
            'mensaje' => 'Usuarios encontrados',
            'data' => $usuarios
        ]);
    }
}
