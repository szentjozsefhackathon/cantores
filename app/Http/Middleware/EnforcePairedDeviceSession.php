<?php

namespace App\Http\Middleware;

use App\Models\DevicePairing;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a screen signed in by QR code on the terms it was signed in on.
 *
 * Three things, all of them only for the handful of sessions that were paired.
 *
 * The cookie is made to expire when the browser closes, which is what the
 * church laptop needs and what nobody else should get. `StartSession` builds
 * that cookie *after* the request has run and reads `expire_on_close` from the
 * live config when it does, so setting it here reaches exactly this response
 * and leaves `SESSION_EXPIRE_ON_CLOSE` alone for everyone.
 *
 * Revocation is enforced rather than merely arranged. Deleting the row from the
 * `sessions` table is the quick way to end another browser's session, but it
 * only works under one session driver; checking the pairing here works under
 * all of them, and catches a laptop that already had the page open.
 *
 * And `last_seen_at` is refreshed, at most once a minute, so the devices list
 * can say which screen is still in the building.
 *
 * @see \App\Http\Controllers\QrLoginClaimController
 */
class EnforcePairedDeviceSession
{
    /**
     * How stale `last_seen_at` is allowed to get before it costs a write.
     */
    private const SEEN_EVERY_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->has(DevicePairing::DEVICE_SESSION_KEY)) {
            return $next($request);
        }

        // Read late by StartSession, so it governs this response's cookie.
        config(['session.expire_on_close' => true]);

        $pairing = DevicePairing::query()
            ->find($request->session()->get(DevicePairing::DEVICE_SESSION_KEY));

        if ($pairing === null || ! $pairing->isLiveDevice()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('home');
        }

        if ($pairing->last_seen_at === null
            || $pairing->last_seen_at->addSeconds(self::SEEN_EVERY_SECONDS)->isPast()) {
            $pairing->touchLastSeen();
        }

        return $next($request);
    }
}
