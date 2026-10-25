<?php

namespace App\Http\Controllers;

use App\Jobs\ProcesarImportacionCuotasImssJob;
use App\Models\Empresa;
use App\Models\ImportacionCuotaImss;
use App\Models\Uma;
use App\Services\CuotasImssImporterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaImportarCuotasImssController extends Controller
{
    /**
     * Show form to upload employee IMSS quotas Excel file and past history.
     */
    public function create(Empresa $empresa): Response
    {
        $historial = $empresa->importacionesCuotasImss()
            ->latest()
            ->take(15)
            ->get();

        $aniosUmas = Uma::select('anio')->orderBy('anio', 'desc')->pluck('anio')->toArray();
        $currentYear = (int) date('Y');
        $aniosDisponibles = array_values(array_unique(array_merge([$currentYear, $currentYear - 1, $currentYear - 2], $aniosUmas)));
        rsort($aniosDisponibles);

        return Inertia::render('empresas/ImportarCuotasImss', [
            'empresa' => $empresa,
            'historial' => $historial,
            'aniosDisponibles' => $aniosDisponibles,
        ]);
    }

    /**
     * Process IMSS quotas Excel file upload, queue job and redirect to progress visualizer.
     */
    public function store(
        Request $request,
        Empresa $empresa,
        CuotasImssImporterService $importerService
    ): RedirectResponse {
        $validated = $request->validate([
            'anio' => ['required', 'integer', 'between:2000,2100'],
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:102400'],
        ], [
            'anio.required' => 'El año es obligatorio.',
            'anio.integer' => 'El año debe ser un número entero.',
            'archivo.required' => 'Debes seleccionar un archivo Excel para importar.',
            'archivo.mimes' => 'El archivo debe estar en formato .xlsx, .xls o .csv.',
            'archivo.max' => 'El archivo no debe exceder los 100 MB.',
        ]);

        $file = $request->file('archivo');

        // Pre-validate file headers before dispatching
        $importerService->validarArchivo(
            $file->getRealPath(),
            $file->getClientOriginalExtension()
        );

        $originalName = $file->getClientOriginalName();
        $path = $file->store("imports/cuotas_imss_{$empresa->id}", 'local');

        $importacion = ImportacionCuotaImss::create([
            'empresa_id' => $empresa->id,
            'anio' => (int) $validated['anio'],
            'archivo_nombre' => $originalName,
            'archivo_ruta' => $path,
            'estado' => 'pendiente',
            'total_filas' => 0,
            'filas_procesadas' => 0,
            'filas_con_error' => 0,
            'cuotas_creadas' => 0,
            'salarios_actualizados' => 0,
            'total_auditoria' => 0,
            'total_imss' => 0,
            'total_diferencia' => 0,
            'empleados_no_encontrados' => [],
            'errores' => [],
        ]);

        ProcesarImportacionCuotasImssJob::dispatch($importacion->id);

        return redirect()->route('empresas.importar-cuotas-imss.show', [
            'empresa' => $empresa->id,
            'importacion' => $importacion->id,
        ])->with('success', 'Archivo recibido. La importación de cuotas IMSS ha comenzado en segundo plano.');
    }

    /**
     * Show live progress and records visualizer for an IMSS quotas import.
     */
    public function show(Request $request, Empresa $empresa, ImportacionCuotaImss $importacion): Response
    {
        abort_if($importacion->empresa_id !== $empresa->id, 404);

        $search = $request->string('search')->trim()->value();
        $periodo = $request->input('periodo');

        $cuotasQuery = $importacion->cuotas()
            ->with(['empleado', 'salario'])
            ->when($periodo, fn ($q) => $q->where('periodo', $periodo))
            ->when($search, function ($q, $term) {
                $q->where(function ($sq) use ($term) {
                    $sq->where('nombre', 'like', "%{$term}%")
                        ->orWhere('nss', 'like', "%{$term}%")
                        ->orWhere('clave', 'like', "%{$term}%")
                        ->orWhere('registro_patronal', 'like', "%{$term}%");
                });
            });

        $cuotasPaginadas = $cuotasQuery->orderBy('id', 'asc')
            ->paginate(50)
            ->withQueryString();

        $periodosDisponibles = $importacion->cuotas()
            ->whereNotNull('periodo')
            ->select('periodo')
            ->distinct()
            ->pluck('periodo')
            ->sortBy(fn ($p) => is_numeric($p) ? (int) $p : $p, SORT_NATURAL)
            ->values()
            ->all();

        return Inertia::render('empresas/ImportacionCuotasProgreso', [
            'empresa' => $empresa,
            'importacion' => $importacion,
            'cuotas' => $cuotasPaginadas,
            'periodosDisponibles' => $periodosDisponibles,
            'filters' => [
                'search' => $search,
                'periodo' => $periodo,
            ],
        ]);
    }

    /**
     * Return JSON status for real-time progress polling.
     */
    public function estado(Empresa $empresa, ImportacionCuotaImss $importacion): JsonResponse
    {
        abort_if($importacion->empresa_id !== $empresa->id, 404);

        return response()->json([
            'id' => $importacion->id,
            'estado' => $importacion->estado,
            'total_filas' => $importacion->total_filas,
            'filas_procesadas' => $importacion->filas_procesadas,
            'filas_con_error' => $importacion->filas_con_error,
            'cuotas_creadas' => $importacion->cuotas_creadas,
            'salarios_actualizados' => $importacion->salarios_actualizados,
            'total_auditoria' => (float) $importacion->total_auditoria,
            'total_imss' => (float) $importacion->total_imss,
            'total_diferencia' => (float) $importacion->total_diferencia,
            'empleados_no_encontrados' => $importacion->empleados_no_encontrados ?? [],
            'porcentaje' => $importacion->porcentaje,
            'errores' => $importacion->errores ?? [],
            'ultimo_error' => $importacion->ultimo_error,
            'iniciado_a' => $importacion->iniciado_a?->toISOString(),
            'finalizado_a' => $importacion->finalizado_a?->toISOString(),
        ]);
    }

    /**
     * Retry a failed IMSS quotas import.
     */
    public function reintentar(Empresa $empresa, ImportacionCuotaImss $importacion): RedirectResponse
    {
        abort_if($importacion->empresa_id !== $empresa->id, 404);

        $importacion->update([
            'estado' => 'pendiente',
            'ultimo_error' => null,
        ]);

        ProcesarImportacionCuotasImssJob::dispatch($importacion->id, true);

        return back()->with('success', 'Se ha reanudado la importación de cuotas IMSS.');
    }
}
