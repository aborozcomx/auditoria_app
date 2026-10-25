<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVacacionRequest;
use App\Http\Requests\UpdateVacacionRequest;
use App\Models\Categoria;
use App\Models\Vacacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VacacionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $categoriaId = $request->input('categoria_id');
        $sort = $request->input('sort', 'anios');
        $direction = strtolower($request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) $request->input('per_page', 10), 5), 100);

        $allowedSorts = ['id', 'anios', 'dias', 'categoria_id', 'created_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'anios';
        }

        $vacaciones = Vacacion::query()
            ->select(['id', 'categoria_id', 'anios', 'dias', 'created_at'])
            ->with(['categoria:id,nombre'])
            ->when($categoriaId, function ($query, $catId) {
                $query->where('categoria_id', $catId);
            })
            ->when($search, function ($query, $term) {
                $query->where(function ($sub) use ($term) {
                    $sub->where('anios', 'like', "%{$term}%")
                        ->orWhere('dias', 'like', "%{$term}%")
                        ->orWhereHas('categoria', function ($catQuery) use ($term) {
                            $catQuery->where('nombre', 'like', "%{$term}%");
                        });
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        $categorias = Categoria::query()
            ->select(['id', 'nombre'])
            ->orderBy('nombre')
            ->get();

        return Inertia::render('vacaciones/Index', [
            'vacaciones' => $vacaciones,
            'categorias' => $categorias,
            'filters' => [
                'search' => $search,
                'categoria_id' => $categoriaId ? (int) $categoriaId : null,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVacacionRequest $request): RedirectResponse
    {
        Vacacion::create($request->validated());

        return back()->with('success', 'Regla de vacaciones creada correctamente.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVacacionRequest $request, Vacacion $vacacion): RedirectResponse
    {
        $vacacion->update($request->validated());

        return back()->with('success', 'Regla de vacaciones actualizada correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vacacion $vacacion): RedirectResponse
    {
        $vacacion->delete();

        return back()->with('success', 'Regla de vacaciones eliminada correctamente.');
    }
}
