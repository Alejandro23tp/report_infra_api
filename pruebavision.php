<?php
require 'vendor/autoload.php';

use Google\Cloud\Vision\V1\ImageAnnotatorClient;

// Configura las credenciales
putenv('GOOGLE_APPLICATION_CREDENTIALS=C:\\Users\\Administrador\\Desktop\\Tesis parte practica\\API\\reportes_infra_api\\apppracticaap-74b3eade9c0b.json');

try {
    $client = new ImageAnnotatorClient();

    // Ruta de la imagen a analizar
    $imagePath = 'C:\Users\Administrador\Desktop\Tesis parte practica\API\reportes_infra_api\storage\app\private\public\reportes\1329456.jpeg';
    if (!file_exists($imagePath)) {
        throw new Exception('La imagen no se encontró en: ' . $imagePath);
    }
    $image = file_get_contents($imagePath);

    // Llama a la API para detección de contenido explícito
    $response = $client->safeSearchDetection($image);
    $annotations = $response->getSafeSearchAnnotation();

    print_r([
        'adult' => $annotations->getAdult(),
        'violence' => $annotations->getViolence(),
        'racy' => $annotations->getRacy(),
    ]);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
