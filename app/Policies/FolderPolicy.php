<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;

class FolderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user !== null;
    }

    public function view(User $user, Folder $folder): bool
    {
        return $folder->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, Folder $folder): bool
    {
        return $folder->user_id === $user->id;
    }

    public function delete(User $user, Folder $folder): bool
    {
        return $folder->user_id === $user->id;
    }

    public function restore(User $user, Folder $folder): bool
    {
        return false;
    }

    public function forceDelete(User $user, Folder $folder): bool
    {
        return false;
    }
}
