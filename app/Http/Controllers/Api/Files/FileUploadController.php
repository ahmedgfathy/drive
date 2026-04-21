<?php

namespace App\Http\Controllers\Api\Files;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\File;
use App\Models\StorageQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('files.upload'), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
        ]);

        $uploaded = $data['file'];
        $size = $uploaded->getSize() ?: 0;

        $quota = StorageQuota::where('user_id', $request->user()->id)->first();
        if ($quota && ($quota->used_bytes + $size) > $quota->quota_bytes) {
            return response()->json(['message' => 'Storage quota exceeded.'], 422);
        }

        $storedName = Str::uuid().'.'.$uploaded->getClientOriginalExtension();
        $storagePath = $uploaded->storeAs('drive/'.$request->user()->id, $storedName, 'local');

        $file = File::create([
            'uuid' => (string) Str::uuid(),
            'folder_id' => $data['folder_id'] ?? null,
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
}
