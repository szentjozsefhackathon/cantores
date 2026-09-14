<?php

namespace App\Livewire\Pages;

use App\Models\Screen;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Which screen this phone is about to drive.
 *
 * A screen and not a deck, which is the whole of what changed here. While the
 * phone could only address a deck, this page had to guess which one the room was
 * seeing, and it guessed by taking the newest running presentation — so a laptop
 * that had not been started yet left the phone with an empty page instead of an
 * answer, and the back arrow on the control page returned to a list that
 * redirected straight back into the deck it had just left.
 *
 * Now there is something to address before there is anything to show. One screen
 * is the normal Sunday and is entered without asking; none is the half hour
 * before the service, and saying so is the useful thing this page does; two is
 * the chapel, and then the choice is a real one and worth making.
 */
class ProjectionRemoteList extends Component
{
    public function mount(): ?RedirectResponse
    {
        $live = $this->screens();

        if ($live->count() === 1) {
            return redirect()->route('projection-remote.control', ['screen' => $live->first()->id]);
        }

        return null;
    }

    /**
     * The screens this person has facing a room right now.
     *
     * @return EloquentCollection<int, Screen>
     */
    #[Computed]
    public function screens(): EloquentCollection
    {
        return Screen::query()
            ->live()
            ->mine()
            ->with(['presentation.projection', 'devicePairing'])
            ->latest('last_seen_at')
            ->get();
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app.main', ['title' => __('Remote'), 'noindex' => true]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.projection-remote-list');
    }
}
