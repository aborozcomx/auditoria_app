<?php

namespace App\Models;

use Database\Factories\EmpleadoSalarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpleadoSalario extends Model
{
    /** @use HasFactory<EmpleadoSalarioFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'empleado_salarios';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'empleado_id',
        'empresa_id',
        'importacion_cfdi_id',
        'importacion_variable_id',
        'categoria_id',
        'anio',
        'periodo',
        'tipo_nomina',
        'salario_base',
        'dias_vacaciones',
        'importe_vacaciones',
        'aguinaldo_diario',
        'prima_vacacional',
        'salario_diario_integrado',
        'salario_diario_integrado_excel',
        'cantidad_dias',
        'importe_suma',
        'sdi_variable',
        'sdi_total',
        'sdi_auditado',
        'imss_cia',
        'diferencia',
        'sdi_quoted',
        'difference',
        'limite_salario_diario',
        'excede_limite',
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
            'salario_base' => 'decimal:2',
            'dias_vacaciones' => 'integer',
            'importe_vacaciones' => 'decimal:2',
            'aguinaldo_diario' => 'decimal:2',
            'prima_vacacional' => 'decimal:2',
            'salario_diario_integrado' => 'decimal:2',
            'salario_diario_integrado_excel' => 'decimal:2',
            'cantidad_dias' => 'decimal:2',
            'importe_suma' => 'decimal:2',
            'sdi_variable' => 'decimal:2',
            'sdi_total' => 'decimal:2',
            'sdi_auditado' => 'decimal:2',
            'imss_cia' => 'decimal:2',
            'diferencia' => 'decimal:2',
            'limite_salario_diario' => 'decimal:2',
            'excede_limite' => 'boolean',
            'uma_utilizada' => 'decimal:2',
            'anio_uma' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Empleado, $this>
     */
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return BelongsTo<ImportacionCfdi, $this>
     */
    public function importacion(): BelongsTo
    {
        return $this->belongsTo(ImportacionCfdi::class, 'importacion_cfdi_id');
    }

    /**
     * @return BelongsTo<ImportacionVariable, $this>
     */
    public function importacionVariable(): BelongsTo
    {
        return $this->belongsTo(ImportacionVariable::class, 'importacion_variable_id');
    }

    /**
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * @return HasMany<EmpleadoCuotaImss, $this>
     */
    public function cuotasImss(): HasMany
    {
        return $this->hasMany(EmpleadoCuotaImss::class, 'empleado_salario_id');
    }

    public function getSdiAudAttribute(): float
    {
        $aud = (float) $this->sdi_auditado;
        if ($aud > 0) {
            return $aud;
        }

        return (float) $this->salario_diario_integrado;
    }

    public function getSdiQuotedAttribute(): float
    {
        return (float) $this->imss_cia;
    }

    public function setSdiQuotedAttribute(mixed $value): void
    {
        $this->attributes['imss_cia'] = $value;
    }

    public function getDifferenceAttribute(): float
    {
        return (float) $this->diferencia;
    }

    public function setDifferenceAttribute(mixed $value): void
    {
        $this->attributes['diferencia'] = $value;
    }
}
