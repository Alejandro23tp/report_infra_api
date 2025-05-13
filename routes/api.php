<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ComentarioController;
use App\Http\Controllers\FCMController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ReaccionController;
use App\Http\Controllers\UserController;
use App\Models\User;  // Agregar este import
use App\Rest\Controllers\CategoriasController;
use App\Rest\Controllers\ReportesController;
use App\Rest\Controllers\UsuariosController;
use App\Services\FCMService;
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

Route::post('password/email', [PasswordResetController::class, 'sendResetLink']);
Route::post('password/reset', [PasswordResetController::class, 'reset']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

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

// Notificaciones
Route::group(['middleware' => ['api']], function () {
    Route::options('/subscribe', function () {
        return response()->noContent()
            ->header('Access-Control-Allow-Origin', 'http://127.0.0.1:8080')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, X-Token-Auth, Authorization')
            ->header('Access-Control-Allow-Credentials', 'true');
    });
    
    Route::post('/subscribe', [FCMController::class, 'subscribe'])->middleware('auth:api');
});
//Notificaciones
//Route::middleware('auth:api')->post('/subscribe', [FCMController::class, 'subscribe']);

//test
Route::get('/test-fcm', function () {
    $fcmService = new FCMService();
    
    try {
        info('Iniciando prueba de FCM');
        
        $user = User::whereNotNull('fcm_token')
                    ->where('fcm_token', '!=', '')
                    ->first();
        
        if (!$user) {
            return response()->json(['error' => 'No hay usuarios con token FCM'], 404);
        }
        
        $messageId = uniqid('fcm_');
        $result = $fcmService->sendNotification(
            $user->fcm_token,
            'Prueba',
            'Notificación de prueba desde Laravel ' . $messageId,
            $messageId
        );
        
        info('Enviando notificación con ID: ' . $messageId);
        info('Token FCM usado: ' . $user->fcm_token);
        info('Resultado del envío: ' . json_encode($result));
        
        return response()->json([
            'success' => $result,
            'messageId' => $messageId,
            'token' => $user->fcm_token,
            'user_id' => $user->id
        ]);
    } catch (\Exception $e) {
        info('Error al enviar notificación: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500);
    }
});