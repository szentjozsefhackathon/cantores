<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectionMoveRequest;
use App\Models\Projection;
use App\Services\PlanOrder;
use App\Services\ProjectionRenderPayload;
use Illuminate\Http\JsonResponse;

/**
 * Move one row, music or slot of a deck from the remote.
 *
 * No rule of its own: the move is made on the same tree, and refused for the
 * same reasons, as in the editors — a row cannot leave its music, a music cannot
 * leave its slot. A refused move is a 422, so the phone keeps the deck it had.
 *
 * The wall does not jump. It keeps its place as `{entryId, slideIndex}`, and a
 * move removes nothing, so the slide on the screen is still found in the new
 * order even when it is its own row that moved.
 */
class ProjectionMoveController extends Controller
{
    public function __invoke(ProjectionMoveRequest $request, PlanOrder $order, ProjectionRenderPayload $payloads, Projection $projection): JsonResponse
    {
        $moved = $order->move($projection, $request->kind(), $request->nodeId(), $request->direction());

        abort_unless($moved, 422, __('That cannot be moved there.'));

        return response()->json([
            ...$payloads->for($projection, $request->user()),
            'revision' => $projection->revision(),
        ]);
    }
}
