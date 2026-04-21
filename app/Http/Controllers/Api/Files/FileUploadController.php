<?php

namespace App\Http\Controllers\Api\Files;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\StorageQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('files.upload'), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
            'relative_path' => ['nullable', 'string', 'max:1024'],
            'source_created_at' => ['nullable', 'date'],
            'source_modified_at' => ['nullable', 'date'],
        ]);

        $uploaded = $data['file'];
        $size = $uploaded->getSize() ?: 0;

        $quota = StorageQuota::where('user_id', $request->user()->id)->first();
        if ($quota && ($quota->used_bytes + $size) > $quota->quota_bytes) {
            return response()->json(['message' => 'Storage quota exceeded.'], 422);
        }

        $targetFolder = $this->resolveTargetFolder(
            $request,
            $data['folder_id'] ?? null,
            $data['relative_path'] ?? null
        );

        $storedName = Str::uuid().'.'.$uploaded->getClientOriginalExtension();
        $relativeDirectory = $this->relativeDirectory($data['relative_path'] ?? null);
        $storageBase = $this->employeeStoragePrefix($request->user()->employee_id);
        $storageDirectory = $relativeDirectory !== '' ? $storageBase.'/'.$relativeDirectory : $storageBase;
        $storagePath = $uploaded->storeAs($storageDirectory, $storedName, 'local');

        $file = File::create([
            'uuid' => (string) Str::uuid(),
            'folder_id' => $targetFolder?->id,
            'owner_id' => $request->user()->id,
            'original_name' => $uploaded->getClientOriginalName(),
            'stored_name' => $storedName,
            'disk' => 'local',
            'storage_path' => $storagePath,
            'mime_type' => $uploaded->getMimeType(),
            'extension' => $uploaded->getClientOriginalExtension(),
            'size_bytes' => $size,
            'checksum_sha256' => hash_file('sha256', $uploaded->getRealPath()),
            'version' => 1,
            'source_created_at' => $data['source_created_at'] ?? null,
            'source_modified_at' => $data['source_modified_at'] ?? null,
        ]);

        if ($quota) {
            $quota->increment('used_bytes', $size);
        }

        ActivityLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'files.upload',
            'subject_type' => File::class,
            'subject_id' => $file->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'size_bytes' => $size,
                'disk' => 'local',
                'path' => $storagePath,
            ],
            'created_at' => now(),
        ]);

        return response()->json($file, 201);
    }

    private function resolveTargetFolder(Request $request, ?int $folderId, ?string $relativePath): ?Folder
    {
        $baseFolder = null;

        if ($folderId) {
            $baseFolder = Folder::findOrFail($folderId);
            $this->authorize('view', $baseFolder);
        } else {
            $baseFolder = $this->ensureUserRootFolder($request->user()->id, $request->user()->employee_id);
        }

        $segments = $this->relativeDirectorySegments($relativePath);
        $target = $baseFolder;

        foreach ($segments as $segment) {
            $target = Folder::firstOrCreate(
                [
                    'parent_id' => $target?->id,
                    'owner_id' => $request->user()->id,
                    'name' => $segment,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'depth' => $target ? ($target->depth + 1) : 0,
                    'path_cache' => $target ? rtrim((string) $target->path_cache, '/').'/'.$segment : '/'.$segment,
                ]
            );
        }

        return $target;
    }

    private function ensureUserRootFolder(int $userId, string $employeeId): Folder
    {
        return Folder::firstOrCreate(
            [
                'owner_id' => $userId,
                'parent_id' => null,
                'name' => $employeeId,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'path_cache' => '/'.$employeeId,
                'depth' => 0,
            ]
        );
    }

    /**
     * @return array<int, string>
     */
    private function relativeDirectorySegments(?string $relativePath): array
    {
        $directory = $this->relativeDirectory($relativePath);
        if ($directory === '') {
            return [];
        }

        return array_values(array_filter(explode('/', $directory), fn ($segment) => $segment !== ''));
    }

    private function relativeDirectory(?string $relativePath): string
    {
        $normalized = str_replace('\\', '/', trim((string) $relativePath));
        if ($normalized === '') {
            return '';
        }

        $directory = trim((string) pathinfo($normalized, PATHINFO_DIRNAME), '/. ');

        if ($directory === '' || $directory === '.') {
            return '';
        }

        $segments = array_filter(explode('/', $directory), fn ($segment) => $segment !== '' && $segment !== '.' && $segment !== '..');

        return implode('/', $segments);
    }

    private function employeeStoragePrefix(string $employeeId): string
    {
        $safeEmployeeId = preg_replace('/[^A-Za-z0-9._-]/', '_', $employeeId) ?: 'unknown';

        return 'drive/employees/'.$safeEmployeeId;
    }
}
