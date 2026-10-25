<?php

namespace App\Models;

use Database\Factories\EmpresaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    /** @use HasFactory<EmpresaFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'rfc',
        'razon_social',
        'dias_aguinaldo',
        'prima_vacacional',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dias_aguinaldo' => 'integer',
            'prima_vacacional' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<Empleado, $this>
     */
    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class);
    }

    /**
     * @return HasMany<ImportacionCfdi, $this>
     */
    public function importacionesCfdi(): HasMany
    {
        return $this->hasMany(ImportacionCfdi::class);
    }

    /**
     * @return HasMany<ImportacionVariable, $this>
     */
    public function importacionesVariables(): HasMany
    {
        return $this->hasMany(ImportacionVariable::class);
    }

    /**
     * @return HasMany<EmpleadoSalario, $this>
     */
    public function empleadoSalarios(): HasMany
    {
        return $this->hasMany(EmpleadoSalario::class);
    }

    /**
     * @return HasMany<ImportacionCuotaImss, $this>
     */
    public function importacionesCuotasImss(): HasMany
    {
        return $this->hasMany(ImportacionCuotaImss::class);
    }

    /**
     * @return HasMany<EmpleadoCuotaImss, $this>
     */
    public function empleadoCuotasImss(): HasMany
    {
        return $this->hasMany(EmpleadoCuotaImss::class);
    }

    public function getVacationBonusAttribute(): float
    {
        return (float) ($this->prima_vacacional ?? 25.0);
    }
}
