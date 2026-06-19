<?php

namespace App\Policies;

use App\Models\ExternalLink;
use App\Models\User;

class ExternalLinkPolicy
{
    /**
     * Determine whether the user can manage external links.
     * Restricted to admins and editors (master data maintainers).
     */
    public function manage(User $user): bool
    {
        return $user->is_admin || $user->hasPermissionTo('masterdata.maintain');
    }

    /**
     * Determine whether the user can view the management list.
     */
    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine whether the user can create external links.
     */
    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine whether the user can update the external link.
     */
    public function update(User $user, ExternalLink $externalLink): bool
    {
        return $this->manage($user);
    }

    /**
     * Determine whether the user can delete the external link.
     */
    public function delete(User $user, ExternalLink $externalLink): bool
    {
        return $this->manage($user);
    }
}
