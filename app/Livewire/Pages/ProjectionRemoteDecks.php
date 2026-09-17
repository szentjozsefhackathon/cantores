<?php

namespace App\Livewire\Pages;

use App\Models\Presentation;
use App\Models\Projection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Which deck to put up.
 *
 * A page of its own rather than a mode of the remote, and that is deliberate:
 * the remote used to be impossible to leave, because the only way out of it led
 * to a list that redirected straight back in. With the choice on its own page,
 * going back is ordinary navigation and needs no flag to escape a redirect.
 *
 * Nothing here is on the hot path. A deck is put up twice a service — once when
 * it starts, once when the next one goes up — so this is an ordinary Livewire
 * action, unlike everything on the control page, which talks JSON so that no
 * re-render can touch a picture the room is reading.
 */
class ProjectionRemoteDecks extends Component
{
    use AuthorizesRequests;

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

    /**
     * The decks put up lately, above the full list: the adoration deck tried
     * yesterday and wanted again today is one tap, not a search.
     *
     * @return EloquentCollection<int, Projection>
     */
    #[Computed]
    public function recents(): EloquentCollection
    {
        return Presentation::recentFor(Auth::user());
    }

    /** The show now, so the list can say which row that is. */
    #[Computed]
    public function showing(): ?Presentation
    {
        return Presentation::currentFor(Auth::user());
    }

    /**
     * Put a deck up as the show, on every device at once.
     */
    public function present(Projection $projection): RedirectResponse
    {
        $this->authorize('view', $projection);

        Presentation::putUp(Auth::user(), $projection);

        return redirect()->route('projection-remote');
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
