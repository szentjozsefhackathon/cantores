<?php

namespace App\Http\Middleware;

use App\Services\HumanVerificationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stands in front of the lending links, so only a person walks through them.
 *
 * A guest is sent to the challenge page once; everything the link then opens —
 * pages, incipits, downloads — is served for the rest of the session without
 * asking again. The URL that was asked for is kept in the session rather than
 * on the query string: a lending token is a secret, and secrets do not belong
 * in a redirect anyone may be watching.
 */
class EnsureVisitorIsHuman
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() || app(HumanVerificationService::class)->isVerified()) {
            return $next($request);
        }

        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()->route('human-check');
    }
}
