<?php

namespace App\Jobs\Callbacks;

use App\Models\DocumentBatch;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Log;

class BatchFailedCallback
{
    public function __construct(
        public DocumentBatch $batch
    ) {
    }

    public function __invoke(Batch $laravelBatch, \Throwable $e): void
    {
        Log::error("BatchFailedCallback: Laravel Batch {$laravelBatch->id} falló: {$e->getMessage()}");
        $this->batch->markAsFailed($e->getMessage());
    }
}
