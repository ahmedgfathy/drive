<?php

namespace App\Services\Files;

use App\Models\File;

class FileRenameService
{
    public function rename(File $file, string $newName): File
    {
        $file->original_name = $newName;
        $file->save();

        return $file;
    }
}
