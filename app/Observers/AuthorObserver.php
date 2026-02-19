<?php

namespace App\Observers;

use App\Models\Author;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;

class AuthorObserver
{
    /**
     * Handle the Author "saved" event.
     */
    public function saved(Author $author): void
    {
        $this->invalidateCache($author);
    }

    /**
     * Handle the Author "deleted" event.
     */
    public function deleted(Author $author): void
    {
        $this->invalidateCache($author);
    }

    /**
     * Invalidate cache keys for the author.
     */
    protected function invalidateCache(Author $author): void
    {
        // Invalidate all authors cache
        Cache::forget(CacheKey::forModel('author', 'all'));

        // Invalidate individual author cache
        Cache::forget(CacheKey::forModel('author', 'id', ['id' => $author->id]));
    }
}
