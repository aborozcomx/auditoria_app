<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportacionCuotaImss extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'importaciones_cuotas_imss';

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
        'cuotas_creadas',
        'salarios_actualizados',
        'total_auditoria',
        'total_imss',
        'total_diferencia',
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
            'cuotas_creadas' => 'integer',
            'salarios_actualizados' => 'integer',
            'total_auditoria' => 'decimal:2',
            'total_imss' => 'decimal:2',
            'total_diferencia' => 'decimal:2',
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
     * @return HasMany<EmpleadoCuotaImss, $this>
     */
    public function cuotas(): HasMany
    {
        return $this->hasMany(EmpleadoCuotaImss::class, 'importacion_cuota_imss_id');
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
