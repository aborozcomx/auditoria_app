<?php

namespace App\Jobs;

use App\Models\ImportacionCuotaImss;
use App\Services\CuotasImssImporterService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcesarImportacionCuotasImssJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $importacionCuotaImssId,
        public bool $resume = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CuotasImssImporterService $importerService): void
    {
        $importacion = ImportacionCuotaImss::find($this->importacionCuotaImssId);

        if (! $importacion) {
            return;
        }

        $importerService->processImportacion($importacion, $this->resume);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception): void
    {
        $importacion = ImportacionCuotaImss::find($this->importacionCuotaImssId);

        if ($importacion) {
            $importacion->update([
                'estado' => 'fallido',
                'ultimo_error' => $exception ? $exception->getMessage() : 'Error inesperado durante la ejecución en cola.',
                'finalizado_a' => now(),
            ]);
        }
    }
}
