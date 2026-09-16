<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectionAddedMusicRequest;
use App\Http\Requests\ProjectionDeckEditRequest;
use App\Models\Projection;
use App\Models\ProjectionMusic;
use App\Services\PlanOrder;
use App\Services\PlanOutline;
use App\Services\ProjectionRenderPayload;
use Illuminate\Http\JsonResponse;

/**
 * The remote's way to add a music the plan does not have, and to take it away.
 *
 * Saved to the deck like any other edit: a deck is made for one occasion, so
 * what changed during it belongs to it. Added at the end of the slot asked for,
 * or at the end of the deck — never at the slide the room is on, since a song
 * asked for during the Kyrie is for later — and moved into place from there.
 *
 * Both answer with the deck made fresh, as the score toggle does, so the phone
 * redraws without polling for it.
 */
class ProjectionAddedMusicController extends Controller
{
    public function __construct(
        private readonly PlanOrder $order,
        private readonly PlanOutline $outline,
        private readonly ProjectionRenderPayload $payloads,
    ) {}

    public function store(ProjectionAddedMusicRequest $request, Projection $projection): JsonResponse
    {
        $slotPlanId = $request->slotPlanId();

        $projection->addedMusics()->create([
            'music_id' => $request->music()?->id,
            'music_plan_slot_plan_id' => $slotPlanId,
            'sequence' => $this->outline->appendIndex($this->order->outlineOf($projection), $slotPlanId, null),
        ]);

        return $this->answer($request, $projection);
    }

    public function destroy(ProjectionDeckEditRequest $request, Projection $projection, ProjectionMusic $addedMusic): JsonResponse
    {
        $this->order->removeAddedMusic($projection, $this->order->outlineOf($projection), $addedMusic);

        return $this->answer($request, $projection);
    }

    private function answer(ProjectionDeckEditRequest $request, Projection $projection): JsonResponse
    {
        return response()->json([
            ...$this->payloads->for($projection, $request->user()),
            'revision' => $projection->revision(),
        ]);
    }
}
