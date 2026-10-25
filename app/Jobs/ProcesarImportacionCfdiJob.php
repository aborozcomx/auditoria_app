<?php

namespace App\Jobs;

use App\Models\ImportacionCfdi;
use App\Services\CfdiExcelImporterService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcesarImportacionCfdiJob implements ShouldQueue
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
        public int $importacionCfdiId,
        public bool $resume = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CfdiExcelImporterService $importerService): void
    {
        $importacion = ImportacionCfdi::find($this->importacionCfdiId);

        if (! $importacion) {
            return;
        }

        $importerService->process($importacion, $this->resume);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception): void
    {
        $importacion = ImportacionCfdi::find($this->importacionCfdiId);

        if ($importacion) {
            $importacion->update([
                'estado' => 'fallido',
                'ultimo_error' => $exception ? $exception->getMessage() : 'Error inesperado durante la ejecución en cola.',
            ]);
        }
    }
}
