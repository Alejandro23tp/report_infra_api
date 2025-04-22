<?php

namespace App\Rest\Controllers;

use App\Models\{Reporte, Categoria, User};
use App\Rest\Controller as RestController;
use App\Rest\Resources\ReporteResource;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Storage, Validator};

class ReportesController extends RestController
{
    protected $model = Reporte::class;
    public static $resource = ReporteResource::class;

    // Constantes para categorías y palabras clave
    protected const CATEGORIAS = [
        'Daños estructurales' => ['building damage', 'structural damage', 'wall damage', 'crack', 'broken structure'],
        'Daños en redes de servicios' => ['utility damage', 'electrical damage', 'water leak', 'broken pipe'],
        'Daños en infraestructuras de transporte' => ['road damage', 'pothole', 'broken pavement', 'bridge damage'],
        'Daños causados por fenómenos naturales' => ['flood damage', 'storm damage', 'landslide', 'weather damage'],
        'Daños en espacios públicos' => ['park damage', 'plaza damage', 'public space', 'bench damage'],
        'Impacto ambiental asociado' => ['pollution', 'environmental damage', 'waste', 'contamination'],
        'Daños por conflictos humanos' => ['vandalism', 'graffiti', 'intentional damage', 'human caused']
    ];

    protected const PALABRAS_CLAVE = [
        'damage', 'broken', 'crack', 'deterioration', 'road', 'street', 'infrastructure',
        'building', 'bridge', 'utility', 'pipe', 'concrete', 'asphalt', 'metal', 'erosion',
        'corrosion', 'bench', 'sign', 'unsafe', 'hazard', 'risk', 'emergency'
    ];

    /**
     * Analiza una imagen para detectar daños
     */
    public function analizarImagen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'imagen' => 'required|file|image|mimes:jpeg,png,jpg|max:10240'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors(), 'Error de validación', 400);
        }

        try {
            return response()->json($this->analizarImagenConGoogle($request->file('imagen')));
        } catch (\Exception $e) {
            Log::error('Error analizando imagen: '.$e->getMessage());
            return $this->errorResponse([], 'Error al analizar la imagen', 500);
        }
    }

    /**
     * Crea un nuevo reporte
     */
    public function crearReporte(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'usuario_id' => 'required|exists:users,id',
            'descripcion' => 'required|string',
            'ubicacion' => 'required',
            'imagen' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:10240'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors(), 'Error de validación', 422);
        }

        try {
            DB::beginTransaction();

            // Procesar categoría
            $categoriaData = $request->hasFile('imagen') 
                ? $this->procesarCategoriaDesdeImagen($request->file('imagen'))
                : $this->obtenerCategoriaDefault();

            // Crear reporte
            $reporte = $this->crearNuevoReporte($request, $categoriaData['id']);

            // Procesar imagen si existe
            if ($request->hasFile('imagen')) {
                $this->guardarImagenReporte($reporte, $request->file('imagen'));
            }

            DB::commit();

            return response()->json([
                'mensaje' => 'Reporte creado con éxito',
                'data' => new ReporteResource($reporte),
                'categoria' => $categoriaData['info'],
                'analisis_imagen' => $categoriaData['analysis'] ?? null
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando reporte: '.$e->getMessage());
            return $this->errorResponse([], 'Error al crear el reporte', 500);
        }
    }

    /**
     * Listar todos los reportes
     */
    public function listarReportes()
    {
        $reportes = Reporte::with('usuario', 'categoria')->get();

        return response()->json([
            'mensaje' => 'Reportes obtenidos',
            'data' => $reportes
        ]);
    }

    /**
     * Actualizar el estado de un reporte
     */
    public function actualizarEstado(Request $request, $id)
    {
        $reporte = Reporte::findOrFail($id);
        $reporte->update([
            'estado' => $request->estado
        ]);

        return response()->json([
            'mensaje' => 'Estado del reporte actualizado',
            'data' => new ReporteResource($reporte)
        ]);
    }

    /**
     * Obtiene ubicaciones para mapas
     */
    public function getUbicaciones()
    {
        try {
            $reportes = Reporte::with('categoria:id,nombre')
                ->select(['id', 'ubicacion', 'estado', 'urgencia', 'categoria_id', 'descripcion'])
                ->get()
                ->map(function($reporte) {
                    $ubicacion = json_decode($reporte->ubicacion, true);
                    return [
                        'id' => $reporte->id,
                        'ubicacion' => [
                            'lat' => (float)$ubicacion['lat'],
                            'lng' => (float)$ubicacion['lon'],
                            'descripcion' => $reporte->descripcion
                        ],
                        'estado' => $reporte->estado,
                        'urgencia' => $reporte->urgencia,
                        'categoria' => $reporte->categoria->nombre
                    ];
                });

            return response()->json(['status' => 'success', 'data' => $reportes]);
        } catch (\Exception $e) {
            return $this->errorResponse([], 'Error al obtener ubicaciones', 500);
        }
    }

    // Métodos protegidos auxiliares

    protected function errorResponse($errors, $message, $code)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $code);
    }

    protected function analizarImagenConGoogle($imagen)
    {
        $credentials = $this->obtenerCredencialesGoogle();
        $imageAnnotator = new ImageAnnotatorClient(['credentials' => $credentials]);

        try {
            $image = file_get_contents($imagen->getPathname());
            $response = $imageAnnotator->labelDetection($image);
            $etiquetas = array_map(fn($label) => $label->getDescription(), iterator_to_array($response->getLabelAnnotations()));

            if (!$this->esImagenRelevante($etiquetas)) {
                return [
                    'success' => false,
                    'message' => 'La imagen no parece mostrar daños en infraestructura urbana',
                    'etiquetas' => $etiquetas,
                    'error_tipo' => 'imagen_no_relevante'
                ];
            }

            $clasificacion = $this->clasificarCategoria($etiquetas);

            return [
                'success' => true,
                'etiquetas' => $etiquetas,
                'categoria_sugerida' => $clasificacion['categoria'],
                'confianza' => $clasificacion['confianza'],
                'detalles_clasificacion' => $clasificacion['puntajes'],
                'message' => 'Imagen analizada correctamente'
            ];
        } finally {
            $imageAnnotator->close();
        }
    }

    protected function esImagenRelevante($etiquetas)
    {
        $contador = 0;
        foreach ($etiquetas as $etiqueta) {
            foreach (self::PALABRAS_CLAVE as $palabra) {
                if (stripos($etiqueta, $palabra) !== false) {
                    if (++$contador >= 2) return true;
                    break;
                }
            }
        }
        return false;
    }

    protected function clasificarCategoria($labels)
    {
        $puntajes = array_fill_keys(array_keys(self::CATEGORIAS), 0);

        foreach ($labels as $label) {
            foreach (self::CATEGORIAS as $categoria => $keywords) {
                foreach ($keywords as $keyword) {
                    if (stripos($label, $keyword) !== false) {
                        $puntajes[$categoria]++;
                        break;
                    }
                }
            }
        }

        arsort($puntajes);
        return [
            'categoria' => key($puntajes),
            'confianza' => current($puntajes) / count($labels),
            'puntajes' => $puntajes
        ];
    }

    protected function obtenerCredencialesGoogle()
    {
        $credentials = json_decode(base64_decode(env('GOOGLE_CREDENTIALS_BASE64')), true);
        if (!$credentials) throw new \Exception('Error con credenciales de Google');
        return $credentials;
    }

    protected function procesarCategoriaDesdeImagen($imagen)
    {
        $resultado = $this->analizarImagenConGoogle($imagen);
        
        if (!$resultado['success']) {
            return $this->obtenerCategoriaDefault();
        }

        $categoria = $this->obtenerOCrearCategoria($resultado['categoria_sugerida']);
        
        return [
            'id' => $categoria->id,
            'info' => [
                'id' => $categoria->id,
                'nombre' => $categoria->nombre,
                'sugerida' => true,
                'confianza' => $resultado['confianza']
            ],
            'analysis' => $resultado
        ];
    }

    protected function obtenerCategoriaDefault()
    {
        $categoria = $this->obtenerOCrearCategoria('Sin clasificar');
        return [
            'id' => $categoria->id,
            'info' => [
                'id' => $categoria->id,
                'nombre' => $categoria->nombre,
                'sugerida' => false
            ]
        ];
    }

    protected function obtenerOCrearCategoria($nombre)
    {
        return Categoria::firstOrCreate(
            ['nombre' => $nombre],
            ['descripcion' => 'Categoría detectada automáticamente', 'activo' => true]
        );
    }

    protected function crearNuevoReporte(Request $request, $categoriaId)
    {
        $ubicacion = $this->parsearUbicacion($request->ubicacion);

        return Reporte::create([
            'usuario_id' => $request->usuario_id,
            'categoria_id' => $categoriaId,
            'descripcion' => $request->descripcion,
            'ubicacion' => json_encode($ubicacion),
            'estado' => $request->estado ?? 'pendiente',
            'urgencia' => $request->urgencia ?? 'normal'
        ]);
    }

    protected function parsearUbicacion($ubicacion)
    {
        if (is_string($ubicacion)) $ubicacion = json_decode($ubicacion, true);
        if (!is_array($ubicacion)) $ubicacion = (array)$ubicacion;
        
        if (!isset($ubicacion['lat']) || !isset($ubicacion['lon'])) {
            throw new \Exception('Ubicación inválida: debe contener lat y lon');
        }

        return [
            'lat' => (float)$ubicacion['lat'],
            'lon' => (float)$ubicacion['lon']
        ];
    }

    protected function guardarImagenReporte($reporte, $imagen)
    {
        $path = $imagen->store('public/reportes');
        if (!$path) throw new \Exception('Error al guardar la imagen');
        $reporte->update(['imagen_url' => ltrim(Storage::url($path), '/')]);
    }
}