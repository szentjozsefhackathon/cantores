<?php

namespace App\Observers;

use App\Models\FirstName;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;

class FirstNameObserver
{
    /**
     * Handle the FirstName "saved" event.
     */
    public function saved(FirstName $firstName): void
    {
        $this->invalidateCache($firstName);
    }

    /**
     * Handle the FirstName "deleted" event.
     */
    public function deleted(FirstName $firstName): void
    {
        $this->invalidateCache($firstName);
    }

    /**
     * Invalidate cache keys for the first name.
     */
    protected function invalidateCache(FirstName $firstName): void
    {
        // Invalidate all first names cache
        Cache::forget(CacheKey::forModel('first_name', 'all'));

        // Invalidate options cache
        Cache::forget(CacheKey::forModel('first_name', 'options'));

        // Invalidate individual first name cache
        Cache::forget(CacheKey::forModel('first_name', 'id', ['id' => $firstName->id]));

        // Invalidate gender-specific caches
        Cache::forget(CacheKey::forModel('first_name', 'gender', ['gender' => $firstName->gender]));
    }
}
