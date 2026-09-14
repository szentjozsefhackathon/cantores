<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Which browser this is, for longer than a session lasts.
 *
 * A screen was keyed by the session, and a session is two hours of quiet. The
 * parish laptop signed in this Sunday is therefore a different row next Sunday —
 * which is tolerable while a screen is anonymous and intolerable the moment
 * anybody names one, because the name would die weekly along with the row it
 * hung on.
 *
 * The browser offers nothing to key on. There is no device identifier to read,
 * and fingerprinting is not a thing to put under a feature the service depends
 * on, so one is minted here and kept in a cookie that outlives the session by
 * years.
 *
 * A cookie rather than anything the page stores, for a mechanical reason: a
 * screen is claimed in `mount()`, server-side, on the way into the page, and a
 * cookie is already on that request. A value held in the browser would arrive
 * one round trip after the claim that needed it.
 *
 * It is a label key and never a credential. Nothing is authorized by it: a
 * screen still belongs to a user, and reaching any of it still needs the
 * session. A copied cookie buys exactly nothing.
 */
class DeviceId
{
    public const COOKIE = 'device';

    /**
     * Minted once and remembered, so the same browser answers the same thing
     * next Sunday.
     *
     * Three places are asked, in the order of how long each is good for. The
     * cookie is the answer; the cookie queued earlier in this same request is
     * the answer for the request that mints one, which is also the request that
     * claims the screen with it; and the session is what is left for a browser
     * that will not keep cookies, which is at least consistent for as long as
     * that browser stays signed in.
     */
    public static function current(): string
    {
        $existing = request()?->cookie(self::COOKIE);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $queued = Cookie::queued(self::COOKIE);

        if ($queued !== null) {
            return $queued->getValue();
        }

        $remembered = Session::get(self::COOKIE);

        if (is_string($remembered) && $remembered !== '') {
            return $remembered;
        }

        $minted = (string) Str::uuid();

        Cookie::queue(Cookie::forever(self::COOKIE, $minted, httpOnly: true));
        Session::put(self::COOKIE, $minted);

        return $minted;
    }
}
