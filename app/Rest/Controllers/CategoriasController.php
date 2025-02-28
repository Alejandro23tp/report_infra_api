<?php

namespace App\Rest\Controllers;

use App\Models\Categoria;
use App\Rest\Controller as RestController;
use App\Rest\Resources\CategoriaResource;

class CategoriasController extends RestController
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<\Lomkit\Rest\Http\Resource>
     */
    public static $resource = CategoriaResource::class;

    public function index()
    {
        $categorias = Categoria::all(); // Obtener todas las categorías
        return response()->json(['data' => $categorias], 200);
    }
}
