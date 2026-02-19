<?php

namespace App\Observers;

use App\Models\Collection;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;

class CollectionObserver
{
    /**
     * Handle the Collection "saved" event.
     */
    public function saved(Collection $collection): void
    {
        $this->invalidateCache($collection);
    }

    /**
     * Handle the Collection "deleted" event.
     */
    public function deleted(Collection $collection): void
    {
        $this->invalidateCache($collection);
    }

    /**
     * Invalidate cache keys for the collection.
     */
    protected function invalidateCache(Collection $collection): void
    {
        // Invalidate all collections cache
        Cache::forget(CacheKey::forModel('collection', 'all'));

        // Invalidate options cache
        Cache::forget(CacheKey::forModel('collection', 'options'));

        // Invalidate individual collection cache
        Cache::forget(CacheKey::forModel('collection', 'id', ['id' => $collection->id]));
    }
}
