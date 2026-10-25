<?php

namespace App\Services;

use App\Models\Empleado;
use App\Models\EmpleadoCuotaImss;
use App\Models\EmpleadoSalario;
use App\Models\Empresa;
use App\Models\ImportacionCuotaImss;
use App\Models\Uma;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class CuotasImssImporterService
{
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
                'archivo' => 'No se pudo leer el archivo. Asegúrate de que sea un archivo Excel (.xlsx, .xls) o CSV válido.',
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

        if (! isset($headerMap['nss']) && ! isset($headerMap['nom'])) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no contiene la columna de NSS (Número de Seguro Social) ni nombre del empleado.',
            ]);
        }

        if (! isset($headerMap['dias'])) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no contiene la columna de Días ("Dias", "Días" o similar).',
            ]);
        }

        if (! isset($headerMap['sdi'])) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no contiene la columna de SDI ("SDI" o similar).',
            ]);
        }

        if (! isset($headerMap['cuota_imss'])) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no contiene la columna de Cuota IMSS ("Cuota IMSS", "Cuota", "Subtotal" o similar).',
            ]);
        }
    }

    /**
     * Process IMSS quotas import asynchronously in queue.
     */
    public function processImportacion(ImportacionCuotaImss $importacion, bool $resume = false): void
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

        if (! $resume) {
            $importacion->cuotas()->delete();
        }

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
                'cuotas_creadas' => $result['cuotas_creadas'],
                'salarios_actualizados' => $result['salarios_actualizados'],
                'total_auditoria' => $result['total_auditoria'],
                'total_imss' => $result['total_imss'],
                'total_diferencia' => $result['total_diferencia'],
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
     * Import and calculate IMSS quotas for a company and year.
     *
     * @return array{
     *     total_filas: int,
     *     cuotas_creadas: int,
     *     salarios_actualizados: int,
     *     total_auditoria: float,
     *     total_imss: float,
     *     total_diferencia: float,
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
        ?ImportacionCuotaImss $importacion = null
    ): array {
        try {
            $reader = $this->createReaderForFile($filePath, $originalExtension);
            $reader->open($filePath);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el archivo. Asegúrate de que sea un archivo Excel (.xlsx, .xls) o CSV válido.',
            ]);
        }

        $headerMap = [];
        $totalFilas = 0;
        $batchCounter = 0;
        $cuotasCreadas = 0;
        $salariosActualizados = 0;
        $salariosActualizadosIds = [];
        $seenRowsInFile = [];
        $totalAuditoriaAcum = 0.0;
        $totalImssAcum = 0.0;
        $totalDiferenciaAcum = 0.0;
        $noEncontrados = [];

        /** @var array<int, Uma> $umasCache */
        $umasCache = [];
        $latestUma = Uma::orderBy('anio', 'desc')->first();

        $vacationBonus = (float) ($empresa->prima_vacacional ?? 25.0);
        if ($vacationBonus <= 0) {
            $vacationBonus = 25.0;
        }

        // Pre-load employees of this company for fast lookup
        $empleadosByNss = Empleado::where('empresa_id', $empresa->id)
            ->whereNotNull('numero_seguro_social')
            ->get()
            ->keyBy(fn (Empleado $e) => preg_replace('/\D/', '', (string) $e->numero_seguro_social));

        $empleadosByNumero = Empleado::where('empresa_id', $empresa->id)
            ->whereNotNull('numero_empleado')
            ->get()
            ->keyBy(fn (Empleado $e) => trim((string) $e->numero_empleado));

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

                    // Extract cell values
                    $periodo = trim((string) $this->getCellValue($cells, $headerMap, 'periodo', '1'));
                    if ($periodo === '') {
                        $periodo = '1';
                    }

                    $nssRaw = trim((string) $this->getCellValue($cells, $headerMap, 'nss', ''));
                    $nssClean = preg_replace('/\D/', '', $nssRaw);
                    $nom = trim((string) $this->getCellValue($cells, $headerMap, 'nom', ''));
                    $clave = trim((string) $this->getCellValue($cells, $headerMap, 'clave', ''));
                    $rp = trim((string) $this->getCellValue($cells, $headerMap, 'rp', ''));

                    $days = (int) $this->parseNumericValue($this->getCellValue($cells, $headerMap, 'dias', 0));
                    $absence = (int) $this->parseNumericValue($this->getCellValue($cells, $headerMap, 'aus', 0));
                    $incapacity = (int) $this->parseNumericValue($this->getCellValue($cells, $headerMap, 'inc', 0));
                    $sdi_quoted = round($this->parseNumericValue($this->getCellValue($cells, $headerMap, 'sdi', 0)), 2);
                    $subtotal = round($this->parseNumericValue($this->getCellValue($cells, $headerMap, 'cuota_imss', 0)), 2);
                    $primaRaw = $this->parseNumericValue($this->getCellValue($cells, $headerMap, 'prima', 0));

                    // Skip empty rows with no days and no sdi
                    if ($days <= 0 && $sdi_quoted <= 0 && $subtotal <= 0) {
                        continue;
                    }

                    // Validate that duplicate records in the file are not processed more than once
                    $empIdentKey = $nssClean !== '' ? $nssClean : ($clave !== '' ? $clave : mb_strtolower($nom));
                    $rowFingerprint = implode('|', [
                        $empIdentKey,
                        (string) $periodo,
                        $days,
                        $absence,
                        $incapacity,
                        number_format($sdi_quoted, 2, '.', ''),
                        number_format($subtotal, 2, '.', ''),
                        $rp,
                    ]);

                    if (isset($seenRowsInFile[$rowFingerprint])) {
                        // Duplicate row in file: already handled
                        continue;
                    }
                    $seenRowsInFile[$rowFingerprint] = true;

                    // Employee lookup
                    /** @var Empleado|null $empleado */
                    $empleado = null;
                    if ($nssClean !== '' && isset($empleadosByNss[$nssClean])) {
                        $empleado = $empleadosByNss[$nssClean];
                    } elseif ($clave !== '' && isset($empleadosByNumero[$clave])) {
                        $empleado = $empleadosByNumero[$clave];
                    } elseif ($nom !== '') {
                        $empleado = Empleado::where('empresa_id', $empresa->id)
                            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nom)])
                            ->first();
                    }

                    if (! $empleado && ($nssClean !== '' || $nom !== '')) {
                        $identifier = $nssClean !== '' ? $nssClean : $nom;
                        if (! in_array($identifier, $noEncontrados, true)) {
                            $noEncontrados[] = $identifier;
                        }
                    }

                    // Salary record lookup for the employee, year and period
                    /** @var EmpleadoSalario|null $salary */
                    $salary = null;
                    if ($empleado) {
                        $periodoInt = (int) preg_replace('/\D/', '', (string) $periodo);
                        $salary = EmpleadoSalario::where('empleado_id', $empleado->id)
                            ->where('empresa_id', $empresa->id)
                            ->where('anio', $anio)
                            ->where(function ($q) use ($periodo, $periodoInt) {
                                $q->where('periodo', (string) $periodo);
                                if ($periodoInt > 0) {
                                    $q->orWhere('periodo', (string) $periodoInt)
                                        ->orWhere('periodo', str_pad((string) $periodoInt, 2, '0', STR_PAD_LEFT));
                                }
                            })
                            ->first();
                    }

                    // =========================================================================
                    // 1. VALOR QUE VIENE DEL ARCHIVO IMPORTADO (Archivo Excel de Cuotas IMSS)
                    // =========================================================================
                    $sdi_archivo_importado = $sdi_quoted;

                    // =========================================================================
                    // 2. VALOR QUE VIENE DE LOS REGISTROS DE LOS SALARIOS
                    // =========================================================================
                    if ($salary) {
                        // Salario Diario Integrado con prestaciones y variables del registro de salarios:
                        $sdi_registro_salarios = (float) ($salary->sdi_total > 0 ? $salary->sdi_total : $salary->sdi_aud);
                        // Salario base de auditoría (topado para ramas de aseguramiento IMSS):
                        $sdi_aud = (float) $salary->sdi_aud;
                        $difference = round($sdi_aud - $sdi_archivo_importado, 2);

                        $updateData = [
                            'difference' => $difference,
                        ];

                        if ($sdi_archivo_importado > 0) {
                            $updateData['sdi_quoted'] = $sdi_archivo_importado;
                        }

                        $salary->update($updateData);

                        if (! in_array($salary->id, $salariosActualizadosIds, true)) {
                            $salariosActualizadosIds[] = $salary->id;
                            $salariosActualizados++;
                        }
                    } elseif ($empleado && (float) $empleado->salario_diario_integrado > 0) {
                        $sdi_registro_salarios = (float) $empleado->salario_diario_integrado;
                        $sdi_aud = $sdi_registro_salarios;
                    } else {
                        $sdi_registro_salarios = $sdi_archivo_importado;
                        $sdi_aud = $sdi_archivo_importado;
                    }

                    // UMA calculation: When period == 1, take UMA of selected year minus 1
                    $periodoInt = (int) $periodo;
                    $anioUma = ($periodoInt === 1) ? ($anio - 1) : $anio;

                    if (! isset($umasCache[$anioUma])) {
                        $umasCache[$anioUma] = Uma::where('anio', $anioUma)->first() ?? $latestUma;
                    }
                    $uma = $umasCache[$anioUma];
                    $umaBalance = (float) ($uma?->balance ?? 108.57);

                    // Required formulas
                    $total_days = $days - $absence - $incapacity;
                    $days_incapacity = $days - $incapacity;

                    // Salario base de auditoría obtenido del registro de salarios:
                    $base_salary = $sdi_aud;

                    $salary_incapacity = round($base_salary * $days_incapacity, 2);
                    $salary_uma_bonus = round($umaBalance * $vacationBonus * $days_incapacity, 2);

                    $base_price_em = round($base_salary * $total_days, 2);
                    $base_price_rt = $base_price_em;
                    $base_price_iv = min($salary_incapacity, $salary_uma_bonus);

                    $salary_uma = round($umaBalance * $days_incapacity, 2);
                    $fixed_price = round($salary_uma * 0.2040, 2);
                    $sdmg = $base_salary > $umaBalance * 3
                        ? round(($base_price_em - $umaBalance * 3 * $total_days) * 0.015, 2)
                        : 0.0;
                    $in_cash = round($base_price_em * 0.0095, 2);
                    $disability_health = round($base_price_iv * 0.02375, 2);
                    $pensioners = round($base_price_em * 0.01425, 2);
                    $risk = $primaRaw / 100;
                    $risk_price = round($base_price_rt * $risk, 2);
                    $nurseries = round($base_price_rt * 0.01, 2);

                    $total_audit = round(
                        $fixed_price + $sdmg + $in_cash + $disability_health + $pensioners + $risk_price + $nurseries,
                        2
                    );
                    $differenceQuote = round($total_audit - $subtotal, 2);

                    $nssToSave = $nssClean !== '' ? $nssClean : ($empleado?->numero_seguro_social ?? $nssRaw);
                    $nombreToSave = $nom !== '' ? $nom : ($empleado?->nombre ?? null);
                    $claveToSave = $clave !== '' ? $clave : ($empleado?->numero_empleado ?? null);
                    $rpToSave = $rp !== '' ? $rp : ($empleado?->registro_patronal ?? null);

                    // Check for existing cuota in the database to prevent duplicate records
                    $cuotaExistenteQuery = EmpleadoCuotaImss::where('empresa_id', $empresa->id)
                        ->where('anio', $anio)
                        ->where('periodo', (string) $periodo)
                        ->where('dias', $days)
                        ->where('ausencias', $absence)
                        ->where('incapacidades', $incapacity)
                        ->where(function ($q) use ($sdi_registro_salarios, $base_salary, $sdi_archivo_importado) {
                            $q->where('sdi_cotizado', $sdi_registro_salarios)
                                ->orWhere('sdi_cotizado', $base_salary)
                                ->orWhere('sdi_cotizado', $sdi_archivo_importado)
                                ->orWhere('salario_base', $base_salary);
                        })
                        ->where('subtotal', $subtotal);

                    if ($nssToSave !== '') {
                        $cuotaExistenteQuery->where('nss', $nssToSave);
                    } elseif ($empleado) {
                        $cuotaExistenteQuery->where('empleado_id', $empleado->id);
                    }

                    if ($rpToSave !== null && $rpToSave !== '') {
                        $cuotaExistenteQuery->where('registro_patronal', $rpToSave);
                    }

                    $cuotaExistente = $cuotaExistenteQuery->first();

                    $cuotaData = [
                        'importacion_cuota_imss_id' => $importacion?->id,
                        'empresa_id' => $empresa->id,
                        'empleado_id' => $empleado?->id,
                        'empleado_salario_id' => $salary?->id,
                        'anio' => $anio,
                        'periodo' => (string) $periodo,
                        'nss' => $nssToSave,
                        'nombre' => $nombreToSave,
                        'clave' => $claveToSave,
                        'registro_patronal' => $rpToSave,
                        'dias' => $days,
                        'ausencias' => $absence,
                        'incapacidades' => $incapacity,
                        'total_dias' => $total_days,
                        'dias_incapacidad' => $days_incapacity,
                        // Valor para columna SDI Cotizado (del registro de salarios con variables y prestaciones):
                        'sdi_cotizado' => $sdi_registro_salarios,
                        // Valor que viene del archivo Excel importado:
                        'sdi_archivo' => $sdi_archivo_importado,
                        // Salario base de auditoría del registro de salarios:
                        'salario_base' => $base_salary,
                        'salario_incapacidad' => $salary_incapacity,
                        'salario_uma_tope' => $salary_uma_bonus,
                        'base_price_em' => $base_price_em,
                        'base_price_rt' => $base_price_rt,
                        'base_price_iv' => $base_price_iv,
                        'salario_uma' => $salary_uma,
                        'fixed_price' => $fixed_price,
                        'sdmg' => $sdmg,
                        'in_cash' => $in_cash,
                        'disability_health' => $disability_health,
                        'pensioners' => $pensioners,
                        'risk' => $risk,
                        'risk_price' => $risk_price,
                        'nurseries' => $nurseries,
                        'total_audit' => $total_audit,
                        'subtotal' => $subtotal,
                        'difference' => $differenceQuote,
                        'uma_utilizada' => $umaBalance,
                        'anio_uma' => $anioUma,
                    ];

                    if ($cuotaExistente) {
                        $cuotaExistente->update($cuotaData);
                    } else {
                        EmpleadoCuotaImss::create($cuotaData);
                    }

                    $cuotasCreadas++;
                    $totalAuditoriaAcum += $total_audit;
                    $totalImssAcum += $subtotal;
                    $totalDiferenciaAcum += $differenceQuote;

                    $batchCounter++;
                    if ($importacion && $batchCounter >= 50) {
                        $importacion->update([
                            'total_filas' => $totalFilas,
                            'filas_procesadas' => $totalFilas,
                            'cuotas_creadas' => $cuotasCreadas,
                            'salarios_actualizados' => $salariosActualizados,
                            'total_auditoria' => round($totalAuditoriaAcum, 2),
                            'total_imss' => round($totalImssAcum, 2),
                            'total_diferencia' => round($totalDiferenciaAcum, 2),
                            'empleados_no_encontrados' => $noEncontrados,
                        ]);
                        $batchCounter = 0;
                    }
                }
                break; // Process first sheet
            }
        } finally {
            $reader->close();
        }

        if (empty($headerMap)) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo está vacío o no contiene encabezados válidos.',
            ]);
        }

        if ($totalFilas === 0 || $cuotasCreadas === 0) {
            throw ValidationException::withMessages([
                'archivo' => 'No se encontraron registros de cuotas IMSS válidos en el archivo.',
            ]);
        }

        return [
            'total_filas' => $totalFilas,
            'cuotas_creadas' => $cuotasCreadas,
            'salarios_actualizados' => $salariosActualizados,
            'total_auditoria' => round($totalAuditoriaAcum, 2),
            'total_imss' => round($totalImssAcum, 2),
            'total_diferencia' => round($totalDiferenciaAcum, 2),
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

            // Periodo
            if (! isset($map['periodo']) && (
                str_contains($normalized, 'periodo') ||
                str_contains($clean, 'periodo') ||
                $clean === 'per'
            )) {
                $map['periodo'] = $index;
            }
            // Clave
            elseif (! isset($map['clave']) && (
                str_contains($normalized, 'clave') ||
                $clean === 'clave' ||
                $clean === 'cve'
            )) {
                $map['clave'] = $index;
            }
            // NSS
            elseif (! isset($map['nss']) && (
                str_contains($clean, 'nss') ||
                str_contains($normalized, 'nss') ||
                str_contains($clean, 'seguro')
            )) {
                $map['nss'] = $index;
            }
            // NOM / Nombre
            elseif (! isset($map['nom']) && (
                $clean === 'nom' ||
                str_contains($normalized, 'nom') ||
                str_contains($clean, 'nombre') ||
                str_contains($clean, 'empleado')
            )) {
                $map['nom'] = $index;
            }
            // Dias
            elseif (! isset($map['dias']) && (
                str_contains($normalized, 'dia') ||
                str_contains($clean, 'dia') ||
                str_contains($clean, 'dias')
            )) {
                $map['dias'] = $index;
            }
            // SDI
            elseif (! isset($map['sdi']) && (
                $clean === 'sdi' ||
                str_contains($normalized, 'sdi')
            )) {
                $map['sdi'] = $index;
            }
            // Inc. / Incapacidad
            elseif (! isset($map['inc']) && (
                $clean === 'inc' ||
                str_contains($clean, 'incap') ||
                str_contains($normalized, 'inc')
            )) {
                $map['inc'] = $index;
            }
            // Aus. / Ausencias
            elseif (! isset($map['aus']) && (
                $clean === 'aus' ||
                str_contains($clean, 'ausen') ||
                str_contains($normalized, 'aus')
            )) {
                $map['aus'] = $index;
            }
            // Cuota IMSS / Subtotal
            elseif (! isset($map['cuota_imss']) && (
                str_contains($normalized, 'cuota') ||
                str_contains($clean, 'cuota') ||
                str_contains($clean, 'cuotaimss') ||
                str_contains($clean, 'subtotal')
            )) {
                $map['cuota_imss'] = $index;
            }
            // Prima
            elseif (! isset($map['prima']) && (
                str_contains($normalized, 'prima') ||
                str_contains($clean, 'prima') ||
                $clean === 'riesgo' ||
                $clean === 'rt'
            )) {
                $map['prima'] = $index;
            }
            // RP / Registro Patronal
            elseif (! isset($map['rp']) && (
                $clean === 'rp' ||
                str_contains($clean, 'patronal') ||
                str_contains($normalized, 'registro_patronal')
            )) {
                $map['rp'] = $index;
            }
        }

        return $map;
    }

    /**
     * Parse numeric value handling both comma and dot decimals.
     */
    protected function parseNumericValue(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (empty($value)) {
            return 0.0;
        }

        $str = str_replace(['$', ' ', 'MXN', 'mxn', '%'], '', trim((string) $value));

        if (str_contains($str, '.') && str_contains($str, ',')) {
            $lastDot = strrpos($str, '.');
            $lastComma = strrpos($str, ',');
            if ($lastComma > $lastDot) {
                // European format: 2.694,54
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                // US format: 2,694.54
                $str = str_replace(',', '', $str);
            }
        } elseif (str_contains($str, ',')) {
            // E.g. 0,61972 or 1234,56
            $str = str_replace(',', '.', $str);
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
