<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DispositivosToken;
use App\Models\User;
use App\Models\Reporte;
use App\Models\Categoria;
use App\Models\Comentario;
use App\Services\FCMService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    protected $fcmService;
    
    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }
    
    /**
     * Envía notificaciones a todos los usuarios sobre un cambio en un reporte
     */
    private function notificarCambioReporte(Reporte $reporte, string $tipo, string $tituloBase, string $mensajeBase, array $datosAdicionales = [])
    {
        // Obtener todos los usuarios activos con sus dispositivos
        $usuarios = User::where('activo', true)
            ->whereHas('dispositivos')
            ->with('dispositivos')
            ->get();
            
        // Preparar la descripción (mostrar solo los primeros 50 caracteres si es muy larga)
        $descripcion = strlen($reporte->descripcion) > 50 
            ? substr($reporte->descripcion, 0, 50) . '...' 
            : $reporte->descripcion;
            
        // Enviar notificación a todos los usuarios con dispositivos registrados
        foreach ($usuarios as $usuario) {
            $esCreador = $usuario->id === $reporte->usuario_id;
            
            // Personalizar el mensaje para el creador
            $titulo = $esCreador 
                ? "Tu reporte: {$tituloBase}" 
                : $tituloBase;
                
            $mensaje = $esCreador 
                ? "Tu reporte \"{$descripcion}\" {$mensajeBase}" 
                : "El reporte \"{$descripcion}\" {$mensajeBase}";
            
            foreach ($usuario->dispositivos as $dispositivo) {
                try {
                    $this->fcmService->sendNotification(
                        $dispositivo->fcm_token,
                        $titulo,
                        $mensaje,
                        'reporte_' . $reporte->id,
                        array_merge([
                            'tipo' => $tipo,
                            'reporte_id' => $reporte->id,
                            'es_creador' => $esCreador
                        ], $datosAdicionales)
                    );
                } catch (\Exception $e) {
                    Log::error('Error enviando notificación a dispositivo: ' . $e->getMessage(), [
                        'usuario_id' => $usuario->id,
                        'dispositivo_id' => $dispositivo->id,
                        'reporte_id' => $reporte->id
                    ]);
                }
            }
        }
    }
    
    /**
     * Obtiene estadísticas para el dashboard del administrador
     */
    public function getDashboardStats()
    {
        $totalUsuarios = User::count();
        $usuariosNuevos = User::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        
        $totalReportes = Reporte::count();
        $reportesNuevos = Reporte::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $reportesPendientes = Reporte::where('estado', 'Pendiente')->count();
        $reportesEnProceso = Reporte::where('estado', 'En Proceso')->count();
        $reportesResueltos = Reporte::where('estado', 'Completado')->count();
        $reportesCancelados = Reporte::where('estado', 'Cancelado')->count();
        
        $reportesPorCategoria = Reporte::select('categoria_id', DB::raw('count(*) as total'))
            ->groupBy('categoria_id')
            ->with('categoria')
            ->get();
            
        $reportesPorMes = Reporte::select(
                DB::raw('MONTH(created_at) as mes'),
                DB::raw('YEAR(created_at) as año'),
                DB::raw('count(*) as total')
            )
            ->where('created_at', '>=', Carbon::now()->subMonths(12))
            ->groupBy('año', 'mes')
            ->orderBy('año')
            ->orderBy('mes')
            ->get();
            
        return response()->json([
            'usuarios' => [
                'total' => $totalUsuarios,
                'nuevos' => $usuariosNuevos
            ],
            'reportes' => [
                'total' => $totalReportes,
                'nuevos' => $reportesNuevos,
                'por_estado' => [
                    'pendientes' => $reportesPendientes,
                    'en_proceso' => $reportesEnProceso,
                    'resueltos' => $reportesResueltos,
                    'cancelados' => $reportesCancelados
                ],
                'por_categoria' => $reportesPorCategoria,
                'por_mes' => $reportesPorMes
            ]
        ]);
    }
    
    /**
     * Lista todos los usuarios con paginación y filtros
     */
    public function listarUsuarios(Request $request)
    {
        $query = User::query();
        
        // Aplicar filtros
        if ($request->has('rol')) {
            $query->where('rol', $request->rol);
        }
        
        // Filtro por estado activo/inactivo
        if ($request->has('activo') && $request->activo !== '') {
            $query->where('activo', $request->activo);
        }
        
        // Búsqueda por id, nombre o cédula
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                // Buscar por ID si el search es numérico
                if (is_numeric($search)) {
                    $q->where('id', $search);
                }
                
                // Buscar por nombre, cédula o email
                $q->orWhere('nombre', 'like', "%{$search}%")
                  ->orWhere('cedula', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        // Ordenar
        $orderBy = $request->input('order_by', 'created_at');
        $orderDir = $request->input('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);
        
        // Paginar
        $perPage = $request->input('per_page', 15);
        $usuarios = $query->paginate($perPage);
        
        return response()->json($usuarios);
    }
    
    /**
     * Ver detalles de un usuario específico
     */
    public function verUsuario($id)
    {
        $usuario = User::with(['reportes', 'comentarios'])->findOrFail($id);
        return response()->json($usuario);
    }
    
    /**
     * Actualizar información de un usuario
     */
    public function actualizarUsuario(Request $request, $id)
    {
        $usuario = User::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:usuario,email,' . $id,
            'direccion' => 'sometimes|string|max:255',
            'activo' => 'sometimes|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $usuario->update($request->only(['nombre', 'email', 'direccion', 'activo']));
        
        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'usuario' => $usuario
        ]);
    }
    
    /**
     * Eliminar un usuario
     */
    public function eliminarUsuario($id)
    {
        $usuario = User::findOrFail($id);
        
        // Verificar que no sea el usuario administrador principal
        if ($usuario->id === 1) {
            return response()->json(['error' => 'No se puede eliminar al administrador principal'], 403);
        }
        
        $usuario->delete();
        
        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }
    
    /**
     * Cambiar el rol de un usuario
     */
    public function cambiarRolUsuario(Request $request, $id)
    {
        $usuario = User::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'rol' => 'required|string|in:admin,usuario',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $usuario->rol = $request->rol;
        $usuario->save();
        
        return response()->json([
            'message' => 'Rol de usuario actualizado correctamente',
            'usuario' => $usuario
        ]);
    }
    
    /**
     * Cambiar el estado de un usuario (activo/inactivo)
     */
    public function cambiarEstadoUsuario(Request $request, $id)
    {
        $usuario = User::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'activo' => 'required|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $usuario->activo = $request->activo;
        $usuario->save();
        
        return response()->json([
            'message' => 'Estado de usuario actualizado correctamente',
            'usuario' => $usuario
        ]);
    }
    
    /**
     * Listar todos los reportes con filtros y paginación
     */
    public function listarReportes(Request $request)
    {
        $query = Reporte::with([
            'usuario:id,nombre,email,cedula', 
            'categoria:id,nombre',
            'asignadoA:id,nombre,email,cedula'
        ]);
        
        // Aplicar filtros
        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }
        
        if ($request->has('categoria_id')) {
            $query->where('categoria_id', $request->categoria_id);
        }
        
        if ($request->has('urgencia')) {
            $query->where('urgencia', $request->urgencia);
        }
        
        // Filtrar por usuario asignado
        if ($request->has('asignado_a')) {
            $query->where('asignado_a', $request->asignado_a);
        }
        
        if ($request->has('buscar')) {
            $buscar = $request->buscar;
            $query->where(function($q) use ($buscar) {
                $q->where('descripcion', 'like', "%{$buscar}%")
                  ->orWhereHas('usuario', function($q) use ($buscar) {
                      $q->where('nombre', 'like', "%{$buscar}%")
                        ->orWhere('email', 'like', "%{$buscar}%")
                        ->orWhere('cedula', 'like', "%{$buscar}%");
                  });
            });
        }
        
        // Ordenar
        $orderBy = $request->input('order_by', 'created_at');
        $orderDir = $request->input('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);
        
        // Paginar
        $perPage = $request->input('per_page', 15);
        $reportes = $query->paginate($perPage);
        
        return response()->json($reportes);
    }
    
    /**
     * Ver detalles de un reporte específico
     */
    public function verReporte($id)
    {
        $reporte = Reporte::with(['usuario', 'categoria', 'comentarios', 'reacciones'])
            ->findOrFail($id);
            
        return response()->json($reporte);
    }
    
    /**
     * Cambiar el estado de un reporte
     */
    public function cambiarEstadoReporte(Request $request, $id)
    {
        $reporte = Reporte::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'estado' => 'required|string|in:Pendiente,En Proceso,Completado,Cancelado',
            'nota_admin' => 'sometimes|nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $estadoAnterior = $reporte->estado;
        $reporte->estado = $request->estado;
        
        if ($request->has('nota_admin')) {
            $reporte->nota_admin = $request->nota_admin;
        }
        
        $reporte->save();
        
        // Notificar a todos los usuarios
        $this->notificarCambioReporte(
            $reporte,
            'cambio_estado',
            'Estado del reporte actualizado',
            "ha cambiado de estado de {$estadoAnterior} a {$reporte->estado}",
            [
                'estado_anterior' => $estadoAnterior,
                'nuevo_estado' => $reporte->estado
            ]
        );
        
        return response()->json([
            'message' => 'Estado del reporte actualizado correctamente',
            'reporte' => $reporte
        ]);
    }
    
    /**
     * Cambiar la prioridad de un reporte
     */
    public function cambiarPrioridadReporte(Request $request, $id)
    {
        $reporte = Reporte::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'urgencia' => 'required|string|in:bajo,medio,alto,crítico',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $urgenciaAnterior = $reporte->urgencia;
        $reporte->urgencia = $request->urgencia;
        $reporte->save();
        
        // Notificar a todos los usuarios
        $this->notificarCambioReporte(
            $reporte,
            'cambio_urgencia',
            'Urgencia del reporte actualizada',
            "ha cambiado de urgencia de {$urgenciaAnterior} a {$reporte->urgencia}",
            [
                'urgencia_anterior' => $urgenciaAnterior,
                'nueva_urgencia' => $reporte->urgencia
            ]
        );
        
        return response()->json([
            'message' => 'Urgencia del reporte actualizada correctamente',
            'reporte' => $reporte
        ]);
        
        return response()->json([
            'message' => 'Urgencia del reporte actualizada correctamente',
            'reporte' => $reporte
        ]);
    }
    
    /**
     * Asignar un reporte a un usuario (técnico o responsable)
     */
    public function asignarReporte(Request $request, $id)
    {
        $reporte = Reporte::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'asignado_a' => 'required|exists:usuario,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $asignadoAnterior = $reporte->asignado_a;
        $reporte->asignado_a = $request->asignado_a;
        $reporte->save();
        
        // Obtener nombre del usuario asignado
        $usuarioAsignado = User::find($request->asignado_a);
        $nombreAsignado = $usuarioAsignado ? $usuarioAsignado->nombre : 'Sin asignar';
        
        // Notificar a todos los usuarios
        $this->notificarCambioReporte(
            $reporte,
            'asignacion',
            'Reporte asignado',
            "ha sido asignado a {$nombreAsignado}",
            [
                'asignado_anterior_id' => $asignadoAnterior,
                'nuevo_asignado_id' => $reporte->asignado_a,
                'nuevo_asignado_nombre' => $nombreAsignado
            ]
        );
        
        return response()->json([
            'message' => 'Reporte asignado correctamente',
            'reporte' => $reporte
        ]);
        
        return response()->json([
            'message' => 'Reporte asignado correctamente',
            'reporte' => $reporte
        ]);
    }
    
    /**
     * Eliminar un reporte
     */
    public function eliminarReporte($id)
    {
        $reporte = Reporte::findOrFail($id);
        $reporte->delete();
        
        return response()->json(['message' => 'Reporte eliminado correctamente']);
    }
    
    /**
     * Listar todas las categorías
     */
    public function listarCategorias()
    {
        $categorias = Categoria::withCount('reportes')->get();
        return response()->json($categorias);
    }
    
    /**
     * Crear una nueva categoría
     */
    public function crearCategoria(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255|unique:categorias,nombre',
            'descripcion' => 'sometimes|nullable|string',
            'color' => 'sometimes|nullable|string|max:7',
            'icono' => 'sometimes|nullable|string|max:50',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $categoria = Categoria::create($request->all());
        
        return response()->json([
            'message' => 'Categoría creada correctamente',
            'categoria' => $categoria
        ], 201);
    }
    
    /**
     * Actualizar una categoría existente
     */
    public function actualizarCategoria(Request $request, $id)
    {
        $categoria = Categoria::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255|unique:categorias,nombre,' . $id,
            'descripcion' => 'sometimes|nullable|string',
            'color' => 'sometimes|nullable|string|max:7',
            'icono' => 'sometimes|nullable|string|max:50',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $categoria->update($request->all());
        
        return response()->json([
            'message' => 'Categoría actualizada correctamente',
            'categoria' => $categoria
        ]);
    }
    
    /**
     * Eliminar una categoría
     */
    public function eliminarCategoria($id)
    {
        $categoria = Categoria::findOrFail($id);
        
        // Verificar si hay reportes asociados
        $reportesCount = Reporte::where('categoria_id', $id)->count();
        if ($reportesCount > 0) {
            return response()->json([
                'error' => 'No se puede eliminar la categoría porque tiene reportes asociados',
                'reportes_count' => $reportesCount
            ], 422);
        }
        
        $categoria->delete();
        
        return response()->json(['message' => 'Categoría eliminada correctamente']);
    }
    
    /**
     * Listar todos los comentarios con filtros
     */
    public function listarComentarios(Request $request)
    {
        $query = Comentario::with(['usuario', 'reporte']);
        
        if ($request->has('reporte_id')) {
            $query->where('reporte_id', $request->reporte_id);
        }
        
        if ($request->has('buscar')) {
            $buscar = $request->buscar;
            $query->where('contenido', 'like', "%{$buscar}%");
        }
        
        // Ordenar
        $orderBy = $request->input('order_by', 'created_at');
        $orderDir = $request->input('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);
        
        // Paginar
        $perPage = $request->input('per_page', 15);
        $comentarios = $query->paginate($perPage);
        
        return response()->json($comentarios);
    }
    
    /**
     * Eliminar un comentario
     */
    public function eliminarComentario($id)
    {
        $comentario = Comentario::findOrFail($id);
        $comentario->delete();
        
        return response()->json(['message' => 'Comentario eliminado correctamente']);
    }
    
    /**
     * Enviar notificación masiva a usuarios
     */
    public function enviarNotificacionMasiva(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'mensaje' => 'required|string',
            'usuarios' => 'sometimes|array',
            'usuarios.*' => 'exists:usuario,id',
            'rol' => 'sometimes|string|in:administrador,usuario,moderador',
            'todos' => 'sometimes|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $query = User::with('dispositivos');
        
        // Filtrar por rol específico
        if ($request->has('rol')) {
            $query->where('rol', $request->rol);
        }
        
        // Filtrar por usuarios específicos
        if ($request->has('usuarios') && is_array($request->usuarios)) {
            $query->whereIn('id', $request->usuarios);
        }
        
        $usuarios = $query->get();
        $enviados = 0;
        $fallidos = 0;
        $dispositivosNotificados = [];
        
        foreach ($usuarios as $usuario) {
            if ($usuario->dispositivos->isEmpty()) {
                continue;
            }
            
            foreach ($usuario->dispositivos as $dispositivo) {
                // Evitar notificar múltiples veces al mismo dispositivo
                if (in_array($dispositivo->id, $dispositivosNotificados)) {
                    continue;
                }
                
                try {
                    $result = $this->fcmService->sendNotification(
                        $dispositivo->fcm_token,
                        $request->titulo,
                        $request->mensaje,
                        'notificacion_admin_' . uniqid()
                    );
                    
                    if ($result) {
                        $enviados++;
                        $dispositivosNotificados[] = $dispositivo->id;
                    } else {
                        $fallidos++;
                        Log::warning('No se pudo enviar notificación a dispositivo', [
                            'usuario_id' => $usuario->id,
                            'dispositivo_id' => $dispositivo->id
                        ]);
                    }
                } catch (\Exception $e) {
                    $fallidos++;
                    Log::error('Error enviando notificación a dispositivo: ' . $e->getMessage(), [
                        'usuario_id' => $usuario->id,
                        'dispositivo_id' => $dispositivo->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        return response()->json([
            'message' => 'Notificaciones enviadas',
            'total_usuarios' => $usuarios->count(),
            'dispositivos_enviados' => $enviados,
            'dispositivos_fallidos' => $fallidos
        ]);
    }

/**
 * Obtener configuración del sistema
 */
public function obtenerConfiguracion()
{
    // Aquí puedes implementar la lógica para obtener configuraciones
    // desde una tabla de configuración o desde el archivo .env

    return response()->json([
        'app_name' => config('app.name'),
        'app_url' => config('app.url'),
        'mail_from_address' => config('mail.from.address'),
        'mail_from_name' => config('mail.from.name'),
        // Otras configuraciones relevantes
    ]);
}

/**
 * Exportar reportes a CSV/Excel
 */
public function exportarReportes(Request $request)
{
    // Aquí implementarías la lógica para exportar reportes
    // Puedes usar paquetes como maatwebsite/excel

    return response()->json([
        'message' => 'Función de exportación de reportes (implementación pendiente)'
    ]);
}
}

