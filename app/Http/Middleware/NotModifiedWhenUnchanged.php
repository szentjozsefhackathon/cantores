<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A poll whose answer has not moved since the last one is answered with nothing.
 *
 * The browser does the asking. The answer carries an ETag and `no-cache`, so the
 * browser's own cache keeps the last body and revalidates it on the next
 * `fetch` with `If-None-Match`; a `304` comes back to the page as that stored
 * body with a `200` on it. The clients are not changed and do not know.
 *
 * What this saves is the wire and the parse, not the work: the answer is still
 * built — the read is also a heartbeat, and a hash of the body is the only
 * honest "has anything changed" there is while the screens' liveness is a clock
 * rather than a write. Taking Postgres out of the quiet path is a later step.
 *
 * The tag is weak on purpose, and that was measured rather than assumed. Caddy's
 * `encode` suffixes a strong tag with the encoding (`"…-zstd"`) and strips it
 * again on the way in, but only from a strong tag — and Cloudflare weakens a
 * strong tag whenever it touches the compression, which leaves `W/"…-zstd"`
 * matching nothing. A weak tag passes through Caddy untouched and compares equal
 * whichever encoding carried it.
 */
class NotModifiedWhenUnchanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodCacheable() || ! $response->isSuccessful() || ! $response->getContent()) {
            return $response;
        }

        $response->setEtag(hash('xxh128', $response->getContent()), weak: true);
        $response->setCache(['private' => true, 'no_cache' => true]);
        $response->isNotModified($request);

        return $response;
    }
}
