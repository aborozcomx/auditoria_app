<?php

use App\Jobs\ProcesarImportacionCfdiJob;
use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\EmpleadoSalario;
use App\Models\Empresa;
use App\Models\ImportacionCfdi;
use App\Models\Uma;
use App\Models\User;
use App\Models\Vacacion;
use App\Services\CfdiExcelImporterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('guests are redirected from importar cfdi', function () {
    $empresa = Empresa::factory()->create();

    $this->get(route('empresas.importar-cfdi.create', $empresa))
        ->assertRedirect(route('login'));
});

test('authenticated user can view import cfdi form', function () {
    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();

    $response = $this->actingAs($user)->get(route('empresas.importar-cfdi.create', $empresa));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('empresas/ImportarCfdi')
            ->has('empresa')
            ->has('historial')
            ->has('aniosDisponibles')
        );
});

test('user can upload cfdi file and it dispatches background job', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $empresa = Empresa::factory()->create(['rfc' => 'AAA010101AAA']);

    $csvContent = implode("\n", [
        'Periodo,Tipo nomina,RFC emisor,Razon emisor,Registro patronal,RFC receptor,Razon receptor,Fecha inicio relacion laboral,Num empleado,Antiguedad,Sindicalizado,CURP,No Seguro social,Salario base cot apor,Salario diario integrado',
        "1,O - Nómina ordinaria,{$empresa->rfc},{$empresa->nombre},L0178008103,TEST800101AA1,JUAN PEREZ,2024-01-01,101,P52W - 52 semanas,No,TEST800101AA1,12345678901,300.00,350.00",
    ]);

    $file = UploadedFile::fake()->createWithContent('nominas_2024.csv', $csvContent);

    $response = $this->actingAs($user)->post(route('empresas.importar-cfdi.store', $empresa), [
        'anio' => 2024,
        'archivo' => $file,
    ]);

    $importacion = ImportacionCfdi::first();

    expect($importacion)->not->toBeNull()
        ->and($importacion->empresa_id)->toBe($empresa->id)
        ->and($importacion->anio)->toBe(2024)
        ->and($importacion->archivo_nombre)->toBe('nominas_2024.csv')
        ->and($importacion->estado)->toBe('pendiente');

    $response->assertRedirect(route('empresas.importar-cfdi.show', [$empresa, $importacion]));

    Queue::assertPushed(ProcesarImportacionCfdiJob::class, function ($job) use ($importacion) {
        return $job->importacionCfdiId === $importacion->id;
    });
});

test('upload fails if rfc emisor is not registered in the system', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $empresa = Empresa::factory()->create(['rfc' => 'AAA010101AAA']);

    $csvContent = implode("\n", [
        'Periodo,Tipo nomina,RFC emisor,Razon emisor,Registro patronal,RFC receptor,Razon receptor,Fecha inicio relacion laboral,Num empleado,Antiguedad,Sindicalizado,CURP,No Seguro social,Salario base cot apor,Salario diario integrado',
        '1,O - Nómina ordinaria,NOEXISTE99999,EMPRESA FANTASMA,L0178008103,TEST800101AA1,JUAN PEREZ,2024-01-01,101,P52W - 52 semanas,No,TEST800101AA1,12345678901,300.00,350.00',
    ]);

    $file = UploadedFile::fake()->createWithContent('nominas_unregistered_rfc.csv', $csvContent);

    $response = $this->actingAs($user)->post(route('empresas.importar-cfdi.store', $empresa), [
        'anio' => 2024,
        'archivo' => $file,
    ]);

    $response->assertSessionHasErrors(['archivo']);
    $errorMsg = session('errors')->first('archivo');
    expect($errorMsg)->toContain('NOEXISTE99999')
        ->and($errorMsg)->toContain('no está dado de alta en el sistema');
});

test('upload fails if rfc emisor does not match the company being imported', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $empresaA = Empresa::factory()->create(['nombre' => 'Empresa A', 'rfc' => 'AAAA010101AAA']);
    $empresaB = Empresa::factory()->create(['nombre' => 'Empresa B', 'rfc' => 'BBBB010101BBB']);

    // Uploading Empresa B's RFC file into Empresa A
    $csvContent = implode("\n", [
        'Periodo,Tipo nomina,RFC emisor,Razon emisor,Registro patronal,RFC receptor,Razon receptor,Fecha inicio relacion laboral,Num empleado,Antiguedad,Sindicalizado,CURP,No Seguro social,Salario base cot apor,Salario diario integrado',
        "1,O - Nómina ordinaria,{$empresaB->rfc},{$empresaB->nombre},L0178008103,TEST800101AA1,JUAN PEREZ,2024-01-01,101,P52W - 52 semanas,No,TEST800101AA1,12345678901,300.00,350.00",
    ]);

    $file = UploadedFile::fake()->createWithContent('nominas_wrong_empresa.csv', $csvContent);

    $response = $this->actingAs($user)->post(route('empresas.importar-cfdi.store', $empresaA), [
        'anio' => 2024,
        'archivo' => $file,
    ]);

    $response->assertSessionHasErrors(['archivo']);
    $errorMsg = session('errors')->first('archivo');
    expect($errorMsg)->toContain($empresaB->rfc)
        ->and($errorMsg)->toContain('no coincide con la empresa seleccionada');
});

test('upload fails if an employee already belongs to another company', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $empresaA = Empresa::factory()->create(['nombre' => 'Empresa Alfa', 'rfc' => 'ALFA010101AAA']);
    $empresaB = Empresa::factory()->create(['nombre' => 'Empresa Beta', 'rfc' => 'BETA020202BBB']);

    // Employee already belongs to Empresa B
    Empleado::factory()->create([
        'empresa_id' => $empresaB->id,
        'nombre' => 'CARLOS LOPEZ HERNANDEZ',
        'rfc_receptor' => 'LOHC850101XYZ',
        'curp' => 'LOHC850101HDFRRN01',
    ]);

    // Try to import this same employee into Empresa A
    $csvContent = implode("\n", [
        'Periodo,Tipo nomina,RFC emisor,Razon emisor,Registro patronal,RFC receptor,Razon receptor,Fecha inicio relacion laboral,Num empleado,Antiguedad,Sindicalizado,CURP,No Seguro social,Salario base cot apor,Salario diario integrado',
        "1,O - Nómina ordinaria,{$empresaA->rfc},{$empresaA->nombre},L0178008103,LOHC850101XYZ,CARLOS LOPEZ HERNANDEZ,2024-01-01,555,P52W - 52 semanas,No,LOHC850101HDFRRN01,12345678901,400.00,450.00",
    ]);

    $file = UploadedFile::fake()->createWithContent('nominas_duplicate_employee.csv', $csvContent);

    $response = $this->actingAs($user)->post(route('empresas.importar-cfdi.store', $empresaA), [
        'anio' => 2024,
        'archivo' => $file,
    ]);

    $response->assertSessionHasErrors(['archivo']);
    $errorMsg = session('errors')->first('archivo');
    expect($errorMsg)->toContain('CARLOS LOPEZ HERNANDEZ')
        ->and($errorMsg)->toContain('LOHC850101XYZ')
        ->and($errorMsg)->toContain('Empresa Beta')
        ->and($errorMsg)->toContain('Un empleado solo puede pertenecer a una empresa');
});

test('upload succeeds if employee already belongs to the same company', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $empresa = Empresa::factory()->create(['nombre' => 'Mi Empresa', 'rfc' => 'MEMP010101AAA']);

    // Employee already belongs to THIS same empresa
    Empleado::factory()->create([
        'empresa_id' => $empresa->id,
        'nombre' => 'MARIA RODRIGUEZ',
        'rfc_receptor' => 'ROMA900101AAA',
        'curp' => 'ROMA900101MDFRRN02',
    ]);

    // Import another period for the same employee
    $csvContent = implode("\n", [
        'Periodo,Tipo nomina,RFC emisor,Razon emisor,Registro patronal,RFC receptor,Razon receptor,Fecha inicio relacion laboral,Num empleado,Antiguedad,Sindicalizado,CURP,No Seguro social,Salario base cot apor,Salario diario integrado',
        "2,O - Nómina ordinaria,{$empresa->rfc},{$empresa->nombre},L0178008103,ROMA900101AAA,MARIA RODRIGUEZ,2024-01-01,102,P52W - 52 semanas,No,ROMA900101MDFRRN02,12345678902,320.00,370.00",
    ]);

    $file = UploadedFile::fake()->createWithContent('nominas_same_empresa.csv', $csvContent);

    $response = $this->actingAs($user)->post(route('empresas.importar-cfdi.store', $empresa), [
        'anio' => 2024,
        'archivo' => $file,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();
    Queue::assertPushed(ProcesarImportacionCfdiJob::class);
});

test('estado polling endpoint returns import progress', function () {
    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();
    $importacion = ImportacionCfdi::create([
        'empresa_id' => $empresa->id,
        'anio' => 2024,
        'archivo_nombre' => 'nominas.xlsx',
        'archivo_ruta' => 'importaciones/dummy.xlsx',
        'estado' => 'procesando',
        'total_filas' => 100,
        'filas_procesadas' => 45,
        'filas_con_error' => 2,
    ]);

    $response = $this->actingAs($user)->get(route('empresas.importar-cfdi.estado', [$empresa, $importacion]));

    $response->assertOk()
        ->assertJson([
            'id' => $importacion->id,
            'estado' => 'procesando',
            'total_filas' => 100,
            'filas_procesadas' => 45,
            'filas_con_error' => 2,
            'porcentaje' => 45,
        ]);
});

test('reintentar dispatches queue job again', function () {
    Queue::fake();

    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();
    $importacion = ImportacionCfdi::create([
        'empresa_id' => $empresa->id,
        'anio' => 2024,
        'archivo_nombre' => 'nominas.xlsx',
        'archivo_ruta' => 'importaciones/dummy.xlsx',
        'estado' => 'fallido',
        'total_filas' => 100,
        'filas_procesadas' => 20,
    ]);

    $response = $this->actingAs($user)->post(route('empresas.importar-cfdi.reintentar', [$empresa, $importacion]));

    $response->assertRedirect();
    $importacion->refresh();
    expect($importacion->estado)->toBe('pendiente');

    Queue::assertPushed(ProcesarImportacionCfdiJob::class);
});

test('cfdi importer service correctly processes rows and calculates formulas', function () {
    // 1. Setup UMAs
    Uma::create(['anio' => 2023, 'saldo' => 103.74]);
    Uma::create(['anio' => 2024, 'saldo' => 108.57]);

    // 2. Setup Category and Vacation Scale
    $categoria = Categoria::create(['nombre' => 'Operativo']);
    Vacacion::create([
        'anios' => 13,
        'dias' => 22,
        'categoria_id' => $categoria->id,
    ]);

    // 3. Setup Empresa
    $empresa = Empresa::create([
        'nombre' => 'Experiencias Xcaret Web',
        'rfc' => 'EXW1610242P5',
        'razon_social' => 'Experiencias Xcaret Web S.A. de C.V.',
        'dias_aguinaldo' => 15,
        'prima_vacacional' => 25.0,
    ]);

    // 4. Create sample CSV with Mexican payroll headers on local disk
    Storage::fake('local');
    $csvContent = implode("\n", [
        'Periodo,Tipo nomina,RFC emisor,Razon emisor,Registro patronal,RFC receptor,Razon receptor,Fecha inicio relacion laboral,Num empleado,Antiguedad,Sindicalizado,CURP,No Seguro social,Salario base cot apor,Salario diario integrado',
        '9,O - Nómina ordinaria,EXW1610242P5,EXPERIENCIAS XCARET WEB,L0178008103,HEVV870218TT9,VICTOR ARMANDO HERNANDEZ VEGA,2024-03-25,51490,P706W - 706 semanas,No,HEVV870218TT9,13058710818,"289,39","1025,39"',
        '1,O - Nómina ordinaria,EXW1610242P5,EXPERIENCIAS XCARET WEB,L0178008103,PEGE791108606,ERICK PEREZ GARCIA,2007-02-05,1064,P30W - 30 semanas,No,PEGE791108606,82067908606,1258.18,1354.27',
        '9,E - Nómina extraordinaria,EXW1610242P5,EXPERIENCIAS XCARET WEB,L0178008103,IGNO990101XYZ,EMPLEADO EXTRAORDINARIO IGNORADO,2020-01-01,9999,P52W - 52 semanas,No,IGNO990101XYZ,99999999999,500.00,550.00',
    ]);

    $relativePath = 'imports/test_cfdi.csv';
    Storage::disk('local')->put($relativePath, $csvContent);

    $importacion = ImportacionCfdi::create([
        'empresa_id' => $empresa->id,
        'anio' => 2024,
        'archivo_nombre' => 'test_cfdi.csv',
        'archivo_ruta' => $relativePath,
        'estado' => 'pendiente',
    ]);

    // Execute service directly
    $service = new CfdiExcelImporterService;
    $service->process($importacion);

    $importacion->refresh();
    expect($importacion->estado)->toBe('completado')
        ->and($importacion->total_filas)->toBe(2) // Only the 2 ordinary rows counted
        ->and($importacion->filas_procesadas)->toBe(2)
        ->and($importacion->filas_con_error)->toBe(0);

    // Verify extraordinary row was ignored and not saved
    expect(Empleado::where('rfc_receptor', 'IGNO990101XYZ')->exists())->toBeFalse();

    // Verify Empleado 1
    $empleado1 = Empleado::where('rfc_receptor', 'HEVV870218TT9')->first();
    expect($empleado1)->not->toBeNull()
        ->and($empleado1->empresa_id)->toBe($empresa->id)
        ->and($empleado1->nombre)->toBe('VICTOR ARMANDO HERNANDEZ VEGA')
        ->and($empleado1->registro_patronal)->toBe('L0178008103')
        ->and($empleado1->numero_empleado)->toBe('51490')
        ->and($empleado1->antiguedad_anios)->toBe(13) // 706 / 52 = 13.57 -> 13
        ->and($empleado1->es_sindicalizado)->toBeFalse()
        ->and($empleado1->curp)->toBe('HEVV870218TT9')
        ->and($empleado1->numero_seguro_social)->toBe('13058710818');

    // Verify Empleado 1 Salario (Periodo 9, 2024 UMA)
    $salario1 = EmpleadoSalario::where('empleado_id', $empleado1->id)->first();
    expect($salario1)->not->toBeNull()
        ->and((float) $salario1->salario_base)->toBe(289.39)
        ->and($salario1->categoria->nombre)->toBe('Operativo')
        ->and($salario1->dias_vacaciones)->toBe(22) // from catalog for 13 years
        ->and((int) $salario1->periodo)->toBe(9)
        ->and($salario1->anio)->toBe(2024);

    // Aguinaldo diario: 289.39 * 15 / 365 = 11.8927
    $expectedAguinaldo = round((289.39 * 15) / 365, 4);
    expect((float) $salario1->aguinaldo_diario)->toEqualWithDelta($expectedAguinaldo, 0.01);

    // Importe de vacaciones: 289.39 * 22 = 6366.58
    expect((float) $salario1->importe_vacaciones)->toEqualWithDelta(round(289.39 * 22, 2), 0.01);

    // Prima vacacional: (289.39 * 22) * (25 / 100) / 365 = 4.3606
    $expectedPrima = round((289.39 * 22 * 0.25) / 365, 4);
    expect((float) $salario1->prima_vacacional)->toEqualWithDelta($expectedPrima, 0.01);

    // Salario diario integrado calculado: base + aguinaldo_diario + prima_vacacional
    $expectedSdi = round(289.39 + $expectedAguinaldo + $expectedPrima, 4);
    expect((float) $salario1->salario_diario_integrado)->toEqualWithDelta($expectedSdi, 0.01);

    // Limite salario diario (Periodo 9 -> 2024 UMA * 25 = 108.57 * 25 = 2714.25)
    expect((float) $salario1->limite_salario_diario)->toBe(round(108.57 * 25, 2));

    // Verify Empleado 2 Salario (Periodo 1 -> 2023 UMA * 25 = 103.74 * 25 = 2593.50)
    $empleado2 = Empleado::where('rfc_receptor', 'PEGE791108606')->first();
    expect($empleado2->antiguedad_anios)->toBe(0); // 30 weeks < 52 -> 0 years

    $salario2 = EmpleadoSalario::where('empleado_id', $empleado2->id)->first();
    expect((int) $salario2->periodo)->toBe(1)
        ->and((float) $salario2->limite_salario_diario)->toBe(round(103.74 * 25, 2));
});

test('cfdi importer service rejects row when employee belongs to another company', function () {
    Uma::create(['anio' => 2024, 'saldo' => 108.57]);
    $categoria = Categoria::create(['nombre' => 'Operativo']);

    $empresa1 = Empresa::create([
        'nombre' => 'Empresa Uno',
        'rfc' => 'UNO111111AAA',
        'razon_social' => 'Empresa Uno S.A.',
        'dias_aguinaldo' => 15,
        'prima_vacacional' => 25.0,
    ]);

    $empresa2 = Empresa::create([
        'nombre' => 'Empresa Dos',
        'rfc' => 'DOS222222BBB',
        'razon_social' => 'Empresa Dos S.A.',
        'dias_aguinaldo' => 15,
        'prima_vacacional' => 25.0,
    ]);

    // Create employee in Empresa 1
    Empleado::create([
        'empresa_id' => $empresa1->id,
        'categoria_id' => $categoria->id,
        'rfc_receptor' => 'CONF800101XYZ',
        'nombre' => 'EMPLEADO CONFLICTO',
        'numero_empleado' => '1001',
    ]);

    // Process a CSV for Empresa 2 containing this employee
    Storage::fake('local');
    $csvContent = implode("\n", [
        'Periodo,Tipo nomina,RFC emisor,Razon emisor,Registro patronal,RFC receptor,Razon receptor,Fecha inicio relacion laboral,Num empleado,Antiguedad,Sindicalizado,CURP,No Seguro social,Salario base cot apor,Salario diario integrado',
        '1,O - Nómina ordinaria,DOS222222BBB,Empresa Dos,L0178008103,CONF800101XYZ,EMPLEADO CONFLICTO,2024-01-01,999,P52W - 52 semanas,No,CONF800101XYZ,12345678901,300.00,350.00',
    ]);

    $relativePath = 'imports/conflict_cfdi.csv';
    Storage::disk('local')->put($relativePath, $csvContent);

    $importacion = ImportacionCfdi::create([
        'empresa_id' => $empresa2->id,
        'anio' => 2024,
        'archivo_nombre' => 'conflict_cfdi.csv',
        'archivo_ruta' => $relativePath,
        'estado' => 'pendiente',
    ]);

    $service = new CfdiExcelImporterService;
    $service->process($importacion);

    $importacion->refresh();
    expect($importacion->filas_con_error)->toBe(1)
        ->and($importacion->errores[0]['mensaje'])->toContain('Empresa Uno')
        ->and($importacion->errores[0]['mensaje'])->toContain('Un empleado solo puede pertenecer a una empresa');
});

test('upload fails if file is missing tipo nomina column', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $empresa = Empresa::factory()->create(['rfc' => 'AAA010101AAA']);

    // CSV without Tipo nomina column
    $csvContent = implode("\n", [
        'Periodo,RFC emisor,Razon emisor,Registro patronal,RFC receptor,Razon receptor,Fecha inicio relacion laboral,Num empleado,Antiguedad,Sindicalizado,CURP,No Seguro social,Salario base cot apor,Salario diario integrado',
        "1,{$empresa->rfc},{$empresa->nombre},L0178008103,TEST800101AA1,JUAN PEREZ,2024-01-01,101,P52W - 52 semanas,No,TEST800101AA1,12345678901,300.00,350.00",
    ]);

    $file = UploadedFile::fake()->createWithContent('nominas_sin_tipo.csv', $csvContent);

    $response = $this->actingAs($user)->post(route('empresas.importar-cfdi.store', $empresa), [
        'anio' => 2024,
        'archivo' => $file,
    ]);

    $response->assertSessionHasErrors(['archivo']);
    $errorMsg = session('errors')->first('archivo');
    expect($errorMsg)->toContain('columna de tipo de nómina');
});

test('upload fails if file has no records with O - Nomina ordinaria', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $empresa = Empresa::factory()->create(['rfc' => 'AAA010101AAA']);

    // CSV with only E - Nómina extraordinaria
    $csvContent = implode("\n", [
        'Periodo,Tipo nomina,RFC emisor,Razon emisor,Registro patronal,RFC receptor,Razon receptor,Fecha inicio relacion laboral,Num empleado,Antiguedad,Sindicalizado,CURP,No Seguro social,Salario base cot apor,Salario diario integrado',
        "1,E - Nómina extraordinaria,{$empresa->rfc},{$empresa->nombre},L0178008103,TEST800101AA1,JUAN PEREZ,2024-01-01,101,P52W - 52 semanas,No,TEST800101AA1,12345678901,300.00,350.00",
    ]);

    $file = UploadedFile::fake()->createWithContent('nominas_solo_extraordinaria.csv', $csvContent);

    $response = $this->actingAs($user)->post(route('empresas.importar-cfdi.store', $empresa), [
        'anio' => 2024,
        'archivo' => $file,
    ]);

    $response->assertSessionHasErrors(['archivo']);
    $errorMsg = session('errors')->first('archivo');
    expect($errorMsg)->toContain('O - Nómina ordinaria');
});
