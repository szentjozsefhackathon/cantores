<?php

namespace App\Http\Controllers;

use App\Http\Requests\PresentationStateRequest;
use App\Models\Presentation;
use App\Services\PresentationState;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Where the service has got to: read it, and write it.
 *
 * Two narrow JSON routes rather than a Livewire round trip, and that is the one
 * decision here worth defending. The presenter's stage is `wire:ignore`d and
 * nothing in that component writes, both so that no re-render can touch the
 * picture mid-service; polling the component would give that up for the sake of
 * a saving that does not exist, since what travels either way is six fields.
 *
 * Authorization is the projection's own `view` plus ownership of the row. A row
 * belonging to somebody else answers 404 rather than 403: a service this account
 * is not running is not a thing it may know exists.
 */
class PresentationStateController extends Controller
{
    public function show(PresentationState $state, Presentation $presentation): JsonResponse
    {
        $this->allow($presentation);

        return response()->json($state->answer($presentation));
    }

    /**
     * A device reporting where it has moved the service to — or simply that it
     * is still there.
     *
     * Last one wins, deliberately: there is one liturgy, and the people driving
     * it can see each other. What is ordered is the *reads*, by a version that
     * moves when the state does.
     */
    public function update(
        PresentationStateRequest $request,
        PresentationState $state,
        Presentation $presentation,
    ): JsonResponse {
        $this->allow($presentation);

        if ($request->boolean('ended')) {
            $presentation->end();

            return response()->json($state->answer($presentation));
        }

        $reported = $request->state();
        $entryId = $reported['entryId'] ?? null;

        $presentation->applyState(
            $reported,
            $entryId === null ? null : $state->entriesOf($presentation)->firstWhere('id', $entryId),
        );

        // Written outside applyState, because it is not part of where the
        // service is: it is this screen's honest answer to "have you finished
        // drawing the edit yet", and a heartbeat that carries nothing else must
        // not look like the deck moved.
        $drawn = $request->input('drawnRevision');

        if ($request->has('drawnRevision') && $drawn !== $presentation->drawn_revision) {
            $presentation->forceFill(['drawn_revision' => $drawn])->save();
        }

        return response()->json($state->answer($presentation));
    }

    /**
     * The two questions this endpoint asks, in the shape the score-page
     * controllers already ask them.
     */
    private function allow(Presentation $presentation): void
    {
        abort_unless(Gate::allows('view', $presentation->projection), 404);
        abort_unless(Gate::allows('view', $presentation), 404);
    }
}
