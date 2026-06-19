<?php

namespace App\Observers;

use App\Models\ExternalLink;
use App\Support\CacheKey;
use Illuminate\Support\Facades\Cache;

class ExternalLinkObserver
{
    /**
     * Handle the ExternalLink "saved" event.
     */
    public function saved(ExternalLink $externalLink): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle the ExternalLink "deleted" event.
     */
    public function deleted(ExternalLink $externalLink): void
    {
        $this->invalidateCache();
    }

    /**
     * Invalidate cache keys for external links.
     */
    protected function invalidateCache(): void
    {
        Cache::forget(CacheKey::forModel('external_link', 'all'));
    }
}
