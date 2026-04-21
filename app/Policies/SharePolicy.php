<?php

namespace App\Policies;

use App\Models\Share;
use App\Models\User;

class SharePolicy
{
    public function create(User $user): bool
    {
        return $user->can('files.share_internal');
    }

    public function delete(User $user, Share $share): bool
    {
        return $user->can('shares.revoke')
            && ($share->granted_by === $user->id || $user->hasAnyRole(['super_admin', 'manager']));
    }
}
