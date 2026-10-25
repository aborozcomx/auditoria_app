<?php

use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\EmpleadoCuotaImssController;
use App\Http\Controllers\EmpleadoSalarioController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\EmpresaImportarCfdiController;
use App\Http\Controllers\EmpresaImportarCuotasImssController;
use App\Http\Controllers\EmpresaImportarVariablesController;
use App\Http\Controllers\ExportarAuditoriaController;
use App\Http\Controllers\UmaController;
use App\Http\Controllers\VacacionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::resource('umas', UmaController::class)->except(['create', 'show', 'edit']);
    Route::resource('categorias', CategoriaController::class)->except(['create', 'show', 'edit']);
    Route::resource('vacaciones', VacacionController::class)
        ->parameters(['vacaciones' => 'vacacion'])
        ->except(['create', 'show', 'edit']);
    Route::resource('empresas', EmpresaController::class)->except(['create', 'show', 'edit']);

    Route::get('salarios', [EmpleadoSalarioController::class, 'index'])->name('salarios.index');
    Route::get('cuotas-imss', [EmpleadoCuotaImssController::class, 'index'])->name('cuotas-imss.index');
    Route::get('exportar-auditoria', [ExportarAuditoriaController::class, 'index'])->name('exportar-auditoria.index');
    Route::get('exportar-auditoria/descargar', [ExportarAuditoriaController::class, 'descargar'])->name('exportar-auditoria.descargar');
    Route::get('exportar-auditoria/resumen', [ExportarAuditoriaController::class, 'resumen'])->name('exportar-auditoria.resumen');

    Route::get('empresas/{empresa}/importar-cfdi', [EmpresaImportarCfdiController::class, 'create'])
        ->name('empresas.importar-cfdi.create');
    Route::post('empresas/{empresa}/importar-cfdi', [EmpresaImportarCfdiController::class, 'store'])
        ->name('empresas.importar-cfdi.store');
    Route::get('empresas/{empresa}/importar-cfdi/{importacion}', [EmpresaImportarCfdiController::class, 'show'])
        ->name('empresas.importar-cfdi.show');
    Route::get('empresas/{empresa}/importar-cfdi/{importacion}/estado', [EmpresaImportarCfdiController::class, 'estado'])
        ->name('empresas.importar-cfdi.estado');
    Route::post('empresas/{empresa}/importar-cfdi/{importacion}/reintentar', [EmpresaImportarCfdiController::class, 'reintentar'])
        ->name('empresas.importar-cfdi.reintentar');

    Route::get('empresas/{empresa}/importar-variables', [EmpresaImportarVariablesController::class, 'create'])
        ->name('empresas.importar-variables.create');
    Route::post('empresas/{empresa}/importar-variables', [EmpresaImportarVariablesController::class, 'store'])
        ->name('empresas.importar-variables.store');
    Route::get('empresas/{empresa}/importar-variables/{importacion}', [EmpresaImportarVariablesController::class, 'show'])
        ->name('empresas.importar-variables.show');
    Route::get('empresas/{empresa}/importar-variables/{importacion}/estado', [EmpresaImportarVariablesController::class, 'estado'])
        ->name('empresas.importar-variables.estado');
    Route::post('empresas/{empresa}/importar-variables/{importacion}/reintentar', [EmpresaImportarVariablesController::class, 'reintentar'])
        ->name('empresas.importar-variables.reintentar');

    Route::get('empresas/{empresa}/importar-cuotas-imss', [EmpresaImportarCuotasImssController::class, 'create'])
        ->name('empresas.importar-cuotas-imss.create');
    Route::post('empresas/{empresa}/importar-cuotas-imss', [EmpresaImportarCuotasImssController::class, 'store'])
        ->name('empresas.importar-cuotas-imss.store');
    Route::get('empresas/{empresa}/importar-cuotas-imss/{importacion}', [EmpresaImportarCuotasImssController::class, 'show'])
        ->name('empresas.importar-cuotas-imss.show');
    Route::get('empresas/{empresa}/importar-cuotas-imss/{importacion}/estado', [EmpresaImportarCuotasImssController::class, 'estado'])
        ->name('empresas.importar-cuotas-imss.estado');
    Route::post('empresas/{empresa}/importar-cuotas-imss/{importacion}/reintentar', [EmpresaImportarCuotasImssController::class, 'reintentar'])
        ->name('empresas.importar-cuotas-imss.reintentar');
});

require __DIR__.'/settings.php';
