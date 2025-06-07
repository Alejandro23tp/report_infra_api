<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ComentarioController;
use App\Http\Controllers\FCMController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\Api\NotificacionController;
use App\Http\Controllers\Api\NotificacionAppController;
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
// Importar el nuevo controlador de administrador
use App\Http\Controllers\Admin\AdminController;

// Configuración CORS para todas las rutas de la API
Route::options('/{any}', function () {
    return response()->noContent()
        ->header('Access-Control-Allow-Origin', 'http://localhost:4200') // Cambia esto a tu origen Angular
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, X-Token-Auth, Authorization')
        ->header('Access-Control-Allow-Credentials', 'true');
})->where('any', '.*');

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
Route::get('reportes/con-interacciones', [ReportesController::class, 'listarReportesConInteracciones']);
Route::post('reportes/analizar-imagen', [ReportesController::class, 'analizarImagen']);
Route::post('reportes/crear', [ReportesController::class, 'crearReporte']);

// Ruta para actualizar la imagen de un reporte existente
Route::post('reportes/{id}/imagen', [ReportesController::class, 'subirImagen']);
Route::post('reportes/actualizar/{id}', [ReportesController::class, 'actualizarEstado']);

Route::get('reportes/ubicaciones/all', [ReportesController::class, 'getUbicaciones']);

//CATEGORIAS
//Rest::resource('categorias', CategoriasController::class);
Route::post('categorias', [CategoriasController::class, 'store']);
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

// Notificaciones Push
Route::group(['middleware' => ['api']], function () {    
    Route::post('/subscribe', [FCMController::class, 'subscribe'])->middleware('auth:api');
    Route::get('/notificaciones/status', [FCMController::class, 'checkNotificationStatus'])->middleware('auth:api');
    Route::post('/unsubscribe', [FCMController::class, 'unsubscribe'])->middleware('auth:api');
        Route::get('/lista-dispositivos', [FCMController::class, 'listDevices'])->middleware('auth:api');
    
    // Rutas para notificaciones de la aplicación
    Route::prefix('notificaciones-app')->group(function () {
        Route::get('/', [NotificacionAppController::class, 'index']);
        Route::post('/marcar-leida/{id}', [NotificacionAppController::class, 'marcarComoLeida']);
        Route::get('/contar-no-leidas', [NotificacionAppController::class, 'contarNoLeidas']);
        Route::post('/marcar-todas-leidas', [NotificacionAppController::class, 'marcarTodasComoLeidas']);
    });
});

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

// Grupo de rutas para administradores
Route::group([
    'middleware' => ['api', 'auth:api', 'admin'],
    'prefix' => 'admin',
], function () {
    // Dashboard y estadísticas
    Route::get('/dashboard', [AdminController::class, 'getDashboardStats']);
    
    // Gestión de usuarios
    Route::get('/usuarios', [AdminController::class, 'listarUsuarios']);
    Route::get('/usuarios/{id}', [AdminController::class, 'verUsuario']);
    Route::put('/usuarios/{id}', [AdminController::class, 'actualizarUsuario']);
    Route::delete('/usuarios/{id}', [AdminController::class, 'eliminarUsuario']);
    Route::put('/usuarios/{id}/rol', [AdminController::class, 'cambiarRolUsuario']);
    Route::put('/usuarios/{id}/estado', [AdminController::class, 'cambiarEstadoUsuario']);
    
    // Gestión de reportes
    Route::get('/reportes', [AdminController::class, 'listarReportes']);
    Route::get('/reportes/{id}', [AdminController::class, 'verReporte']);
    Route::put('/reportes/{id}/estado', [AdminController::class, 'cambiarEstadoReporte']);
    Route::put('/reportes/{id}/prioridad', [AdminController::class, 'cambiarPrioridadReporte']);
    Route::post('/reportes/{id}/asignar', [AdminController::class, 'asignarReporte']);
    Route::delete('/reportes/{id}', [AdminController::class, 'eliminarReporte']);
    
    // Gestión de categorías
    //Route::post('/categorias', [CategoriasController::class, 'store']);
    //Route::get('/categorias/all', [CategoriasController::class, 'index']);
    Route::get('/categorias', [AdminController::class, 'listarCategorias']);
    Route::post('/categorias', [AdminController::class, 'crearCategoria']);
    Route::put('/categorias/{id}', [AdminController::class, 'actualizarCategoria']);
    Route::delete('/categorias/{id}', [AdminController::class, 'eliminarCategoria']);
    
    // Gestión de comentarios
    Route::get('/comentarios', [AdminController::class, 'listarComentarios']);
    Route::delete('/comentarios/{id}', [AdminController::class, 'eliminarComentario']);
    
    // Notificaciones masivas
    Route::post('/notificaciones/enviar', [AdminController::class, 'enviarNotificacionMasiva']);
    
    // Exportación de datos
    Route::get('/exportar/reportes', [AdminController::class, 'exportarReportes'])->name('admin.reportes.exportar');
    Route::get('/exportar/usuarios', [AdminController::class, 'exportarUsuarios'])->name('admin.usuarios.exportar');
    
    // Historial de reportes
    Route::prefix('reportes/{reporte}/historial')->group(function () {
        Route::get('/', [\App\Http\Controllers\ReporteHistorialController::class, 'index']);
        Route::post('/comentario', [\App\Http\Controllers\ReporteHistorialController::class, 'agregarComentario']);
    });

    // Suscripciones a notificaciones por correo
    Route::prefix('suscripciones-correo')->group(function () {
    // Suscribir un correo para recibir notificaciones
    Route::post('/suscribir', [NotificacionController::class, 'suscribir']);
    
    // Cancelar suscripción de notificaciones
    Route::post('/cancelar/{email}', [NotificacionController::class, 'cancelarSuscripcion']);
    
    // Obtener lista de suscriptores (protegido, solo administradores)
    Route::get('/listar', [NotificacionController::class, 'listarSuscriptores']);
    });
});
