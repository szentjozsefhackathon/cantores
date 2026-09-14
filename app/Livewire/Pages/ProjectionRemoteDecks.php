<?php

namespace App\Livewire\Pages;

use App\Models\Presentation;
use App\Models\Projection;
use App\Models\Screen;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Which deck to put on the screen.
 *
 * A page of its own rather than a mode of the remote, and that is deliberate:
 * the remote used to be impossible to leave, because the only way out of it led
 * to a list that redirected straight back in. With the choice on its own page,
 * going back is ordinary navigation and needs no flag to escape a redirect.
 *
 * Nothing here is on the hot path. A screen is pointed at a deck twice a
 * service — once when it starts, once when the next one is put up — so this is
 * an ordinary Livewire action, unlike everything on the control page, which
 * talks JSON so that no re-render can touch a picture the room is reading.
 */
class ProjectionRemoteDecks extends Component
{
    use AuthorizesRequests;

    public Screen $screen;

    public function mount(Screen $screen): void
    {
        // 404 rather than 403, as at the endpoints: a screen somebody else is
        // facing a room with is not a thing this account may know exists.
        abort_unless(Gate::allows('view', $screen), 404);

        $this->screen = $screen;
    }

    /**
     * The decks that could go up, newest first — the one being prepared this
     * week is the one at the top.
     *
     * @return EloquentCollection<int, Projection>
     */
    #[Computed]
    public function projections(): EloquentCollection
    {
        return Projection::query()
            ->mine()
            ->latest('updated_at')
            ->limit(25)
            ->get();
    }

    /** What the screen is showing now, so the list can say which row that is. */
    #[Computed]
    public function showing(): ?Presentation
    {
        return $this->screen->showing();
    }

    /**
     * Put a deck on the screen.
     *
     * Joined rather than started afresh, so that a laptop already showing this
     * deck and a phone putting it up land on the same row and follow each other
     * — the same rule the presenter mounts under.
     */
    public function present(Projection $projection): RedirectResponse
    {
        $this->authorize('view', $projection);
        abort_unless(Gate::allows('update', $this->screen), 404);

        $this->screen->point(Presentation::resumeFor(
            $projection,
            Auth::user(),
            $this->screen->device_pairing_id,
        ));

        return redirect()->route('projection-remote.control', ['screen' => $this->screen->id]);
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app.main', ['title' => __('Choose a deck'), 'noindex' => true]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.projection-remote-decks');
    }
}
