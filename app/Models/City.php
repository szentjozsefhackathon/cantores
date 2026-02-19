<?php

namespace App\Models;

use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;

class City extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Get all cities ordered by name, cached for 24 hours.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function allCached(): SupportCollection
    {
        $key = CacheKey::forModel('city', 'all');

        return Cache::remember($key, 86400, function () {
            return self::orderBy('name')->get();
        });
    }

    /**
     * Find a city by ID, cached for 24 hours.
     */
    public static function findCached(int $id): ?self
    {
        $key = CacheKey::forModel('city', 'id', ['id' => $id]);

        return Cache::remember($key, 86400, function () use ($id) {
            return self::find($id);
        });
    }

    /**
     * Get city options (id => name), cached for 24 hours.
     *
     * @return array<int, string>
     */
    public static function optionsCached(): array
    {
        $key = CacheKey::forModel('city', 'options');

        return Cache::remember($key, 86400, function () {
            return self::allCached()->mapWithKeys(fn ($city) => [
                $city->id => $city->name,
            ])->toArray();
        });
    }
}
