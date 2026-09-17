<?php

namespace App\Livewire\Projection;

use App\Models\Presentation;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A way back to the remote from wherever the cantor wandered off to.
 *
 * Sits in the top navbar on every page. While a show is up, it is the fastest
 * way back to the device that drives it — most needed on a phone, where the
 * sidebar is a drawer rather than a rail, so it also floats over the corner of
 * the full desktop layout. Silent the rest of the time: nothing is up, nothing
 * to jump back to.
 *
 * Rendered once for each half so neither ends up nested inside the other's
 * layout wrapper: the mobile icon lives inside a header that is itself
 * `lg:hidden`, and a floating button nested in there would never reach the
 * desktop viewport no matter what its own classes said.
 */
class RemoteControlLink extends Component
{
    public string $only = 'both';

    #[Computed]
    public function presentation(): ?Presentation
    {
        if (! Auth::check()) {
            return null;
        }

        return Presentation::currentFor(Auth::user());
    }

    #[On('show-screens-changed')]
    public function refreshPresentation(): void
    {
        unset($this->presentation);
    }

    public function render(): IlluminateView
    {
        return view('livewire.projection.remote-control-link');
    }
}
