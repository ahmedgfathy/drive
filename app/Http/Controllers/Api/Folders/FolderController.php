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
    public function root(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('folders.view'), 403);

        $root = $this->ensureRootFolder($request->user()->id, (string) $request->user()->employee_id);

        return response()->json([
            'folder' => $root,
            'children' => $root->children()->withCount('files')->get(),
            'files' => $root->files()->latest()->get(),
        ]);
    }

    public function tree(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('folders.view'), 403);

        $query = Folder::query()->orderBy('path_cache');
        if (! $request->user()->hasAnyRole(['super_admin', 'manager'])) {
            $query->where('owner_id', $request->user()->id);
        }

        return response()->json($query->get(['id', 'owner_id', 'parent_id', 'name', 'path_cache', 'depth']));
    }

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
        $pathCache = $parent ? rtrim((string) $parent->path_cache, '/').'/'.$data['name'] : '/'.$data['name'];

        $folder = Folder::create([
            'uuid' => (string) Str::uuid(),
            'parent_id' => $data['parent_id'] ?? null,
            'owner_id' => $request->user()->id,
            'name' => $data['name'],
            'path_cache' => $pathCache,
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

        if (array_key_exists('parent_id', $data) && (int) $data['parent_id'] === (int) $folder->id) {
            return response()->json(['message' => 'Folder cannot be parent of itself.'], 422);
        }

        $parent = null;
        if (! empty($data['parent_id'])) {
            $parent = Folder::findOrFail($data['parent_id']);
            $this->authorize('view', $parent);

            if (! $request->user()->hasAnyRole(['super_admin', 'manager']) && $parent->owner_id !== $folder->owner_id) {
                return response()->json(['message' => 'Invalid parent folder.'], 422);
            }
        }

        $folder->fill($data);
        if ($parent) {
            $folder->depth = $parent->depth + 1;
            $folder->path_cache = rtrim((string) $parent->path_cache, '/').'/'.$folder->name;
        } elseif (array_key_exists('parent_id', $data) && $data['parent_id'] === null) {
            $folder->depth = 0;
            $folder->path_cache = '/'.$folder->name;
        } elseif (isset($data['name'])) {
            $base = $folder->parent ? rtrim((string) $folder->parent->path_cache, '/') : '';
            $folder->path_cache = ($base !== '' ? $base : '').'/'.$folder->name;
        }

        $folder->save();
        $this->syncChildrenPathCache($folder);

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

    private function ensureRootFolder(int $userId, string $employeeId): Folder
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

    private function syncChildrenPathCache(Folder $folder): void
    {
        $folder->load('children');

        foreach ($folder->children as $child) {
            $child->depth = $folder->depth + 1;
            $child->path_cache = rtrim((string) $folder->path_cache, '/').'/'.$child->name;
            $child->save();

            $this->syncChildrenPathCache($child);
        }
    }
}
