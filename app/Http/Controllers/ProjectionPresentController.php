<?php

namespace App\Http\Controllers;

use App\Models\Presentation;
use App\Models\Projection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Present: put this deck up as the show, and go to the screen.
 *
 * Not a page of its own. A person has one show at a time, so a wall addressed
 * by deck is an address that goes stale the moment a phone puts another one up
 * — and reloading it would put the old deck back over the new. The screen lives
 * at one address, and this is only the way onto it.
 *
 * Nothing about the picture is decided here. Whether the wall is black stays
 * whatever the cantor last made it: putting a deck up is preparation, and only
 * B — on the wall or on the phone — is a cue.
 */
class ProjectionPresentController extends Controller
{
    public function __invoke(Request $request, Projection $projection): RedirectResponse
    {
        Gate::authorize('view', $projection);

        Presentation::putUp($request->user(), $projection);

        return redirect()->route('projection-screen');
    }
}
