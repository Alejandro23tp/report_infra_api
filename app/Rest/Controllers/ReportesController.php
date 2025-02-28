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

class ReportesController extends RestController
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<\Lomkit\Rest\Http\Resource>
     */
    public static $resource = ReporteResource::class;

    public function crearReporte(Request $request)
    {
        // Verificar si existe una imagen en la solicitud
        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $esInapropiado = $this->verificarImagenConGoogleCloud($file);

            if ($esInapropiado) {
                return response()->json([
                    'error' => 'La imagen no está relacionada con infraestructura o daños',
                ], 400);
            }
        }

        // Decodificar la ubicación desde JSON
        $ubicacion = json_decode($request->ubicacion, true);

        // Crear el reporte con la ubicación (latitud y longitud)
        $reporte = Reporte::create([
            'usuario_id' => $request->usuario_id,
            'categoria_id' => $request->categoria_id,
            'descripcion' => $request->descripcion,
            'ubicacion' => json_encode([
                'lat' => $ubicacion['lat'], 
                'lon' => $ubicacion['lon']
            ]), // Almacenar la ubicación como JSON
            'estado' => $request->estado ?? 'pendiente', // Valor por defecto
            'urgencia' => $request->urgencia ?? 'normal', // Valor por defecto
        ]);

        // Subir la imagen si existe
        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('public/reportes', $filename);
            $reporte->imagen_url = Storage::url($path);
            $reporte->save();
        }

        return response()->json([
            'mensaje' => 'Reporte creado con éxito',
            'data' => new ReporteResource($reporte),
        ]);
    }



    private function verificarImagenConGoogleCloud($file)
{
    $imagePath = $file->getPathname();  // Obtener la ruta del archivo de imagen

    // Especificar la ruta completa del archivo de credenciales
    $credentialsPath = 'C:\\Users\\Administrador\\Desktop\\Tesis parte practica\\API\\reportes_infra_api\\apppracticaap-74b3eade9c0b.json';

    // Crear un cliente para Google Vision con las credenciales especificadas
    $client = new ImageAnnotatorClient([
        'credentials' => $credentialsPath
    ]);

    // Cargar la imagen
    $imageData = file_get_contents($imagePath);
    $image = (new \Google\Cloud\Vision\V1\Image())->setContent($imageData);

    // Detectar etiquetas y objetos en la imagen
    $response = $client->labelDetection($image);
    $labels = $response->getLabelAnnotations();

    // Definir las etiquetas y objetos relevantes para infraestructura o daños
    $infraestructuraEtiquetas = [
        'Traffic light', 'Traffic sign', 'Road', 'Building', 'Signage', 'Pole',
        'Street', 'Bridge', 'Sidewalk', 'Tunnel', 'Lamp post', 'Parking',
        'Highway', 'Street light', 'Traffic cone', 'Guard rail', 'Road markings',
        'Curb', 'Crosswalk', 'Street sign', 'Building facade', 'Fence',
        'Construction site', 'Drain', 'Pothole', 'Construction vehicle', 'Manhole',
        'Wall', 'Roof', 'Window', 'Door', 'Facade', 'Facade damage', 'Building entrance',
        'Street furniture', 'Bicycle lane', 'Bus stop', 'Street corner', 'Public bench'
    ];

    $damagesEtiquetas = [
        'Car', 'Vehicle', 'Broken', 'Damaged', 'Leak', 'Debris', 'Construction',
        'Flood', 'Earthquake', 'Fire', 'Collapse', 'Crack', 'Fallen', 'Rubble',
        'Destroyed', 'Structural damage', 'Pothole', 'Broken glass', 'Crumbling',
        'Defective', 'Vehicle accident', 'Damaged road', 'Obstruction', 'Garbage',
        'Vandalism', 'Fallen tree', 'Broken pipe', 'Sinkhole', 'Hazardous waste',
        'Contamination', 'Urban decay', 'Rotting', 'Damage to street sign', 'Fallen sign',
        'Overturned vehicle'
    ];

    // Verificar si alguna de las etiquetas coincide con las de infraestructura o daños
    foreach ($labels as $label) {
        $labelText = $label->getDescription();

        if (in_array($labelText, $infraestructuraEtiquetas) || in_array($labelText, $damagesEtiquetas)) {
            // Si encontramos una etiqueta relevante, devolver false (indicando que la imagen está relacionada con infraestructura o daños)
            return false;
        }
    }

    // Si no se encontraron etiquetas relevantes, devolver true (la imagen no está relacionada con infraestructura o daños)
    return true;
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
