<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;

class FolderPolicy
{
    public function view(User $user, Folder $folder): bool
    {
        if (! $user->can('folders.view')) {
            return false;
        }

        if ($this->isPrivileged($user) || $folder->owner_id === $user->id) {
            return true;
        }

        return Folder::query()
            ->where('owner_id', $folder->owner_id)
            ->whereIn('path_cache', $this->ancestorPaths($folder->path_cache))
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

    public function create(User $user): bool
    {
        return $user->can('folders.create');
    }

    public function update(User $user, Folder $folder): bool
    {
        return $user->can('folders.rename') && ($this->isPrivileged($user) || $folder->owner_id === $user->id);
    }

    public function delete(User $user, Folder $folder): bool
    {
        return $user->can('folders.delete') && ($this->isOwnerOrSuperAdmin($user, $folder));
    }

    public function restore(User $user, Folder $folder): bool
    {
        return $user->can('folders.restore') && ($this->isOwnerOrSuperAdmin($user, $folder));
    }

    private function isPrivileged(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager']);
    }

    private function isOwnerOrSuperAdmin(User $user, Folder $folder): bool
    {
        return $folder->owner_id === $user->id || $user->hasRole('super_admin');
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
