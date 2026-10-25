<?php

namespace App\Http\Controllers;

use App\Jobs\ProcesarImportacionCfdiJob;
use App\Models\Empresa;
use App\Models\ImportacionCfdi;
use App\Models\Uma;
use App\Services\CfdiExcelImporterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaImportarCfdiController extends Controller
{
    /**
     * Show the form to upload CFDI Excel file for the company.
     */
    public function create(Empresa $empresa): Response
    {
        $historial = $empresa->importacionesCfdi()
            ->withCount('salarios')
            ->latest()
            ->take(15)
            ->get();

        $aniosUmas = Uma::select('anio')->orderBy('anio', 'desc')->pluck('anio')->toArray();
        $currentYear = (int) date('Y');
        $aniosDisponibles = array_values(array_unique(array_merge([$currentYear, $currentYear - 1, $currentYear - 2], $aniosUmas)));
        rsort($aniosDisponibles);

        return Inertia::render('empresas/ImportarCfdi', [
            'empresa' => $empresa,
            'historial' => $historial,
            'aniosDisponibles' => $aniosDisponibles,
        ]);
    }

    /**
     * Handle upload and queue CFDI Excel processing.
     */
    public function store(Request $request, Empresa $empresa, CfdiExcelImporterService $importerService): RedirectResponse
    {
        $validated = $request->validate([
            'anio' => ['required', 'integer', 'between:2000,2100'],
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:102400'], // Up to 100MB
        ], [
            'anio.required' => 'El año fiscal es obligatorio.',
            'anio.integer' => 'El año debe ser un número entero.',
            'archivo.required' => 'Debes seleccionar un archivo Excel para importar.',
            'archivo.mimes' => 'El archivo debe estar en formato .xlsx, .xls o .csv.',
            'archivo.max' => 'El archivo no debe exceder los 100 MB.',
        ]);

        $file = $request->file('archivo');

        // Pre-validate file:
        // 1. RFC emisor must exist in system and match the current company.
        // 2. Employees must only belong to one company.
        $importerService->validarArchivo(
            $file->getRealPath(),
            $empresa,
            $file->getClientOriginalExtension()
        );

        $originalName = $file->getClientOriginalName();
        $path = $file->store("imports/empresa_{$empresa->id}", 'local');

        $importacion = ImportacionCfdi::create([
            'empresa_id' => $empresa->id,
            'anio' => $validated['anio'],
            'archivo_nombre' => $originalName,
            'archivo_ruta' => $path,
            'estado' => 'pendiente',
            'total_filas' => 0,
            'filas_procesadas' => 0,
            'filas_con_error' => 0,
            'errores' => [],
        ]);

        // Dispatch background processing job
        ProcesarImportacionCfdiJob::dispatch($importacion->id);

        return redirect()->route('empresas.importar-cfdi.show', [
            'empresa' => $empresa->id,
            'importacion' => $importacion->id,
        ])->with('success', 'Archivo recibido. La importación ha comenzado en segundo plano.');
    }

    /**
     * Show live progress view for an import.
     */
    public function show(Empresa $empresa, ImportacionCfdi $importacion): Response
    {
        abort_if($importacion->empresa_id !== $empresa->id, 404);

        $salariosMuestra = $importacion->salarios()
            ->with(['empleado', 'categoria'])
            ->latest()
            ->take(15)
            ->get();

        return Inertia::render('empresas/ImportacionProgreso', [
            'empresa' => $empresa,
            'importacion' => $importacion,
            'salariosMuestra' => $salariosMuestra,
        ]);
    }

    /**
     * Return JSON status for real-time progress polling.
     */
    public function estado(Empresa $empresa, ImportacionCfdi $importacion): JsonResponse
    {
        abort_if($importacion->empresa_id !== $empresa->id, 404);

        return response()->json([
            'id' => $importacion->id,
            'estado' => $importacion->estado,
            'total_filas' => $importacion->total_filas,
            'filas_procesadas' => $importacion->filas_procesadas,
            'filas_con_error' => $importacion->filas_con_error,
            'porcentaje' => $importacion->porcentaje,
            'errores' => $importacion->errores ?? [],
            'ultimo_error' => $importacion->ultimo_error,
            'iniciado_a' => $importacion->iniciado_a?->toIso8601String(),
            'finalizado_a' => $importacion->finalizado_a?->toIso8601String(),
        ]);
    }

    /**
     * Resume or retry a failed / paused import.
     */
    public function reintentar(Empresa $empresa, ImportacionCfdi $importacion): RedirectResponse
    {
        abort_if($importacion->empresa_id !== $empresa->id, 404);

        $importacion->update([
            'estado' => 'pendiente',
            'ultimo_error' => null,
        ]);

        ProcesarImportacionCfdiJob::dispatch($importacion->id, true);

        return back()->with('success', 'Reanudando importación desde la última fila procesada...');
    }
}
