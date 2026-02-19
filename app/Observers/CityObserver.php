<?php

namespace App\Observers;

use App\Models\City;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;

class CityObserver
{
    /**
     * Handle the City "saved" event.
     */
    public function saved(City $city): void
    {
        $this->invalidateCache($city);
    }

    /**
     * Handle the City "deleted" event.
     */
    public function deleted(City $city): void
    {
        $this->invalidateCache($city);
    }

    /**
     * Invalidate cache keys for the city.
     */
    protected function invalidateCache(City $city): void
    {
        // Invalidate all cities cache
        Cache::forget(CacheKey::forModel('city', 'all'));

        // Invalidate options cache
        Cache::forget(CacheKey::forModel('city', 'options'));

        // Invalidate individual city cache
        Cache::forget(CacheKey::forModel('city', 'id', ['id' => $city->id]));
    }
}
