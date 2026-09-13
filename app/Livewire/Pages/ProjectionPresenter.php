<?php

namespace App\Livewire\Pages;

use App\Models\Projection;
use App\Services\ProjectionRenderPayload;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;

/**
 * The deck as the room sees it: one slide at a time, full screen.
 *
 * A page of its own rather than a mode of the editor, and that is the whole
 * design. The person running the projector has a second screen showing this and
 * nothing else — no plan, no toolbar, no half-open panel to be caught by a stray
 * click during the Sanctus — and they get there by opening a URL they can leave
 * open.
 *
 * It draws from the same ProjectionRenderPayload the editor does, so what was
 * arranged is exactly what is shown; and, like everywhere else, entitlement is
 * resolved afresh here rather than carried over, so a score that stopped being
 * readable between the rehearsal and the service simply is not on the screen.
 *
 * Nothing here writes. The deck is finished by the time it is projected, and a
 * presenter that could change it is a presenter that can be changed by accident.
 */
class ProjectionPresenter extends Component
{
    use AuthorizesRequests;

    public Projection $projection;

    public string $title = '';

    /** @var array<string, mixed> */
    public array $geometry = [];

    /** @var list<array<string, mixed>> */
    public array $entries = [];

    public function mount(Projection $projection): void
    {
        $this->authorize('view', $projection);

        $this->projection = $projection;
        $this->title = $projection->title;

        $payload = app(ProjectionRenderPayload::class)->for($projection, Auth::user());

        $this->geometry = $payload['geometry'];
        $this->entries = $payload['entries'];
    }

    /**
     * Read the deck again without leaving the presenter.
     *
     * A correction made in the editor on another screen — or on another
     * machine — reaches the projector by pressing this, rather than by closing
     * what is being projected and opening it again.
     */
    public function reload(): void
    {
        $this->authorize('view', $this->projection);

        $projection = $this->projection->fresh();

        $this->title = $projection->title;

        $payload = app(ProjectionRenderPayload::class)->for($projection, Auth::user());

        $this->geometry = $payload['geometry'];
        $this->entries = $payload['entries'];

        $this->dispatch('projection-updated', payload: $this->entries, geometry: $this->geometry);
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app.main', ['title' => $this->title, 'noindex' => true]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.projection-presenter');
    }
}
