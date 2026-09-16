<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectionScoreToggleRequest;
use App\Models\Projection;
use App\Services\ProjectionRenderPayload;
use App\Services\ProjectionScoreToggle;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * The remote's own "+" and "×": add one of a music's other engravings to today's
 * deck, or take one already in it back out — without leaving the screen that is
 * driving the service.
 *
 * Plain JSON rather than a Livewire round trip, for the reason the whole remote
 * is: nothing here may touch the wire:ignore'd canvas mid-service. The deck is
 * written to exactly as the editor writes it, through the one service both share,
 * and the answer is the deck's own payload made fresh — the same one polling
 * picks up when a revision moves underneath it — so the phone can redraw without
 * a second request.
 */
class ProjectionScoreToggleController extends Controller
{
    public function __invoke(ProjectionScoreToggleRequest $request, ProjectionScoreToggle $toggle, ProjectionRenderPayload $payloads, Projection $projection): JsonResponse
    {
        $toggle->toggle(
            $projection,
            Auth::user(),
            $request->scoreId(),
            $request->assignmentId(),
            $request->fileId(),
        );

        return response()->json([
            ...$payloads->for($projection, Auth::user()),
            'revision' => $projection->revision(),
        ]);
    }
}
