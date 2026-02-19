<?php

namespace App\Observers;

use App\Models\Music;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;

class MusicObserver
{
    /**
     * Handle the Music "saved" event.
     */
    public function saved(Music $music): void
    {
        $this->invalidateCache($music);
    }

    /**
     * Handle the Music "deleted" event.
     */
    public function deleted(Music $music): void
    {
        $this->invalidateCache($music);
    }

    /**
     * Invalidate cache keys for the music.
     */
    protected function invalidateCache(Music $music): void
    {
        // Invalidate all music cache
        Cache::forget(CacheKey::forModel('music', 'all'));

        // Invalidate individual music cache
        Cache::forget(CacheKey::forModel('music', 'id', ['id' => $music->id]));
    }
}
