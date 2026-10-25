<?php

namespace App\Http\Controllers;

use App\Jobs\ProcesarImportacionVariablesJob;
use App\Models\Empresa;
use App\Models\ImportacionVariable;
use App\Models\Uma;
use App\Services\VariablesEmpleadoImporterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaImportarVariablesController extends Controller
{
    /**
     * Show form to upload employee variables Excel file and past history.
     */
    public function create(Empresa $empresa): Response
    {
        $historial = $empresa->importacionesVariables()
            ->latest()
            ->take(15)
            ->get();

        $aniosUmas = Uma::select('anio')->orderBy('anio', 'desc')->pluck('anio')->toArray();
        $currentYear = (int) date('Y');
        $aniosDisponibles = array_values(array_unique(array_merge([$currentYear, $currentYear - 1, $currentYear - 2], $aniosUmas)));
        rsort($aniosDisponibles);

        return Inertia::render('empresas/ImportarVariables', [
            'empresa' => $empresa,
            'historial' => $historial,
            'aniosDisponibles' => $aniosDisponibles,
        ]);
    }

    /**
     * Process variables Excel file upload, queue job and redirect to progress view.
     */
    public function store(
        Request $request,
        Empresa $empresa,
        VariablesEmpleadoImporterService $importerService
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
        $path = $file->store("imports/variables_{$empresa->id}", 'local');

        $importacion = ImportacionVariable::create([
            'empresa_id' => $empresa->id,
            'anio' => (int) $validated['anio'],
            'archivo_nombre' => $originalName,
            'archivo_ruta' => $path,
            'estado' => 'pendiente',
            'total_filas' => 0,
            'filas_procesadas' => 0,
            'filas_con_error' => 0,
            'empleados_actualizados' => 0,
            'salarios_actualizados' => 0,
            'empleados_no_encontrados' => [],
            'errores' => [],
        ]);

        ProcesarImportacionVariablesJob::dispatch($importacion->id);

        return redirect()->route('empresas.importar-variables.show', [
            'empresa' => $empresa->id,
            'importacion' => $importacion->id,
        ])->with('success', 'Archivo recibido. La importación de variables ha comenzado en segundo plano.');
    }

    /**
     * Show live progress view for a variables import.
     */
    public function show(Empresa $empresa, ImportacionVariable $importacion): Response
    {
        abort_if($importacion->empresa_id !== $empresa->id, 404);

        $salariosMuestra = $importacion->salarios()
            ->with(['empleado', 'categoria'])
            ->latest()
            ->take(15)
            ->get();

        return Inertia::render('empresas/ImportacionVariablesProgreso', [
            'empresa' => $empresa,
            'importacion' => $importacion,
            'salariosMuestra' => $salariosMuestra,
        ]);
    }

    /**
     * Return JSON status for real-time progress polling.
     */
    public function estado(Empresa $empresa, ImportacionVariable $importacion): JsonResponse
    {
        abort_if($importacion->empresa_id !== $empresa->id, 404);

        return response()->json([
            'id' => $importacion->id,
            'estado' => $importacion->estado,
            'total_filas' => $importacion->total_filas,
            'filas_procesadas' => $importacion->filas_procesadas,
            'filas_con_error' => $importacion->filas_con_error,
            'empleados_actualizados' => $importacion->empleados_actualizados,
            'salarios_actualizados' => $importacion->salarios_actualizados,
            'empleados_no_encontrados' => $importacion->empleados_no_encontrados ?? [],
            'porcentaje' => $importacion->porcentaje,
            'errores' => $importacion->errores ?? [],
            'ultimo_error' => $importacion->ultimo_error,
            'iniciado_a' => $importacion->iniciado_a?->toISOString(),
            'finalizado_a' => $importacion->finalizado_a?->toISOString(),
        ]);
    }

    /**
     * Retry a failed variables import.
     */
    public function reintentar(Empresa $empresa, ImportacionVariable $importacion): RedirectResponse
    {
        abort_if($importacion->empresa_id !== $empresa->id, 404);

        $importacion->update([
            'estado' => 'pendiente',
            'ultimo_error' => null,
        ]);

        ProcesarImportacionVariablesJob::dispatch($importacion->id, true);

        return back()->with('success', 'Se ha reanudado la importación de variables.');
    }
}
