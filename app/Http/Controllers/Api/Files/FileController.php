<?php

namespace App\Http\Controllers\Api\Files;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function show(Request $request, File $file): JsonResponse
    {
        $this->authorize('view', $file);

        return response()->json($file->load('owner', 'folder'));
    }

    public function search(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('files.view'), 403);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $query = File::query();

        if ($request->user()->hasRole('employee')) {
            $query->where('owner_id', $request->user()->id);
        }

        if (! empty($data['q'])) {
            $query->where('original_name', 'like', '%'.$data['q'].'%');
        }

        if (! empty($data['mime_type'])) {
            $query->where('mime_type', $data['mime_type']);
        }

        if (! empty($data['owner_id'])) {
            $query->where('owner_id', $data['owner_id']);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function update(Request $request, File $file): JsonResponse
    {
        $this->authorize('update', $file);

        $data = $request->validate([
            'original_name' => ['nullable', 'string', 'max:255'],
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
        ]);

        if (array_key_exists('folder_id', $data) && $data['folder_id']) {
            $targetFolder = Folder::findOrFail($data['folder_id']);
            $this->authorize('view', $targetFolder);

            if (! $request->user()->hasAnyRole(['super_admin', 'manager']) && $targetFolder->owner_id !== $file->owner_id) {
                return response()->json(['message' => 'Invalid target folder.'], 422);
            }
        }

        $file->fill($data);
        $file->save();

        $this->logAction($request, 'files.update', $file);

        return response()->json($file);
    }

    public function destroy(Request $request, File $file): JsonResponse
    {
        $this->authorize('delete', $file);

        $file->delete();

        $this->logAction($request, 'files.delete', $file);

        return response()->json(['message' => 'File moved to trash.']);
    }

    public function restore(Request $request, int $file): JsonResponse
    {
        $fileModel = File::withTrashed()->findOrFail($file);
        $this->authorize('restore', $fileModel);

        $fileModel->restore();

        $this->logAction($request, 'files.restore', $fileModel);

        return response()->json(['message' => 'File restored.']);
    }

    public function download(Request $request, File $file)
    {
        $this->authorize('download', $file);

        $this->logAction($request, 'files.download', $file);

        return Storage::disk($file->disk)->download($file->storage_path, $file->original_name);
    }

    private function logAction(Request $request, string $action, File $subject): void
    {
        ActivityLog::create([
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => File::class,
            'subject_id' => $subject->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
