<?php

namespace App\Http\Controllers;

use App\Services\ShowStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets a device listen for its person's show moving.
 *
 * The token travels as a cookie scoped to the hub, because a native
 * EventSource cannot send a header and a token in the query string ends up in
 * every log between here and the phone. A device asks again whenever the hub
 * turns it away, so the token can stay short.
 *
 * With the hub switched off the answer says so, and the device goes on polling
 * as it always did.
 */
class ShowStreamController extends Controller
{
    public function __invoke(Request $request, ShowStream $stream): JsonResponse
    {
        if (! $stream->enabled()) {
            return response()->json(['hubUrl' => null, 'topic' => null]);
        }

        $user = $request->user();

        return response()
            ->json(['hubUrl' => ShowStream::HUB_PATH, 'topic' => $stream->topicFor($user)])
            ->withCookie(cookie(
                ShowStream::COOKIE,
                $stream->subscriberToken($user),
                ShowStream::TOKEN_MINUTES,
                ShowStream::HUB_PATH,
                null,
                $request->isSecure(),
                true,
                false,
                'strict',
            ));
    }
}
