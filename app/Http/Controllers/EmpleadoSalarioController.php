<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\EmpleadoSalario;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmpleadoSalarioController extends Controller
{
    /**
     * Display a listing of employee salaries with filtering by company, year, and period.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $empresaId = $request->input('empresa_id');
        $anio = $request->input('anio');
        $periodo = $request->input('periodo');
        $soloExcedidos = $request->boolean('solo_excedidos');
        $soloDiferencias = $request->boolean('solo_diferencias');
        $sinDiferencias = $request->boolean('sin_diferencias');
        $diferenciaFiltro = $request->input('diferencia'); // 'con', 'sin', or null

        $sort = $request->input('sort', 'id');
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);

        $allowedSorts = [
            'id',
            'nombre',
            'empleado_nombre',
            'anio',
            'periodo',
            'salario_base',
            'salario_diario_integrado',
            'limite_salario_diario',
            'dias_vacaciones',
            'aguinaldo_diario',
            'prima_vacacional',
            'cantidad_dias',
            'importe_suma',
            'sdi_variable',
            'sdi_total',
            'sdi_auditado',
            'imss_cia',
            'diferencia',
            'created_at',
        ];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'id';
        }

        $baseQuery = EmpleadoSalario::query()
            ->when($empresaId, function ($query, $id) {
                $query->where('empresa_id', $id);
            })
            ->when($anio, function ($query, $yr) {
                $query->where('anio', $yr);
            })
            ->when($periodo, function ($query, $per) {
                $query->where('periodo', $per);
            })
            ->when($soloExcedidos, function ($query) {
                $query->where('excede_limite', true);
            })
            ->when($soloDiferencias || $diferenciaFiltro === 'con', function ($query) {
                $query->where('diferencia', '!=', 0);
            })
            ->when($sinDiferencias || $diferenciaFiltro === 'sin', function ($query) {
                $query->where('diferencia', 0);
            })
            ->when($search, function ($query, $term) {
                $query->whereHas('empleado', function ($empQuery) use ($term) {
                    $empQuery->where('nombre', 'like', "%{$term}%")
                        ->orWhere('rfc_receptor', 'like', "%{$term}%")
                        ->orWhere('numero_empleado', 'like', "%{$term}%")
                        ->orWhere('curp', 'like', "%{$term}%")
                        ->orWhere('numero_seguro_social', 'like', "%{$term}%");
                });
            });

        // Totals for metrics header based on current filters
        $totales = [
            'total_registros' => (clone $baseQuery)->count(),
            'promedio_salario_base' => round((float) ((clone $baseQuery)->avg('salario_base') ?? 0), 2),
            'promedio_sdi' => round((float) ((clone $baseQuery)->avg('salario_diario_integrado') ?? 0), 2),
            'con_exceso_tope' => (clone $baseQuery)->where('excede_limite', true)->count(),
            'con_diferencias' => (clone $baseQuery)->where('diferencia', '!=', 0)->count(),
            'total_diferencia' => round((float) ((clone $baseQuery)->sum('diferencia') ?? 0), 2),
        ];

        $salariosQuery = $baseQuery
            ->with([
                'empleado:id,empresa_id,nombre,rfc_receptor,numero_empleado,curp,numero_seguro_social,antiguedad_anios,antiguedad_semanas,es_sindicalizado,fecha_inicio_relacion_laboral,registro_patronal',
                'empresa:id,nombre,rfc,dias_aguinaldo,prima_vacacional',
                'categoria:id,nombre',
            ]);

        if (in_array($sort, ['nombre', 'empleado_nombre'], true)) {
            $salariosQuery->orderBy(
                Empleado::select('nombre')
                    ->whereColumn('empleados.id', 'empleado_salarios.empleado_id'),
                $direction
            );
        } else {
            $salariosQuery->orderBy($sort, $direction);
        }

        $salarios = $salariosQuery
            ->paginate($perPage)
            ->withQueryString();

        // Filter options for dropdowns
        $empresas = Empresa::query()
            ->select(['id', 'nombre', 'rfc'])
            ->orderBy('nombre')
            ->get();

        $anios = EmpleadoSalario::query()
            ->select('anio')
            ->distinct()
            ->orderBy('anio', 'desc')
            ->pluck('anio')
            ->toArray();

        if (empty($anios)) {
            $curYear = (int) date('Y');
            $anios = [$curYear, $curYear - 1];
        }

        $periodos = EmpleadoSalario::query()
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->when($anio, fn ($q) => $q->where('anio', $anio))
            ->select('periodo')
            ->distinct()
            ->orderByRaw('CAST(periodo AS UNSIGNED) ASC, periodo ASC')
            ->pluck('periodo')
            ->toArray();

        return Inertia::render('salarios/Index', [
            'salarios' => $salarios,
            'empresas' => $empresas,
            'anios' => $anios,
            'periodos' => $periodos,
            'totales' => $totales,
            'filters' => [
                'search' => $search,
                'empresa_id' => $empresaId ? (int) $empresaId : null,
                'anio' => $anio ? (int) $anio : null,
                'periodo' => $periodo ? (string) $periodo : null,
                'solo_excedidos' => $soloExcedidos,
                'solo_diferencias' => $soloDiferencias || $diferenciaFiltro === 'con',
                'sin_diferencias' => $sinDiferencias || $diferenciaFiltro === 'sin',
                'diferencia' => $diferenciaFiltro,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }
}
