<?php

namespace App\Livewire\Pages;

use App\Models\DevicePairing;
use App\Models\Presentation;
use App\Models\Projection;
use App\Models\Screen;
use App\Services\ProjectionRenderPayload;
use App\Support\DeviceId;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
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
 * Nothing here writes to the *deck*. The deck is finished by the time it is
 * projected, and a presenter that could change it is a presenter that can be
 * changed by accident.
 *
 * Where the service has got to is a different thing, and it is written — but not
 * through Livewire. The component puts the deck up as the person's show at
 * mount and hands the browser its URLs; everything after that is Alpine talking JSON to
 * them. That keeps the promise the stage's `wire:ignore` makes: once the picture
 * is up, no component re-render can touch it.
 */
class ProjectionPresenter extends Component
{
    use AuthorizesRequests;

    /**
     * The deck this page was opened on, where it was opened on one at all. Only
     * the way back to the editor reads it: what the room is looking at is the
     * person's show, read by the poll, and a phone may change it at any time.
     */
    public ?Projection $projection = null;

    public string $title = '';

    /** @var array<string, mixed> */
    public array $geometry = [];

    /** @var list<array<string, mixed>> */
    public array $entries = [];

    /**
     * Which slides each row makes but this service walks past — the verses left
     * out today, kept in the deck for the Sunday that wants them.
     *
     * @var array<int, list<int>>
     */
    public array $excluded = [];

    /**
     * This browser, as the screen it is.
     *
     * Claimed on the way in, because this is the page only a wall opens, and
     * opening it is the whole of what claiming a screen means: both devices were
     * already the same person, so what the phone lacked was never permission but
     * an address.
     */
    public Screen $screen;

    /**
     * The show this page opened on. Null on a screen that is still waiting for
     * one.
     */
    public ?Presentation $presentation = null;

    /**
     * The deck as it stood when this page engraved it, so the poll can tell an
     * edit made since from the one it is already showing.
     */
    public string $revision = '';

    /**
     * Two ways in, one page.
     *
     * With a deck in the URL, the deck is put up as this person's show — on
     * every device of theirs, not only this one, because Present means put it
     * up — and engraved from the server's answer, so the first slide is up
     * before anything is polled.
     *
     * Without one, this is a screen showing whatever the show is. Nothing is
     * engraved at mount; the poll reads the deck, and the page goes black while
     * it draws it.
     */
    public function mount(?Projection $projection = null): void
    {
        $this->screen = Screen::claimFor(
            Auth::user(),
            DeviceId::current(),
            Session::getId(),
            Session::get(DevicePairing::DEVICE_SESSION_KEY),
            request()->userAgent(),
        );

        // `exists` and not merely the type: an optional model parameter that the
        // route did not bind is filled in by the container with a blank
        // instance, not with null, so a screen opened bare would otherwise try
        // to authorize a projection that is not any projection.
        if (! $projection instanceof Projection || ! $projection->exists) {
            $this->title = __('Projection screen');
            $this->presentation = Presentation::currentFor(Auth::user());

            return;
        }

        $this->authorize('view', $projection);

        $this->projection = $projection;
        $this->title = $projection->title;

        // The deck already up is left exactly where it is, so reloading the wall
        // mid-service lands on the hymn; anything else starts over, on the title
        // card if nothing was up.
        $this->presentation = Presentation::putUp(Auth::user(), $projection);
        $this->revision = $projection->revision();

        $payload = app(ProjectionRenderPayload::class)->for($projection, Auth::user());

        $this->geometry = $payload['geometry'];
        $this->entries = $payload['entries'];
        $this->excluded = $payload['excluded'];
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
        // The show's deck and not the one in the URL: a phone may have put
        // another one up since, and reading the old one again would paint it
        // over the new. A screen showing nothing has nothing to re-read.
        $presentation = Presentation::currentFor(Auth::user());

        if (! $presentation instanceof Presentation) {
            return;
        }

        $projection = $presentation->projection;

        $this->authorize('view', $projection);

        $this->title = $projection->title;
        $this->revision = $projection->revision();

        $payload = app(ProjectionRenderPayload::class)->for($projection, Auth::user());

        $this->geometry = $payload['geometry'];
        $this->entries = $payload['entries'];
        $this->excluded = $payload['excluded'];

        $this->dispatch(
            'projection-updated',
            presentationId: $presentation->id,
            payload: $this->entries,
            geometry: $this->geometry,
            excluded: $this->excluded,
            revision: $this->revision,
        );
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
