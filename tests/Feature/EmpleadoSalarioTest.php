<?php

use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\EmpleadoSalario;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected from salarios index', function () {
    $this->get(route('salarios.index'))->assertRedirect(route('login'));
});

test('authenticated user can view salarios list with metrics and filter dropdowns', function () {
    $user = User::factory()->create();
    $empresa = Empresa::factory()->create(['nombre' => 'Acme Corp']);
    $categoria = Categoria::create(['nombre' => 'Operativo']);

    $empleado = Empleado::create([
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'Juan Perez',
        'rfc_receptor' => 'PEJU800101XYZ',
        'numero_empleado' => '1001',
        'antiguedad_anios' => 2,
        'antiguedad_semanas' => 104,
        'es_sindicalizado' => false,
        'salario_base' => 300.00,
        'salario_diario_integrado' => 320.00,
    ]);

    EmpleadoSalario::create([
        'empleado_id' => $empleado->id,
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'anio' => 2024,
        'periodo' => '9',
        'tipo_nomina' => 'O - Nómina ordinaria',
        'salario_base' => 300.00,
        'dias_vacaciones' => 14,
        'importe_vacaciones' => 4200.00,
        'aguinaldo_diario' => 12.33,
        'prima_vacacional' => 2.88,
        'salario_diario_integrado' => 315.21,
        'limite_salario_diario' => 2714.25,
        'excede_limite' => false,
        'uma_utilizada' => 108.57,
        'anio_uma' => 2024,
    ]);

    $response = $this->actingAs($user)->get(route('salarios.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('salarios/Index')
            ->has('salarios.data', 1)
            ->has('empresas')
            ->has('anios')
            ->has('periodos')
            ->has('totales')
            ->where('salarios.data.0.salario_base', '300.00')
            ->where('totales.total_registros', 1)
        );
});

test('can filter salarios by empresa, anio, and periodo', function () {
    $user = User::factory()->create();
    $empresa1 = Empresa::factory()->create(['nombre' => 'Empresa Uno']);
    $empresa2 = Empresa::factory()->create(['nombre' => 'Empresa Dos']);
    $categoria = Categoria::create(['nombre' => 'Operativo']);

    $emp1 = Empleado::create([
        'empresa_id' => $empresa1->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'Empleado Uno',
        'rfc_receptor' => 'AAA010101AAA',
        'numero_empleado' => '1',
        'antiguedad_anios' => 1,
        'antiguedad_semanas' => 52,
        'es_sindicalizado' => false,
        'salario_base' => 200,
        'salario_diario_integrado' => 210,
    ]);

    $emp2 = Empleado::create([
        'empresa_id' => $empresa2->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'Empleado Dos',
        'rfc_receptor' => 'BBB020202BBB',
        'numero_empleado' => '2',
        'antiguedad_anios' => 1,
        'antiguedad_semanas' => 52,
        'es_sindicalizado' => false,
        'salario_base' => 200,
        'salario_diario_integrado' => 210,
    ]);

    // Record for Empresa 1, Anio 2024, Periodo 9
    EmpleadoSalario::create([
        'empleado_id' => $emp1->id,
        'empresa_id' => $empresa1->id,
        'categoria_id' => $categoria->id,
        'anio' => 2024,
        'periodo' => '9',
        'tipo_nomina' => 'O - Nómina ordinaria',
        'salario_base' => 200.00,
        'dias_vacaciones' => 12,
        'importe_vacaciones' => 2400.00,
        'aguinaldo_diario' => 8.22,
        'prima_vacacional' => 1.64,
        'salario_diario_integrado' => 209.86,
        'limite_salario_diario' => 2714.25,
        'excede_limite' => false,
        'uma_utilizada' => 108.57,
        'anio_uma' => 2024,
    ]);

    // Record for Empresa 2, Anio 2023, Periodo 5
    EmpleadoSalario::create([
        'empleado_id' => $emp2->id,
        'empresa_id' => $empresa2->id,
        'categoria_id' => $categoria->id,
        'anio' => 2023,
        'periodo' => '5',
        'tipo_nomina' => 'O - Nómina ordinaria',
        'salario_base' => 200.00,
        'dias_vacaciones' => 12,
        'importe_vacaciones' => 2400.00,
        'aguinaldo_diario' => 8.22,
        'prima_vacacional' => 1.64,
        'salario_diario_integrado' => 209.86,
        'limite_salario_diario' => 2593.50,
        'excede_limite' => false,
        'uma_utilizada' => 103.74,
        'anio_uma' => 2023,
    ]);

    // Filter by Empresa 1
    $this->actingAs($user)->get(route('salarios.index', ['empresa_id' => $empresa1->id]))
        ->assertInertia(fn ($page) => $page->has('salarios.data', 1)->where('salarios.data.0.empresa_id', $empresa1->id));

    // Filter by Anio 2023
    $this->actingAs($user)->get(route('salarios.index', ['anio' => 2023]))
        ->assertInertia(fn ($page) => $page->has('salarios.data', 1)->where('salarios.data.0.anio', 2023));

    // Filter by Periodo 9
    $this->actingAs($user)->get(route('salarios.index', ['periodo' => '9']))
        ->assertInertia(fn ($page) => $page->has('salarios.data', 1)->where('salarios.data.0.periodo', '9'));

    // Filter by Search (Empleado Dos)
    $this->actingAs($user)->get(route('salarios.index', ['search' => 'BBB020202BBB']))
        ->assertInertia(fn ($page) => $page->has('salarios.data', 1)->where('salarios.data.0.empleado.rfc_receptor', 'BBB020202BBB'));
});

test('can sort salarios by employee name', function () {
    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();
    $categoria = Categoria::create(['nombre' => 'General']);

    $empA = Empleado::create([
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'ANA ZARAGOZA',
        'rfc_receptor' => 'ZARA800101XYZ',
        'numero_empleado' => '1001',
        'salario_base' => 1000,
        'salario_diario_integrado' => 1100,
    ]);

    $empB = Empleado::create([
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'BERNARDO ALVAREZ',
        'rfc_receptor' => 'ALVB800101XYZ',
        'numero_empleado' => '1002',
        'salario_base' => 2000,
        'salario_diario_integrado' => 2100,
    ]);

    EmpleadoSalario::create([
        'empleado_id' => $empA->id,
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'anio' => 2025,
        'periodo' => '1',
        'tipo_nomina' => 'O - Nómina ordinaria',
        'salario_base' => 1000,
        'salario_diario_integrado' => 1100,
    ]);

    EmpleadoSalario::create([
        'empleado_id' => $empB->id,
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'anio' => 2025,
        'periodo' => '1',
        'tipo_nomina' => 'O - Nómina ordinaria',
        'salario_base' => 2000,
        'salario_diario_integrado' => 2100,
    ]);

    // Sort ascending by nombre
    $responseAsc = $this->actingAs($user)->get(route('salarios.index', ['sort' => 'nombre', 'direction' => 'asc']));
    $responseAsc->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('salarios.data.0.empleado.nombre', 'ANA ZARAGOZA')
            ->where('salarios.data.1.empleado.nombre', 'BERNARDO ALVAREZ')
        );

    // Sort descending by nombre
    $responseDesc = $this->actingAs($user)->get(route('salarios.index', ['sort' => 'nombre', 'direction' => 'desc']));
    $responseDesc->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('salarios.data.0.empleado.nombre', 'BERNARDO ALVAREZ')
            ->where('salarios.data.1.empleado.nombre', 'ANA ZARAGOZA')
        );
});

test('can filter salarios with or without difference', function () {
    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();
    $categoria = Categoria::create(['nombre' => 'General']);

    $emp1 = Empleado::create([
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'EMPLEADO CON DIFERENCIA',
        'rfc_receptor' => 'ABCD800101XYZ',
        'numero_empleado' => '1001',
        'salario_base' => 1000,
        'salario_diario_integrado' => 1100,
    ]);

    $emp2 = Empleado::create([
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'nombre' => 'EMPLEADO SIN DIFERENCIA',
        'rfc_receptor' => 'EFGH800101XYZ',
        'numero_empleado' => '1002',
        'salario_base' => 2000,
        'salario_diario_integrado' => 2100,
    ]);

    EmpleadoSalario::create([
        'empleado_id' => $emp1->id,
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'anio' => 2025,
        'periodo' => '1',
        'tipo_nomina' => 'O - Nómina ordinaria',
        'salario_base' => 1000,
        'salario_diario_integrado' => 1100,
        'imss_cia' => 1050,
        'diferencia' => 50.00,
    ]);

    EmpleadoSalario::create([
        'empleado_id' => $emp2->id,
        'empresa_id' => $empresa->id,
        'categoria_id' => $categoria->id,
        'anio' => 2025,
        'periodo' => '1',
        'tipo_nomina' => 'O - Nómina ordinaria',
        'salario_base' => 2000,
        'salario_diario_integrado' => 2100,
        'imss_cia' => 2100,
        'diferencia' => 0.00,
    ]);

    // Filter solo con diferencias
    $responseCon = $this->actingAs($user)->get(route('salarios.index', ['solo_diferencias' => '1']));
    $responseCon->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('salarios.data', 1)
            ->where('salarios.data.0.empleado.nombre', 'EMPLEADO CON DIFERENCIA')
            ->where('filters.solo_diferencias', true)
        );

    // Filter solo sin diferencias
    $responseSin = $this->actingAs($user)->get(route('salarios.index', ['sin_diferencias' => '1']));
    $responseSin->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('salarios.data', 1)
            ->where('salarios.data.0.empleado.nombre', 'EMPLEADO SIN DIFERENCIA')
            ->where('filters.sin_diferencias', true)
        );
});
