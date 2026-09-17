<?php

namespace App\Livewire\Projection;

use App\Models\Screen;
use App\Services\ShowState;
use App\Support\DeviceId;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Where the show is on, said in one line on the remote.
 *
 * What used to be a page of screens to choose from. There is nothing to choose
 * now — every screen shows the same show — so all that is left is to say which
 * walls are showing it, with the pencil beside each for naming it, or that none
 * is, which is the half hour before Mass and the useful thing to say then.
 *
 * Its own small component, polling slowly, so that the remote around it — which
 * talks JSON so that no re-render can touch a picture the room is reading — is
 * never re-rendered for it.
 */
class ShowStatus extends Component
{
    /**
     * The screens showing the show, less this browser's own: a phone that
     * pressed Present is a screen too, and saying the show is on the phone in
     * the cantor's hand tells them nothing.
     *
     * @return EloquentCollection<int, Screen>
     */
    #[Computed]
    public function screens(): EloquentCollection
    {
        return ShowState::screensFor(Auth::user())
            ->reject(fn (Screen $screen): bool => $screen->device_id === DeviceId::current())
            ->values();
    }

    #[On('screen-renamed')]
    public function screenRenamed(): void
    {
        unset($this->screens);
    }

    public function render(): IlluminateView
    {
        return view('livewire.projection.show-status');
    }
}
