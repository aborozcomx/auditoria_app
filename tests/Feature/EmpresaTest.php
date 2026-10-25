<?php

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected from empresas', function () {
    $this->get(route('empresas.index'))->assertRedirect(route('login'));
});

test('authenticated user can view empresas index and search by RFC or name', function () {
    $user = User::factory()->create();
    Empresa::factory()->create([
        'nombre' => 'Grupo Alfa Contable',
        'rfc' => 'GAC010101XYZ',
    ]);
    Empresa::factory()->create([
        'nombre' => 'Beta Logística',
        'rfc' => 'BLO020202ABC',
    ]);

    $response = $this->actingAs($user)->get(route('empresas.index', ['search' => 'GAC010101XYZ']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('empresas/Index')
            ->has('empresas.data', 1)
            ->where('empresas.data.0.nombre', 'Grupo Alfa Contable')
        );
});

test('user can create an empresa with valid RFC and defaults', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('empresas.store'), [
        'nombre' => 'Finanzas Integradas del Centro',
        'rfc' => 'fic190801ab1',
        'razon_social' => 'Finanzas Integradas del Centro S.A. de C.V.',
        'dias_aguinaldo' => 20,
        'prima_vacacional' => 30.00,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('empresas', [
        'nombre' => 'Finanzas Integradas del Centro',
        'rfc' => 'FIC190801AB1', // uppercase sanitized
        'dias_aguinaldo' => 20,
        'prima_vacacional' => 30.00,
    ]);
});

test('cannot create duplicate RFC', function () {
    $user = User::factory()->create();
    Empresa::factory()->create(['rfc' => 'ABC010101XYZ']);

    $response = $this->actingAs($user)->post(route('empresas.store'), [
        'nombre' => 'Otra Empresa',
        'rfc' => 'ABC010101XYZ',
        'razon_social' => 'Otra Empresa S.A.',
        'dias_aguinaldo' => 15,
        'prima_vacacional' => 25.00,
    ]);

    $response->assertSessionHasErrors(['rfc']);
});

test('user can update an empresa', function () {
    $user = User::factory()->create();
    $empresa = Empresa::factory()->create(['nombre' => 'Nombre Viejo']);

    $response = $this->actingAs($user)->put(route('empresas.update', $empresa), [
        'nombre' => 'Nombre Renovado',
        'rfc' => $empresa->rfc,
        'razon_social' => $empresa->razon_social,
        'dias_aguinaldo' => 25,
        'prima_vacacional' => 35.00,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('empresas', [
        'id' => $empresa->id,
        'nombre' => 'Nombre Renovado',
        'dias_aguinaldo' => 25,
    ]);
});

test('user can delete an empresa', function () {
    $user = User::factory()->create();
    $empresa = Empresa::factory()->create();

    $response = $this->actingAs($user)->delete(route('empresas.destroy', $empresa));

    $response->assertRedirect();
    $this->assertDatabaseMissing('empresas', ['id' => $empresa->id]);
});
