<?php

namespace App\Rest\Controllers;

use App\Models\Categoria;
use App\Rest\Controller as RestController;
use App\Rest\Resources\CategoriaResource;
use Illuminate\Http\Request;

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

    // Método para crear nuevas categorías
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:categorias,nombre',
            'descripcion' => 'nullable|string',
            'es_autogenerada' => 'sometimes|boolean'
        ]);

        $categoria = Categoria::create([
            'nombre' => ucfirst($validated['nombre']),
            'descripcion' => $validated['descripcion'] ?? 'Descripción automática',
            'es_autogenerada' => $validated['es_autogenerada'] ?? false
        ]);

        return response()->json([
            'message' => 'Categoría creada exitosamente',
            'data' => $categoria
        ], 201);
    }

}
