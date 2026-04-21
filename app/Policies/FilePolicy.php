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
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
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
        return $user->can('files.delete') && $this->ownsOrPrivileged($user, $file);
    }

    public function restore(User $user, File $file): bool
    {
        return $user->can('files.restore') && $this->ownsOrPrivileged($user, $file);
    }

    private function ownsOrPrivileged(User $user, File $file): bool
    {
        return $file->owner_id === $user->id || $user->hasAnyRole(['super_admin', 'manager']);
    }
}
