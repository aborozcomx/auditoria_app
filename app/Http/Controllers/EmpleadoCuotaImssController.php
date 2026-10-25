<?php

namespace App\Http\Controllers;

use App\Models\EmpleadoCuotaImss;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmpleadoCuotaImssController extends Controller
{
    /**
     * Display a listing of employee IMSS quotas with filtering and audit metrics.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $empresaId = $request->input('empresa_id');
        $anio = $request->input('anio');
        $periodo = $request->input('periodo');
        $soloDiferencias = $request->boolean('solo_diferencias');

        $sort = $request->input('sort', 'id');
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);

        $allowedSorts = [
            'id',
            'anio',
            'periodo',
            'nss',
            'nombre',
            'dias',
            'ausencias',
            'incapacidades',
            'total_dias',
            'sdi_cotizado',
            'salario_base',
            'base_price_em',
            'base_price_rt',
            'base_price_iv',
            'fixed_price',
            'sdmg',
            'in_cash',
            'disability_health',
            'pensioners',
            'risk_price',
            'nurseries',
            'total_audit',
            'subtotal',
            'difference',
            'created_at',
        ];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'id';
        }

        $baseQuery = EmpleadoCuotaImss::query()
            ->when($empresaId, function ($query, $id) {
                $query->where('empresa_id', $id);
            })
            ->when($anio, function ($query, $yr) {
                $query->where('anio', $yr);
            })
            ->when($periodo, function ($query, $per) {
                $query->where('periodo', $per);
            })
            ->when($soloDiferencias, function ($query) {
                $query->where('difference', '!=', 0);
            })
            ->when($search, function ($query, $term) {
                $query->where(function ($q) use ($term) {
                    $q->where('nombre', 'like', "%{$term}%")
                        ->orWhere('nss', 'like', "%{$term}%")
                        ->orWhere('clave', 'like', "%{$term}%")
                        ->orWhere('registro_patronal', 'like', "%{$term}%")
                        ->orWhereHas('empleado', function ($empQuery) use ($term) {
                            $empQuery->where('nombre', 'like', "%{$term}%")
                                ->orWhere('rfc_receptor', 'like', "%{$term}%")
                                ->orWhere('numero_empleado', 'like', "%{$term}%");
                        });
                });
            });

        // Totals for metrics cards
        $totales = [
            'total_registros' => (clone $baseQuery)->count(),
            'total_auditoria' => round((float) ((clone $baseQuery)->sum('total_audit') ?? 0), 2),
            'total_imss' => round((float) ((clone $baseQuery)->sum('subtotal') ?? 0), 2),
            'total_diferencia' => round((float) ((clone $baseQuery)->sum('difference') ?? 0), 2),
            'con_diferencias' => (clone $baseQuery)->where('difference', '!=', 0)->count(),
        ];

        $cuotas = $baseQuery
            ->with([
                'empresa:id,nombre,rfc,prima_vacacional',
                'empleado:id,empresa_id,nombre,rfc_receptor,numero_empleado,curp,numero_seguro_social',
                'salario:id,sdi_total,sdi_auditado,salario_diario_integrado,imss_cia,diferencia',
            ])
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        $empresas = Empresa::query()
            ->select(['id', 'nombre', 'rfc'])
            ->orderBy('nombre')
            ->get();

        $anios = EmpleadoCuotaImss::query()
            ->select('anio')
            ->distinct()
            ->orderBy('anio', 'desc')
            ->pluck('anio')
            ->toArray();

        if (empty($anios)) {
            $curYear = (int) date('Y');
            $anios = [$curYear, $curYear - 1];
        }

        $periodos = EmpleadoCuotaImss::query()
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->when($anio, fn ($q) => $q->where('anio', $anio))
            ->select('periodo')
            ->distinct()
            ->orderByRaw('CAST(periodo AS UNSIGNED) ASC, periodo ASC')
            ->pluck('periodo')
            ->toArray();

        return Inertia::render('cuotas-imss/Index', [
            'cuotas' => $cuotas,
            'empresas' => $empresas,
            'anios' => $anios,
            'periodos' => $periodos,
            'totales' => $totales,
            'filters' => [
                'search' => $search,
                'empresa_id' => $empresaId ? (int) $empresaId : null,
                'anio' => $anio ? (int) $anio : null,
                'periodo' => $periodo ? (string) $periodo : null,
                'solo_diferencias' => $soloDiferencias,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }
}
