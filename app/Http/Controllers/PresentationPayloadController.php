<?php

namespace App\Http\Controllers;

use App\Models\Presentation;
use App\Services\ProjectionRenderPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * The deck itself, read again without leaving the screen that is showing it.
 *
 * The half hour before a service is when a deck changes most — a stanza is
 * retyped, a row is moved, a wrong note is fixed in the score itself — and both
 * the wall and the remote engraved theirs once at load. The state answer carries
 * a revision that says a change happened; this is what is read when it did.
 *
 * Entitlement is resolved here afresh, like everywhere: a score that stopped
 * being readable between Thursday and Sunday is no more on the phone than it is
 * on the wall. The revision travels with the payload rather than being read
 * again afterwards, so a client always records the revision of the deck it
 * actually drew.
 */
class PresentationPayloadController extends Controller
{
    public function __invoke(ProjectionRenderPayload $payloads, Presentation $presentation): JsonResponse
    {
        abort_unless(Gate::allows('view', $presentation->projection), 404);
        abort_unless(Gate::allows('view', $presentation), 404);

        $projection = $presentation->projection;
        $revision = $projection->revision();

        return response()->json([
            ...$payloads->for($projection, Auth::user()),
            'title' => $projection->title,
            'revision' => $revision,
        ]);
    }
}
