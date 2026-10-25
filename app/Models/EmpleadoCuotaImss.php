<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpleadoCuotaImss extends Model
{
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'empleado_cuotas_imss';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'importacion_cuota_imss_id',
        'empresa_id',
        'empleado_id',
        'empleado_salario_id',
        'anio',
        'periodo',
        'nss',
        'nombre',
        'clave',
        'registro_patronal',
        'dias',
        'ausencias',
        'incapacidades',
        'total_dias',
        'dias_incapacidad',
        'sdi_cotizado',
        'sdi_archivo',
        'salario_base',
        'salario_incapacidad',
        'salario_uma_tope',
        'base_price_em',
        'base_price_rt',
        'base_price_iv',
        'salario_uma',
        'fixed_price',
        'sdmg',
        'in_cash',
        'disability_health',
        'pensioners',
        'risk',
        'risk_price',
        'nurseries',
        'total_audit',
        'subtotal',
        'difference',
        'uma_utilizada',
        'anio_uma',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'dias' => 'integer',
            'ausencias' => 'integer',
            'incapacidades' => 'integer',
            'total_dias' => 'integer',
            'dias_incapacidad' => 'integer',
            'sdi_cotizado' => 'decimal:2',
            'sdi_archivo' => 'decimal:2',
            'salario_base' => 'decimal:2',
            'salario_incapacidad' => 'decimal:2',
            'salario_uma_tope' => 'decimal:2',
            'base_price_em' => 'decimal:2',
            'base_price_rt' => 'decimal:2',
            'base_price_iv' => 'decimal:2',
            'salario_uma' => 'decimal:2',
            'fixed_price' => 'decimal:2',
            'sdmg' => 'decimal:2',
            'in_cash' => 'decimal:2',
            'disability_health' => 'decimal:2',
            'pensioners' => 'decimal:2',
            'risk' => 'decimal:5',
            'risk_price' => 'decimal:2',
            'nurseries' => 'decimal:2',
            'total_audit' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'difference' => 'decimal:2',
            'uma_utilizada' => 'decimal:2',
            'anio_uma' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ImportacionCuotaImss, $this>
     */
    public function importacion(): BelongsTo
    {
        return $this->belongsTo(ImportacionCuotaImss::class, 'importacion_cuota_imss_id');
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return BelongsTo<Empleado, $this>
     */
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    /**
     * @return BelongsTo<EmpleadoSalario, $this>
     */
    public function salario(): BelongsTo
    {
        return $this->belongsTo(EmpleadoSalario::class, 'empleado_salario_id');
    }

    // English aliases
    public function getDaysAttribute(): int
    {
        return (int) $this->dias;
    }

    public function getAbsenceAttribute(): int
    {
        return (int) $this->ausencias;
    }

    public function getIncapacityAttribute(): int
    {
        return (int) $this->incapacidades;
    }

    public function getTotalDaysAttribute(): int
    {
        return (int) $this->total_dias;
    }

    public function getDaysIncapacityAttribute(): int
    {
        return (int) $this->dias_incapacidad;
    }

    public function getSdiQuotedAttribute(): float
    {
        return (float) $this->sdi_cotizado;
    }

    public function getSalaryIncapacityAttribute(): float
    {
        return (float) $this->salario_incapacidad;
    }

    public function getSalaryUmaBonusAttribute(): float
    {
        return (float) $this->salario_uma_tope;
    }

    public function getSalaryUmaAttribute(): float
    {
        return (float) $this->salario_uma;
    }
}
