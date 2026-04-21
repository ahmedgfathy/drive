<?php

namespace App\Jobs;

use App\Models\File;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScanFileForVirusJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $fileId)
    {
    }

    public function handle(): void
    {
        $file = File::find($this->fileId);

        if (! $file) {
            return;
        }

        // Integrate your virus scan provider and mark file status.
    }
}
