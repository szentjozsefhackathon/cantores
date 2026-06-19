<?php

namespace App\Models;

use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string $url
 * @property int $sort_order
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 *
 * @method static \Database\Factories\ExternalLinkFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalLink newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalLink newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalLink ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalLink query()
 *
 * @mixin \Eloquent
 */
class ExternalLink extends Model implements Auditable
{
    /** @use HasFactory<\Database\Factories\ExternalLinkFactory> */
    use HasFactory;

    use \OwenIt\Auditing\Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'url',
        'sort_order',
    ];

    /**
     * Scope a query to order links for display.
     */
    public function scopeOrdered(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('title');
    }

    /**
     * Get all external links ordered for display, with caching (TTL: 1 hour).
     *
     * @return Collection<int, self>
     */
    public static function allCached(): Collection
    {
        $key = CacheKey::forModel('external_link', 'all');

        return Cache::remember($key, 3600, function () {
            return static::query()->ordered()->get();
        });
    }
}
