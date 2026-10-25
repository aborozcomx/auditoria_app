<?php

namespace App\Models;

use Database\Factories\EmpleadoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empleado extends Model
{
    /** @use HasFactory<EmpleadoFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'empleados';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'empresa_id',
        'categoria_id',
        'registro_patronal',
        'rfc_receptor',
        'nombre',
        'fecha_inicio_relacion_laboral',
        'numero_empleado',
        'antiguedad_texto',
        'antiguedad_semanas',
        'antiguedad_anios',
        'es_sindicalizado',
        'curp',
        'numero_seguro_social',
        'salario_base',
        'salario_diario_integrado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio_relacion_laboral' => 'date',
            'antiguedad_semanas' => 'integer',
            'antiguedad_anios' => 'integer',
            'es_sindicalizado' => 'boolean',
            'salario_base' => 'decimal:2',
            'salario_diario_integrado' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * @return HasMany<EmpleadoSalario, $this>
     */
    public function salarios(): HasMany
    {
        return $this->hasMany(EmpleadoSalario::class);
    }

    /**
     * @return HasMany<EmpleadoCuotaImss, $this>
     */
    public function cuotasImss(): HasMany
    {
        return $this->hasMany(EmpleadoCuotaImss::class);
    }

    /**
     * Alias for employee company.
     *
     * @return BelongsTo<Empresa, $this>
     */
    public function company(): BelongsTo
    {
        return $this->empresa();
    }
}
