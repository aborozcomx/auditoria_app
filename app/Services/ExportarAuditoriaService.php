<?php

namespace App\Services;

use App\Models\EmpleadoCuotaImss;
use App\Models\EmpleadoSalario;
use App\Models\Empresa;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class ExportarAuditoriaService
{
    /**
     * Generate an Excel (.xlsx) file with IMSS and CFDIS sheets matching the audit layout.
     *
     * @return string Temporary file path of the generated Excel workbook.
     */
    public function generarExcel(Empresa $empresa, int $anio, string $periodo): string
    {
        $tempDir = sys_get_temp_dir();
        $tempPath = tempnam($tempDir, 'auditoria_').'.xlsx';

        $options = new Options;
        $writer = new Writer($options);
        $writer->openToFile($tempPath);

        $periodoRango = $this->formatPeriodoRango($anio, $periodo);
        $empresaNombre = (string) ($empresa->nombre ?? 'EMPRESA');

        // =====================================================================
        // SHEET 1: IMSS
        // =====================================================================
        $sheetImss = $writer->getCurrentSheet();
        $sheetImss->setName('IMSS');

        // Top Metadata Rows
        $writer->addRow(Row::fromValues(['CIA', $empresaNombre]));
        $writer->addRow(Row::fromValues(['Tipo de pru', 'IMSS']));
        $writer->addRow(Row::fromValues(['Periodo', $periodoRango]));
        $writer->addRow(Row::fromValues([])); // Blank row 4

        // Row 5: Column Headers
        $headersImss = [
            'Año',
            'Periodo',
            'Empleado',
            'RFC',
            'Num.Emple',
            'NSS',
            'SDC',
            'Días',
            'Ausencias',
            'Incapacidad',
            'Días totales',
            'Diferencia',
            'Cotización',
            'Cotización',
            'Cotización',
            'Cuota fija',
            'SDMG',
            'En dinero',
            'Invalidez y vida',
            'Pensionados',
            'Prima de riesgo',
            'Guarderías',
            'Total audit',
            'Total Comp',
            'Dif. a pagar',
        ];
        $writer->addRow(Row::fromValues($headersImss));

        // Query Cuotas IMSS
        $cuotas = EmpleadoCuotaImss::where('empresa_id', $empresa->id)
            ->where('anio', $anio)
            ->where('periodo', (string) $periodo)
            ->with(['empleado', 'salario'])
            ->orderBy('id', 'asc')
            ->get();

        foreach ($cuotas as $cuota) {
            $sdc = (float) ($cuota->sdi_cotizado > 0 ? $cuota->sdi_cotizado : $cuota->salario_base);
            $difDias = (int) ($cuota->dias - $cuota->total_dias);

            $writer->addRow(Row::fromValues([
                (int) $cuota->anio,
                (string) $cuota->periodo,
                (string) ($cuota->nombre ?: ($cuota->empleado?->nombre ?? '')),
                (string) ($cuota->empleado?->rfc_receptor ?? ''),
                (string) ($cuota->clave ?: ($cuota->empleado?->numero_empleado ?? '')),
                (string) ($cuota->nss ?: ($cuota->empleado?->numero_seguro_social ?? '')),
                $sdc,
                (int) $cuota->dias,
                (int) $cuota->ausencias,
                (int) $cuota->incapacidades,
                (int) $cuota->total_dias,
                $difDias,
                (float) $cuota->base_price_em,
                (float) $cuota->base_price_rt,
                (float) $cuota->base_price_iv,
                (float) $cuota->fixed_price,
                (float) $cuota->sdmg,
                (float) $cuota->in_cash,
                (float) $cuota->disability_health,
                (float) $cuota->pensioners,
                (float) $cuota->risk_price,
                (float) $cuota->nurseries,
                (float) $cuota->total_audit,
                (float) $cuota->subtotal,
                (float) $cuota->difference,
            ]));
        }

        // =====================================================================
        // SHEET 2: CFDIS
        // =====================================================================
        $sheetCfdis = $writer->addNewSheetAndMakeItCurrent();
        $sheetCfdis->setName('CFDIS');

        // Top Metadata Rows
        $writer->addRow(Row::fromValues(['CIA', $empresaNombre]));
        $writer->addRow(Row::fromValues(['Tipo de pru', 'CFDIS']));
        $writer->addRow(Row::fromValues(['Periodo', $periodoRango]));
        $writer->addRow(Row::fromValues([])); // Blank row 4

        // Row 5: Column Headers
        $headersCfdis = [
            'Año',
            'Periodo',
            'Empleado',
            'RFC',
            'Num.Emple',
            'NSS',
            'Fecha inicio',
            'Sueldo diario',
            'Aguinaldo diario',
            'Dias de Vac',
            'Importe de vacaciones',
            'Prima vacacional',
            'SDI fijo',
            'SDI variable',
            'SDI total',
            'Tope SDI',
            'SDI aud.',
            'IMSS',
            'Diferencia',
        ];
        $writer->addRow(Row::fromValues($headersCfdis));

        // Query Salarios CFDI
        $salarios = EmpleadoSalario::where('empresa_id', $empresa->id)
            ->where('anio', $anio)
            ->where('periodo', (string) $periodo)
            ->with(['empleado'])
            ->orderBy('id', 'asc')
            ->get();

        foreach ($salarios as $salario) {
            $fechaInicio = $salario->empleado?->fecha_inicio_relacion_laboral
                ? $salario->empleado->fecha_inicio_relacion_laboral->format('Y-m-d')
                : '';

            $writer->addRow(Row::fromValues([
                (int) $salario->anio,
                (string) $salario->periodo,
                (string) ($salario->empleado?->nombre ?? ''),
                (string) ($salario->empleado?->rfc_receptor ?? ''),
                (string) ($salario->empleado?->numero_empleado ?? ''),
                (string) ($salario->empleado?->numero_seguro_social ?? ''),
                $fechaInicio,
                (float) $salario->salario_base,
                (float) $salario->aguinaldo_diario,
                (int) $salario->dias_vacaciones,
                (float) $salario->importe_vacaciones,
                (float) $salario->prima_vacacional,
                (float) $salario->salario_diario_integrado,
                (float) $salario->sdi_variable,
                (float) $salario->sdi_total,
                (float) $salario->limite_salario_diario,
                (float) $salario->sdi_auditado,
                (float) $salario->imss_cia,
                (float) $salario->diferencia,
            ]));
        }

        $writer->close();

        return $tempPath;
    }

    /**
     * Get the standardized file name: Pruebas_(RFC_empresa)_(periodo)_(año).xlsx
     */
    public function obtenerNombreArchivo(Empresa $empresa, int $anio, string $periodo): string
    {
        $rfc = preg_replace('/[^A-Za-z0-9]/', '', strtoupper((string) ($empresa->rfc ?: 'EMPRESA')));

        return "Pruebas_{$rfc}_{$periodo}_{$anio}.xlsx";
    }

    /**
     * Format the period into standard readable date range (e.g. 1 de marzo de 2025 - 31 de marzo de 2025).
     */
    public function formatPeriodoRango(int $anio, string $periodo): string
    {
        $periodoNum = (int) preg_replace('/\D/', '', $periodo);

        $meses = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        if ($periodoNum >= 1 && $periodoNum <= 12) {
            $mesNombre = $meses[$periodoNum];
            $ultimoDia = (int) date('t', (int) strtotime("{$anio}-".str_pad((string) $periodoNum, 2, '0', STR_PAD_LEFT).'-01'));

            return "1 de {$mesNombre} de {$anio} - {$ultimoDia} de {$mesNombre} de {$anio}";
        }

        return "Periodo {$periodo} de {$anio}";
    }
}
