<?php

use App\Models\Categoria;
use App\Models\User;
use App\Models\Vacacion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected from vacaciones', function () {
    $this->get(route('vacaciones.index'))->assertRedirect(route('login'));
});

test('authenticated user can view vacaciones index and filter by category', function () {
    $user = User::factory()->create();
    $catA = Categoria::factory()->create(['nombre' => 'Operativo']);
    $catB = Categoria::factory()->create(['nombre' => 'Directivo']);

    Vacacion::factory()->create(['categoria_id' => $catA->id, 'anios' => 1, 'dias' => 12]);
    Vacacion::factory()->create(['categoria_id' => $catB->id, 'anios' => 1, 'dias' => 20]);

    $response = $this->actingAs($user)->get(route('vacaciones.index', ['categoria_id' => $catA->id]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vacaciones/Index')
            ->has('vacaciones.data', 1)
            ->where('vacaciones.data.0.categoria_id', $catA->id)
            ->has('categorias')
        );
});

test('user can create a vacacion rule', function () {
    $user = User::factory()->create();
    $cat = Categoria::factory()->create();

    $response = $this->actingAs($user)->post(route('vacaciones.store'), [
        'categoria_id' => $cat->id,
        'anios' => 5,
        'dias' => 20,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('vacaciones', [
        'categoria_id' => $cat->id,
        'anios' => 5,
        'dias' => 20,
    ]);
});

test('cannot create duplicate vacacion rule for same category and year', function () {
    $user = User::factory()->create();
    $cat = Categoria::factory()->create();
    Vacacion::factory()->create(['categoria_id' => $cat->id, 'anios' => 3]);

    $response = $this->actingAs($user)->post(route('vacaciones.store'), [
        'categoria_id' => $cat->id,
        'anios' => 3,
        'dias' => 16,
    ]);

    $response->assertSessionHasErrors(['anios']);
});

test('user can update a vacacion rule', function () {
    $user = User::factory()->create();
    $cat = Categoria::factory()->create();
    $vacacion = Vacacion::factory()->create(['categoria_id' => $cat->id, 'anios' => 2, 'dias' => 14]);

    $response = $this->actingAs($user)->put(route('vacaciones.update', $vacacion), [
        'categoria_id' => $cat->id,
        'anios' => 2,
        'dias' => 16,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('vacaciones', [
        'id' => $vacacion->id,
        'dias' => 16,
    ]);
});

test('user can delete a vacacion rule', function () {
    $user = User::factory()->create();
    $vacacion = Vacacion::factory()->create();

    $response = $this->actingAs($user)->delete(route('vacaciones.destroy', $vacacion));

    $response->assertRedirect();
    $this->assertDatabaseMissing('vacaciones', ['id' => $vacacion->id]);
});
