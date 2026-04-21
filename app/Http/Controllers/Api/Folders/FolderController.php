<?php

namespace App\Http\Controllers\Api\Folders;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FolderController extends Controller
{
    public function children(Request $request, Folder $folder): JsonResponse
    {
        $this->authorize('view', $folder);

        return response()->json([
            'folder' => $folder,
            'children' => $folder->children()->withCount('files')->get(),
            'files' => $folder->files()->latest()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('folders.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:folders,id'],
        ]);

        $parent = null;
        if (! empty($data['parent_id'])) {
            $parent = Folder::findOrFail($data['parent_id']);
            $this->authorize('view', $parent);
        }

        $depth = $parent ? $parent->depth + 1 : 0;

        $folder = Folder::create([
            'uuid' => (string) Str::uuid(),
            'parent_id' => $data['parent_id'] ?? null,
            'owner_id' => $request->user()->id,
            'name' => $data['name'],
            'path_cache' => null,
            'depth' => $depth,
        ]);

        $this->logAction($request, 'folders.create', $folder);

        return response()->json($folder, 201);
    }

    public function update(Request $request, Folder $folder): JsonResponse
    {
        $this->authorize('update', $folder);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:folders,id'],
        ]);

        $folder->fill($data);
        $folder->save();

        $this->logAction($request, 'folders.update', $folder);

        return response()->json($folder);
    }

    public function destroy(Request $request, Folder $folder): JsonResponse
    {
        $this->authorize('delete', $folder);

        $folder->delete();

        $this->logAction($request, 'folders.delete', $folder);

        return response()->json(['message' => 'Folder moved to trash.']);
    }

    public function restore(Request $request, int $folder): JsonResponse
    {
        $folderModel = Folder::withTrashed()->findOrFail($folder);
        $this->authorize('restore', $folderModel);

        $folderModel->restore();

        $this->logAction($request, 'folders.restore', $folderModel);

        return response()->json(['message' => 'Folder restored.']);
    }

    private function logAction(Request $request, string $action, Folder $subject): void
    {
        ActivityLog::create([
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => Folder::class,
            'subject_id' => $subject->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
