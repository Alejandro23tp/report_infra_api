<?php

use App\Http\Controllers\LoginController;
 use App\Http\Controllers\UserController;
use App\Rest\Controllers\CategoriasController;
use App\Rest\Controllers\ReportesController;
use App\Rest\Controllers\UsuariosController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth',
], function () {
    Route::post('login', [LoginController::class, 'login']);
    Route::post('logout', [LoginController::class, 'logout']);
    Route::post('refresh', [LoginController::class, 'refresh']);
    Route::post('register', [UserController::class, 'register']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Rest::resource('usuarios', UsuariosController::class);
// Ruta para listar todos los usuarios
Route::get('usuarios/all', [UsuariosController::class, 'listarTodos']);


// Rutas para reportes
Rest::resource('reportes', ReportesController::class);
Route::get('reportes/all', [ReportesController::class, 'listarReportes']);
Route::post('reportes/analizar-imagen', [ReportesController::class, 'analizarImagen']);
Route::post('reportes/crear', [ReportesController::class, 'crearReporte']);

// Ruta para actualizar la imagen de un reporte existente
Route::post('reportes/{id}/imagen', [ReportesController::class, 'subirImagen']);
Route::post('reportes/actualizar/{id}', [ReportesController::class, 'actualizarEstado']);

//CATEGORIAS
Rest::resource('categorias', CategoriasController::class);
Route::get('categorias/all', [CategoriasController::class, 'index']);
