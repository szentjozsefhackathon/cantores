<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScreenAcknowledgementRequest;
use App\Models\Presentation;
use App\Models\Screen;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * A wall saying what it has actually drawn.
 *
 * Sent when that changes and not on a clock, so this is the one write on the
 * whole feature that is allowed to be a write: it happens about as often as a
 * cantor presses a key, and never in between.
 *
 * What it costs is therefore worth keeping small. The show a screen may
 * acknowledge is this person's un-ended row, of which there is at most one —
 * the partial unique index says so — so having found it by id under `mine`,
 * there is nothing further to ask the database to confirm.
 */
class ScreenAcknowledgementController extends Controller
{
    public function __invoke(ScreenAcknowledgementRequest $request, Screen $screen): JsonResponse
    {
        $presentation = Presentation::query()
            ->mine($request->user())
            ->find($request->integer('presentationId'));

        abort_unless($presentation instanceof Presentation, 404);

        if ($presentation->ended_at !== null || $request->integer('appliedVersion') > $presentation->version) {
            throw ValidationException::withMessages([
                'appliedVersion' => __('This presentation state is no longer current.'),
            ]);
        }

        // A show nobody has been heard from about for five minutes is no longer
        // anybody's show, and a screen reporting against it is reporting
        // against something the phone has already stopped being shown.
        abort_unless($presentation->isLive(), 409);

        $screen = $screen->acknowledge(
            $presentation,
            $request->integer('appliedVersion'),
            $request->input('drawnRevision'),
        );

        return response()->json([
            'presentationId' => $screen->applied_presentation_id,
            'appliedVersion' => $screen->applied_version,
            'drawnRevision' => $screen->drawn_revision,
            'appliedAt' => $screen->applied_at?->toIso8601String(),
        ]);
    }
}
