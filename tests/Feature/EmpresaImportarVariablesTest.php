<?php

use App\Jobs\ProcesarImportacionVariablesJob;
use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\EmpleadoSalario;
use App\Models\Empresa;
use App\Models\ImportacionVariable;
use App\Models\Uma;
use App\Models\User;
use App\Services\VariablesEmpleadoImporterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('guests are redirected from importar variables', function () {
    $empresa = Empresa::factory()->create();

    $this->get(route('empresas.importar-variables.create', $empresa))
        ->assertRedirect(route('login'));
});

test('authenticated user can view import variables form with history', function () {
    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();

    $response = $this->actingAs($user)->get(route('empresas.importar-variables.create', $empresa));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('empresas/ImportarVariables')
            ->has('empresa')
            ->has('historial')
            ->has('aniosDisponibles')
        );
});

test('user can upload variables file, it dispatches background job and redirects to progress view', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();

    $csvContent = implode("\n", [
        'Número de personal,Final del período en Nómina,Texto expl.CC-nómina,Cantidad,Importe',
        '1,15/01/2025,Días calendario,15.00,0.00',
        '1,15/01/2025,Parte variable acumulada,0.00,"350.14"',
    ]);

    $file = UploadedFile::fake()->createWithContent('variables_2025.csv', $csvContent);

    $response = $this->actingAs($user)->post(route('empresas.importar-variables.store', $empresa), [
        'anio' => 2025,
        'archivo' => $file,
    ]);
    $importacion = ImportacionVariable::where('empresa_id', $empresa->id)->first();
    expect($importacion)->not->toBeNull();
    expect($importacion->anio)->toBe(2025);
    expect($importacion->estado)->toBe('pendiente');

    Queue::assertPushed(ProcesarImportacionVariablesJob::class, function ($job) use ($importacion) {
        return $job->importacionVariableId === $importacion->id;
    });

    $response->assertRedirect(route('empresas.importar-variables.show', [
        'empresa' => $empresa->id,
        'importacion' => $importacion->id,
    ]));
});

test('job processes variables file and updates salaries correctly', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();
    $categoria = Categoria::create(['nombre' => 'General']);

    Uma::factory()->create([
        'anio' => 2025,
        'saldo' => 108.57,
    ]);

    // Create employee 1
    $empleado1 = Empleado::create([
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'Empleado Uno',
        'rfc_receptor' => 'UNO010101AAA',
        'numero_empleado' => '1',
        'salario_base' => 500.00,
        'salario_diario_integrado' => 550.00,
    ]);

    // Create existing salary records for period 3 and 4 in year 2025
    $salarioP3 = EmpleadoSalario::create([
        'empleado_id' => $empleado1->id,
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'anio' => 2025,
        'periodo' => '3',
        'tipo_nomina' => 'O - Nómina ordinaria',
        'salario_base' => 500.00,
        'salario_diario_integrado' => 550.00,
        'limite_salario_diario' => 2714.25,
        'excede_limite' => false,
        'uma_utilizada' => 108.57,
        'anio_uma' => 2025,
    ]);

    $salarioP4 = EmpleadoSalario::create([
        'empleado_id' => $empleado1->id,
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'anio' => 2025,
        'periodo' => '4',
        'tipo_nomina' => 'O - Nómina ordinaria',
        'salario_base' => 500.00,
        'salario_diario_integrado' => 550.00,
        'limite_salario_diario' => 2714.25,
        'excede_limite' => false,
        'uma_utilizada' => 108.57,
        'anio_uma' => 2025,
    ]);

    // CSV with user example data:
    // Días: 15 + 16 + 15 + 13 = 59
    // Importe: 350.14 + 1051.03 + 364.88 + 316.23 = 2082.28
    $csvContent = implode("\n", [
        'Número de personal,Final del período en Nómina,Texto expl.CC-nómina,Cantidad,Importe',
        '1,15/01/2025,Días calendario,15.00,0.00',
        '1,31/01/2025,Días calendario,16.00,0.00',
        '1,15/02/2025,Días calendario,15.00,0.00',
        '1,28/02/2025,Días calendario,13.00,0.00',
        '1,15/01/2025,Parte variable acumulada,0.00,"350.14"',
        '1,31/01/2025,Parte variable acumulada,0.00,"1,051.03"',
        '1,15/02/2025,Parte variable acumulada,0.00,"364.88"',
        '1,28/02/2025,Parte variable acumulada,0.00,"316.23"',
        // Non-existent employee:
        '999,15/01/2025,Días calendario,15.00,0.00',
        '999,15/01/2025,Parte variable acumulada,0.00,"500.00"',
    ]);

    $file = UploadedFile::fake()->createWithContent('variables_2025.csv', $csvContent);
    $path = $file->store("imports/variables_{$empresa->id}", 'local');

    $importacion = ImportacionVariable::create([
        'empresa_id' => $empresa->id,
        'anio' => 2025,
        'archivo_nombre' => 'variables_2025.csv',
        'archivo_ruta' => $path,
        'estado' => 'pendiente',
        'total_filas' => 0,
        'filas_procesadas' => 0,
        'filas_con_error' => 0,
    ]);

    $job = new ProcesarImportacionVariablesJob($importacion->id);
    $job->handle(app(VariablesEmpleadoImporterService::class));

    $importacion->refresh();
    expect($importacion->estado)->toBe('completado');
    expect($importacion->salarios_actualizados)->toBeGreaterThanOrEqual(2);
    expect($importacion->empleados_actualizados)->toBe(1);
    expect($importacion->empleados_no_encontrados)->toContain('999');

    // Verify P3 and P4 updated
    $salarioP3->refresh();
    $salarioP4->refresh();

    // 59 days
    expect((float) $salarioP3->cantidad_dias)->toBe(59.0);
    expect((float) $salarioP4->cantidad_dias)->toBe(59.0);

    // 2082.28 importe
    expect(round((float) $salarioP3->importe_suma, 2))->toBe(2082.28);
    expect(round((float) $salarioP4->importe_suma, 2))->toBe(2082.28);

    // sdi_variable = 2082.28 / 59 = 35.29
    $expectedSdiVar = round(2082.28 / 59, 2);
    expect(round((float) $salarioP3->sdi_variable, 2))->toBe($expectedSdiVar);

    // sdi_total = 550.00 + 35.29 = 585.29
    expect(round((float) $salarioP3->sdi_total, 2))->toBe(round(550.00 + $expectedSdiVar, 2));

    // sdi_auditado = min(585.29, 2714.25) = 585.29
    expect(round((float) $salarioP3->sdi_auditado, 2))->toBe(round(550.00 + $expectedSdiVar, 2));
});

test('user can view progress page and query json status endpoint', function () {
    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();

    $importacion = ImportacionVariable::create([
        'empresa_id' => $empresa->id,
        'anio' => 2025,
        'archivo_nombre' => 'variables_test.xlsx',
        'archivo_ruta' => 'imports/test.xlsx',
        'estado' => 'procesando',
        'total_filas' => 100,
        'filas_procesadas' => 45,
        'filas_con_error' => 0,
        'empleados_actualizados' => 10,
        'salarios_actualizados' => 20,
    ]);

    // Test Show View
    $response = $this->actingAs($user)->get(route('empresas.importar-variables.show', [
        'empresa' => $empresa->id,
        'importacion' => $importacion->id,
    ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('empresas/ImportacionVariablesProgreso')
            ->has('empresa')
            ->has('importacion')
            ->has('salariosMuestra')
        );

    // Test JSON status endpoint
    $statusResponse = $this->actingAs($user)->get(route('empresas.importar-variables.estado', [
        'empresa' => $empresa->id,
        'importacion' => $importacion->id,
    ]));

    $statusResponse->assertOk()
        ->assertJson([
            'id' => $importacion->id,
            'estado' => 'procesando',
            'total_filas' => 100,
            'filas_procesadas' => 45,
            'empleados_actualizados' => 10,
            'salarios_actualizados' => 20,
            'porcentaje' => 45,
        ]);
});

test('sdi_auditado is capped at limite_salario_diario when sdi_total exceeds it', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();
    $categoria = Categoria::create(['nombre' => 'Directivo']);

    Uma::factory()->create([
        'anio' => 2025,
        'saldo' => 100.00,
    ]);

    $empleado = Empleado::create([
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'Director General',
        'rfc_receptor' => 'DIR010101AAA',
        'numero_empleado' => '100',
        'salario_base' => 2400.00,
        'salario_diario_integrado' => 2400.00,
    ]);

    $salario = EmpleadoSalario::create([
        'empleado_id' => $empleado->id,
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'anio' => 2025,
        'periodo' => '1',
        'tipo_nomina' => 'O - Nómina ordinaria',
        'salario_base' => 2400.00,
        'salario_diario_integrado' => 2400.00,
        'limite_salario_diario' => 2500.00, // Top = 25 * 100 = 2500
        'excede_limite' => false,
        'uma_utilizada' => 100.00,
        'anio_uma' => 2025,
    ]);

    // November date affects periods 1 and 2
    $csvContent = implode("\n", [
        'Número de personal,Final del período en Nómina,Texto expl.CC-nómina,Cantidad,Importe',
        '100,15/11/2024,Días calendario,30.00,0.00',
        '100,15/11/2024,Parte variable acumulada,0.00,"6,000.00"',
    ]);

    $file = UploadedFile::fake()->createWithContent('variables_nov.csv', $csvContent);
    $path = $file->store("imports/variables_{$empresa->id}", 'local');

    $importacion = ImportacionVariable::create([
        'empresa_id' => $empresa->id,
        'anio' => 2025,
        'archivo_nombre' => 'variables_nov.csv',
        'archivo_ruta' => $path,
        'estado' => 'pendiente',
        'total_filas' => 0,
        'filas_procesadas' => 0,
        'filas_con_error' => 0,
    ]);

    $job = new ProcesarImportacionVariablesJob($importacion->id);
    $job->handle(app(VariablesEmpleadoImporterService::class));

    $salario->refresh();
    // 6000 / 30 = 200 sdi_variable
    // sdi_total = 2400 + 200 = 2600
    // But limit is 2500.00 -> sdi_auditado must be 2500.00
    expect(round((float) $salario->sdi_variable, 2))->toBe(200.00);
    expect(round((float) $salario->sdi_total, 2))->toBe(2600.00);
    expect(round((float) $salario->sdi_auditado, 2))->toBe(2500.00);
});

test('does not create salary record when it does not exist for the employee', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();
    $categoria = Categoria::create(['nombre' => 'General']);

    $empleado = Empleado::create([
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'Empleado Sin Salarios',
        'rfc_receptor' => 'SIN010101AAA',
        'numero_empleado' => '500',
        'salario_base' => 300.00,
        'salario_diario_integrado' => 300.00,
    ]);

    // Employee has NO EmpleadoSalario records yet
    expect(EmpleadoSalario::where('empleado_id', $empleado->id)->count())->toBe(0);

    $csvContent = implode("\n", [
        'Número de personal,Final del período en Nómina,Texto expl.CC-nómina,Cantidad,Importe',
        '500,15/01/2025,Días calendario,15.00,0.00',
        '500,15/01/2025,Parte variable acumulada,0.00,"500.00"',
    ]);

    $file = UploadedFile::fake()->createWithContent('variables_sin_salarios.csv', $csvContent);
    $path = $file->store("imports/variables_{$empresa->id}", 'local');

    $importacion = ImportacionVariable::create([
        'empresa_id' => $empresa->id,
        'anio' => 2025,
        'archivo_nombre' => 'variables_sin_salarios.csv',
        'archivo_ruta' => $path,
        'estado' => 'pendiente',
        'total_filas' => 0,
        'filas_procesadas' => 0,
        'filas_con_error' => 0,
    ]);

    $job = new ProcesarImportacionVariablesJob($importacion->id);
    $job->handle(app(VariablesEmpleadoImporterService::class));

    $importacion->refresh();
    expect($importacion->estado)->toBe('completado');
    expect($importacion->salarios_actualizados)->toBe(0);
    expect($importacion->empleados_actualizados)->toBe(0);

    // Verify still NO EmpleadoSalario records were created
    expect(EmpleadoSalario::where('empleado_id', $empleado->id)->count())->toBe(0);
});
