<?php

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected from categorias', function () {
    $this->get(route('categorias.index'))->assertRedirect(route('login'));
});

test('authenticated user can view categorias index and search', function () {
    $user = User::factory()->create();
    Categoria::factory()->create(['nombre' => 'Operativo Especial']);
    Categoria::factory()->create(['nombre' => 'Administrativo']);

    $response = $this->actingAs($user)->get(route('categorias.index', ['search' => 'Operativo']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('categorias/Index')
            ->has('categorias.data', 1)
            ->where('categorias.data.0.nombre', 'Operativo Especial')
        );
});

test('user can create a categoria', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('categorias.store'), [
        'nombre' => 'Gerencia General',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('categorias', [
        'nombre' => 'Gerencia General',
    ]);
});

test('cannot create duplicate categoria name', function () {
    $user = User::factory()->create();
    Categoria::factory()->create(['nombre' => 'Ventas']);

    $response = $this->actingAs($user)->post(route('categorias.store'), [
        'nombre' => 'Ventas',
    ]);

    $response->assertSessionHasErrors(['nombre']);
});

test('user can update a categoria', function () {
    $user = User::factory()->create();
    $categoria = Categoria::factory()->create(['nombre' => 'Finanzas']);

    $response = $this->actingAs($user)->put(route('categorias.update', $categoria), [
        'nombre' => 'Finanzas y Tesorería',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('categorias', [
        'id' => $categoria->id,
        'nombre' => 'Finanzas y Tesorería',
    ]);
});

test('user can delete a categoria', function () {
    $user = User::factory()->create();
    $categoria = Categoria::factory()->create();

    $response = $this->actingAs($user)->delete(route('categorias.destroy', $categoria));

    $response->assertRedirect();
    $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
});
