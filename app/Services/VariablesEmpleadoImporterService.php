<?php

namespace App\Services;

use App\Models\Empleado;
use App\Models\EmpleadoSalario;
use App\Models\Empresa;
use App\Models\ImportacionVariable;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class VariablesEmpleadoImporterService
{
    /**
     * Map of month to target salary periods.
     *
     * @return array<int, list<string>>
     */
    public function getTargetPeriodsForMonth(int $month): array
    {
        return match ($month) {
            11, 12 => ['1', '2'],
            1, 2 => ['3', '4'],
            3, 4 => ['5', '6'],
            5, 6 => ['7', '8'],
            7, 8 => ['9', '10'],
            9, 10 => ['11', '12'],
            default => [],
        };
    }

    /**
     * Pre-validate file headers before queuing.
     *
     * @throws ValidationException
     */
    public function validarArchivo(string $filePath, ?string $originalExtension = null): void
    {
        try {
            $reader = $this->createReaderForFile($filePath, $originalExtension);
            $reader->open($filePath);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el archivo. Asegúrate de que sea un archivo Excel (.xlsx) o CSV válido.',
            ]);
        }

        $headerMap = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $headerMap = $this->buildHeaderMap($row->toArray());
                    break;
                }
                break;
            }
        } finally {
            $reader->close();
        }

        if (! isset($headerMap['numero_empleado'])) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no contiene la columna de Número de empleado ("Número de personal" o similar).',
            ]);
        }

        if (! isset($headerMap['fecha_final'])) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no contiene la columna de fecha ("Final del período en Nómina" o similar).',
            ]);
        }
    }

    /**
     * Process variables import asynchronously in queue.
     */
    public function processImportacion(ImportacionVariable $importacion, bool $resume = false): void
    {
        $filePath = Storage::disk('local')->path($importacion->archivo_ruta);

        if (! file_exists($filePath)) {
            $importacion->update([
                'estado' => 'fallido',
                'ultimo_error' => "El archivo en la ruta {$importacion->archivo_ruta} no existe en el almacenamiento.",
                'finalizado_a' => now(),
            ]);

            return;
        }

        $importacion->update([
            'estado' => 'procesando',
            'iniciado_a' => $importacion->iniciado_a ?? now(),
        ]);

        try {
            $result = $this->process(
                $filePath,
                $importacion->empresa,
                $importacion->anio,
                $importacion->archivo_nombre,
                $importacion
            );

            $importacion->update([
                'estado' => 'completado',
                'total_filas' => $result['total_filas'],
                'filas_procesadas' => $result['total_filas'],
                'empleados_actualizados' => $result['empleados_actualizados'],
                'salarios_actualizados' => $result['salarios_actualizados'],
                'empleados_no_encontrados' => $result['empleados_no_encontrados'],
                'finalizado_a' => now(),
            ]);
        } catch (\Throwable $e) {
            $importacion->update([
                'estado' => 'fallido',
                'ultimo_error' => $e->getMessage(),
                'finalizado_a' => now(),
            ]);
        }
    }

    /**
     * Import and apply variable salary components for a company and year.
     *
     * @return array{
     *     total_filas: int,
     *     empleados_en_archivo: int,
     *     empleados_actualizados: int,
     *     salarios_actualizados: int,
     *     empleados_no_encontrados: list<string>
     * }
     *
     * @throws ValidationException|Exception
     */
    public function process(
        string $filePath,
        Empresa $empresa,
        int $anio,
        ?string $originalExtension = null,
        ?ImportacionVariable $importacion = null
    ): array {
        try {
            $reader = $this->createReaderForFile($filePath, $originalExtension);
            $reader->open($filePath);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el archivo. Asegúrate de que sea un archivo Excel (.xlsx) o CSV válido.',
            ]);
        }

        $headerMap = [];
        $totalFilas = 0;
        $batchCounter = 0;

        /**
         * Grouped structure:
         * [
         *     numero_empleado => [
         *         bimestre_key (e.g. '3_4') => [
         *             'target_periods' => ['3', '4'],
         *             'dias' => 59.00,
         *             'importe' => 2082.28,
         *         ]
         *     ]
         * ]
         *
         * @var array<string, array<string, array{target_periods: list<string>, dias: float, importe: float}>> $grouped
         */
        $grouped = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rowIndex = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $rowIndex++;
                    $cells = $row->toArray();

                    if ($rowIndex === 1) {
                        $headerMap = $this->buildHeaderMap($cells);

                        continue;
                    }

                    if ($this->isEmptyRow($cells)) {
                        continue;
                    }

                    $totalFilas++;

                    // Extract numero de empleado / personal
                    $numEmpleado = trim((string) $this->getCellValue($cells, $headerMap, 'numero_empleado', ''));
                    if ($numEmpleado === '') {
                        continue;
                    }

                    // Extract date / month
                    $fechaRaw = $this->getCellValue($cells, $headerMap, 'fecha_final');
                    $mes = $this->parseMonth($fechaRaw);
                    if ($mes === null) {
                        continue;
                    }

                    $targetPeriods = $this->getTargetPeriodsForMonth($mes);
                    if (empty($targetPeriods)) {
                        continue;
                    }

                    $bimestreKey = implode('_', $targetPeriods);

                    if (! isset($grouped[$numEmpleado][$bimestreKey])) {
                        $grouped[$numEmpleado][$bimestreKey] = [
                            'target_periods' => $targetPeriods,
                            'dias' => 0.0,
                            'importe' => 0.0,
                        ];
                    }

                    $conceptoRaw = (string) $this->getCellValue($cells, $headerMap, 'concepto', '');
                    $conceptoClean = mb_strtolower(Str::slug($conceptoRaw, ' '));

                    $cantidad = $this->parseNumericValue($this->getCellValue($cells, $headerMap, 'cantidad', 0));
                    $importe = $this->parseNumericValue($this->getCellValue($cells, $headerMap, 'importe', 0));

                    // Días calendario
                    if (str_contains($conceptoClean, 'dia') || str_contains($conceptoClean, 'calendario')) {
                        $grouped[$numEmpleado][$bimestreKey]['dias'] += $cantidad;
                    }
                    // Parte variable acumulada
                    elseif (str_contains($conceptoClean, 'variable') || str_contains($conceptoClean, 'acumulad')) {
                        $grouped[$numEmpleado][$bimestreKey]['importe'] += $importe;
                    } else {
                        // Fallback: if quantity > 0 and importe == 0, count as days; if importe > 0, count as amount
                        if ($cantidad > 0 && $importe == 0) {
                            $grouped[$numEmpleado][$bimestreKey]['dias'] += $cantidad;
                        } elseif ($importe > 0) {
                            $grouped[$numEmpleado][$bimestreKey]['importe'] += $importe;
                        }
                    }

                    $batchCounter++;
                    if ($importacion && $batchCounter >= 50) {
                        $importacion->update([
                            'total_filas' => $totalFilas,
                            'filas_procesadas' => $totalFilas,
                        ]);
                        $batchCounter = 0;
                    }
                }
                break; // Process the first sheet
            }
        } finally {
            $reader->close();
        }

        if (empty($headerMap)) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo está vacío o no contiene encabezados válidos.',
            ]);
        }

        if ($totalFilas === 0 || empty($grouped)) {
            throw ValidationException::withMessages([
                'archivo' => 'No se encontraron registros de variables válidos en el archivo.',
            ]);
        }

        // Apply to database
        $empleadosActualizados = 0;
        $salariosActualizados = 0;
        $noEncontrados = [];

        foreach ($grouped as $numEmpleado => $bimestres) {
            $empleado = Empleado::where('empresa_id', $empresa->id)
                ->where('numero_empleado', (string) $numEmpleado)
                ->first();

            if (! $empleado) {
                // If employee is not registered in the system, it's ignored/set to 0
                if (! in_array((string) $numEmpleado, $noEncontrados, true)) {
                    $noEncontrados[] = (string) $numEmpleado;
                }

                continue;
            }

            $empleadoActualizado = false;

            foreach ($bimestres as $data) {
                $dias = round($data['dias'], 2);
                $importe = round($data['importe'], 2);
                $sdiVariable = $dias > 0 ? round($importe / $dias, 2) : 0.00;

                foreach ($data['target_periods'] as $periodo) {
                    $salario = EmpleadoSalario::where('empleado_id', $empleado->id)
                        ->where('empresa_id', $empresa->id)
                        ->where('anio', $anio)
                        ->where('periodo', (string) $periodo)
                        ->first();

                    if (! $salario) {
                        continue;
                    }

                    $sdiCalculado = (float) $salario->salario_diario_integrado;
                    $sdiTotal = round($sdiCalculado + $sdiVariable, 2);
                    $tope = (float) $salario->limite_salario_diario;
                    $sdiAuditado = ($tope > 0 && $sdiTotal > $tope) ? $tope : $sdiTotal;

                    $salario->update([
                        'importacion_variable_id' => $importacion?->id ?? $salario->importacion_variable_id,
                        'cantidad_dias' => $dias,
                        'importe_suma' => $importe,
                        'sdi_variable' => $sdiVariable,
                        'sdi_total' => $sdiTotal,
                        'sdi_auditado' => $sdiAuditado,
                    ]);

                    $salariosActualizados++;
                    $empleadoActualizado = true;
                }
            }

            if ($empleadoActualizado) {
                $empleadosActualizados++;
            }

            if ($importacion && $empleadosActualizados % 20 === 0) {
                $importacion->update([
                    'empleados_actualizados' => $empleadosActualizados,
                    'salarios_actualizados' => $salariosActualizados,
                    'empleados_no_encontrados' => $noEncontrados,
                ]);
            }
        }

        return [
            'total_filas' => $totalFilas,
            'empleados_en_archivo' => count($grouped),
            'empleados_actualizados' => $empleadosActualizados,
            'salarios_actualizados' => $salariosActualizados,
            'empleados_no_encontrados' => $noEncontrados,
        ];
    }

    /**
     * Build header map based on expected columns.
     *
     * @param  array<int, mixed>  $row
     * @return array<string, int>
     */
    protected function buildHeaderMap(array $row): array
    {
        $map = [];

        foreach ($row as $index => $rawHeader) {
            $normalized = Str::slug((string) $rawHeader, '_');
            $clean = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $rawHeader));

            // Número de personal / empleado
            if (! isset($map['numero_empleado']) && (
                str_contains($clean, 'personal') ||
                str_contains($clean, 'empleado') ||
                str_contains($normalized, 'personal') ||
                str_contains($normalized, 'empleado')
            )) {
                $map['numero_empleado'] = $index;
            }
            // Final del período en Nómina / fecha final
            elseif (! isset($map['fecha_final']) && (
                str_contains($normalized, 'final') ||
                str_contains($clean, 'final') ||
                str_contains($clean, 'fechafin') ||
                str_contains($clean, 'periodofin')
            )) {
                $map['fecha_final'] = $index;
            }
            // Texto expl.CC-nómina / concepto
            elseif (! isset($map['concepto']) && (
                str_contains($normalized, 'texto') ||
                str_contains($normalized, 'expl') ||
                str_contains($clean, 'texto') ||
                str_contains($clean, 'concepto') ||
                str_contains($clean, 'nomina')
            )) {
                $map['concepto'] = $index;
            }
            // Cantidad
            elseif (! isset($map['cantidad']) && (
                str_contains($normalized, 'cantidad') ||
                str_contains($clean, 'cantidad') ||
                $clean === 'cant'
            )) {
                $map['cantidad'] = $index;
            }
            // Importe
            elseif (! isset($map['importe']) && (
                str_contains($normalized, 'importe') ||
                str_contains($clean, 'importe') ||
                str_contains($clean, 'monto')
            )) {
                $map['importe'] = $index;
            }
        }

        return $map;
    }

    /**
     * Parse date value to extract month (1-12).
     */
    protected function parseMonth(mixed $value): ?int
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return (int) $value->format('n');
        }

        $str = trim((string) $value);
        if (empty($str)) {
            return null;
        }

        // Match dd/mm/yyyy or d/m/yyyy
        if (preg_match('/^\d{1,2}[\/\-](\d{1,2})[\/\-]\d{2,4}/', $str, $matches)) {
            $month = (int) $matches[1];
            if ($month >= 1 && $month <= 12) {
                return $month;
            }
        }

        // Try Carbon parse
        try {
            return (int) Carbon::parse($str)->format('n');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Parse numeric value.
     */
    protected function parseNumericValue(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (empty($value)) {
            return 0.0;
        }

        $str = str_replace(['$', ' ', 'MXN'], '', trim((string) $value));
        if (str_contains($str, ',') && ! str_contains($str, '.')) {
            $str = str_replace(',', '.', $str);
        } else {
            $str = str_replace(',', '', $str);
        }

        return (float) $str;
    }

    /**
     * Safely get cell value.
     *
     * @param  array<int, mixed>  $cells
     * @param  array<string, int>  $headerMap
     */
    protected function getCellValue(array $cells, array $headerMap, string $key, mixed $default = null): mixed
    {
        if (isset($headerMap[$key]) && array_key_exists($headerMap[$key], $cells)) {
            $val = $cells[$headerMap[$key]];

            return $val !== null ? $val : $default;
        }

        return $default;
    }

    /**
     * Check if row is empty.
     *
     * @param  array<int, mixed>  $cells
     */
    protected function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Create OpenSpout reader.
     */
    public function createReaderForFile(string $filePath, ?string $originalExtension = null): ReaderInterface
    {
        $ext = '';
        if (! empty($originalExtension)) {
            $ext = strtolower(pathinfo($originalExtension, PATHINFO_EXTENSION));
            if (empty($ext)) {
                $ext = strtolower($originalExtension);
            }
        }
        if (empty($ext)) {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        }

        if ($ext === 'csv' || $ext === 'txt') {
            return new CsvReader;
        }

        return new XlsxReader;
    }
}
