<?php

namespace App\Observers;

use App\Models\Genre;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;

class GenreObserver
{
    /**
     * Handle the Genre "saved" event.
     */
    public function saved(Genre $genre): void
    {
        $this->invalidateCache($genre);
    }

    /**
     * Handle the Genre "deleted" event.
     */
    public function deleted(Genre $genre): void
    {
        $this->invalidateCache($genre);
    }

    /**
     * Invalidate cache keys for the genre.
     */
    protected function invalidateCache(Genre $genre): void
    {
        // Invalidate all genres cache
        Cache::forget(CacheKey::forModel('genre', 'all'));

        // Invalidate options cache
        Cache::forget(CacheKey::forModel('genre', 'options'));

        // Invalidate individual genre cache
        Cache::forget(CacheKey::forModel('genre', 'id', ['id' => $genre->id]));
    }
}
