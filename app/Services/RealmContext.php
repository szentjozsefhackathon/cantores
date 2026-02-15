<?php

namespace App\Services;

use App\Models\Realm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class RealmContext
{
    /**
     * Get the current realm ID, preferring the authenticated user's realm,
     * falling back to session, then null.
     */
    public function getId(): ?int
    {
        $user = Auth::user();
        if ($user && $user->current_realm_id !== null) {
            return $user->current_realm_id;
        }

        return Session::get('current_realm_id');
    }

    /**
     * Get the current Realm model instance, if any.
     */
    public function get(): ?Realm
    {
        $id = $this->getId();
        if ($id === null) {
            return null;
        }

        return Realm::find($id);
    }

    /**
     * Set the current realm ID for the current user (if authenticated) or session.
     */
    public function set(?int $realmId): void
    {
        $user = Auth::user();
        if ($user) {
            $user->current_realm_id = $realmId;
            $user->save();
            // Clear session value to avoid confusion
            Session::forget('current_realm_id');
        } else {
            if ($realmId === null) {
                Session::forget('current_realm_id');
            } else {
                Session::put('current_realm_id', $realmId);
            }
        }
    }

    /**
     * Clear the current realm ID (set to null).
     */
    public function clear(): void
    {
        $this->set(null);
    }

    /**
     * Determine whether a realm is currently selected.
     */
    public function hasRealm(): bool
    {
        return $this->getId() !== null;
    }

    /**
     * Get the current realm's name, or a default label.
     */
    public function label(): string
    {
        $realm = $this->get();
        if ($realm) {
            return $realm->label();
        }

        return __('No realm selected');
    }

    /**
     * Get the current realm's icon name.
     */
    public function icon(): ?string
    {
        $realm = $this->get();
        if ($realm) {
            return $realm->icon();
        }

        return null;
    }

    /**
     * Get the current realm's color.
     */
    public function color(): ?string
    {
        $realm = $this->get();
        if ($realm) {
            return $realm->color();
        }

        return null;
    }
}
