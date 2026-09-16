<?php

namespace App\Livewire\Pages;

use App\Models\Presentation;
use App\Models\Projection;
use App\Models\Screen;
use App\Services\ProjectionRenderPayload;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;

/**
 * The screen driven from a phone.
 *
 * The Sunday this exists for: the parish laptop opens the site, is signed in
 * with the QR code, opens the screen page and is put in front of the room — and
 * is then not touched again, because the person who knows when to advance is at
 * the organ and the laptop is at the back of the church. Everything after that
 * happens here: which deck goes up, when it advances, when it goes dark, and
 * when the service is over.
 *
 * Nothing had to be paired to make it work. Both devices already hold a session
 * for the same person, so the remote is an ordinary page of the site rather than
 * an arrangement of radios — no hotspot built by hand before every Mass, no
 * parish laptop pulled off the network it is actually allowed to use.
 *
 * What it is bound to is the *screen*, not the deck on it. That is what lets the
 * phone start a deck, swap it for another and take it off again, and it is also
 * what makes the page leaveable: the deck list is a page of its own, so going
 * back is navigation rather than a redirect into the row that was just left.
 *
 * Like the presenter, it engraves whatever is up at mount and then talks JSON:
 * what the phone shows is what the wall shows, drawn from the same payload
 * through the same renderer, and entitlement is resolved here afresh so a score
 * that stopped being readable between Thursday and Sunday is no more on the
 * phone than it is on the wall.
 */
class ProjectionRemote extends Component
{
    public Screen $screen;

    /** What the screen is showing at mount, if anything. */
    public ?Presentation $presentation = null;

    public ?Projection $projection = null;

    public string $title = '';

    /** @var array<string, mixed> */
    public array $geometry = [];

    /** @var list<array<string, mixed>> */
    public array $entries = [];

    /** @var array<int, list<int>> */
    public array $excluded = [];

    /** @var list<array<string, mixed>> */
    public array $outline = [];

    public string $revision = '';

    public function mount(Screen $screen): void
    {
        // 404 rather than 403, as at the endpoints: a screen somebody else is
        // facing a room with is not a thing this account may know exists.
        abort_unless(Gate::allows('view', $screen), 404);

        $this->screen = $screen;

        $presentation = $screen->showing();

        if (! $presentation instanceof Presentation) {
            $this->title = __('Remote');

            return;
        }

        // A deck that stopped being readable is not shown here either, and the
        // phone then holds a screen it can drive but not draw — which is the
        // honest answer, and the same one the wall gives.
        abort_unless(Gate::allows('view', $presentation), 404);
        abort_unless(Gate::allows('view', $presentation->projection), 404);

        $this->presentation = $presentation;
        $this->projection = $presentation->projection;
        $this->title = $this->projection->title;
        $this->revision = $this->projection->revision();

        $payload = app(ProjectionRenderPayload::class)->for($this->projection, Auth::user());

        $this->geometry = $payload['geometry'];
        $this->entries = $payload['entries'];
        $this->excluded = $payload['excluded'];
        $this->outline = $payload['outline'];
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app.main', ['title' => $this->title, 'noindex' => true]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.projection-remote');
    }
}
