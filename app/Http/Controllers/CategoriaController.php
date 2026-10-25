<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoriaRequest;
use App\Http\Requests\UpdateCategoriaRequest;
use App\Models\Categoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoriaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $sort = $request->input('sort', 'nombre');
        $direction = strtolower($request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) $request->input('per_page', 10), 5), 100);

        $allowedSorts = ['id', 'nombre', 'created_at', 'vacaciones_count'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'nombre';
        }

        $categorias = Categoria::query()
            ->select(['id', 'nombre', 'created_at'])
            ->withCount('vacaciones')
            ->when($search, function ($query, $term) {
                $query->where('nombre', 'like', "%{$term}%");
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('categorias/Index', [
            'categorias' => $categorias,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoriaRequest $request): RedirectResponse
    {
        Categoria::create($request->validated());

        return back()->with('success', 'Categoría creada correctamente.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoriaRequest $request, Categoria $categoria): RedirectResponse
    {
        $categoria->update($request->validated());

        return back()->with('success', 'Categoría actualizada correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Categoria $categoria): RedirectResponse
    {
        $categoria->delete();

        return back()->with('success', 'Categoría eliminada correctamente.');
    }
}
