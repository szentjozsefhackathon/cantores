<?php

namespace App\Livewire\Pages;

use App\Models\Booklet;
use App\Models\Loan;
use App\Services\BookletRenderPayload;
use App\Services\LoanAccessService;
use App\Services\LoanKeepingService;
use App\Support\BookletSettingFields;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View as IlluminateView;
use Livewire\Component;

/**
 * The booklet as the band reads it.
 *
 * The cantor's booklet is a paper object: A5, a fixed margin, one type size for
 * everybody. On a phone at a music stand none of that is true — the page is
 * whatever the screen is, and the size that suits a twenty-year-old guitarist in
 * good light is not the size that suits the organist. So the link does not send
 * a picture of the pages. It sends the booklet itself, and the phone engraves it
 * at its own width, at whatever size the person holding it asked for.
 *
 * That is also what makes it live. Nothing is baked: the payload is read from the
 * booklet on every request, so a chord fixed or a line written during the
 * rehearsal is in everybody's hands as soon as they pull to refresh — the same
 * live-reference posture the booklet takes towards the scores it prints.
 *
 * Nothing here writes. What a reader changes is theirs and stays on their
 * device, because the alternative — a dozen musicians editing the leader's
 * handout — is not what the link is for.
 */
class BookletLoanView extends Component
{
    public string $loanToken = '';

    public string $title = '';

    /** Whose handout this is, so a borrowed page says whose work it is. */
    public string $ownerName = '';

    /** @var array<string, mixed> */
    public array $geometry = [];

    /** @var list<array<string, mixed>> */
    public array $entries = [];

    public function mount(string $token): void
    {
        $loan = app(LoanAccessService::class)->resolveOfType($token, Booklet::class);

        abort_if(! $loan instanceof Loan, 404);

        $loan->touchLastViewed();

        app(LoanKeepingService::class)->recordOpen($loan, Auth::user());

        /** @var Booklet $booklet */
        $booklet = $loan->lendable;

        $this->loanToken = $token;
        $this->title = $booklet->title;
        $this->ownerName = $booklet->user?->displayName ?? '';

        $this->load($loan, $booklet);
    }

    /**
     * Read the booklet again.
     *
     * The button beside the title during a rehearsal: the leader has just
     * written the second verse in, and this is how it arrives without anyone
     * losing the sizes they set on their own phone — the payload is replaced,
     * the reader's own settings are not.
     */
    public function reload(): void
    {
        $loan = app(LoanAccessService::class)->resolveOfType($this->loanToken, Booklet::class);

        abort_if(! $loan instanceof Loan, 404);

        /** @var Booklet $booklet */
        $booklet = $loan->lendable;

        $this->title = $booklet->title;

        $this->load($loan, $booklet);

        $this->dispatch('booklet-updated', payload: $this->entries, geometry: $this->geometry);
    }

    /**
     * The knobs a reader is offered for one score, by format.
     *
     * A short list on purpose — see BookletSettingFields::READER_FIELDS. The
     * editor's full panel belongs to someone laying out a page; the person
     * singing from it gets the two things that are about them rather than about
     * the paper: bigger, and lower.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function panels(): array
    {
        $panels = [];

        foreach (['abc', 'gabc', 'aretino', 'chordpro', 'file'] as $format) {
            $panels[$format] = BookletSettingFields::readerPanelFor($format);
        }

        return $panels;
    }

    public function rendering(IlluminateView $view): void
    {
        $view->layout('layouts::app.main', [
            'title' => $this->title,
            'noindex' => true,
        ]);
    }

    public function render(): IlluminateView
    {
        return view('livewire.pages.booklet-loan-view');
    }

    private function load(Loan $loan, Booklet $booklet): void
    {
        $payload = app(BookletRenderPayload::class)->for($booklet, Auth::user(), $loan);

        $this->geometry = $payload['geometry'];
        $this->entries = $payload['entries'];
    }
}
