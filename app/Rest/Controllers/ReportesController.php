<?php

namespace App\Rest\Controllers;

use App\Models\{Reporte, Categoria, User};
use App\Services\NotificacionService;
use App\Rest\Controller as RestController;
use App\Rest\Resources\ReporteResource;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Storage, Validator};

class ReportesController extends RestController
{
    protected $model = Reporte::class;
    public static $resource = ReporteResource::class;

    // Constantes para categorías y palabras clave
    protected const CATEGORIAS = [
        'Daños estructurales' => ['building damage', 'structural damage', 'wall damage', 'crack', 'broken structure'],
        'Daños en redes de servicios' => ['utility damage', 'electrical damage', 'water leak', 'broken pipe', 'puddle'],
        'Daños en infraestructuras de transporte' => ['road damage', 'pothole', 'broken pavement', 'bridge damage', 'tar'],
        'Daños causados por fenómenos naturales' => ['flood damage', 'storm damage', 'landslide', 'weather damage', 'wetland','rubble', 'earthquake', 'geological phenomenon'],
        'Daños en espacios públicos' => ['park damage', 'plaza damage', 'public space', 'bench damage', 'Park'],
        'Impacto ambiental asociado' => ['pollution', 'environmental damage', 'waste', 'contamination', 'puddle', 'wetland'],
        'Daños por conflictos humanos' => ['vandalism', 'graffiti', 'intentional damage', 'human caused']
    ];

    protected const PALABRAS_CLAVE_PRINCIPALES = [
        'damage', 'broken', 'construction', 'infrastructure', 'repair',
        'maintenance', 'pothole', 'leak', 'erosion', 'destruction',
        'pollution', 'puddle', 'wetland', 'tar', 'rubble', 'earthquake', 'Public space', 'Park', 'geological phenomenon'
    ];

    protected const PALABRAS_CLAVE_CONTEXTO = [
        'construction worker', 'workwear', 'hard hat', 'engineer',
        'concrete', 'pipe', 'drainage', 'soil', 'tradesman',
        'project', 'road', 'street', 'building', 'structure',
        'puddle', 'wetland', 'tar', 'rubble', 'earthquake', 'Public space', 'Park', 'geological phenomenon'
    ];

    protected const PALABRAS_CLAVE_NEGATIVAS = [
        'logo', 'symbol', 'graphic', 'art', 'design', 'game',
        'toy', 'cartoon', 'drawing', 'illustration', 'text'
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
            'usuario_id' => 'required|exists:usuario,id',
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
            $reporte = $this->crearNuevoReporte($request, $categoriaData);

            if ($request->hasFile('imagen')) {
                $this->guardarImagenReporte($reporte, $request->file('imagen'));
            }

            // Intentar notificar de manera segura
            try {
                app(NotificacionService::class)->notificarNuevoReporte($reporte);
            } catch (\Exception $e) {
                Log::error('Error al notificar nuevo reporte: ' . $e->getMessage());
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
        try {
            $validator = Validator::make($request->all(), [
                'estado' => 'required|string|in:Pendiente,En Proceso,Completado,Cancelado'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors(), 'Estado no válido', 422);
            }

            $reporte = Reporte::findOrFail($id);
            $estadoAnterior = $reporte->estado;
            $reporte->estado = $request->estado;
            $reporte->save();

            // Enviar notificación del cambio de estado
            try {
                app(NotificacionService::class)->notificarCambioEstado($reporte, $estadoAnterior);
            } catch (\Exception $e) {
                Log::error('Error al notificar cambio de estado: ' . $e->getMessage());
            }

            Log::info('Estado de reporte actualizado', [
                'reporte_id' => $id,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $request->estado
            ]);

            return response()->json([
                'success' => true,
                'mensaje' => 'Estado del reporte actualizado',
                'data' => new ReporteResource($reporte)
            ]);

        } catch (\Exception $e) {
            Log::error('Error actualizando estado: ' . $e->getMessage());
            return $this->errorResponse([], 'Error al actualizar el estado', 500);
        }
    }

    /**
     * Obtiene ubicaciones para mapas
     */
    public function getUbicaciones()
    {
        try {
            $reportes = Reporte::with('categoria:id,nombre')
                ->select(['id', 'ubicacion', 'estado', 'urgencia', 'categoria_id', 'descripcion', 'imagen_url'])
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
                        'imagen' => $reporte->imagen_url,
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
        try {
            Log::info('Iniciando análisis de imagen');
            $credentials = $this->obtenerCredencialesGoogle();
            
            $imageAnnotator = new ImageAnnotatorClient([
                'credentials' => $credentials
            ]);

            // Preparar imagen
            $imageContent = file_get_contents($imagen->getPathname());
            $image = new Image();
            $image->setContent($imageContent);

            // Configurar feature
            $feature = new Feature();
            $feature->setType(Type::LABEL_DETECTION);
            $feature->setMaxResults(20);

            // Crear request
            $request = new AnnotateImageRequest();
            $request->setImage($image);
            $request->setFeatures([$feature]);

            // Ejecutar análisis
            $batchRequest = new \Google\Cloud\Vision\V1\BatchAnnotateImagesRequest();
            $batchRequest->setRequests([$request]);
            $response = $imageAnnotator->batchAnnotateImages($batchRequest);
            $annotations = $response->getResponses()[0];

            // Procesar etiquetas
            $etiquetas = [];
            foreach ($annotations->getLabelAnnotations() as $label) {
                $etiquetas[] = [
                    'descripcion' => $label->getDescription(),
                    'confianza' => $label->getScore()
                ];
            }

            $descriptions = array_map(function($etiqueta) {
                return $etiqueta['descripcion'];
            }, $etiquetas);

            Log::info('Etiquetas detectadas:', $descriptions);

            $esRelevante = $this->esImagenRelevante($etiquetas);
            if (!$esRelevante) {
                return [
                    'success' => false,
                    'message' => 'La imagen no parece mostrar daños en infraestructura urbana',
                    'etiquetas' => $etiquetas,
                    'error_tipo' => 'imagen_no_relevante'
                ];
            }

            $clasificacion = $this->clasificarCategoria($descriptions);

            $imageAnnotator->close();

            return [
                'success' => true,
                'etiquetas' => $etiquetas,
                'relevante' => $esRelevante,
                'categoria_sugerida' => $clasificacion['categoria'],
                'confianza' => $clasificacion['confianza'],
                'puntajes_categorias' => $clasificacion['puntajes'],
                'message' => 'Imagen analizada correctamente'
            ];

        } catch (\Exception $e) {
            Log::error('Error Vision API: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    protected function esImagenRelevante($etiquetas)
    {
        $puntajeTotal = 0;
        $contienePalabrasPrincipales = false;
        $contieneContexto = false;
        $contienePalabrasNegativas = false;

        foreach ($etiquetas as $etiqueta) {
            $descripcion = is_array($etiqueta) ? strtolower($etiqueta['descripcion']) : strtolower($etiqueta);
            $confianza = is_array($etiqueta) ? $etiqueta['confianza'] : 1.0;

            // Verificar palabras negativas
            foreach (self::PALABRAS_CLAVE_NEGATIVAS as $palabra) {
                if (stripos($descripcion, $palabra) !== false && $confianza > 0.7) {
                    $contienePalabrasNegativas = true;
                    break 2;
                }
            }

            // Verificar palabras principales
            foreach (self::PALABRAS_CLAVE_PRINCIPALES as $palabra) {
                if (stripos($descripcion, $palabra) !== false) {
                    $contienePalabrasPrincipales = true;
                    $puntajeTotal += ($confianza * 2);
                    break;
                }
            }

            // Verificar palabras de contexto
            foreach (self::PALABRAS_CLAVE_CONTEXTO as $palabra) {
                if (stripos($descripcion, $palabra) !== false) {
                    $contieneContexto = true;
                    $puntajeTotal += $confianza;
                    break;
                }
            }
        }

        return !$contienePalabrasNegativas && 
               ($contienePalabrasPrincipales || $contieneContexto) && 
               $puntajeTotal >= 1.0;
    }

    protected function clasificarCategoria($labels)
    {
        $puntajes = array_fill_keys(array_keys(self::CATEGORIAS), 0);
        
        foreach ($labels as $label) {
            $label = strtolower($label);
            
            foreach (self::CATEGORIAS as $categoria => $keywords) {
                // Puntaje base por cada etiqueta analizada
                $maxPuntajeEtiqueta = 0;
                
                foreach ($keywords as $keyword) {
                    $keyword = strtolower($keyword);
                    
                    // Verificar coincidencia exacta
                    if (strpos($label, $keyword) !== false) {
                        $maxPuntajeEtiqueta = max($maxPuntajeEtiqueta, 1.0);
                        continue;
                    }
                    
                    // Verificar palabras individuales
                    $keywordWords = explode(' ', $keyword);
                    $labelWords = explode(' ', $label);
                    $wordsMatched = 0;
                    
                    foreach ($keywordWords as $word) {
                        if (strlen($word) <= 3) continue; // Ignorar palabras muy cortas
                        
                        foreach ($labelWords as $labelWord) {
                            similar_text($word, $labelWord, $similarity);
                            if ($similarity > 80) { // Umbral de similitud más bajo
                                $wordsMatched++;
                                break;
                            }
                        }
                    }
                    
                    if ($wordsMatched > 0) {
                        $matchScore = $wordsMatched / count($keywordWords);
                        $maxPuntajeEtiqueta = max($maxPuntajeEtiqueta, $matchScore);
                    }
                }
                
                // Acumular el mejor puntaje encontrado para esta etiqueta
                if ($maxPuntajeEtiqueta > 0) {
                    $puntajes[$categoria] += $maxPuntajeEtiqueta;
                }
            }
        }

        // Normalizar y filtrar puntajes
        $maxPuntaje = max(array_sum($puntajes), 1);
        array_walk($puntajes, function(&$puntaje) use ($maxPuntaje) {
            $puntaje = $puntaje / $maxPuntaje;
        });

        // Filtrar categorías con puntaje mínimo más bajo
        $puntajes = array_filter($puntajes, fn($score) => $score >= 0.15);
        
        if (empty($puntajes)) {
            return [
                'categoria' => 'Sin clasificar',
                'confianza' => 0,
                'puntajes' => []
            ];
        }

        arsort($puntajes);
        
        return [
            'categoria' => key($puntajes),
            'confianza' => current($puntajes),
            'puntajes' => $puntajes
        ];
    }

    protected function contarPalabrasComunes($texto1, $texto2)
    {
        $palabras1 = explode(' ', strtolower($texto1));
        $palabras2 = explode(' ', strtolower($texto2));
        return count(array_intersect($palabras1, $palabras2));
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
    
        // Asegurar que se retorna la estructura completa
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
        // Estructura completa requerida
        return [
            'id' => $categoria->id,
            'info' => [
                'id' => $categoria->id,
                'nombre' => $categoria->nombre,
                'sugerida' => false,
                'confianza' => 0
            ]
        ];
    }

    protected function obtenerOCrearCategoria($nombre)
    {
        // Asegurar formato consistente (primera letra mayúscula)
        $nombre = ucfirst(strtolower(trim($nombre)));
        
        return Categoria::firstOrCreate(
            ['nombre' => $nombre],
            [
                'descripcion' => 'Categoría detectada automáticamente', 
                'activo' => true,
                'es_autogenerada' => true // Campo nuevo para identificar
            ]
        );
    }

    protected function crearNuevoReporte(Request $request, array $categoriaData)
    {
        $ubicacion = $this->parsearUbicacion($request->ubicacion);

        // Asegurar valores por defecto para estado y urgencia
        $estado = $request->estado ?? 'Pendiente'; // Valor por defecto explícito
            // Calcular urgencia automáticamente
        $urgencia = $this->calcularUrgencia(
            $categoriaData['id'],
            $request->descripcion,
            $ubicacion,
            $categoriaData['info']['confianza'] ?? null
        );

        return Reporte::create([
            'usuario_id' => $request->usuario_id,
            'categoria_id' => $categoriaData['id'],
            'descripcion' => $request->descripcion,
            'ubicacion' => json_encode($ubicacion),
            'estado' => $estado,
            'nota_admin' => $request->nota_admin, // Agregar el campo nota_admin
            'urgencia' => $this->calcularUrgencia(
                $categoriaData['id'],
                $request->descripcion,
                $ubicacion,
                $categoriaData['info']['confianza'] ?? null
            )
        ]);
    }

    protected function calcularUrgencia($categoriaId, $descripcion, $ubicacion, $confianza = null)
{
    $puntaje = 0;
    $categoria = Categoria::findOrFail($categoriaId);

    // 1. Puntaje por categoría
    $puntaje += match($categoria->nombre) {
        'Daños estructurales',
        'Daños en redes de servicios',
        'Daños causados por fenómenos naturales' => 3,
        'Daños en infraestructuras de transporte',
        'Impacto ambiental asociado',
        'Daños por conflictos humanos' => 2,
        default => 1
    };

    // 2. Puntaje por palabras clave en descripción
    $palabrasClave = ['emergencia', 'urgente', 'peligro', 'colapso', 'fuego', 'accidente'];
    foreach ($palabrasClave as $palabra) {
        if (stripos($descripcion, $palabra) !== false) {
            $puntaje += 2;
            break;
        }
    }

    // 3. Puntaje por confianza en análisis de imagen
    if ($confianza >= 0.9) {
        $puntaje += 2;
    } elseif ($confianza >= 0.7) {
        $puntaje += 1;
    }

    // 4. Puntaje por reportes recientes en la misma ubicación
    $ubicacionStr = json_encode(['lat' => $ubicacion['lat'], 'lon' => $ubicacion['lon']]);
    $reportesRecientes = Reporte::where('ubicacion', $ubicacionStr)
        ->where('created_at', '>=', now()->subHours(12))
        ->count();

    if ($reportesRecientes >= 3) {
        $puntaje += 3;
    } elseif ($reportesRecientes >= 1) {
        $puntaje += 1;
    }

    // Determinar nivel de urgencia
    return match(true) {
        $puntaje >= 6 => 'crítico',
        $puntaje >= 4 => 'alto',
        $puntaje >= 2 => 'medio',
        default => 'bajo'
    };
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