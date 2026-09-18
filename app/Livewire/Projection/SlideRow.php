<?php

namespace App\Livewire\Projection;

use App\Models\ProjectionSlide;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * One row of the projection: what goes on the screen there, and everything that
 * can be done to it.
 *
 * A component of its own for the reason App\Livewire\Booklet\EntryRow is: a deck
 * is long, and opening one row's toolbar changes nothing anywhere else. Livewire
 * leaves a component it has already drawn alone when its parent redraws, so a row
 * answers for itself and the rest of the list is not touched.
 *
 * What that costs: a row knows nothing of where it stands in the list. Its number,
 * and whether it is at an end and so cannot move further that way, are left to
 * the browser — moving a row renumbers its neighbours, and they are no longer
 * listening. See the `.projection-plan` counters in resources/css/app.css.
 */
class SlideRow extends Component
{
    use AuthorizesRequests;

    public ProjectionSlide $entry;

    /**
     * Where the score itself can be read, and its opening notes, as the deck
     * resolved them.
     *
     * Handed down rather than looked up: resolving one is a question about what
     * this viewer may see, and asking it once for the whole deck is the point of
     * asking it in the parent.
     */
    public ?string $scoreUrl = null;

    public ?string $incipitUrl = null;

    /** Whether this row's own panel of knobs stands open. */
    public bool $adjusting = false;

    public bool $writing = false;

    /** Whether the look at this row's score stands open in a modal. */
    public bool $previewingScore = false;

    /** The Markdown of a text row while it is being written. */
    #[Validate('nullable|string|max:20000')]
    public string $text = '';

    public function mount(): void
    {
        $this->text = $this->entry->text ?? '';
    }

    /**
     * The deck chose this row's sections on our behalf, so our own copy of
     * the entry — left untouched by the deck's own redraw, which is the
     * whole point of being a component of our own — is out of date.
     */
    #[On('projection-entry-sections-changed.{entry.id}')]
    public function refreshSections(): void
    {
        $this->entry->refresh();
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

    /**
     * Open or close the look at this row's score.
     *
     * All this decides is whether the modal stands there. What it shows is read
     * out of the editor's own payload in the browser — see score-preview.js —
     * so the server is asked for nothing beyond this flag, and the preview can
     * never show a score the editor has stopped showing.
     */
    public function togglePreview(): void
    {
        $this->previewingScore = ! $this->previewingScore;
    }

    public function updatedText(): void
    {
        $this->authorize('update', $this->entry->projection);
        $this->validateOnly('text');

        if (! $this->entry->isText()) {
            return;
        }

        $this->entry->update(['text' => $this->text]);

        $this->announceChange();
    }

    /**
     * The variation name is the one heading line shown against the row itself
     * rather than against a slot or a music, so its switch is the row's own. The
     * slot's and the music's are asked of the deck, beside those names in the
     * plan.
     */
    public function toggleShowVariation(): void
    {
        $this->authorize('update', $this->entry->projection);

        $this->entry->update(['show_variation' => ! $this->entry->show_variation]);

        $this->announceChange();
    }

    /**
     * Tell the deck that what it shows has changed.
     *
     * The slides are drawn from a picture of the whole deck that only the parent
     * can put together, so this row says what it did and leaves the redrawing to
     * it.
     */
    private function announceChange(): void
    {
        $this->dispatch('projection-entry-changed');
    }

    public function render(): View
    {
        return view('livewire.projection.slide-row');
    }
}
