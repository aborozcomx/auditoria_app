<?php

namespace App\Http\Controllers;

use App\Models\EmpleadoCuotaImss;
use App\Models\EmpleadoSalario;
use App\Models\Empresa;
use App\Services\ExportarAuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportarAuditoriaController extends Controller
{
    public function __construct(
        protected ExportarAuditoriaService $exportarAuditoriaService
    ) {}

    /**
     * Display the export audit page with selectors for empresa, anio, and periodo.
     */
    public function index(Request $request): Response
    {
        $empresas = Empresa::orderBy('nombre')
            ->get(['id', 'nombre', 'rfc', 'razon_social']);

        $aniosCuotas = EmpleadoCuotaImss::select('anio')->distinct()->pluck('anio');
        $aniosSalarios = EmpleadoSalario::select('anio')->distinct()->pluck('anio');
        $anios = $aniosCuotas->merge($aniosSalarios)
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        if (empty($anios)) {
            $anios = [(int) now()->year, (int) now()->year - 1];
        }

        $empresaId = (int) ($request->input('empresa_id') ?: ($empresas->first()?->id ?? 0));
        $anio = (int) ($request->input('anio') ?: ($anios[0] ?? now()->year));
        $periodo = (string) ($request->input('periodo') ?: '1');

        $empresa = $empresas->firstWhere('id', $empresaId);

        $totalCuotas = 0;
        $totalSalarios = 0;
        $nombreArchivo = '';
        $periodoRango = '';

        if ($empresa) {
            $totalCuotas = EmpleadoCuotaImss::where('empresa_id', $empresa->id)
                ->where('anio', $anio)
                ->where('periodo', $periodo)
                ->count();

            $totalSalarios = EmpleadoSalario::where('empresa_id', $empresa->id)
                ->where('anio', $anio)
                ->where('periodo', $periodo)
                ->count();

            $nombreArchivo = $this->exportarAuditoriaService->obtenerNombreArchivo($empresa, $anio, $periodo);
            $periodoRango = $this->exportarAuditoriaService->formatPeriodoRango($anio, $periodo);
        }

        return Inertia::render('exportar-auditoria/Index', [
            'empresas' => $empresas,
            'anios' => $anios,
            'initialSelection' => [
                'empresa_id' => $empresaId,
                'anio' => $anio,
                'periodo' => $periodo,
                'total_cuotas' => $totalCuotas,
                'total_salarios' => $totalSalarios,
                'nombre_archivo' => $nombreArchivo,
                'periodo_rango' => $periodoRango,
            ],
        ]);
    }

    /**
     * Get live summary counts and file preview for selected filters.
     */
    public function resumen(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'anio' => ['required', 'integer'],
            'periodo' => ['required', 'string'],
        ]);

        $empresa = Empresa::findOrFail($validated['empresa_id']);
        $anio = (int) $validated['anio'];
        $periodo = (string) $validated['periodo'];

        $totalCuotas = EmpleadoCuotaImss::where('empresa_id', $empresa->id)
            ->where('anio', $anio)
            ->where('periodo', $periodo)
            ->count();

        $totalSalarios = EmpleadoSalario::where('empresa_id', $empresa->id)
            ->where('anio', $anio)
            ->where('periodo', $periodo)
            ->count();

        return response()->json([
            'empresa' => [
                'id' => $empresa->id,
                'nombre' => $empresa->nombre,
                'rfc' => $empresa->rfc,
            ],
            'anio' => $anio,
            'periodo' => $periodo,
            'total_cuotas' => $totalCuotas,
            'total_salarios' => $totalSalarios,
            'nombre_archivo' => $this->exportarAuditoriaService->obtenerNombreArchivo($empresa, $anio, $periodo),
            'periodo_rango' => $this->exportarAuditoriaService->formatPeriodoRango($anio, $periodo),
        ]);
    }

    /**
     * Generate and stream the Excel audit report download.
     */
    public function descargar(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'empresa_id' => ['required', 'exists:empresas,id'],
            'anio' => ['required', 'integer'],
            'periodo' => ['required', 'string'],
        ]);

        $empresa = Empresa::findOrFail($validated['empresa_id']);
        $anio = (int) $validated['anio'];
        $periodo = (string) $validated['periodo'];

        $filePath = $this->exportarAuditoriaService->generarExcel($empresa, $anio, $periodo);
        $fileName = $this->exportarAuditoriaService->obtenerNombreArchivo($empresa, $anio, $periodo);

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
