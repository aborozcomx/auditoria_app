<?php

use App\Jobs\ProcesarImportacionCuotasImssJob;
use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\EmpleadoCuotaImss;
use App\Models\EmpleadoSalario;
use App\Models\Empresa;
use App\Models\ImportacionCuotaImss;
use App\Models\Uma;
use App\Models\User;
use App\Services\CuotasImssImporterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('guests are redirected from importar cuotas imss', function () {
    $empresa = Empresa::factory()->create();

    $this->get(route('empresas.importar-cuotas-imss.create', $empresa))
        ->assertRedirect(route('login'));
});

test('authenticated user can view import cuotas form with history', function () {
    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();

    $response = $this->actingAs($user)->get(route('empresas.importar-cuotas-imss.create', $empresa));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('empresas/ImportarCuotasImss')
            ->has('empresa')
            ->has('historial')
            ->has('aniosDisponibles')
        );
});

test('user can upload cuotas imss file, dispatches background job and redirects to visualizer', function () {
    Queue::fake();
    Storage::fake('local');

    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();

    $csvContent = implode("\n", [
        'Periodo,Clave,NSS,NOM,Dias,SDI,Inc.,Aus.,Cuota IMSS,Prima,RP',
        '1,,01068407293,ACEVES SANCHEZ SARA BEATRIZ,15,"2,694.54",0,0,"3,439.74",0.61972,Y7229637108',
    ]);

    $file = UploadedFile::fake()->createWithContent('cuotas_imss_2025.csv', $csvContent);

    $response = $this->actingAs($user)->post(route('empresas.importar-cuotas-imss.store', $empresa), [
        'anio' => 2025,
        'archivo' => $file,
    ]);

    $importacion = ImportacionCuotaImss::where('empresa_id', $empresa->id)->first();
    expect($importacion)->not->toBeNull();
    expect($importacion->anio)->toBe(2025);
    expect($importacion->estado)->toBe('pendiente');

    Queue::assertPushed(ProcesarImportacionCuotasImssJob::class, function ($job) use ($importacion) {
        return $job->importacionCuotaImssId === $importacion->id;
    });

    $response->assertRedirect(route('empresas.importar-cuotas-imss.show', [
        'empresa' => $empresa->id,
        'importacion' => $importacion->id,
    ]));
});

test('job processes cuotas imss file, applies period 1 uma rule, updates salary and stores cuotas', function () {
    Storage::fake('local');

    $empresa = Empresa::factory()->create([
        'prima_vacacional' => 25.00,
    ]);
    $categoria = Categoria::create(['nombre' => 'General']);

    // Seed UMA 2024 and 2025
    Uma::create(['anio' => 2024, 'saldo' => 108.57]);
    Uma::create(['anio' => 2025, 'saldo' => 113.14]);

    // Create employee
    $empleado = Empleado::create([
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'ACEVES SANCHEZ SARA BEATRIZ',
        'rfc_receptor' => 'AESA800101XYZ',
        'numero_empleado' => '1001',
        'numero_seguro_social' => '01068407293',
        'salario_base' => 2500.00,
        'salario_diario_integrado' => 2694.54,
    ]);

    // Create salary for periodo 1 and periodo 2 in 2025
    $salarioP1 = EmpleadoSalario::create([
        'empleado_id' => $empleado->id,
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'anio' => 2025,
        'periodo' => '1',
        'tipo_nomina' => 'O - Nómina ordinaria',
        'salario_base' => 2500.00,
        'salario_diario_integrado' => 2694.54,
        'sdi_auditado' => 2694.54,
        'imss_cia' => 0.00,
        'diferencia' => 0.00,
        'limite_salario_diario' => 2828.50,
        'excede_limite' => false,
        'uma_utilizada' => 108.57,
        'anio_uma' => 2024,
    ]);

    $salarioP2 = EmpleadoSalario::create([
        'empleado_id' => $empleado->id,
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'anio' => 2025,
        'periodo' => '2',
        'tipo_nomina' => 'O - Nómina ordinaria',
        'salario_base' => 2500.00,
        'salario_diario_integrado' => 2694.54,
        'sdi_auditado' => 2700.00,
        'imss_cia' => 0.00,
        'diferencia' => 0.00,
        'limite_salario_diario' => 2828.50,
        'excede_limite' => false,
        'uma_utilizada' => 113.14,
        'anio_uma' => 2025,
    ]);

    // CSV with Periodo 1 and Periodo 2 rows
    $csvContent = implode("\n", [
        'Periodo,Clave,NSS,NOM,Dias,SDI,Inc.,Aus.,Cuota IMSS,Prima,RP',
        '1,1001,01068407293,ACEVES SANCHEZ SARA BEATRIZ,15,"2,694.54",0,0,"3,439.74",0.61972,Y7229637108',
        '2,1001,01068407293,ACEVES SANCHEZ SARA BEATRIZ,15,"2,694.54",0,0,"3,445.00",0.61972,Y7229637108',
    ]);

    $file = UploadedFile::fake()->createWithContent('cuotas_2025.csv', $csvContent);
    $path = $file->store("imports/cuotas_imss_{$empresa->id}", 'local');

    $importacion = ImportacionCuotaImss::create([
        'empresa_id' => $empresa->id,
        'anio' => 2025,
        'archivo_nombre' => 'cuotas_2025.csv',
        'archivo_ruta' => $path,
        'estado' => 'pendiente',
        'total_filas' => 0,
        'filas_procesadas' => 0,
        'filas_con_error' => 0,
        'cuotas_creadas' => 0,
        'salarios_actualizados' => 0,
    ]);

    $job = new ProcesarImportacionCuotasImssJob($importacion->id);
    $job->handle(app(CuotasImssImporterService::class));

    $importacion->refresh();
    expect($importacion->estado)->toBe('completado');
    expect($importacion->cuotas_creadas)->toBe(2);
    expect($importacion->salarios_actualizados)->toBe(2);

    // Verify Periodo 1 cuota and salary
    $salarioP1->refresh();
    expect((float) $salarioP1->imss_cia)->toBe(2694.54);
    expect((float) $salarioP1->diferencia)->toBe(0.00); // 2694.54 - 2694.54 = 0

    $cuotaP1 = EmpleadoCuotaImss::where('importacion_cuota_imss_id', $importacion->id)
        ->where('periodo', '1')
        ->first();
    expect($cuotaP1)->not->toBeNull();
    // For Periodo 1: uses UMA 2024 (108.57) because $anio - 1
    expect($cuotaP1->anio_uma)->toBe(2024);
    expect((float) $cuotaP1->sdi_cotizado)->toBe(2694.54); // Valor del registro de salarios
    expect((float) $cuotaP1->sdi_archivo)->toBe(2694.54); // Valor del archivo importado
    expect((float) $cuotaP1->salario_base)->toBe(2694.54);
    expect((float) $cuotaP1->fixed_price)->toBe(332.22);
    expect((float) $cuotaP1->sdmg)->toBe(532.99);
    expect((float) $cuotaP1->in_cash)->toBe(383.97);
    expect((float) $cuotaP1->disability_health)->toBe(959.93);
    expect((float) $cuotaP1->pensioners)->toBe(575.96);
    expect((float) $cuotaP1->risk_price)->toBe(250.48);
    expect((float) $cuotaP1->nurseries)->toBe(404.18);
    expect((float) $cuotaP1->total_audit)->toBe(3439.73);
    expect((float) $cuotaP1->subtotal)->toBe(3439.74);
    expect((float) $cuotaP1->difference)->toBe(-0.01);

    // Verify Periodo 2: uses UMA 2025 (113.14)
    $salarioP2->refresh();
    expect((float) $salarioP2->imss_cia)->toBe(2694.54);
    expect((float) $salarioP2->diferencia)->toBe(5.46); // 2700.00 - 2694.54 = 5.46

    $cuotaP2 = EmpleadoCuotaImss::where('importacion_cuota_imss_id', $importacion->id)
        ->where('periodo', '2')
        ->first();
    expect($cuotaP2)->not->toBeNull();
    expect($cuotaP2->anio_uma)->toBe(2025);
    expect((float) $cuotaP2->uma_utilizada)->toBe(113.14);
    expect((float) $cuotaP2->sdi_cotizado)->toBe(2700.00); // Equal to salary record sdi_auditado
    expect((float) $cuotaP2->sdi_archivo)->toBe(2694.54); // Equal to imported file SDI
});

test('can view live status json and progress visualizer page', function () {
    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();

    $importacion = ImportacionCuotaImss::create([
        'empresa_id' => $empresa->id,
        'anio' => 2025,
        'archivo_nombre' => 'test.xlsx',
        'archivo_ruta' => 'imports/test.xlsx',
        'estado' => 'completado',
        'total_filas' => 50,
        'filas_procesadas' => 50,
        'cuotas_creadas' => 50,
        'salarios_actualizados' => 50,
        'total_auditoria' => 125000.50,
        'total_imss' => 124800.00,
        'total_diferencia' => 200.50,
    ]);

    // Check status API
    $statusResponse = $this->actingAs($user)->getJson(
        route('empresas.importar-cuotas-imss.estado', ['empresa' => $empresa->id, 'importacion' => $importacion->id])
    );

    $statusResponse->assertOk()
        ->assertJson([
            'id' => $importacion->id,
            'estado' => 'completado',
            'cuotas_creadas' => 50,
            'porcentaje' => 100,
        ]);

    // Check show visualizer page
    $showResponse = $this->actingAs($user)->get(
        route('empresas.importar-cuotas-imss.show', ['empresa' => $empresa->id, 'importacion' => $importacion->id])
    );

    $showResponse->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('empresas/ImportacionCuotasProgreso')
            ->has('empresa')
            ->has('importacion')
            ->has('cuotas')
            ->has('periodosDisponibles')
        );
});

test('guests are redirected from cuotas imss catalog', function () {
    $this->get(route('cuotas-imss.index'))
        ->assertRedirect(route('login'));
});

test('authenticated user can view cuotas imss catalog with filters and metrics', function () {
    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();
    $categoria = Categoria::create(['nombre' => 'General']);

    $empleado = Empleado::create([
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'ACEVES SANCHEZ SARA BEATRIZ',
        'rfc_receptor' => 'AESA800101XYZ',
        'numero_empleado' => '1001',
        'numero_seguro_social' => '01068407293',
        'salario_base' => 2500.00,
        'salario_diario_integrado' => 2694.54,
    ]);

    $importacion = ImportacionCuotaImss::create([
        'empresa_id' => $empresa->id,
        'anio' => 2025,
        'archivo_nombre' => 'test.xlsx',
        'archivo_ruta' => 'imports/test.xlsx',
        'estado' => 'completado',
        'total_filas' => 1,
        'filas_procesadas' => 1,
    ]);

    EmpleadoCuotaImss::create([
        'empresa_id' => $empresa->id,
        'empleado_id' => $empleado->id,
        'importacion_cuota_imss_id' => $importacion->id,
        'anio' => 2025,
        'periodo' => '1',
        'nss' => '01068407293',
        'nombre_empleado' => 'ACEVES SANCHEZ SARA BEATRIZ',
        'dias' => 15,
        'dias_incapacidad' => 15,
        'ausentismos' => 0,
        'incapacidades' => 0,
        'sdi_cotizado' => 2694.54,
        'salario_base' => 2694.54,
        'fixed_price' => 332.22,
        'sdmg' => 532.99,
        'in_cash' => 383.97,
        'disability_health' => 959.93,
        'pensioners' => 575.96,
        'risk_price' => 250.48,
        'nurseries' => 404.18,
        'total_audit' => 3439.73,
        'subtotal' => 3439.74,
        'difference' => -0.01,
        'prima_riesgo' => 0.61972,
        'registro_patronal' => 'Y7229637108',
        'uma_utilizada' => 108.57,
        'anio_uma' => 2024,
    ]);

    $response = $this->actingAs($user)->get(route('cuotas-imss.index', [
        'empresa_id' => $empresa->id,
        'anio' => 2025,
        'periodo' => '1',
        'solo_diferencias' => '1',
    ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('cuotas-imss/Index')
            ->has('cuotas.data', 1)
            ->has('empresas')
            ->has('anios')
            ->has('periodos')
            ->has('totales')
            ->where('totales.total_registros', 1)
            ->where('totales.con_diferencias', 1)
        );
});

test('an employee can have 1 or more distinct records and duplicate records are not created', function () {
    Storage::fake('local');

    $empresa = Empresa::factory()->create([
        'prima_vacacional' => 25.00,
    ]);
    $categoria = Categoria::create(['nombre' => 'General']);

    Uma::create(['anio' => 2024, 'saldo' => 108.57]);
    Uma::create(['anio' => 2025, 'saldo' => 113.14]);

    $empleado = Empleado::create([
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'JUAN PEREZ GONZALEZ',
        'rfc_receptor' => 'PEGJ850101XYZ',
        'numero_empleado' => '2001',
        'numero_seguro_social' => '12345678901',
        'salario_base' => 2000.00,
        'salario_diario_integrado' => 2100.00,
    ]);

    $salario = EmpleadoSalario::create([
        'empleado_id' => $empleado->id,
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'anio' => 2025,
        'periodo' => '1',
        'tipo_nomina' => 'O - Nómina ordinaria',
        'salario_base' => 2000.00,
        'salario_diario_integrado' => 2100.00,
        'sdi_auditado' => 2100.00,
        'imss_cia' => 0.00,
        'diferencia' => 0.00,
        'limite_salario_diario' => 2828.50,
        'excede_limite' => false,
        'uma_utilizada' => 108.57,
        'anio_uma' => 2024,
    ]);

    // CSV with:
    // Row 1: Movement 1 (10 days, SDI 2,000.00, Cuota 1,500.00)
    // Row 2: Movement 2 (5 days, SDI 2,500.00, Cuota 1,000.00) -> Valid second distinct record for the employee
    // Row 3: Exact duplicate of Row 1 (10 days, SDI 2,000.00, Cuota 1,500.00) -> Should NOT be duplicated!
    $csvContent = implode("\n", [
        'Periodo,Clave,NSS,NOM,Dias,SDI,Inc.,Aus.,Cuota IMSS,Prima,RP',
        '1,2001,12345678901,JUAN PEREZ GONZALEZ,10,"2,000.00",0,0,"1,500.00",0.50000,Y7229637108',
        '1,2001,12345678901,JUAN PEREZ GONZALEZ,5,"2,500.00",0,0,"1,000.00",0.50000,Y7229637108',
        '1,2001,12345678901,JUAN PEREZ GONZALEZ,10,"2,000.00",0,0,"1,500.00",0.50000,Y7229637108',
    ]);

    $file = UploadedFile::fake()->createWithContent('cuotas_multiple_records.csv', $csvContent);
    $path = $file->store("imports/cuotas_imss_{$empresa->id}", 'local');

    $importacion = ImportacionCuotaImss::create([
        'empresa_id' => $empresa->id,
        'anio' => 2025,
        'archivo_nombre' => 'cuotas_multiple_records.csv',
        'archivo_ruta' => $path,
        'estado' => 'pendiente',
        'total_filas' => 0,
        'filas_procesadas' => 0,
        'cuotas_creadas' => 0,
        'salarios_actualizados' => 0,
    ]);

    $service = app(CuotasImssImporterService::class);
    $service->processImportacion($importacion);

    $importacion->refresh();
    expect($importacion->estado)->toBe('completado');

    // Exactly 2 cuota records should exist (movement 1 and movement 2). Duplicate row 3 was not created!
    $cuotas = EmpleadoCuotaImss::where('empresa_id', $empresa->id)->get();
    expect($cuotas)->toHaveCount(2);

    $cuota1 = $cuotas->firstWhere('dias', 10);
    expect($cuota1)->not->toBeNull();
    expect((float) $cuota1->sdi_cotizado)->toBe(2100.00); // Equal to salary record sdi_auditado
    expect((float) $cuota1->subtotal)->toBe(1500.00);

    $cuota2 = $cuotas->firstWhere('dias', 5);
    expect($cuota2)->not->toBeNull();
    expect((float) $cuota2->sdi_cotizado)->toBe(2100.00); // Equal to salary record sdi_auditado
    expect((float) $cuota2->subtotal)->toBe(1000.00);

    // Only 1 salary was updated, not inflated by the multiple rows
    expect($importacion->salarios_actualizados)->toBe(1);

    // Running import a second time (re-import) should NOT create duplicate records
    $service->processImportacion($importacion);
    expect(EmpleadoCuotaImss::where('empresa_id', $empresa->id)->count())->toBe(2);
});
