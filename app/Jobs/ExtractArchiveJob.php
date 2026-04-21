<?php

namespace App\Jobs;

use App\Models\File;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExtractArchiveJob implements ShouldQueue
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

        // Add archive extraction logic and child file indexing.
    }
}
