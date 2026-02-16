<?php

namespace App\Services;

use App\Models\Genre;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class GenreContext
{
    /**
     * Get the current genre ID, preferring the authenticated user's genre,
     * falling back to session, then null.
     */
    public function getId(): ?int
    {
        $user = Auth::user();
        if ($user && $user->current_genre_id !== null) {
            return $user->current_genre_id;
        }

        return Session::get('current_genre_id');
    }

    /**
     * Get the current Genre model instance, if any.
     */
    public function get(): ?Genre
    {
        $id = $this->getId();
        if ($id === null) {
            return null;
        }

        return Genre::find($id);
    }

    /**
     * Set the current genre ID for the current user (if authenticated) or session.
     */
    public function set(?int $genreId): void
    {
        $user = Auth::user();
        if ($user) {
            $user->current_genre_id = $genreId;
            $user->save();
            // Clear session value to avoid confusion
            Session::forget('current_genre_id');
        } else {
            if ($genreId === null) {
                Session::forget('current_genre_id');
            } else {
                Session::put('current_genre_id', $genreId);
            }
        }
    }

    /**
     * Clear the current genre ID (set to null).
     */
    public function clear(): void
    {
        $this->set(null);
    }

    /**
     * Determine whether a genre is currently selected.
     */
    public function hasGenre(): bool
    {
        return $this->getId() !== null;
    }

    /**
     * Get the current genre's name, or a default label.
     */
    public function label(): string
    {
        $genre = $this->get();
        if ($genre) {
            return $genre->label();
        }

        return __('No genre selected');
    }

    /**
     * Get the current genre's icon name.
     */
    public function icon(): ?string
    {
        $genre = $this->get();
        if ($genre) {
            return $genre->icon();
        }

        return null;
    }

    /**
     * Get the current genre's color.
     */
    public function color(): ?string
    {
        $genre = $this->get();
        if ($genre) {
            return $genre->color();
        }

        return null;
    }
}
