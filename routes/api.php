<?php

use App\Http\Controllers\ComentarioController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ReaccionController;
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

Route::get('reportes/ubicaciones/all', [ReportesController::class, 'getUbicaciones']);

//CATEGORIAS
Rest::resource('categorias', CategoriasController::class);
Route::get('categorias/all', [CategoriasController::class, 'index']);

// Rutas para reacciones y comentarios (sin autenticación)
Route::post('/reacciones/toggle', [ReaccionController::class, 'toggle']);
Route::get('/reacciones/{reporteId}', [ReaccionController::class, 'getReacciones']);

// Rutas para comentarios
Route::prefix('comentarios')->group(function () {
    Route::post('/', [ComentarioController::class, 'store']);
    Route::get('/count/{reporteId}', [ComentarioController::class, 'contarComentariosReporte']);
    Route::get('/principales/{reporteId}', [ComentarioController::class, 'getComentariosPrincipales']);
    Route::get('/respuestas/{comentarioId}', [ComentarioController::class, 'getRespuestasComentario']);
    Route::delete('/{id}', [ComentarioController::class, 'destroy']);
});
