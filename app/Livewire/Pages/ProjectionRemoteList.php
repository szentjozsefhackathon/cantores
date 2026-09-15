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
        $elsewhere = $this->elsewhere();

        if ($elsewhere->count() === 1) {
            return redirect()->route('projection-remote.control', ['screen' => $elsewhere->first()->id]);
        }

        return null;
    }

    /**
     * The screens this person has facing a room right now, this browser's own
     * among them.
     *
     * Its own was left out while the remote was a phone's page and nothing
     * else: a browser cannot be the remote for itself, and the presenter claims
     * a screen on the way in whichever way it was opened, so a phone that
     * pressed Present to look at a deck arrived here offering itself as
     * somewhere to send one.
     *
     * The laptop is the case that says otherwise, because there the two windows
     * *are* one device. The deck goes on the projector and the control window
     * stays on the built-in display — the arrangement every desktop display
     * program has — and keying the exclusion to the device made exactly that
     * screen the one thing the remote could not address.
     *
     * So both are listed and only one is entered without asking. See
     * `elsewhere()`: the normal Sunday keeps its best moment, and the row for
     * this browser is there for the person who deliberately wants it.
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
            ->with(['presentation.projection', 'devicePairing'])
            ->latest('last_seen_at')
            ->get();
    }

    /**
     * The same screens, less this browser's own.
     *
     * What the redirect counts, and only the redirect. One screen across the
     * room is the normal Sunday and is entered without asking; a phone that is
     * a screen itself must not turn that into a choice, and a laptop must not
     * be thrown into driving its own window before it has asked to.
     *
     * @return EloquentCollection<int, Screen>
     */
    #[Computed]
    public function elsewhere(): EloquentCollection
    {
        return $this->screens->reject(fn (Screen $screen): bool => $this->isThisDevice($screen));
    }

    /** Whether a row is the browser this list is being read in. */
    public function isThisDevice(Screen $screen): bool
    {
        return $screen->device_id === DeviceId::current();
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
