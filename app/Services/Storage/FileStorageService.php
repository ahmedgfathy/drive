<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService
{
    /**
     * Store an uploaded file and return persisted metadata.
     *
     * @return array<string, mixed>
     */
    public function storeForUser(UploadedFile $uploadedFile, string $employeeId, string $disk = 'local'): array
    {
        $safeEmployeeId = preg_replace('/[^A-Za-z0-9._-]/', '_', $employeeId) ?: 'unknown';
        $storedName = Str::uuid().'.'.$uploadedFile->getClientOriginalExtension();
        $storagePath = $uploadedFile->storeAs('drive/employees/'.$safeEmployeeId, $storedName, $disk);

        return [
            'stored_name' => $storedName,
            'disk' => $disk,
            'storage_path' => $storagePath,
            'mime_type' => $uploadedFile->getMimeType(),
            'extension' => $uploadedFile->getClientOriginalExtension(),
            'size_bytes' => $uploadedFile->getSize() ?: 0,
            'checksum_sha256' => hash_file('sha256', $uploadedFile->getRealPath()),
        ];
    }

    public function delete(string $path, string $disk = 'local'): bool
    {
        return Storage::disk($disk)->delete($path);
    }
}
