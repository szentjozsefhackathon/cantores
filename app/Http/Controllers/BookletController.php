<?php

namespace App\Http\Controllers;

use App\Models\Booklet;
use App\Models\MusicPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class BookletController extends Controller
{
    /**
     * Start a booklet, usually from the plan whose service it is for.
     *
     * The plan supplies nothing but a name and a link: which of its scores go in
     * is the whole point of the editor, and is chosen there.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Booklet::class);

        $plan = null;
        $planId = $request->integer('music_plan_id');

        if ($planId > 0) {
            $plan = MusicPlan::query()->findOrFail($planId);
            abort_unless(Gate::allows('view', $plan), 403);
        }

        $booklet = Booklet::create([
            'user_id' => Auth::id(),
            'music_plan_id' => $plan?->getKey(),
            'title' => Booklet::titleFor($plan),
        ]);

        return redirect()->route('booklets.edit', ['booklet' => $booklet->id]);
    }

    public function destroy(Booklet $booklet): RedirectResponse
    {
        $this->authorize('delete', $booklet);

        $booklet->delete();

        return redirect()->route('booklets');
    }
}
