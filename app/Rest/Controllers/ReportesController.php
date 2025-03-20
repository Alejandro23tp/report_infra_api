<?php

namespace App\Rest\Controllers;

use App\Models\Reporte;
use App\Rest\Controller as RestController;
use App\Rest\Resources\ReporteResource;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\Categoria;
use Illuminate\Support\Facades\DB;

class ReportesController extends RestController
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<\Lomkit\Rest\Http\Resource>
     */
    public static $resource = ReporteResource::class;

    private function clasificarCategoria($labels) {
        $categorias = [
            'Daños estructurales' => [
                'building damage', 'structural damage', 'wall damage', 'crack', 
                'broken structure', 'construction damage', 'foundation damage'
            ],
            'Daños en redes de servicios' => [
                'utility damage', 'electrical damage', 'water leak', 'broken pipe',
                'cable damage', 'power line', 'utility infrastructure'
            ],
            'Daños en infraestructuras de transporte' => [
                'road damage', 'pothole', 'broken pavement', 'bridge damage',
                'traffic infrastructure', 'street damage', 'sidewalk damage'
            ],
            'Daños causados por fenómenos naturales' => [
                'flood damage', 'storm damage', 'landslide', 'weather damage',
                'natural disaster', 'erosion', 'earthquake damage'
            ],
            'Daños en espacios públicos' => [
                'park damage', 'plaza damage', 'public space', 'bench damage',
                'playground damage', 'public furniture', 'street light damage'
            ],
            'Impacto ambiental asociado' => [
                'pollution', 'environmental damage', 'waste', 'contamination',
                'ecological damage', 'environmental impact', 'debris'
            ],
            'Daños por conflictos humanos' => [
                'vandalism', 'graffiti', 'intentional damage', 'human caused',
                'destruction', 'sabotage', 'criminal damage'
            ]
        ];

        $puntajes = array_fill_keys(array_keys($categorias), 0);

        foreach ($labels as $label) {
            $labelLower = strtolower($label);
            foreach ($categorias as $categoria => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($labelLower, $keyword)) {
                        $puntajes[$categoria] += 1;
                        break;
                    }
                }
            }
        }

        arsort($puntajes);
        $categoriaSeleccionada = key($puntajes);
        $confianza = current($puntajes) > 0 ? current($puntajes) / count($labels) : 0;

        return [
            'categoria' => $categoriaSeleccionada,
            'confianza' => $confianza,
            'puntajes' => $puntajes
        ];
    }

    private function esImagenRelevante($etiquetas) {
        // Palabras clave que indican daños en infraestructura urbana
        $palabrasClaveRelevantes = [
            // Daños generales
            'damage', 'broken', 'crack', 'deterioration', 'decay', 'worn',
            'destroyed', 'collapse', 'failure', 'defect', 'hole', 'leak',
            
            // Infraestructura vial
            'road', 'street', 'highway', 'sidewalk', 'pavement', 'asphalt',
            'pothole', 'curb', 'crossing', 'intersection', 'pathway', 'lane',
            
            // Infraestructura urbana
            'infrastructure', 'building', 'bridge', 'tunnel', 'construction',
            'wall', 'fence', 'barrier', 'foundation', 'column', 'pillar',
            
            // Servicios públicos
            'utility', 'pipe', 'drainage', 'sewer', 'manhole', 'gutter',
            'electrical', 'wiring', 'cable', 'pole', 'lighting', 'lamppost',
            
            // Materiales
            'concrete', 'asphalt', 'metal', 'steel', 'iron', 'wood',
            'brick', 'stone', 'cement', 'material', 'surface', 'structure',
            
            // Tipos de daños
            'erosion', 'corrosion', 'rust', 'rupture', 'breakage', 'obstruction',
            'deformation', 'displacement', 'sinking', 'flooding', 'spill',
            
            // Mobiliario urbano
            'bench', 'sign', 'signal', 'traffic light', 'bus stop', 'shelter',
            'guardrail', 'railing', 'fence', 'bin', 'drain', 'hydrant',
            
            // Descriptores de estado
            'unsafe', 'hazard', 'risk', 'emergency', 'structural', 'critical',
            'urgent', 'severe', 'dangerous', 'poor condition', 'maintenance'
        ];
    
        $contadorRelevante = 0;
        foreach ($etiquetas as $etiqueta) {
            $etiquetaLower = strtolower($etiqueta);
            foreach ($palabrasClaveRelevantes as $palabra) {
                if (str_contains($etiquetaLower, $palabra)) {
                    $contadorRelevante++;
                }
            }
        }
    
        // Requiere al menos 2 palabras clave relevantes
        return $contadorRelevante >= 2;
    }

    private function verificarImagenConGoogleCloud($imagen)
    {
        try {
            // Obtener y decodificar credenciales desde .env
            $encodedCredentials = env('GOOGLE_CREDENTIALS_BASE64');
            if (!$encodedCredentials) {
                throw new \Exception('Credenciales de Google Cloud no configuradas');
            }

            $decodedCredentials = base64_decode($encodedCredentials);
            $credentials = json_decode($decodedCredentials, true);

            if (!$credentials) {
                throw new \Exception('Error al decodificar las credenciales');
            }

            // Crear cliente con credenciales decodificadas
            $imageAnnotator = new ImageAnnotatorClient([
                'credentials' => $credentials
            ]);
            
            // Asegurarse de que la imagen es válida
            if (!$imagen->isValid()) {
                throw new \Exception('Archivo de imagen no válido');
            }

            $image = file_get_contents($imagen->getPathname());
            $response = $imageAnnotator->labelDetection($image);
            $labels = $response->getLabelAnnotations();

            $etiquetas = [];
            foreach ($labels as $label) {
                $etiquetas[] = $label->getDescription();
            }

            // Verificar relevancia
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

        } catch (\Exception $e) {
            Log::error('Error en Google Vision:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } finally {
            if (isset($imageAnnotator)) {
                $imageAnnotator->close();
            }
        }
    }

    private function obtenerOCrearCategoria($nombreCategoria) {
        try {
            Log::info('Buscando categoría:', ['nombre' => $nombreCategoria]);
            
            $categoria = Categoria::where('nombre', $nombreCategoria)->first();
            
            if (!$categoria) {
                Log::info('Categoría no encontrada, creando nueva:', ['nombre' => $nombreCategoria]);
                
                $categoria = DB::transaction(function() use ($nombreCategoria) {
                    return Categoria::create([
                        'nombre' => $nombreCategoria,
                        'descripcion' => 'Categoría detectada automáticamente',
                        'activo' => true
                    ]);
                });
                
                Log::info('Categoría creada exitosamente:', [
                    'id' => $categoria->id,
                    'nombre' => $categoria->nombre
                ]);
            } else {
                Log::info('Categoría existente encontrada:', [
                    'id' => $categoria->id,
                    'nombre' => $categoria->nombre
                ]);
            }
            
            return $categoria;
        } catch (\Exception $e) {
            Log::error('Error en obtenerOCrearCategoria:', [
                'nombre' => $nombreCategoria,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Error al procesar la categoría: ' . $e->getMessage());
        }
    }

    public function analizarImagen(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'imagen' => 'required|file|image|mimes:jpeg,png,jpg|max:10240'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 400);
            }

            $resultado = $this->verificarImagenConGoogleCloud($request->file('imagen'));
            return response()->json($resultado);

        } catch (\Exception $e) {
            Log::error('Error al analizar imagen:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al analizar la imagen'
            ], 500);
        }
    }

    public function crearReporte(Request $request)
    {
        try {
            // Verificar si el usuario existe
            $usuario = \App\Models\User::find($request->usuario_id);
            if (!$usuario) {
                return response()->json([
                    'error' => 'Usuario no encontrado',
                    'details' => 'El usuario_id proporcionado no existe'
                ], 404);
            }

            Log::info('Iniciando creación de reporte', [
                'request_all' => $request->all(),
                'files' => $request->allFiles(),
                'headers' => $request->headers->all()
            ]);

            // Validación básica sin requerir categoria_id
            $validator = Validator::make($request->all(), [
                'usuario_id' => 'required',
                'descripcion' => 'required|string',
                'ubicacion' => 'required',
                'imagen' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:10240'
            ]);

            if ($validator->fails()) {
                Log::error('Validación fallida', ['errors' => $validator->errors()->toArray()]);
                return response()->json([
                    'error' => 'Error de validación',
                    'details' => $validator->errors()
                ], 422);
            }

            // Procesar imagen y obtener categoría
            $categoriaId = null;
            $categoriaInfo = null;
            $resultado = null;

            if ($request->hasFile('imagen')) {
                $resultado = $this->verificarImagenConGoogleCloud($request->file('imagen'));
                Log::info('Resultado análisis de imagen:', $resultado);
                
                if ($resultado['success'] && !empty($resultado['categoria_sugerida'])) {
                    try {
                        $categoria = $this->obtenerOCrearCategoria($resultado['categoria_sugerida']);
                        $categoriaId = $categoria->id;
                        $categoriaInfo = [
                            'id' => $categoria->id,
                            'nombre' => $categoria->nombre,
                            'sugerida' => true,
                            'confianza' => $resultado['confianza']
                        ];
                    } catch (\Exception $e) {
                        Log::error('Error al procesar categoría sugerida:', ['error' => $e->getMessage()]);
                        // Si falla, usaremos la categoría por defecto
                    }
                }
            }

            // Si no se pudo obtener una categoría, usar la por defecto
            if (!$categoriaId) {
                try {
                    $categoria = $this->obtenerOCrearCategoria('Sin clasificar');
                    $categoriaId = $categoria->id;
                    $categoriaInfo = [
                        'id' => $categoria->id,
                        'nombre' => $categoria->nombre,
                        'sugerida' => false
                    ];
                } catch (\Exception $e) {
                    Log::error('Error al crear categoría por defecto:', ['error' => $e->getMessage()]);
                    return response()->json([
                        'error' => 'Error al procesar la categoría',
                        'message' => $e->getMessage()
                    ], 500);
                }
            }

            // Procesar ubicación
            try {
                $ubicacion = $request->ubicacion;
                if (is_string($ubicacion)) {
                    $ubicacion = json_decode($ubicacion, true);
                }

                if (!is_array($ubicacion)) {
                    $ubicacion = json_decode(json_encode($ubicacion), true);
                }

                Log::info('Ubicación procesada', ['ubicacion' => $ubicacion]);

                if (!isset($ubicacion['lat']) || !isset($ubicacion['lon'])) {
                    throw new \Exception('Ubicación inválida: debe contener lat y lon');
                }
            } catch (\Exception $e) {
                Log::error('Error procesando ubicación', [
                    'error' => $e->getMessage(),
                    'ubicacion_original' => $request->ubicacion
                ]);
                return response()->json([
                    'error' => 'Error en formato de ubicación',
                    'details' => $e->getMessage()
                ], 422);
            }

            try {
                DB::beginTransaction();

                // Crear reporte base
                $reporteData = [
                    'usuario_id' => $request->usuario_id,
                    'categoria_id' => $categoriaId, // Usar la categoría determinada
                    'descripcion' => $request->descripcion,
                    'ubicacion' => json_encode([
                        'lat' => (float)$ubicacion['lat'],
                        'lon' => (float)$ubicacion['lon']
                    ]),
                    'estado' => $request->estado ?? 'pendiente',
                    'urgencia' => $request->urgencia ?? 'normal'
                ];

                Log::info('Creando reporte con datos', ['data' => $reporteData]);
                
                $reporte = Reporte::create($reporteData);

                // Procesar imagen si existe
                if ($request->hasFile('imagen') && $request->file('imagen')->isValid()) {
                    $file = $request->file('imagen');
                    $filename = time() . '_' . $file->getClientOriginalName();
                    
                    $file = $request->file('imagen');
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs('public/reportes', $filename);
                    if (!$path) {
                        throw new \Exception('Error al guardar la imagen');
                    }

                    // Corregir la URL de la imagen para evitar doble slash
                    $reporte->imagen_url = ltrim(Storage::url($path), '/');
                    $reporte->save();
                }

                DB::commit();

                Log::info('Reporte creado exitosamente', ['reporte_id' => $reporte->id]);

                return response()->json([
                    'mensaje' => 'Reporte creado con éxito',
                    'data' => new ReporteResource($reporte),
                    'categoria' => $categoriaInfo,
                    'analisis_imagen' => $resultado['success'] ? $resultado : null
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error en transacción', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error general en crearReporte', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'error' => 'Error al crear el reporte',
                'message' => $e->getMessage()
            ], 500);
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
}
