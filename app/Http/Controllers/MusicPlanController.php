<?php

namespace App\Http\Controllers;

use App\Facades\GenreContext;
use App\Models\MusicPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class MusicPlanController extends Controller
{
    /**
     * Store a newly created music plan.
     */
    public function store(): RedirectResponse
    {
        $musicPlan = MusicPlan::create([
            'user_id' => Auth::id(),
            'is_private' => true,
            'genre_id' => GenreContext::getId(),
        ]);

        // Create a custom celebration for this new music plan
        $musicPlan->createCustomCelebration('Egyedi ünnep');

        return redirect()->route('music-plan-editor', ['musicPlan' => $musicPlan->id]);
    }

    /**
     * Copy an existing music plan.
     */
    public function copy(MusicPlan $musicPlan): RedirectResponse
    {
        $this->authorize('copy', $musicPlan);

        $newPlan = $musicPlan->copy(Auth::user());

        return redirect()->route('music-plan-editor', ['musicPlan' => $newPlan->id]);
    }
}
