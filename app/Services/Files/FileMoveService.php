<?php

namespace App\Services\Files;

use App\Models\File;

class FileMoveService
{
    public function move(File $file, ?int $folderId): File
    {
        $file->folder_id = $folderId;
        $file->save();

        return $file;
    }
}
