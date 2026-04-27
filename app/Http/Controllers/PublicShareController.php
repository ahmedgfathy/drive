<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Folder;
use App\Models\Share;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class PublicShareController extends Controller
{
    public function show(Request $request, string $token): JsonResponse
    {
        $share = $this->resolvePublicShare($token, $request->input('password'));

        if ($share->shareable instanceof File) {
            return response()->json([
                'share' => $this->sharePayload($share),
                'item' => [
                    'type' => 'file',
                    'id' => $share->shareable->id,
                    'name' => $share->shareable->original_name,
                    'size_bytes' => $share->shareable->size_bytes,
                    'mime_type' => $share->shareable->mime_type,
                ],
                'children' => [],
                'files' => [],
            ]);
        }

        /** @var Folder $folder */
        $folder = $share->shareable;

        return response()->json([
            'share' => $this->sharePayload($share),
            'item' => [
                'type' => 'folder',
                'id' => $folder->id,
                'name' => $folder->name,
                'path_cache' => $folder->path_cache,
            ],
            'children' => $folder->children()->withCount('files')->get(),
            'files' => $folder->files()->latest()->get(),
        ]);
    }

    public function folder(Request $request, string $token, Folder $folder): JsonResponse
    {
        $share = $this->resolvePublicShare($token, $request->input('password'));
        abort_unless($share->shareable instanceof Folder, 404);
        abort_unless($this->isSameOrDescendantFolder($share->shareable, $folder), 403);

        return response()->json([
            'share' => $this->sharePayload($share),
            'item' => [
                'type' => 'folder',
                'id' => $folder->id,
                'name' => $folder->name,
                'path_cache' => $folder->path_cache,
            ],
            'children' => $folder->children()->withCount('files')->get(),
            'files' => $folder->files()->latest()->get(),
        ]);
    }

    public function download(Request $request, string $token, ?File $file = null)
    {
        $share = $this->resolvePublicShare($token, $request->input('password'));
        abort_if(! $share->allow_download, 403, 'This share does not allow downloads.');

        if ($share->shareable instanceof File) {
            $subject = $share->shareable;
        } else {
            abort_if(! $file, 404);
            abort_unless($this->isFileInsideFolder($share->shareable, $file), 403);
            $subject = $file;
        }

        return Storage::disk($subject->disk)->download($subject->storage_path, $subject->original_name);
    }

    private function resolvePublicShare(string $token, ?string $password): Share
    {
        $share = Share::query()
            ->with(['shareable', 'grantedBy'])
            ->where('channel', 'external')
            ->where('public_token', $token)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->firstOrFail();

        if ($share->public_password) {
            abort_unless(is_string($password) && Hash::check($password, $share->public_password), 423, 'Password required.');
        }

        return $share;
    }

    private function sharePayload(Share $share): array
    {
        return [
            'id' => $share->id,
            'permission' => $share->permission,
            'expires_at' => optional($share->expires_at)?->toISOString(),
            'target_name' => $share->target_name,
            'target_email' => $share->target_email,
            'allow_download' => $share->allow_download,
            'granted_by' => [
                'name' => $share->grantedBy?->name,
                'full_name' => $share->grantedBy?->full_name,
            ],
            'requires_password' => (bool) $share->public_password,
        ];
    }

    private function isSameOrDescendantFolder(Folder $root, Folder $candidate): bool
    {
        return $candidate->owner_id === $root->owner_id
            && ($candidate->path_cache === $root->path_cache || str_starts_with($candidate->path_cache, rtrim($root->path_cache, '/').'/'));
    }

    private function isFileInsideFolder(Folder $root, File $file): bool
    {
        if ($file->owner_id !== $root->owner_id) {
            return false;
        }

        $file->loadMissing('folder');
        $path = $file->folder?->path_cache;

        return is_string($path)
            && ($path === $root->path_cache || str_starts_with($path, rtrim($root->path_cache, '/').'/'));
    }
}
