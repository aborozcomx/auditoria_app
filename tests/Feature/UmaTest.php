<?php

use App\Models\Uma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected from umas', function () {
    $this->get(route('umas.index'))->assertRedirect(route('login'));
});

test('authenticated user can view umas index and search', function () {
    $user = User::factory()->create();
    Uma::factory()->create(['anio' => 2024, 'saldo' => 108.57]);
    Uma::factory()->create(['anio' => 2025, 'saldo' => 113.14]);

    $response = $this->actingAs($user)->get(route('umas.index', ['search' => '2024']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('umas/Index')
            ->has('umas.data', 1)
            ->where('umas.data.0.anio', 2024)
        );
});

test('user can create an uma', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('umas.store'), [
        'anio' => 2027,
        'saldo' => 124.50,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('umas', [
        'anio' => 2027,
        'saldo' => 124.50,
    ]);
});

test('cannot create duplicate uma for same year', function () {
    $user = User::factory()->create();
    Uma::factory()->create(['anio' => 2025]);

    $response = $this->actingAs($user)->post(route('umas.store'), [
        'anio' => 2025,
        'saldo' => 115.00,
    ]);

    $response->assertSessionHasErrors(['anio']);
});

test('user can update an uma', function () {
    $user = User::factory()->create();
    $uma = Uma::factory()->create(['anio' => 2024, 'saldo' => 108.00]);

    $response = $this->actingAs($user)->put(route('umas.update', $uma), [
        'anio' => 2024,
        'saldo' => 108.57,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('umas', [
        'id' => $uma->id,
        'saldo' => 108.57,
    ]);
});

test('user can delete an uma', function () {
    $user = User::factory()->create();
    $uma = Uma::factory()->create();

    $response = $this->actingAs($user)->delete(route('umas.destroy', $uma));

    $response->assertRedirect();
    $this->assertDatabaseMissing('umas', ['id' => $uma->id]);
});
