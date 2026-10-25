<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Empleado;
use App\Models\EmpleadoSalario;
use App\Models\Empresa;
use App\Models\ImportacionCfdi;
use App\Models\Uma;
use App\Models\Vacacion;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class CfdiExcelImporterService
{
    /**
     * Cache for categories and UMAS to minimize database queries during import.
     *
     * @var array<string, int>
     */
    protected array $categoryCache = [];

    /**
     * @var array<int, float>
     */
    protected array $umaCache = [];

    /**
     * @var array<string, int>
     */
    protected array $vacationDaysCache = [];

    public function process(ImportacionCfdi $importacion, bool $resume = false): void
    {
        $filePath = Storage::disk('local')->path($importacion->archivo_ruta);

        if (! file_exists($filePath)) {
            $importacion->update([
                'estado' => 'fallido',
                'ultimo_error' => "El archivo en la ruta {$importacion->archivo_ruta} no existe en el almacenamiento.",
            ]);

            return;
        }

        $importacion->update([
            'estado' => 'procesando',
            'iniciado_a' => $importacion->iniciado_a ?? now(),
        ]);

        $reader = $this->createReaderForFile($filePath, $importacion->archivo_nombre);
        $reader->open($filePath);

        $empresa = $importacion->empresa;
        $defaultCategoriaId = $this->getDefaultCategoriaId();

        $headerMap = [];
        $totalFilasContadas = 0;
        $filasProcesadas = $resume ? $importacion->filas_procesadas : 0;
        $filasConError = $resume ? $importacion->filas_con_error : 0;
        $errores = $resume ? ($importacion->errores ?? []) : [];

        $currentRowIndex = 0;
        $batchCounter = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $currentRowIndex++;
                    $cells = $row->toArray();

                    // First row is the header
                    if ($currentRowIndex === 1) {
                        $headerMap = $this->buildHeaderMap($cells);

                        if (! isset($headerMap['tipo_nomina'])) {
                            throw new Exception('El archivo no contiene la columna de tipo de nómina ("Tipo nómina").');
                        }

                        continue;
                    }

                    // Skip empty rows
                    if ($this->isEmptyRow($cells)) {
                        continue;
                    }

                    // Strictly filter: only consider records where tipo nomina is "O - Nómina ordinaria", ignore any other value
                    $tipoNomina = (string) $this->getCellValue($cells, $headerMap, 'tipo_nomina', '');
                    if (! $this->isNominaOrdinaria($tipoNomina)) {
                        continue;
                    }

                    $totalFilasContadas++;

                    // If resuming, skip already processed rows
                    if ($resume && $totalFilasContadas <= $filasProcesadas) {
                        continue;
                    }

                    try {
                        $this->processRow(
                            $cells,
                            $headerMap,
                            $empresa,
                            $importacion,
                            $defaultCategoriaId,
                            $totalFilasContadas
                        );
                        $filasProcesadas++;
                    } catch (Exception $e) {
                        $filasConError++;
                        if (count($errores) < 100) {
                            $errores[] = [
                                'fila' => $totalFilasContadas + 1,
                                'mensaje' => $e->getMessage(),
                            ];
                        }
                    }

                    $batchCounter++;
                    // Update progress periodically (every 25 rows)
                    if ($batchCounter >= 25) {
                        $importacion->update([
                            'total_filas' => max($importacion->total_filas, $totalFilasContadas),
                            'filas_procesadas' => $filasProcesadas,
                            'filas_con_error' => $filasConError,
                            'errores' => $errores,
                        ]);
                        $batchCounter = 0;
                    }
                }
                break; // Process the first sheet
            }

            $reader->close();

            $importacion->update([
                'estado' => $filasConError > 0 && $filasProcesadas === 0 ? 'fallido' : 'completado',
                'total_filas' => $totalFilasContadas,
                'filas_procesadas' => $filasProcesadas,
                'filas_con_error' => $filasConError,
                'errores' => $errores,
                'finalizado_a' => now(),
            ]);
        } catch (Exception $e) {
            if (isset($reader)) {
                $reader->close();
            }

            $importacion->update([
                'estado' => 'fallido',
                'total_filas' => max($importacion->total_filas, $totalFilasContadas),
                'filas_procesadas' => $filasProcesadas,
                'filas_con_error' => $filasConError,
                'errores' => $errores,
                'ultimo_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process an individual row from the spreadsheet.
     *
     * @param  array<int, mixed>  $cells
     * @param  array<string, int>  $headerMap
     */
    protected function processRow(
        array $cells,
        array $headerMap,
        Empresa $empresa,
        ImportacionCfdi $importacion,
        int $defaultCategoriaId,
        int $rowNumber
    ): void {
        $tipoNomina = (string) $this->getCellValue($cells, $headerMap, 'tipo_nomina', '');
        // Guard clause: only records with tipo nomina "O - Nómina ordinaria" are created / registered
        if (! $this->isNominaOrdinaria($tipoNomina)) {
            return;
        }

        $periodo = $this->getCellValue($cells, $headerMap, 'periodo', '1');
        $registroPatronal = $this->getCellValue($cells, $headerMap, 'registro_patronal');
        $rfcReceptor = strtoupper(trim((string) $this->getCellValue($cells, $headerMap, 'rfc_receptor', '')));
        $razonReceptor = trim((string) $this->getCellValue($cells, $headerMap, 'razon_receptor', ''));
        $fechaInicioLaboral = $this->parseDateValue($this->getCellValue($cells, $headerMap, 'fecha_inicio_relacion_laboral'));
        $numEmpleado = trim((string) $this->getCellValue($cells, $headerMap, 'numero_empleado', ''));
        $antiguedadRaw = trim((string) $this->getCellValue($cells, $headerMap, 'antiguedad', ''));
        $sindicalizadoRaw = $this->getCellValue($cells, $headerMap, 'sindicalizado', 'No');
        $curp = strtoupper(trim((string) $this->getCellValue($cells, $headerMap, 'curp', '')));
        $nss = trim((string) $this->getCellValue($cells, $headerMap, 'numero_seguro_social', ''));
        $salarioBase = $this->parseNumericValue($this->getCellValue($cells, $headerMap, 'salario_base', 0));
        $sdiExcel = $this->parseNumericValue($this->getCellValue($cells, $headerMap, 'salario_diario_integrado', 0));

        if (empty($numEmpleado)) {
            $numEmpleado = ! empty($rfcReceptor) ? $rfcReceptor : 'EMP-'.$rowNumber;
        }

        if (empty($razonReceptor)) {
            $razonReceptor = 'Empleado '.$numEmpleado;
        }

        // Validate that RFC emisor in row matches current company
        if (isset($headerMap['rfc_emisor'])) {
            $rfcEmisorFila = strtoupper(trim((string) $this->getCellValue($cells, $headerMap, 'rfc_emisor', '')));
            if (! empty($rfcEmisorFila) && $rfcEmisorFila !== strtoupper(trim($empresa->rfc))) {
                throw new Exception("El RFC emisor '{$rfcEmisorFila}' no coincide con la empresa '{$empresa->nombre}' ({$empresa->rfc}).");
            }
        }

        // Validate that employee only belongs to one company
        if (! empty($rfcReceptor) && ! in_array($rfcReceptor, ['XAXX010101000', 'XEXX010101000'], true)) {
            $empleadoEnOtraEmpresa = Empleado::where('empresa_id', '!=', $empresa->id)
                ->where(function ($q) use ($rfcReceptor, $curp) {
                    $q->where('rfc_receptor', $rfcReceptor);
                    if (! empty($curp)) {
                        $q->orWhere('curp', $curp);
                    }
                })
                ->with('empresa')
                ->first();

            if ($empleadoEnOtraEmpresa) {
                $nombreOtraEmpresa = $empleadoEnOtraEmpresa->empresa?->nombre ?? 'otra empresa';
                throw new Exception("El empleado '{$razonReceptor}' (RFC: {$rfcReceptor}) ya está dado de alta en la empresa '{$nombreOtraEmpresa}'. Un empleado solo puede pertenecer a una empresa.");
            }
        }

        // Calculate weeks and closed years of seniority
        [$semanas, $aniosAntiguedad] = $this->calculateSeniority($antiguedadRaw, $fechaInicioLaboral, $importacion->anio);

        $esSindicalizado = in_array(strtolower(trim((string) $sindicalizadoRaw)), ['si', 'sí', 'true', '1', 's'], true);

        // Resolve or create employee
        $empleado = Empleado::updateOrCreate(
            [
                'empresa_id' => $empresa->id,
                'numero_empleado' => $numEmpleado,
            ],
            [
                'categoria_id' => $defaultCategoriaId,
                'registro_patronal' => $registroPatronal,
                'rfc_receptor' => $rfcReceptor ?: 'XAXX010101000',
                'nombre' => $razonReceptor,
                'fecha_inicio_relacion_laboral' => $fechaInicioLaboral,
                'antiguedad_texto' => $antiguedadRaw,
                'antiguedad_semanas' => $semanas,
                'antiguedad_anios' => $aniosAntiguedad,
                'es_sindicalizado' => $esSindicalizado,
                'curp' => $curp,
                'numero_seguro_social' => $nss,
                'salario_base' => $salarioBase,
                'salario_diario_integrado' => $sdiExcel > 0 ? $sdiExcel : $salarioBase,
            ]
        );

        $categoriaId = $empleado->categoria_id ?? $defaultCategoriaId;

        // Vacation Days lookup based on category and closed years
        $diasVacaciones = $this->getVacationDaysFor($categoriaId, $aniosAntiguedad);

        // Aguinaldo diario = salario base * dias de aguinaldo de la empresa / 365
        $diasAguinaldo = $empresa->dias_aguinaldo ?: 15;
        $aguinaldoDiario = round(($salarioBase * $diasAguinaldo) / 365, 2);

        // Importe de vacaciones = salario base * dias de vacaciones
        $importeVacaciones = round($salarioBase * $diasVacaciones, 2);

        // Prima vacacional = (salario base * dias vacaciones) * (prima_vacacional / 100) / 365
        $primaPorcentaje = (float) $empresa->prima_vacacional ?: 25.00;
        $primaVacacional = round(($salarioBase * $diasVacaciones) * ($primaPorcentaje / 100) / 365, 2);

        // Salario Diario Integrado (SDI) = salario base + aguinaldo diario + prima vacacional
        $sdiCalculado = round($salarioBase + $aguinaldoDiario + $primaVacacional, 2);

        // UMA & Daily Salary Limit (25 times UMA)
        // If periodo is 1 or 2, use previous year UMA
        $periodoNumero = (int) preg_replace('/\D/', '', (string) $periodo);
        $anioUma = in_array($periodoNumero, [1, 2], true) ? ($importacion->anio - 1) : $importacion->anio;
        $umaSaldo = $this->getUmaValue($anioUma);
        $limiteSalarioDiario = round($umaSaldo * 25, 2);
        $excedeLimite = $sdiCalculado > $limiteSalarioDiario;

        // Check if salary already has variable component from variables import
        $salarioExistente = EmpleadoSalario::where('empleado_id', $empleado->id)
            ->where('anio', $importacion->anio)
            ->where('periodo', (string) $periodo)
            ->first();

        $sdiVariable = (float) ($salarioExistente->sdi_variable ?? 0);
        $sdiTotal = round($sdiCalculado + $sdiVariable, 2);
        $sdiAuditado = ($limiteSalarioDiario > 0 && $sdiTotal > $limiteSalarioDiario) ? $limiteSalarioDiario : $sdiTotal;

        // Save / update salary details for employee, year, and period
        EmpleadoSalario::updateOrCreate(
            [
                'empleado_id' => $empleado->id,
                'anio' => $importacion->anio,
                'periodo' => (string) $periodo,
            ],
            [
                'empresa_id' => $empresa->id,
                'importacion_cfdi_id' => $importacion->id,
                'categoria_id' => $categoriaId,
                'tipo_nomina' => $tipoNomina,
                'salario_base' => $salarioBase,
                'dias_vacaciones' => $diasVacaciones,
                'importe_vacaciones' => $importeVacaciones,
                'aguinaldo_diario' => $aguinaldoDiario,
                'prima_vacacional' => $primaVacacional,
                'salario_diario_integrado' => $sdiCalculado,
                'salario_diario_integrado_excel' => $sdiExcel,
                'sdi_total' => $sdiTotal,
                'sdi_auditado' => $sdiAuditado,
                'limite_salario_diario' => $limiteSalarioDiario,
                'excede_limite' => $excedeLimite,
                'uma_utilizada' => $umaSaldo,
                'anio_uma' => $anioUma,
            ]
        );
    }

    /**
     * Calculate weeks and integer years of seniority.
     *
     * @return array{0: int, 1: int}
     */
    protected function calculateSeniority(string $antiguedadRaw, ?string $fechaInicio, int $importacionAnio): array
    {
        $semanas = 0;

        if (preg_match('/(\d+)\s*semanas?/i', $antiguedadRaw, $matches)) {
            $semanas = (int) $matches[1];
        } elseif (preg_match('/P(\d+)W/i', $antiguedadRaw, $matches)) {
            $semanas = (int) $matches[1];
        } elseif (! empty($fechaInicio)) {
            try {
                $inicio = Carbon::parse($fechaInicio);
                $fin = Carbon::create($importacionAnio, 12, 31);
                if ($inicio->lessThan($fin)) {
                    $semanas = (int) $inicio->diffInWeeks($fin);
                }
            } catch (Exception) {
                $semanas = 0;
            }
        }

        // Years closed (round down to integer year; if less than 52 weeks, 0)
        $anios = (int) floor($semanas / 52);
        if ($anios < 0) {
            $anios = 0;
        }

        return [$semanas, $anios];
    }

    /**
     * Get vacation days for category and years of tenure.
     */
    protected function getVacationDaysFor(int $categoriaId, int $anios): int
    {
        if ($anios <= 0) {
            return 12;
        }

        $cacheKey = "{$categoriaId}_{$anios}";
        if (isset($this->vacationDaysCache[$cacheKey])) {
            return $this->vacationDaysCache[$cacheKey];
        }

        // Exact match for the year
        $rule = Vacacion::where('categoria_id', $categoriaId)
            ->where('anios', $anios)
            ->first();

        // If no exact match, find closest lower or equal tenure rule
        if (! $rule) {
            $rule = Vacacion::where('categoria_id', $categoriaId)
                ->where('anios', '<=', $anios)
                ->orderBy('anios', 'desc')
                ->first();
        }

        // Fallback to Mexican standard LFT minimum (12 days 1st year, +2 per year up to 20, then +2 per 5 years)
        if (! $rule) {
            $days = match (true) {
                $anios === 1 => 12,
                $anios === 2 => 14,
                $anios === 3 => 16,
                $anios === 4 => 18,
                $anios === 5 => 20,
                $anios <= 10 => 22,
                $anios <= 15 => 24,
                $anios <= 20 => 26,
                default => 28,
            };
            $this->vacationDaysCache[$cacheKey] = $days;

            return $days;
        }

        $days = (int) $rule->dias;
        $this->vacationDaysCache[$cacheKey] = $days;

        return $days;
    }

    /**
     * Get UMA daily value for given year with caching and fallback.
     */
    protected function getUmaValue(int $anio): float
    {
        if (isset($this->umaCache[$anio])) {
            return $this->umaCache[$anio];
        }

        $uma = Uma::where('anio', $anio)->first();

        if (! $uma) {
            // Fallback to nearest or latest UMA
            $uma = Uma::orderBy('anio', 'desc')->first();
        }

        $valor = $uma ? (float) $uma->saldo : 108.57;
        $this->umaCache[$anio] = $valor;

        return $valor;
    }

    /**
     * Get default Operativo category ID.
     */
    protected function getDefaultCategoriaId(): int
    {
        if (isset($this->categoryCache['operativo'])) {
            return $this->categoryCache['operativo'];
        }

        $categoria = Categoria::firstOrCreate(
            ['nombre' => 'Operativo']
        );

        $this->categoryCache['operativo'] = $categoria->id;

        return $categoria->id;
    }

    /**
     * Build map of header index by field name.
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
            if (str_contains($normalized, 'periodo')) {
                $map['periodo'] = $index;
            }
            // Tipo nomina
            elseif (! isset($map['tipo_nomina']) && (
                str_contains($normalized, 'tipo_nomina') ||
                str_contains($normalized, 'tipo_de_nomina') ||
                str_contains($clean, 'tiponomina') ||
                str_contains($clean, 'tipodenomina') ||
                (str_contains($clean, 'tipo') && str_contains($clean, 'nomina')) ||
                $clean === 'tiponomina' ||
                $clean === 'tipodecomprobante'
            )) {
                $map['tipo_nomina'] = $index;
            }
            // RFC Emisor
            elseif (str_contains($normalized, 'rfc_emisor') || str_contains($clean, 'rfcemisor') || (str_contains($clean, 'emisor') && str_contains($clean, 'rfc'))) {
                $map['rfc_emisor'] = $index;
            }
            // Razon emisor
            elseif (str_contains($normalized, 'razon_emisor') || (str_contains($clean, 'emisor') && (str_contains($clean, 'razon') || str_contains($clean, 'nombre')))) {
                $map['razon_emisor'] = $index;
            }
            // RFC Receptor
            elseif (str_contains($normalized, 'rfc_receptor') || str_contains($clean, 'rfcreceptor') || (str_contains($clean, 'rfc') && ! str_contains($clean, 'emisor'))) {
                $map['rfc_receptor'] = $index;
            }
            // Razon receptor / nombre empleado
            elseif (str_contains($normalized, 'razon_receptor') || str_contains($normalized, 'nombre') || str_contains($normalized, 'receptor')) {
                if (! str_contains($clean, 'rfc') && ! str_contains($clean, 'emisor')) {
                    $map['razon_receptor'] = $index;
                }
            }
            // Registro patronal
            elseif (str_contains($normalized, 'registro_patronal') || str_contains($clean, 'registropatronal')) {
                $map['registro_patronal'] = $index;
            }
            // Fecha inicio relacion laboral
            elseif (str_contains($normalized, 'fecha_inicio') || str_contains($normalized, 'inicio_relacion') || str_contains($normalized, 'ingreso')) {
                $map['fecha_inicio_relacion_laboral'] = $index;
            }
            // Numero de empleado
            elseif (str_contains($normalized, 'num_empleado') || str_contains($normalized, 'numero_empleado') || str_contains($normalized, 'no_empleado') || str_contains($clean, 'numempleado') || str_contains($clean, 'numeroempleado')) {
                $map['numero_empleado'] = $index;
            }
            // Antiguedad
            elseif (str_contains($normalized, 'antiguedad') || str_contains($normalized, 'antig')) {
                $map['antiguedad'] = $index;
            }
            // Sindicalizado
            elseif (str_contains($normalized, 'sindicalizado')) {
                $map['sindicalizado'] = $index;
            }
            // CURP
            elseif (str_contains($normalized, 'curp')) {
                $map['curp'] = $index;
            }
            // NSS
            elseif (str_contains($normalized, 'seguro_social') || str_contains($normalized, 'nss') || str_contains($clean, 'segurosocial')) {
                $map['numero_seguro_social'] = $index;
            }
            // Salario base
            elseif (str_contains($normalized, 'salario_base') || str_contains($normalized, 'cot_apor') || str_contains($normalized, 'sbc')) {
                $map['salario_base'] = $index;
            }
            // Salario diario integrado
            elseif (str_contains($normalized, 'salario_diario_integrado') || str_contains($normalized, 'sdi') || str_contains($normalized, 'integrado')) {
                $map['salario_diario_integrado'] = $index;
            }
        }

        return $map;
    }

    /**
     * Extract cell value safely.
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
     * Parse date value to Y-m-d.
     */
    protected function parseDateValue(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        try {
            $str = trim((string) $value);
            if (empty($str)) {
                return null;
            }

            return Carbon::parse($str)->format('Y-m-d');
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Parse numeric values supporting comma as decimal.
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
        // If contains comma and no dot, replace comma with dot (e.g. "289,39" -> "289.39")
        if (str_contains($str, ',') && ! str_contains($str, '.')) {
            $str = str_replace(',', '.', $str);
        } else {
            $str = str_replace(',', '', $str);
        }

        return (float) $str;
    }

    /**
     * Check if a row is completely blank.
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
     * Create OpenSpout reader according to file extension.
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

    /**
     * Validate CFDI file before queueing import:
     * 1. The file can be read and contains headers.
     * 2. It contains the RFC emisor column.
     * 3. The RFC emisor in the file is registered in the system (empresas table).
     * 4. The RFC emisor matches the selected empresa.
     * 5. None of the employees belong to another empresa (single-company constraint).
     *
     * @return array{rfc_emisor: string, total_filas: int, total_empleados: int}
     *
     * @throws ValidationException
     */
    public function validarArchivo(string $filePath, Empresa $empresa, ?string $originalExtension = null): array
    {
        try {
            $reader = $this->createReaderForFile($filePath, $originalExtension);
            $reader->open($filePath);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el archivo. Asegúrate de que sea un archivo Excel (.xlsx) o CSV válido.',
            ]);
        }

        $headerMap = [];
        $rfcEmisorEncontrado = null;
        $empleadosArchivo = []; // rfc => nombre
        $curpsArchivo = []; // curp => true
        $totalFilasValidas = 0;

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

                    // Strictly filter: only consider records where tipo nomina is "O - Nómina ordinaria"
                    $tipoNomina = (string) $this->getCellValue($cells, $headerMap, 'tipo_nomina', '');
                    if (! $this->isNominaOrdinaria($tipoNomina)) {
                        continue;
                    }

                    $totalFilasValidas++;

                    // RFC Emisor
                    if (isset($headerMap['rfc_emisor'])) {
                        $rfcEmisorFila = strtoupper(trim((string) $this->getCellValue($cells, $headerMap, 'rfc_emisor', '')));
                        if (! empty($rfcEmisorFila)) {
                            if ($rfcEmisorEncontrado === null) {
                                $rfcEmisorEncontrado = $rfcEmisorFila;
                            } elseif ($rfcEmisorEncontrado !== $rfcEmisorFila) {
                                throw ValidationException::withMessages([
                                    'archivo' => "El archivo contiene múltiples RFC emisores ('{$rfcEmisorEncontrado}' y '{$rfcEmisorFila}'). Un archivo solo debe pertenecer a una sola empresa.",
                                ]);
                            }
                        }
                    }

                    // RFC Receptor
                    if (isset($headerMap['rfc_receptor'])) {
                        $rfcRec = strtoupper(trim((string) $this->getCellValue($cells, $headerMap, 'rfc_receptor', '')));
                        if (! empty($rfcRec) && ! in_array($rfcRec, ['XAXX010101000', 'XEXX010101000'], true)) {
                            $razonRec = trim((string) $this->getCellValue($cells, $headerMap, 'razon_receptor', ''));
                            if (! isset($empleadosArchivo[$rfcRec])) {
                                $empleadosArchivo[$rfcRec] = $razonRec ?: $rfcRec;
                            }
                        }
                    }

                    // CURP
                    if (isset($headerMap['curp'])) {
                        $curp = strtoupper(trim((string) $this->getCellValue($cells, $headerMap, 'curp', '')));
                        if (! empty($curp)) {
                            $curpsArchivo[$curp] = true;
                        }
                    }
                }
                break; // First sheet
            }
        } finally {
            $reader->close();
        }

        if (empty($headerMap)) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo está vacío o no contiene encabezados válidos.',
            ]);
        }

        if (! isset($headerMap['tipo_nomina'])) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no contiene la columna de tipo de nómina ("Tipo nómina"). Es requerida para asegurar que solo se dé de alta la nómina ordinaria.',
            ]);
        }

        if ($totalFilasValidas === 0) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no contiene registros de nómina ordinaria ("O - Nómina ordinaria") para dar de alta.',
            ]);
        }

        if (! isset($headerMap['rfc_emisor']) || empty($rfcEmisorEncontrado)) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no contiene la columna o el valor del RFC emisor.',
            ]);
        }

        // Validate that RFC emisor is registered in the system
        $empresaEmisora = Empresa::where('rfc', $rfcEmisorEncontrado)->first();
        if (! $empresaEmisora) {
            throw ValidationException::withMessages([
                'archivo' => "El RFC emisor '{$rfcEmisorEncontrado}' no está dado de alta en el sistema.",
            ]);
        }

        // Validate that RFC emisor matches the current company
        if (strtoupper(trim($empresa->rfc)) !== $rfcEmisorEncontrado) {
            throw ValidationException::withMessages([
                'archivo' => "El RFC emisor '{$rfcEmisorEncontrado}' pertenece a '{$empresaEmisora->nombre}' y no coincide con la empresa seleccionada '{$empresa->nombre}' ({$empresa->rfc}).",
            ]);
        }

        // Validate that employees only belong to one company
        $rfcsList = array_keys($empleadosArchivo);
        $curpsList = array_keys($curpsArchivo);

        if (! empty($rfcsList) || ! empty($curpsList)) {
            $conflicto = Empleado::where('empresa_id', '!=', $empresa->id)
                ->where(function ($query) use ($rfcsList, $curpsList) {
                    if (! empty($rfcsList)) {
                        $query->whereIn('rfc_receptor', $rfcsList);
                    }
                    if (! empty($curpsList)) {
                        $query->orWhereIn('curp', $curpsList);
                    }
                })
                ->with('empresa')
                ->first();

            if ($conflicto) {
                $nombreOtraEmpresa = $conflicto->empresa?->nombre ?? 'otra empresa';
                throw ValidationException::withMessages([
                    'archivo' => "El empleado '{$conflicto->nombre}' (RFC: {$conflicto->rfc_receptor}) ya está registrado en la empresa '{$nombreOtraEmpresa}'. Un empleado solo puede pertenecer a una empresa.",
                ]);
            }
        }

        return [
            'rfc_emisor' => $rfcEmisorEncontrado,
            'total_filas' => $totalFilasValidas,
            'total_empleados' => count($empleadosArchivo),
        ];
    }

    /**
     * Determine if the row corresponds strictly to ordinary payroll (O - Nómina ordinaria).
     */
    protected function isNominaOrdinaria(string $tipoNomina): bool
    {
        $normalized = trim($tipoNomina);
        if ($normalized === '') {
            return false;
        }

        // Clean non-breaking spaces (\u00A0), tabs, carriage returns
        $clean = str_replace(["\xc2\xa0", "\u{00A0}", "\t", "\r", "\n"], ' ', $normalized);
        // Normalize en-dash, em-dash, minus to standard hyphen
        $clean = str_replace(['–', '—', '−'], '-', $clean);
        // Normalize accents (ó, ó, etc.)
        $clean = str_replace(['ó', 'ó', 'Ó', 'Ó'], 'o', $clean);
        $clean = mb_strtolower($clean);
        // Normalize spacing around hyphens
        $clean = preg_replace('/\s*-\s*/', ' - ', $clean) ?? $clean;
        // Collapse multiple spaces
        $clean = preg_replace('/\s+/', ' ', trim($clean)) ?? $clean;

        return $clean === 'o - nomina ordinaria' ||
               $clean === 'o-nomina ordinaria' ||
               $clean === 'o' ||
               $clean === 'nomina ordinaria';
    }
}
