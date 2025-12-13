<?php

namespace App\Jobs\Callbacks;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BatchFinallyCallback
{
    public function __construct(
        public string $zipPath
    ) {
    }

    public function __invoke(Batch $laravelBatch): void
    {
        // Limpiar archivo ZIP temporal
        try {
            Storage::disk('local')->delete($this->zipPath);
            Log::info("BatchFinallyCallback: Archivo temporal eliminado: {$this->zipPath}");
        } catch (\Exception $e) {
            Log::warning("BatchFinallyCallback: No se pudo eliminar archivo temporal: {$this->zipPath}");
        }
    }
}
