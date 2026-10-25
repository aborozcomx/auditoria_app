<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUmaRequest;
use App\Http\Requests\UpdateUmaRequest;
use App\Models\Uma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UmaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $sort = $request->input('sort', 'anio');
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) $request->input('per_page', 10), 5), 100);

        $allowedSorts = ['id', 'anio', 'saldo', 'created_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'anio';
        }

        $umas = Uma::query()
            ->select(['id', 'anio', 'saldo', 'created_at'])
            ->when($search, function ($query, $term) {
                $query->where(function ($sub) use ($term) {
                    $sub->where('anio', 'like', "%{$term}%")
                        ->orWhere('saldo', 'like', "%{$term}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('umas/Index', [
            'umas' => $umas,
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
    public function store(StoreUmaRequest $request): RedirectResponse
    {
        Uma::create($request->validated());

        return back()->with('success', 'Registro de UMA guardado correctamente.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUmaRequest $request, Uma $uma): RedirectResponse
    {
        $uma->update($request->validated());

        return back()->with('success', 'Registro de UMA actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Uma $uma): RedirectResponse
    {
        $uma->delete();

        return back()->with('success', 'Registro de UMA eliminado correctamente.');
    }
}
