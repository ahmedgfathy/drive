<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;

class FilePolicy
{
    public function view(User $user, File $file): bool
    {
        if (! $user->can('files.view')) {
            return false;
        }

        if ($this->ownsOrPrivileged($user, $file)) {
            return true;
        }

        return $file->shares()
            ->where('target_user_id', $user->id)
            ->where('channel', 'internal')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists()
            || $this->hasSharedFolderAccess($user, $file);
    }

    public function download(User $user, File $file): bool
    {
        return $user->can('files.download') && $this->view($user, $file);
    }

    public function upload(User $user): bool
    {
        return $user->can('files.upload');
    }

    public function update(User $user, File $file): bool
    {
        return $user->can('files.rename') && $this->ownsOrPrivileged($user, $file);
    }

    public function move(User $user, File $file): bool
    {
        return $user->can('files.move') && $this->ownsOrPrivileged($user, $file);
    }

    public function delete(User $user, File $file): bool
    {
        return $user->can('files.delete') && $this->ownsOrSuperAdmin($user, $file);
    }

    public function restore(User $user, File $file): bool
    {
        return $user->can('files.restore') && $this->ownsOrSuperAdmin($user, $file);
    }

    private function ownsOrPrivileged(User $user, File $file): bool
    {
        return $file->owner_id === $user->id || $user->hasAnyRole(['super_admin', 'manager']);
    }

    private function ownsOrSuperAdmin(User $user, File $file): bool
    {
        return $file->owner_id === $user->id || $user->hasRole('super_admin');
    }

    private function hasSharedFolderAccess(User $user, File $file): bool
    {
        $file->loadMissing('folder');
        $path = $file->folder?->path_cache;

        if (! is_string($path) || $path === '') {
            return false;
        }

        return \App\Models\Folder::query()
            ->where('owner_id', $file->owner_id)
            ->whereIn('path_cache', $this->ancestorPaths($path))
            ->whereHas('shares', function ($query) use ($user): void {
                $query->where('target_user_id', $user->id)
                    ->where('channel', 'internal')
                    ->where(function ($shareQuery): void {
                        $shareQuery->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
            })
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private function ancestorPaths(string $path): array
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '') {
            return ['/'];
        }

        $segments = explode('/', $trimmed);
        $paths = [];

        foreach ($segments as $index => $segment) {
            $slice = array_slice($segments, 0, $index + 1);
            $paths[] = '/'.implode('/', $slice);
        }

        return $paths;
    }
}
