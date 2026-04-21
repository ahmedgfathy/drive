<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;

class FolderPolicy
{
    public function view(User $user, Folder $folder): bool
    {
        return $user->can('folders.view') && ($this->isPrivileged($user) || $folder->owner_id === $user->id);
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
        return $user->can('folders.delete') && ($this->isPrivileged($user) || $folder->owner_id === $user->id);
    }

    public function restore(User $user, Folder $folder): bool
    {
        return $user->can('folders.restore') && ($this->isPrivileged($user) || $folder->owner_id === $user->id);
    }

    private function isPrivileged(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'manager']);
    }
}
