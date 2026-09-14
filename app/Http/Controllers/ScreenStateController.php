<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScreenStateRequest;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\Screen;
use App\Services\ScreenState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * What the room is looking at: read it, and point it somewhere else.
 *
 * The read is the hot path — the wall polls it about once a second, and the
 * phone alongside it — and it carries the presentation's own state nested
 * inside, so that following the screen and following the service are one
 * request rather than two.
 *
 * The write is not hot at all. It happens when a deck is put on the screen and
 * when one is taken off, which is twice a service, and it is the whole of what
 * the phone needed: starting a deck, switching to another, and clearing the
 * wall were all impossible while the only address a remote had was a deck the
 * laptop had already chosen.
 */
class ScreenStateController extends Controller
{
    public function show(Request $request, ScreenState $state, Screen $screen): JsonResponse
    {
        abort_unless(Gate::allows('view', $screen), 404);

        // The read doubles as the screen's heartbeat, but only for the browser
        // that *is* the screen. The phone reads this too, and a phone polling a
        // laptop that has been closed must not keep the laptop looking alive.
        if ($screen->session_id === $request->session()->getId()) {
            $screen->touchLastSeen();
        }

        return response()->json($state->answer($screen));
    }

    /**
     * Put a deck on the screen, take one off, or say nothing and merely stay
     * alive.
     */
    public function update(ScreenStateRequest $request, ScreenState $state, Screen $screen): JsonResponse
    {
        abort_unless(Gate::allows('view', $screen), 404);

        if (! $request->pointsSomewhere()) {
            $screen->touchLastSeen();

            return response()->json($state->answer($screen));
        }

        $screen->point($this->presentationFor($screen, $request->projectionId()));

        return response()->json($state->answer($screen->refresh()));
    }

    /**
     * The presentation a deck is to be shown as — joined rather than started
     * afresh, so that a laptop already showing this deck and a phone pointing a
     * screen at it land on the same row and follow each other.
     */
    private function presentationFor(Screen $screen, ?int $projectionId): ?Presentation
    {
        if ($projectionId === null) {
            return null;
        }

        $projection = Projection::query()->findOrFail($projectionId);

        abort_unless(Gate::allows('view', $projection), 404);

        // The screen's own pairing, not the caller's: the device driving this
        // write is usually the phone, and which borrowed laptop the deck is on
        // is a fact about the screen.
        return Presentation::resumeFor($projection, Auth::user(), $screen->device_pairing_id);
    }
}
