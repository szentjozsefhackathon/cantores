<?php

namespace App\Livewire\Booklet;

use App\Models\BookletScore;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * One row of the booklet: what is printed there, and everything that can be done
 * to it.
 *
 * A component of its own because a booklet is long. Every knob on the page used
 * to be answered by rebuilding the whole plan — thirty rows of buttons, a second
 * of the server's time — even to open one row's toolbar, which changes nothing
 * anywhere else. Livewire leaves a component it has already drawn alone when its
 * parent redraws, so a row now answers for itself and the rest of the list is
 * not touched.
 *
 * What that costs: a row knows nothing of where it stands in the list. Its
 * number, and whether it is at an end and so cannot move further that way, are
 * left to the browser — moving a row renumbers its neighbours, and they are no
 * longer listening.
 */
class EntryRow extends Component
{
    use AuthorizesRequests;

    public BookletScore $entry;

    /**
     * Where the score itself can be read, and its opening notes, as the booklet
     * resolved them.
     *
     * Handed down rather than looked up: resolving one is a question about what
     * this viewer may see, and asking it once for the whole booklet is the point
     * of asking it in the parent.
     */
    public ?string $scoreUrl = null;

    public ?string $incipitUrl = null;

    /** Whether this row's own panels stand open. */
    public bool $adjusting = false;

    public bool $writing = false;

    /** The Markdown of a text entry while it is being written. */
    #[Validate('nullable|string|max:20000')]
    public string $text = '';

    public function mount(): void
    {
        $this->text = $this->entry->text ?? '';
    }

    /**
     * Open or close the toolbar of knobs for this score.
     *
     * Nothing is saved and nothing is told: which panels stand open is this
     * row's business and nobody else's.
     */
    public function adjust(): void
    {
        $this->adjusting = ! $this->adjusting;
    }

    public function write(): void
    {
        $this->writing = ! $this->writing;

        if ($this->writing) {
            $this->text = $this->entry->text ?? '';
        }
    }

    public function updatedText(): void
    {
        $this->authorize('update', $this->entry->booklet);
        $this->validateOnly('text');

        if (! $this->entry->isText()) {
            return;
        }

        $this->entry->update(['text' => $this->text]);

        $this->announceChange();
    }

    public function toggleStartOnNewPage(): void
    {
        $this->flip('start_on_new_page');
    }

    public function toggleShowVariation(): void
    {
        $this->flip('show_variation');
    }

    public function toggleShowMusicTitle(): void
    {
        $this->flip('show_music_title');
    }

    private function flip(string $column): void
    {
        $this->authorize('update', $this->entry->booklet);

        $this->entry->update([$column => ! $this->entry->{$column}]);

        $this->announceChange();
    }

    /**
     * Tell the booklet that what it prints has changed.
     *
     * The pages are drawn from a picture of the whole booklet that only the
     * parent can put together, so this row says what it did and leaves the
     * redrawing to it.
     */
    private function announceChange(): void
    {
        $this->dispatch('booklet-entry-changed');
    }

    public function render(): View
    {
        return view('livewire.booklet.entry-row');
    }
}
