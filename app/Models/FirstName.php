<?php

namespace App\Models;

use App\Support\CacheKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;

class FirstName extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'gender',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => 'string',
        ];
    }

    /**
     * Get the available gender options.
     *
     * @return array<string, string>
     */
    public static function genderOptions(): array
    {
        return [
            'male' => __('Male'),
            'female' => __('Female'),
        ];
    }

    /**
     * Get all first names ordered by name, cached for 24 hours.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function allCached(): SupportCollection
    {
        $key = CacheKey::forModel('first_name', 'all');

        return Cache::remember($key, 86400, function () {
            return self::orderBy('name')->get();
        });
    }

    /**
     * Find a first name by ID, cached for 24 hours.
     */
    public static function findCached(int $id): ?self
    {
        $key = CacheKey::forModel('first_name', 'id', ['id' => $id]);

        return Cache::remember($key, 86400, function () use ($id) {
            return self::find($id);
        });
    }

    /**
     * Get first name options (id => name), cached for 24 hours.
     *
     * @return array<int, string>
     */
    public static function optionsCached(): array
    {
        $key = CacheKey::forModel('first_name', 'options');

        return Cache::remember($key, 86400, function () {
            return self::allCached()->mapWithKeys(fn ($firstName) => [
                $firstName->id => $firstName->name,
            ])->toArray();
        });
    }

    /**
     * Get first names filtered by gender ordered by name, cached for 24 hours.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function byGenderCached(string $gender): SupportCollection
    {
        $key = CacheKey::forModel('first_name', 'gender', ['gender' => $gender]);

        return Cache::remember($key, 86400, function () use ($gender) {
            return self::where('gender', $gender)->orderBy('name')->get();
        });
    }
}
