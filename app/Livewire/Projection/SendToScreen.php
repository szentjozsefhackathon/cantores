<?php

namespace App\Livewire\Projection;

use App\Models\Presentation;
use App\Models\Projection;
use App\Models\Screen;
use App\Support\DeviceId;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Put this deck on the screen the room is reading, without leaving the page.
 *
 * The Present button navigates *this* browser to the deck, and that is the right
 * gesture for one screen and the wrong one for two: on a laptop with the
 * projector as a second display, the window being worked in is the window that
 * must go on showing the plan. So the two gestures are separated — Present takes
 * over this window, this takes over the screen — and the person editing keeps
 * the music plan, the slides and the scores in front of them while the room
 * reads something else.
 *
 * It is the same write the phone makes from the remote, deliberately: a laptop
 * that aims a screen and does not show a deck is precisely what the phone is, so
 * the two-screen setup needs no mechanism of its own.
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

    /**
     * Which screen was last aimed from here, so the button can answer for itself
     * rather than leaving the sender to walk across the building and look.
     */
    public ?int $sentScreenId = null;

    public function mount(Projection $projection, bool $compact = false): void
    {
        $this->projection = $projection;
        $this->compact = $compact;
    }

    /**
     * The screens this person has facing a room right now.
     *
     * A second window of the same browser is the same screen — the row is keyed
     * by the browser's device cookie — which is what makes the two-screen laptop
     * work without anything being paired: the window on the projector claimed the
     * screen, and this window, carrying the same cookie, is already addressing it.
     *
     * Its own screen stays in the list, unlike on the remote, and for that same
     * reason: here the same-device row *is* the wall.
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
            ->withDeviceName()
            ->with(['presentation.projection'])
            ->latest('last_seen_at')
            ->get();
    }

    /**
     * Whether this row is the browser asking — the two-screen laptop, where the
     * screen is another window of this same browser.
     *
     * It cannot be sent to the way another machine can. A deck pointed at it
     * arrives nowhere unless that window is open, and whether it is open is the
     * one thing this page cannot find out: a closed window ages out of the list
     * over five minutes, deliberately, so that a moment of bad signal is not
     * read as a laptop that was never started. Between the window closing and
     * the row ageing away, a button that only points a deck is a button that
     * does nothing visible — which is exactly what it looked like.
     *
     * So for its own device the control is a link as well: it points the deck
     * and opens the window, which is right whether the window was closed or
     * merely behind this one.
     */
    public function isThisDevice(Screen $screen): bool
    {
        return $screen->device_id === DeviceId::current();
    }

    /**
     * Where the screen window goes when this browser is the screen.
     */
    public function deckUrl(): string
    {
        return route('projections.present', ['projection' => $this->projection->id]);
    }

    /**
     * Whether the screen just aimed from here is still showing this deck, so a
     * deck taken off it elsewhere stops being reported as sent.
     */
    #[Computed]
    public function sentScreen(): ?Screen
    {
        if ($this->sentScreenId === null) {
            return null;
        }

        $screen = $this->screens->firstWhere('id', $this->sentScreenId);

        return $screen?->showing()?->projection_id === $this->projection->id ? $screen : null;
    }

    /**
     * Point a screen at this deck.
     *
     * Joined rather than started afresh, as everywhere else a deck goes up: a
     * laptop already showing it and a window putting it there land on the same
     * row and follow each other.
     */
    public function send(int $screenId): void
    {
        $this->authorize('view', $this->projection);

        $screen = Screen::query()->find($screenId);

        // 404 rather than 403, as at the endpoints: a screen somebody else is
        // facing a room with is not a thing this account may know exists.
        abort_unless($screen instanceof Screen && Gate::allows('update', $screen), 404);

        $screen->point(Presentation::resumeFor(
            $this->projection,
            Auth::user(),
            $screen->device_pairing_id,
            splash: Presentation::splashFor($screen),
        ));

        $this->sentScreenId = $screen->id;

        unset($this->screens, $this->sentScreen);
    }

    public function render(): IlluminateView
    {
        return view('livewire.projection.send-to-screen');
    }
}
