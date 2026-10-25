<?php

use App\Models\Empleado;
use App\Models\EmpleadoCuotaImss;
use App\Models\EmpleadoSalario;
use App\Models\Empresa;
use App\Models\ImportacionCuotaImss;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

uses(RefreshDatabase::class);

test('guests are redirected from exportar auditoria routes', function () {
    $this->get(route('exportar-auditoria.index'))->assertRedirect(route('login'));
    $this->get(route('exportar-auditoria.resumen'))->assertRedirect(route('login'));
    $this->get(route('exportar-auditoria.descargar'))->assertRedirect(route('login'));
});

test('authenticated user can view exportar auditoria page', function () {
    $user = User::factory()->create();
    $empresa = Empresa::factory()->create([
        'nombre' => 'Test Tech SA de CV',
        'rfc' => 'TTE200101XYZ',
    ]);

    $response = $this->actingAs($user)->get(route('exportar-auditoria.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('exportar-auditoria/Index')
            ->has('empresas')
            ->has('anios')
            ->has('initialSelection')
        );
});

test('user can get live resumen metrics via json', function () {
    $user = User::factory()->create();
    $empresa = Empresa::factory()->create([
        'nombre' => 'ACME Corporation',
        'rfc' => 'ACM900101ABC',
    ]);

    $empleado = Empleado::factory()->create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Juan Perez',
        'rfc_receptor' => 'PEPJ900101XYZ',
    ]);

    $importacion = ImportacionCuotaImss::create([
        'empresa_id' => $empresa->id,
        'anio' => 2025,
        'archivo_nombre' => 'cuotas.xlsx',
        'archivo_ruta' => 'dummy.xlsx',
        'estado' => 'completado',
    ]);

    EmpleadoCuotaImss::create([
        'importacion_cuota_imss_id' => $importacion->id,
        'empresa_id' => $empresa->id,
        'empleado_id' => $empleado->id,
        'anio' => 2025,
        'periodo' => '3',
        'nss' => '12345678901',
        'nombre' => 'Juan Perez',
        'dias' => 31,
        'ausencias' => 0,
        'incapacidades' => 0,
        'total_dias' => 31,
        'sdi_cotizado' => 500.00,
        'salario_base' => 500.00,
        'fixed_price' => 715.50,
        'sdmg' => 100.00,
        'in_cash' => 50.00,
        'total_audit' => 1500.00,
        'subtotal' => 1510.00,
        'difference' => -10.00,
    ]);

    EmpleadoSalario::create([
        'empresa_id' => $empresa->id,
        'empleado_id' => $empleado->id,
        'anio' => 2025,
        'periodo' => '3',
        'salario_base' => 450.00,
        'aguinaldo_diario' => 18.50,
        'dias_vacaciones' => 12,
        'importe_vacaciones' => 5400.00,
        'prima_vacacional' => 1350.00,
        'salario_diario_integrado' => 480.00,
        'sdi_variable' => 20.00,
        'sdi_total' => 500.00,
        'limite_salario_diario' => 2828.50,
        'sdi_auditado' => 500.00,
        'imss_cia' => 505.00,
        'diferencia' => -5.00,
    ]);

    $response = $this->actingAs($user)->getJson(route('exportar-auditoria.resumen', [
        'empresa_id' => $empresa->id,
        'anio' => 2025,
        'periodo' => '3',
    ]));

    $response->assertOk()
        ->assertJson([
            'anio' => 2025,
            'periodo' => '3',
            'total_cuotas' => 1,
            'total_salarios' => 1,
            'nombre_archivo' => 'Pruebas_ACM900101ABC_3_2025.xlsx',
        ]);
});

test('user can download export file with correct nomenclature and multi-sheet content', function () {
    $user = User::factory()->create();
    $empresa = Empresa::factory()->create([
        'nombre' => 'Industrias del Norte SA',
        'rfc' => 'IDN1508209Z1',
    ]);

    $empleado = Empleado::factory()->create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Maria Garcia',
        'rfc_receptor' => 'GARM850101AA1',
        'numero_empleado' => '1001',
        'numero_seguro_social' => '98765432109',
        'fecha_inicio_relacion_laboral' => '2021-05-10',
    ]);

    $importacion = ImportacionCuotaImss::create([
        'empresa_id' => $empresa->id,
        'anio' => 2025,
        'archivo_nombre' => 'cuotas.xlsx',
        'archivo_ruta' => 'dummy.xlsx',
        'estado' => 'completado',
    ]);

    EmpleadoCuotaImss::create([
        'importacion_cuota_imss_id' => $importacion->id,
        'empresa_id' => $empresa->id,
        'empleado_id' => $empleado->id,
        'anio' => 2025,
        'periodo' => '4',
        'nss' => '98765432109',
        'nombre' => 'Maria Garcia',
        'clave' => '1001',
        'dias' => 30,
        'ausencias' => 0,
        'incapacidades' => 0,
        'total_dias' => 30,
        'sdi_cotizado' => 750.00,
        'salario_base' => 750.00,
        'fixed_price' => 715.50,
        'total_audit' => 2100.00,
        'subtotal' => 2105.00,
        'difference' => -5.00,
    ]);

    EmpleadoSalario::create([
        'empresa_id' => $empresa->id,
        'empleado_id' => $empleado->id,
        'anio' => 2025,
        'periodo' => '4',
        'salario_base' => 600.00,
        'aguinaldo_diario' => 24.66,
        'dias_vacaciones' => 14,
        'importe_vacaciones' => 8400.00,
        'prima_vacacional' => 2100.00,
        'salario_diario_integrado' => 700.00,
        'sdi_variable' => 50.00,
        'sdi_total' => 750.00,
        'limite_salario_diario' => 2828.50,
        'sdi_auditado' => 750.00,
        'imss_cia' => 752.00,
        'diferencia' => -2.00,
    ]);

    $response = $this->actingAs($user)->get(route('exportar-auditoria.descargar', [
        'empresa_id' => $empresa->id,
        'anio' => 2025,
        'periodo' => '4',
    ]));

    $response->assertOk();
    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain('attachment; filename=Pruebas_IDN1508209Z1_4_2025.xlsx');

    // Save content to temp file to verify OpenSpout reading
    $tempFile = tempnam(sys_get_temp_dir(), 'test_dl_').'.xlsx';
    file_put_contents($tempFile, $response->streamedContent());

    $reader = new XlsxReader;
    $reader->open($tempFile);

    $sheetNames = [];
    $sheetRows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        $sheetNames[] = $sheet->getName();
        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
        $sheetRows[$sheet->getName()] = $rows;
    }
    $reader->close();
    unlink($tempFile);

    expect($sheetNames)->toBe(['IMSS', 'CFDIS']);

    // Check Sheet 1 (IMSS) - note: row 4 in Excel is blank, so header is at index 3 and data at index 4
    $imssRows = $sheetRows['IMSS'];
    expect($imssRows[0][0])->toBe('CIA')
        ->and($imssRows[0][1])->toBe('Industrias del Norte SA')
        ->and($imssRows[1][0])->toBe('Tipo de pru')
        ->and($imssRows[1][1])->toBe('IMSS')
        ->and($imssRows[2][0])->toBe('Periodo')
        ->and($imssRows[2][1])->toBe('1 de abril de 2025 - 30 de abril de 2025')
        ->and($imssRows[3][0])->toBe('Año')
        ->and($imssRows[3][1])->toBe('Periodo')
        ->and($imssRows[3][2])->toBe('Empleado')
        ->and($imssRows[3][3])->toBe('RFC')
        ->and($imssRows[3][4])->toBe('Num.Emple')
        ->and($imssRows[3][5])->toBe('NSS')
        ->and($imssRows[3][6])->toBe('SDC')
        ->and($imssRows[3][24])->toBe('Dif. a pagar');

    // Data row in IMSS
    expect($imssRows[4][0])->toBe(2025)
        ->and($imssRows[4][1])->toBe('4')
        ->and($imssRows[4][2])->toBe('Maria Garcia')
        ->and($imssRows[4][3])->toBe('GARM850101AA1')
        ->and($imssRows[4][4])->toBe('1001')
        ->and($imssRows[4][5])->toBe('98765432109');

    // Check Sheet 2 (CFDIS) - note: row 4 in Excel is blank, so header is at index 3 and data at index 4
    $cfdisRows = $sheetRows['CFDIS'];
    expect($cfdisRows[0][0])->toBe('CIA')
        ->and($cfdisRows[0][1])->toBe('Industrias del Norte SA')
        ->and($cfdisRows[1][0])->toBe('Tipo de pru')
        ->and($cfdisRows[1][1])->toBe('CFDIS')
        ->and($cfdisRows[2][0])->toBe('Periodo')
        ->and($cfdisRows[2][1])->toBe('1 de abril de 2025 - 30 de abril de 2025')
        ->and($cfdisRows[3][0])->toBe('Año')
        ->and($cfdisRows[3][1])->toBe('Periodo')
        ->and($cfdisRows[3][2])->toBe('Empleado')
        ->and($cfdisRows[3][3])->toBe('RFC')
        ->and($cfdisRows[3][4])->toBe('Num.Emple')
        ->and($cfdisRows[3][5])->toBe('NSS')
        ->and($cfdisRows[3][6])->toBe('Fecha inicio')
        ->and($cfdisRows[3][18])->toBe('Diferencia');

    // Data row in CFDIS
    expect($cfdisRows[4][0])->toBe(2025)
        ->and($cfdisRows[4][1])->toBe('4')
        ->and($cfdisRows[4][2])->toBe('Maria Garcia')
        ->and($cfdisRows[4][3])->toBe('GARM850101AA1')
        ->and($cfdisRows[4][4])->toBe('1001')
        ->and($cfdisRows[4][5])->toBe('98765432109')
        ->and($cfdisRows[4][6])->toBe('2021-05-10');
});
