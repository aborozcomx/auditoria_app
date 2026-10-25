<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportacionVariable extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'importaciones_variables';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'empresa_id',
        'anio',
        'archivo_nombre',
        'archivo_ruta',
        'estado',
        'total_filas',
        'filas_procesadas',
        'filas_con_error',
        'empleados_actualizados',
        'salarios_actualizados',
        'empleados_no_encontrados',
        'errores',
        'ultimo_error',
        'iniciado_a',
        'finalizado_a',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'total_filas' => 'integer',
            'filas_procesadas' => 'integer',
            'filas_con_error' => 'integer',
            'empleados_actualizados' => 'integer',
            'salarios_actualizados' => 'integer',
            'empleados_no_encontrados' => 'array',
            'errores' => 'array',
            'iniciado_a' => 'datetime',
            'finalizado_a' => 'datetime',
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
     * @return HasMany<EmpleadoSalario, $this>
     */
    public function salarios(): HasMany
    {
        return $this->hasMany(EmpleadoSalario::class, 'importacion_variable_id');
    }

    public function getPorcentajeAttribute(): int
    {
        if ($this->estado === 'completado') {
            return 100;
        }

        if ($this->total_filas <= 0) {
            return $this->filas_procesadas > 0 ? 50 : 5;
        }

        return (int) min(100, round(($this->filas_procesadas / $this->total_filas) * 100));
    }
}
