<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static int|null getId()
 * @method static \App\Models\Realm|null get()
 * @method static void set(?int $realmId)
 * @method static void clear()
 * @method static bool hasRealm()
 * @method static string label()
 * @method static string|null icon()
 * @method static string|null color()
 *
 * @see \App\Services\RealmContext
 */
class RealmContext extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\RealmContext::class;
    }
}
