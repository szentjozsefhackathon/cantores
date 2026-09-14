<?php

namespace App\Livewire\Pages;

use App\Models\Screen;
use App\Support\DeviceId;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
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
    /**
     * A screen renamed from the pencil beside its row.
     *
     * The row says the name itself here, unlike the pages where the pencil
     * carries it, because the name is the row's title and sits inside the link.
     * So this is the one page a rename has to re-read — which costs nothing:
     * it is a list on a phone, not a picture a room is reading.
     */
    #[On('screen-renamed')]
    public function screenRenamed(): void
    {
        unset($this->screens);
    }

    public function mount(): ?RedirectResponse
    {
        $live = $this->screens();

        if ($live->count() === 1) {
            return redirect()->route('projection-remote.control', ['screen' => $live->first()->id]);
        }

        return null;
    }

    /**
     * The screens this person has facing a room right now, other than the one
     * they are holding.
     *
     * Its own screen is excluded because a browser cannot be the remote for
     * itself: the presenter page claims a screen on the way in whichever way it
     * was opened, so a phone that pressed Present to look at a deck arrives here
     * offering itself as somewhere to send one. That is never the answer, and
     * while it stands it costs the normal Sunday its best moment — the one live
     * screen entered without asking becomes a picker between the wall and the
     * hand.
     *
     * Only here. Sending a deck keeps the same-session row, because the
     * two-screen laptop is exactly that: the window on the projector and the
     * window being worked in are one session, and excluding it would leave the
     * wall unaddressable.
     *
     * @return EloquentCollection<int, Screen>
     */
    #[Computed]
    public function screens(): EloquentCollection
    {
        return Screen::query()
            ->live()
            ->mine()
            ->offered()
            ->where('device_id', '!=', DeviceId::current())
            ->withDeviceName()
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
