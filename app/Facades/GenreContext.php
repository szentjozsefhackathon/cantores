<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static int|null getId()
 * @method static \App\Models\Genre|null get()
 * @method static void set(?int $genreId)
 * @method static void clear()
 * @method static bool hasGenre()
 * @method static string label()
 * @method static string|null icon()
 * @method static string|null color()
 *
 * @see \App\Services\GenreContext
 */
class GenreContext extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\GenreContext::class;
    }
}
