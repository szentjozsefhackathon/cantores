<?php

namespace App\Http\Controllers;

use App\Http\Requests\HumanCheckRequest;
use App\Services\HumanVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The one gate a guest passes before a lending link opens.
 *
 * @see \App\Http\Middleware\EnsureVisitorIsHuman
 */
class HumanCheckController extends Controller
{
    /**
     * Show the challenge, unless there is nothing left to prove.
     */
    public function show(Request $request, HumanVerificationService $humans): View|RedirectResponse
    {
        if ($request->user() !== null || $humans->isVerified()) {
            return redirect()->intended(route('home'));
        }

        return view('pages.human-check');
    }

    /**
     * Accept a passed challenge and carry on to the link that was asked for.
     */
    public function store(HumanCheckRequest $request, HumanVerificationService $humans): RedirectResponse
    {
        $humans->markVerified();

        return redirect()->intended(route('home'));
    }
}
