<?php

namespace App\Policies;

use App\Models\MusicVerification;
use App\Models\User;

class MusicVerificationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view verifications (they are community-maintained)
        return $user !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MusicVerification $musicVerification): bool
    {
        // Admin can view any verification
        if ($user !== null && $user->is_admin) {
            return true;
        }

        // Non-admin can view only verifications they created (verifier_id matches)
        return $user !== null && $musicVerification->verifier_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only admin (or editors with verification permission) can create verifications
        return $user !== null && $user->is_admin;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MusicVerification $musicVerification): bool
    {
        // Only admin can update verification status
        return $user !== null && $user->is_admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MusicVerification $musicVerification): bool
    {
        // Only admin can delete verifications
        return $user !== null && $user->is_admin;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MusicVerification $musicVerification): bool
    {
        return $user !== null && $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MusicVerification $musicVerification): bool
    {
        return $user !== null && $user->is_admin;
    }

    /**
     * Determine whether the user can verify the verification.
     */
    public function verify(User $user, MusicVerification $musicVerification): bool
    {
        // Only admin can mark as verified
        return $user !== null && $user->is_admin;
    }

    /**
     * Determine whether the user can reject the verification.
     */
    public function reject(User $user, MusicVerification $musicVerification): bool
    {
        // Only admin can mark as rejected
        return $user !== null && $user->is_admin;
    }
}
