<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Uma;
use App\Models\User;
use App\Models\Vacacion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Auditor Principal',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $umas = [
            ['anio' => 2022, 'saldo' => 96.22],
            ['anio' => 2023, 'saldo' => 103.74],
            ['anio' => 2024, 'saldo' => 108.57],
            ['anio' => 2025, 'saldo' => 113.14],
            ['anio' => 2026, 'saldo' => 118.42],
        ];
        foreach ($umas as $uma) {
            Uma::updateOrCreate(['anio' => $uma['anio']], $uma);
        }

        $categoriaOperativa = Categoria::firstOrCreate(['nombre' => 'Operativo']);
        $categoriaAdmin = Categoria::firstOrCreate(['nombre' => 'Administrativo']);
        $categoriaConfianza = Categoria::firstOrCreate(['nombre' => 'Personal de Confianza']);

        $tablaVacaciones = [
            ['anios' => 1, 'dias' => 12],
            ['anios' => 2, 'dias' => 14],
            ['anios' => 3, 'dias' => 16],
            ['anios' => 4, 'dias' => 18],
            ['anios' => 5, 'dias' => 20],
            ['anios' => 6, 'dias' => 22],
        ];

        foreach ([$categoriaOperativa, $categoriaAdmin, $categoriaConfianza] as $cat) {
            foreach ($tablaVacaciones as $vac) {
                Vacacion::updateOrCreate(
                    ['categoria_id' => $cat->id, 'anios' => $vac['anios']],
                    ['dias' => $vac['dias']]
                );
            }
        }

        Empresa::firstOrCreate(
            ['rfc' => 'ACO980415XYZ'],
            [
                'nombre' => 'Auditoría y Consultoría Omega',
                'razon_social' => 'Auditoría y Consultoría Omega S.A. de C.V.',
                'dias_aguinaldo' => 15,
                'prima_vacacional' => 25.00,
            ]
        );

        Empresa::firstOrCreate(
            ['rfc' => 'MIN120520ABC'],
            [
                'nombre' => 'Manufacturas Industriales del Norte',
                'razon_social' => 'Manufacturas Industriales del Norte S.A.P.I. de C.V.',
                'dias_aguinaldo' => 30,
                'prima_vacacional' => 50.00,
            ]
        );
    }
}
