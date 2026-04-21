<?php

namespace App\Services\Files;

use App\Models\File;

class FileDeleteService
{
    public function trash(File $file): void
    {
        $file->delete();
    }
}
