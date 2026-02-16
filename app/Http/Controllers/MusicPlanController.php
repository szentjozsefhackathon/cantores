<?php

namespace App\Http\Controllers;

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
            'is_published' => false,
            'realm_id' => null,
        ]);

        // Create a custom celebration for this new music plan
        $musicPlan->createCustomCelebration('Egyedi ünnep');

        return redirect()->route('music-plan-editor', ['musicPlan' => $musicPlan->id]);
    }
}
