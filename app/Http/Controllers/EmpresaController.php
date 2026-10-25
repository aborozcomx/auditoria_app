<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmpresaRequest;
use App\Http\Requests\UpdateEmpresaRequest;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaController extends Controller
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

        $allowedSorts = ['id', 'nombre', 'rfc', 'razon_social', 'dias_aguinaldo', 'prima_vacacional', 'created_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'nombre';
        }

        $empresas = Empresa::query()
            ->select(['id', 'nombre', 'rfc', 'razon_social', 'dias_aguinaldo', 'prima_vacacional', 'created_at'])
            ->when($search, function ($query, $term) {
                $query->where(function ($sub) use ($term) {
                    $sub->where('nombre', 'like', "%{$term}%")
                        ->orWhere('rfc', 'like', "%{$term}%")
                        ->orWhere('razon_social', 'like', "%{$term}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('empresas/Index', [
            'empresas' => $empresas,
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
    public function store(StoreEmpresaRequest $request): RedirectResponse
    {
        Empresa::create($request->validated());

        return back()->with('success', 'Empresa dada de alta correctamente.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmpresaRequest $request, Empresa $empresa): RedirectResponse
    {
        $empresa->update($request->validated());

        return back()->with('success', 'Empresa actualizada correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Empresa $empresa): RedirectResponse
    {
        $empresa->delete();

        return back()->with('success', 'Empresa eliminada correctamente.');
    }
}
