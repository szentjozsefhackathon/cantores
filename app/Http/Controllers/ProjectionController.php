<?php

namespace App\Http\Controllers;

use App\Models\MusicPlan;
use App\Models\Projection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ProjectionController extends Controller
{
    /**
     * Start a projection, usually from the plan whose service it is for.
     *
     * The plan supplies nothing but a name and a link: which of its scores go on
     * the screen is the whole point of the editor, and is chosen there.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Projection::class);

        $plan = null;
        $planId = $request->integer('music_plan_id');

        if ($planId > 0) {
            $plan = MusicPlan::query()->findOrFail($planId);
            abort_unless(Gate::allows('view', $plan), 403);
        }

        // Most cantors make most of their decks for the same church, so a new
        // deck starts in the style their last styled deck was in.
        $style = Projection::query()->mine()->whereNotNull('projection_style_id')->latest('updated_at')->first()?->style;

        $projection = Projection::create([
            'user_id' => Auth::id(),
            'music_plan_id' => $plan?->getKey(),
            'title' => Projection::titleFor($plan),
            ...($style === null ? [] : ['projection_style_id' => $style->id, 'ratio' => $style->ratio]),
        ]);

        return redirect()->route('projections.edit', ['projection' => $projection->id]);
    }

    public function destroy(Projection $projection): RedirectResponse
    {
        $this->authorize('delete', $projection);

        $projection->delete();

        return redirect()->route('plan-documents');
    }
}
