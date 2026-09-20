<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowStateRequest;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\Screen;
use App\Services\ShowState;
use App\Support\DeviceId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * This person's show: read it, and put another deck up.
 *
 * The read is the hot path — the wall polls it about once a second, and the
 * phone alongside it — and it carries the presentation's own state nested
 * inside, so that following the show and following the service are one request
 * rather than two. No screen is named in the address, because there is nothing
 * to choose: every device of this person's follows the same show.
 *
 * The write is not hot at all. It happens when a deck is put up and when one is
 * taken down, which is twice a service.
 */
class ShowStateController extends Controller
{
    public function show(Request $request, ShowState $state): JsonResponse
    {
        $user = $request->user();

        $screens = ShowState::allFor($user);

        // The read doubles as a screen's heartbeat, but only for the wall
        // itself, which says so. The remote reads this too — from a phone, or
        // from the very laptop the wall is on — and neither must keep a closed
        // wall looking alive.
        //
        // And it is the wall's own row out of the answer being built anyway,
        // not a query of its own: a wall's poll asked for this row twice, once
        // to keep it warm and once to read its fit back off it. The only
        // browser this misses is one that has been away longer than the answer
        // reaches back, which is a screen returning from sleep and not a Mass.
        if ($request->boolean('screen')) {
            $own = $screens->firstWhere('device_id', DeviceId::current());

            if ($own instanceof Screen) {
                $own->touchLastSeen();
            } else {
                Screen::query()
                    ->mine($user)
                    ->where('device_id', DeviceId::current())
                    ->first()
                    ?->touchLastSeen();

                $screens = ShowState::allFor($user);
            }
        }

        $presentation = Presentation::currentFor($user);

        // And the show's: a deck set up from the phone before the laptop is on
        // is still up when the laptop arrives, because somebody was watching it.
        $presentation?->keepAlive();

        return response()->json($state->answer($user, $presentation, $screens));
    }

    /**
     * Put a deck up, or with null take the show down.
     */
    public function update(ShowStateRequest $request, ShowState $state): JsonResponse
    {
        $user = $request->user();
        $projectionId = $request->projectionId();

        if ($projectionId === null) {
            Presentation::takeDownFor($user);

            return response()->json($state->answer($user, null));
        }

        $projection = Projection::query()->find($projectionId);

        abort_unless($projection instanceof Projection && Gate::allows('view', $projection), 404);

        return response()->json($state->answer($user, Presentation::putUp($user, $projection)));
    }
}
