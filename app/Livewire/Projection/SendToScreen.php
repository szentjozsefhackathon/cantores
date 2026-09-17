<?php

namespace App\Livewire\Projection;

use App\Models\Presentation;
use App\Models\Projection;
use App\Models\Screen;
use App\Services\ShowState;
use App\Support\DeviceId;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Put this deck on the screen, without leaving the page.
 *
 * The Present button navigates *this* browser to the deck, and that is the right
 * gesture for one screen and the wrong one for two: on a laptop with the
 * projector as a second display, the window being worked in is the window that
 * must go on showing the plan. So this puts the deck up as the show and stays
 * where it is, and every screen this person has on — the second window, the
 * laptop across the church — switches to it.
 *
 * One button and no list of screens, because there is nothing to choose: a
 * person has one show, and every screen of theirs shows it.
 *
 * Nothing here is on the hot path. A deck goes up twice a service, so this is an
 * ordinary Livewire action, unlike everything on the presenter and the remote,
 * which talk JSON so that no re-render can touch a picture the room is reading.
 */
class SendToScreen extends Component
{
    use AuthorizesRequests;

    /**
     * The name the screen window is opened under.
     *
     * Named, and not a fresh tab each time: a second copy of the wall behind the
     * first is not a thing anybody wants, and a browser given a name it already
     * has brings that window forward instead of making another.
     */
    public const WINDOW = 'projection-screen';

    public Projection $projection;

    /**
     * Icon only, for the document list, where a row already carries four
     * controls and the words would push the title out of the way.
     */
    public bool $compact = false;

    public function mount(Projection $projection, bool $compact = false): void
    {
        $this->projection = $projection;
        $this->compact = $compact;
    }

    /**
     * The screens this person has facing a room right now.
     *
     * @return EloquentCollection<int, Screen>
     */
    #[Computed]
    public function screens(): EloquentCollection
    {
        return ShowState::screensFor(Auth::user());
    }

    /** Whether this deck is the show right now. */
    #[Computed]
    public function isOnScreen(): bool
    {
        return Presentation::currentFor(Auth::user())?->projection_id === $this->projection->id;
    }

    /**
     * Whether the only screen on is a window of this same browser — the
     * two-screen laptop.
     *
     * Then the button opens that window as well as putting the deck up. A
     * closed window ages out over five minutes, deliberately, so that a moment
     * of bad signal is not read as a laptop that was never started, and
     * between the window closing and the row ageing away a button that only
     * put the deck up would do nothing visible.
     */
    #[Computed]
    public function opensWindow(): bool
    {
        return $this->screens->isNotEmpty()
            && $this->screens->every(fn (Screen $screen): bool => $screen->device_id === DeviceId::current());
    }

    /**
     * Where the screen window goes when this browser is the screen.
     */
    public function deckUrl(): string
    {
        return route('projections.present', ['projection' => $this->projection->id]);
    }

    /**
     * Put this deck up as the show.
     */
    public function putUp(): void
    {
        $this->authorize('view', $this->projection);

        Presentation::putUp(Auth::user(), $this->projection);

        unset($this->screens, $this->isOnScreen, $this->opensWindow);
    }

    public function render(): IlluminateView
    {
        return view('livewire.projection.send-to-screen');
    }
}
